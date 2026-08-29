<?php

namespace App\Http\Controllers\Tenant;

use App\Contracts\Dialer\DialerGateway;
use App\Http\Controllers\Controller;
use App\Models\Call;
use App\Models\Team;
use App\Models\User;
use App\Services\Dialer\DialerCallRequest;
use App\Services\Telephony\BrazilianPhoneNormalizer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PbxController extends Controller
{
    public function index(Request $request): View
    {
        $companyId = $request->user()->company_id;

        return view('tenant.pbx.index', [
            'teams' => Team::query()
                ->where('company_id', $companyId)
                ->where('operational_type', 'pbx')
                ->with(['members' => fn ($query) => $query->where('users.status', 'active')])
                ->orderBy('name')
                ->get(),
            'agents' => User::query()
                ->where('company_id', $companyId)
                ->where('user_type', 'agent')
                ->whereHas('assignedTeams', fn ($query) => $query
                    ->where('teams.company_id', $companyId)
                    ->where('teams.operational_type', 'pbx'))
                ->orderBy('name')
                ->get(),
            'calls' => Call::query()
                ->where('company_id', $companyId)
                ->whereNull('campaign_id')
                ->with(['user', 'telephonyRoute'])
                ->latest('started_at')
                ->limit(25)
                ->get(),
        ]);
    }

    public function call(Request $request, BrazilianPhoneNormalizer $normalizer, DialerGateway $gateway): RedirectResponse
    {
        $data = $request->validate(['phone' => ['required', 'string', 'max:32']]);
        $user = $request->user();

        $isPbxAgent = $user->user_type === 'agent' && $user->teams()
            ->wherePivot('membership_type', 'agent')
            ->where('teams.operational_type', 'pbx')
            ->exists();
        if (! $isPbxAgent) {
            abort(403);
        }

        $phone = $normalizer->normalize($data['phone']);
        if (! $phone) {
            throw ValidationException::withMessages(['phone' => 'Informe um telefone brasileiro válido com DDD.']);
        }

        $call = $gateway->originate(new DialerCallRequest(
            companyId: $user->company_id,
            userId: $user->id,
            campaignId: null,
            contactId: null,
            contactPhoneId: null,
            phone: $phone,
            channel: 'pbx',
        ));

        return redirect()->route('tenant.pbx.index')->with('status', "Ligação PABX iniciada. Protocolo {$call->protocol}.");
    }
}
