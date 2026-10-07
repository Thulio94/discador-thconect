<?php

namespace App\Services\Dialer;

use App\Contracts\Dialer\DialerGateway;
use App\Models\Call;
use App\Models\Campaign;
use App\Models\TelephonyRoute;
use App\Models\Company;
use App\Models\User;
use App\Exceptions\CapacityExceededException;
use App\Services\Capacity\ChannelCapacityService;
use App\Services\Reports\CallReportSnapshot;
use App\Services\Telephony\AsteriskBridgeClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class AsteriskDialerGateway implements DialerGateway
{
    public function __construct(
        private readonly SimulatedDialerGateway $simulated,
        private readonly AsteriskBridgeClient $bridge,
        private readonly CallReportSnapshot $snapshots,
        private readonly ChannelCapacityService $capacity,
        private readonly CallWrapUpService $wrapUps,
    ) {}

    public function originate(DialerCallRequest $request): Call
    {
        $campaign = $request->campaignId
            ? Campaign::query()
                ->with(['fixedTelephonyRoute', 'mobileTelephonyRoute'])
                ->where('company_id', $request->companyId)
                ->find($request->campaignId)
            : null;
        $route = $this->routeForPhone($campaign, $request->phone, $request->companyId);

        $manualRouteValidation = $campaign === null && $route?->connection_status === 'pending_test';

        if (! $route?->isReady() || ($route->connection_status !== 'online' && ! $manualRouteValidation)) {
            if (app()->environment('testing') && ! config('app.force_real_telephony', false)) {
                return $this->simulated->originate($request);
            }

            return $this->simulated->cancel($request, 'route_unavailable');
        }

        $company = Company::query()->with(['license', 'resourceLimit'])->findOrFail($request->companyId);
        try {
            $reservation = $this->capacity->reserve($company, $route, $campaign, $request->userId);
        } catch (CapacityExceededException $exception) {
            $call = Call::create([
                'company_id' => $request->companyId, 'user_id' => $request->userId,
                'campaign_id' => $request->campaignId, 'telephony_route_id' => $route->id,
                'dial_batch_id' => $request->batchId,
                'contact_id' => $request->contactId, 'contact_phone_id' => $request->contactPhoneId,
                'direction' => 'outbound', 'status' => 'cancelled', 'channel' => 'capacity_guard',
                'phone' => $request->phone, 'started_at' => now(), 'ended_at' => now(),
                'end_reason' => 'capacity_'.$exception->limitKey, 'hangup_party' => 'system',
            ]);
            $this->snapshots->capture($call);
            return $call;
        }

        $actionId = 'thconect-'.$request->companyId.'-'.Str::uuid()->toString();
        $call = Call::create([
            'company_id' => $request->companyId,
            'user_id' => $request->userId,
            'campaign_id' => $request->campaignId,
            'dial_batch_id' => $request->batchId,
            'telephony_route_id' => $route->id,
            'channel_reservation_id' => $reservation->id,
            'contact_id' => $request->contactId,
            'contact_phone_id' => $request->contactPhoneId,
            'direction' => 'outbound',
            'status' => 'dialing',
            'channel' => 'asterisk_ami',
            'telephony_action_id' => $actionId,
            'phone' => $request->phone,
            'started_at' => now(),
        ]);
        $this->capacity->activate($reservation, $call->id);
        $this->snapshots->capture($call);

        $agentExtension = $request->userId
            ? User::query()
                ->where('company_id', $request->companyId)
                ->whereKey($request->userId)
                ->value('extension')
            : null;

        $payload = [
            'company_id' => $request->companyId,
            'route_id' => $route->id,
            'call_id' => $call->id,
            'action_id' => $actionId,
            'phone' => $route->formattedPhone($request->phone),
            'channel' => 'PJSIP/'.$route->formattedPhone($request->phone).'@thconect-route-'.$route->id,
            'context' => $route->dialplan_context,
            'extension' => $route->dialplan_extension,
            'caller_id' => $route->caller_id,
            'agent_extension' => $agentExtension,
            'agent_endpoint' => 't'.$request->companyId.'-e'.$agentExtension,
            'recording_file' => sprintf('call-%d.wav', $call->id),
            'batch_id' => $request->batchId,
        ];

        if (DB::transactionLevel() > 0) {
            DB::afterCommit(fn () => $this->originateCommittedCall($call->id, $route->id, $payload));
        } else {
            $this->originateCommittedCall($call->id, $route->id, $payload);
            $call->refresh();
        }

        return $call;
    }

    public function cancel(DialerCallRequest $request, string $reason): Call
    {
        return $this->simulated->cancel($request, $reason);
    }

    public function hangup(Call $call): Call
    {
        if ($call->channel !== 'asterisk_ami') {
            return $this->simulated->hangup($call);
        }

        if ($call->status === 'ended' || $call->status === 'cancelled') {
            return $call;
        }

        try {
            $call->loadMissing('telephonyRoute.asteriskNode');
            $this->bridge->hangup([
                'company_id' => $call->company_id,
                'route_id' => $call->telephony_route_id,
                'call_id' => $call->id,
                'channel' => $call->telephony_channel,
                'uniqueid' => $call->telephony_uniqueid,
            ], $call->telephonyRoute?->asteriskNode);
        } catch (Throwable $exception) {
            report($exception);
        }

        $call->update([
            'status' => 'ended',
            'ended_at' => now(),
            'hangup_party' => $call->hangup_party ?? 'agent',
        ]);
        $this->capacity->release($call->channel_reservation_id, 'agent_hangup');

        return $call;
    }

    private function routeForPhone(?Campaign $campaign, string $phone, int $companyId): ?TelephonyRoute
    {
        if (! $campaign) {
            $routes = Company::query()
                ->find($companyId)?->telephonyRoutes()
                ->with('asteriskNode')
                ->where('enabled', true)
                ->whereIn('connection_status', ['online', 'pending_test'])
                ->orderBy('priority')
                ->orderBy('telephony_routes.id');

            if (! $routes) {
                return null;
            }

            return $routes->orderByRaw("CASE WHEN connection_status = 'online' THEN 0 ELSE 1 END")
                ->first();
        }

        $digits = preg_replace('/\D+/', '', $phone) ?: '';
        $nationalNumber = str_starts_with($digits, '55') ? substr($digits, 2) : $digits;
        $isMobile = strlen($nationalNumber) >= 11 && ($nationalNumber[2] ?? null) === '9';

        return $isMobile
            ? ($campaign->mobileTelephonyRoute ?? $campaign->fixedTelephonyRoute)
            : ($campaign->fixedTelephonyRoute ?? $campaign->mobileTelephonyRoute);
    }

    /** @param array<string, mixed> $payload */
    private function originateCommittedCall(int $callId, int $routeId, array $payload): void
    {
        $call = Call::query()->find($callId);
        $route = TelephonyRoute::query()->with('asteriskNode')->find($routeId);

        if (! $call || ! $route || ! in_array($call->status, Call::ACTIVE_STATUSES, true)) {
            return;
        }

        try {
            $this->bridge->originate($payload, $route->asteriskNode);
        } catch (Throwable $exception) {
            report($exception);

            $call->update([
                'status' => 'cancelled',
                'end_reason' => 'bridge_unavailable',
                'hangup_party' => 'system',
                'ended_at' => now(),
            ]);
            $this->capacity->release($call->channel_reservation_id, 'bridge_unavailable');
            if ($call->campaign_id && $call->contact_phone_id) {
                app(MailingQueueService::class)->recordUnansweredAttempt($call, 'failed');
            }
            if ($call->user_id && $user = User::query()->find($call->user_id)) {
                $this->wrapUps->releaseAgent($user, $call);
            }
        }
    }
}
