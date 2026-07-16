<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\BlacklistNumber;
use App\Models\Company;
use App\Models\Disposition;
use App\Models\OperationalParameter;
use App\Models\PauseReason;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            'operationalParameter' => OperationalParameter::forCompany($companyId),
        ]);
    }

    public function updateOperationalParameters(Request $request): RedirectResponse
    {
        $parameter = OperationalParameter::forCompany($request->user()->company_id);
        $parameter->fill($this->validateOperationalParameters($request));
        $parameter->save();

        return redirect()->route('tenant.settings.index')->with('status', 'Parametros operacionais atualizados.');
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

        DB::transaction(function () use ($data): void {
            Company::query()->whereKey($data['company_id'])->lockForUpdate()->firstOrFail();
            $data['code'] = $this->nextDispositionCode($data['company_id']);

            Disposition::create($data);
        });

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

    public function importBlacklist(Request $request): RedirectResponse
    {
        $request->validate([
            'numbers' => ['nullable', 'string', 'max:500000'],
            'file' => ['nullable', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $content = (string) $request->input('numbers', '');

        if ($request->hasFile('file')) {
            $content = (string) file_get_contents($request->file('file')->getRealPath());
        }

        if (blank(trim($content))) {
            return back()->withErrors(['numbers' => 'Informe numeros no campo ou envie um arquivo CSV/TXT.']);
        }

        $companyId = $request->user()->company_id;
        $rows = collect(preg_split('/\R/u', $content) ?: [])
            ->map(fn (string $line): array => array_map('trim', preg_split('/[;,]/', $line, 2) ?: []))
            ->filter(fn (array $row): bool => filled($row[0] ?? null));
        $numbers = [];
        $invalid = 0;

        foreach ($rows as $row) {
            $phone = $this->normalizePhone($row[0]);

            if (mb_strlen($phone) < 8 || mb_strlen($phone) > 20) {
                $invalid++;

                continue;
            }

            $numbers[$phone] = $row[1] ?? null;
        }

        $created = 0;
        $reactivated = 0;

        DB::transaction(function () use ($companyId, $numbers, &$created, &$reactivated): void {
            foreach ($numbers as $phone => $reason) {
                $entry = BlacklistNumber::query()
                    ->where('company_id', $companyId)
                    ->where('phone', $phone)
                    ->lockForUpdate()
                    ->first();

                if (! $entry) {
                    BlacklistNumber::create([
                        'company_id' => $companyId,
                        'phone' => $phone,
                        'reason' => filled($reason) ? $reason : null,
                        'status' => 'active',
                    ]);
                    $created++;

                    continue;
                }

                if ($entry->status !== 'active') {
                    $entry->update([
                        'status' => 'active',
                        'reason' => filled($reason) ? $reason : $entry->reason,
                    ]);
                    $reactivated++;
                }
            }
        });

        return redirect()->route('tenant.settings.index')->with(
            'status',
            sprintf('Blacklist importada: %d novo(s), %d reativado(s), %d invalido(s) e %d duplicado(s) ignorado(s).', $created, $reactivated, $invalid, max(0, count($rows) - count($numbers) - $invalid))
        );
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
            'category' => ['required', Rule::in(['success', 'neutral', 'negative', 'unproductive', 'callback'])],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'ends_call' => ['sometimes', 'boolean'],
            'is_success' => ['sometimes', 'boolean'],
            'retry_enabled' => ['sometimes', 'boolean'],
            'retry_delay_minutes' => ['nullable', 'integer', 'min:0', 'max:10080'],
            'max_attempts' => ['nullable', 'integer', 'min:1', 'max:50'],
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

    private function validateOperationalParameters(Request $request): array
    {
        return $request->validate([
            'min_pause_minutes' => ['required', 'integer', 'min:0', 'max:480'],
            'max_pause_minutes' => ['required', 'integer', 'min:1', 'max:1440', 'gte:min_pause_minutes'],
            'pause_alert_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'call_alert_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'auto_available_after_call' => ['sometimes', 'boolean'],
            'allow_manual_calls_while_paused' => ['sometimes', 'boolean'],
        ]);
    }

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?: $phone;
    }

    private function nextDispositionCode(int $companyId): string
    {
        $sequence = Disposition::query()->where('company_id', $companyId)->count() + 1;

        do {
            $code = sprintf('TAB-%04d', $sequence++);
        } while (Disposition::query()->where('company_id', $companyId)->where('code', $code)->exists());

        return $code;
    }

    private function authorizeTenantRecord(Request $request, int $companyId): void
    {
        abort_unless($request->user()->company_id === $companyId, 404);
    }
}
