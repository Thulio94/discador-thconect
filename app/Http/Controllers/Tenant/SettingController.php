<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\BlacklistNumber;
use App\Models\Disposition;
use App\Models\PauseReason;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SettingController extends Controller
{
    public function index(Request $request): View
    {
        $companyId = $request->user()->company_id;

        return view('tenant.settings.index', [
            'pauseReasons' => PauseReason::query()
                ->where('company_id', $companyId)
                ->latest()
                ->get(),
            'dispositions' => Disposition::query()
                ->where('company_id', $companyId)
                ->orderBy('category')
                ->orderBy('name')
                ->get(),
            'blacklistNumbers' => BlacklistNumber::query()
                ->where('company_id', $companyId)
                ->latest()
                ->get(),
        ]);
    }

    public function storePauseReason(Request $request): RedirectResponse
    {
        $data = $this->validatePauseReason($request);
        $data['company_id'] = $request->user()->company_id;

        PauseReason::create($data);

        return redirect()->route('tenant.settings.index')->with('status', 'Motivo de pausa cadastrado.');
    }

    public function updatePauseReason(Request $request, PauseReason $pauseReason): RedirectResponse
    {
        $this->authorizeTenantRecord($request, $pauseReason->company_id);

        $pauseReason->update($this->validatePauseReason($request, $pauseReason));

        return redirect()->route('tenant.settings.index')->with('status', 'Motivo de pausa atualizado.');
    }

    public function destroyPauseReason(Request $request, PauseReason $pauseReason): RedirectResponse
    {
        $this->authorizeTenantRecord($request, $pauseReason->company_id);

        $pauseReason->update(['status' => 'inactive']);

        return redirect()->route('tenant.settings.index')->with('status', 'Motivo de pausa inativado.');
    }

    public function storeDisposition(Request $request): RedirectResponse
    {
        $data = $this->validateDisposition($request);
        $data['company_id'] = $request->user()->company_id;

        Disposition::create($data);

        return redirect()->route('tenant.settings.index')->with('status', 'Tabulacao cadastrada.');
    }

    public function updateDisposition(Request $request, Disposition $disposition): RedirectResponse
    {
        $this->authorizeTenantRecord($request, $disposition->company_id);

        $disposition->update($this->validateDisposition($request, $disposition));

        return redirect()->route('tenant.settings.index')->with('status', 'Tabulacao atualizada.');
    }

    public function destroyDisposition(Request $request, Disposition $disposition): RedirectResponse
    {
        $this->authorizeTenantRecord($request, $disposition->company_id);

        $disposition->update(['status' => 'inactive']);

        return redirect()->route('tenant.settings.index')->with('status', 'Tabulacao inativada.');
    }

    public function storeBlacklist(Request $request): RedirectResponse
    {
        $data = $this->validateBlacklistNumber($request);
        $data['company_id'] = $request->user()->company_id;
        $data['phone'] = $this->normalizePhone($data['phone']);

        BlacklistNumber::create($data);

        return redirect()->route('tenant.settings.index')->with('status', 'Numero adicionado a blacklist.');
    }

    public function updateBlacklist(Request $request, BlacklistNumber $blacklistNumber): RedirectResponse
    {
        $this->authorizeTenantRecord($request, $blacklistNumber->company_id);

        $data = $this->validateBlacklistNumber($request, $blacklistNumber);
        $data['phone'] = $this->normalizePhone($data['phone']);
        $blacklistNumber->update($data);

        return redirect()->route('tenant.settings.index')->with('status', 'Numero da blacklist atualizado.');
    }

    public function destroyBlacklist(Request $request, BlacklistNumber $blacklistNumber): RedirectResponse
    {
        $this->authorizeTenantRecord($request, $blacklistNumber->company_id);

        $blacklistNumber->update(['status' => 'inactive']);

        return redirect()->route('tenant.settings.index')->with('status', 'Numero da blacklist inativado.');
    }

    private function validatePauseReason(Request $request, ?PauseReason $pauseReason = null): array
    {
        $companyId = $request->user()->company_id;

        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('pause_reasons', 'name')
                    ->where('company_id', $companyId)
                    ->ignore($pauseReason),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'is_billable' => ['sometimes', 'boolean'],
        ]);
    }

    private function validateDisposition(Request $request, ?Disposition $disposition = null): array
    {
        $companyId = $request->user()->company_id;

        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => [
                'required',
                'string',
                'max:40',
                Rule::unique('dispositions', 'code')
                    ->where('company_id', $companyId)
                    ->ignore($disposition),
            ],
            'category' => ['required', Rule::in(['success', 'neutral', 'negative', 'callback'])],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'ends_call' => ['sometimes', 'boolean'],
            'is_success' => ['sometimes', 'boolean'],
        ]);
    }

    private function validateBlacklistNumber(Request $request, ?BlacklistNumber $blacklistNumber = null): array
    {
        $companyId = $request->user()->company_id;
        $phone = $this->normalizePhone((string) $request->input('phone'));

        $request->merge(['phone' => $phone]);

        return $request->validate([
            'phone' => [
                'required',
                'string',
                'min:8',
                'max:20',
                Rule::unique('blacklist_numbers', 'phone')
                    ->where('company_id', $companyId)
                    ->ignore($blacklistNumber),
            ],
            'reason' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);
    }

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?: $phone;
    }

    private function authorizeTenantRecord(Request $request, int $companyId): void
    {
        abort_unless($request->user()->company_id === $companyId, 404);
    }
}
