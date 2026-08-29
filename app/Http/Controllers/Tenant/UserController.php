<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Services\Capacity\ResourceLimitService;
use App\Services\Telephony\AgentWebRtcService;
use App\Services\Campaigns\CampaignOperationalGuard;
use App\Services\Users\ExtensionAllocator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $companyId = $request->user()->company_id;
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'user_type' => ['nullable', Rule::in(['agent', 'supervisor', 'company_admin'])],
            'role_id' => ['nullable', Rule::exists('roles', 'id')->where('company_id', $companyId)],
            'team_id' => ['nullable', Rule::exists('teams', 'id')->where('company_id', $companyId)],
        ]);

        return view('tenant.users.index', [
            'users' => User::query()
                ->where('company_id', $companyId)
                ->with(['role', 'assignedTeams', 'assignedCampaigns'])
                ->when($filters['q'] ?? null, fn ($query, $search) => $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('extension', 'like', "%{$search}%");
                }))
                ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
                ->when($filters['user_type'] ?? null, fn ($query, $type) => $query->where('user_type', $type))
                ->when($filters['role_id'] ?? null, fn ($query, $roleId) => $query->where('role_id', $roleId))
                ->when($filters['team_id'] ?? null, fn ($query, $teamId) => $query->whereIn('users.id', function ($teamQuery) use ($companyId, $teamId): void {
                    $teamQuery
                        ->select('team_user.user_id')
                        ->from('team_user')
                        ->where('team_user.company_id', $companyId)
                        ->where('team_user.team_id', $teamId);
                }))
                ->latest()
                ->paginate(12)
                ->withQueryString(),
            'roles' => Role::query()->where('company_id', $companyId)->orderBy('name')->get(),
            'teams' => Team::query()->where('company_id', $companyId)->orderBy('name')->get(),
            'campaigns' => Campaign::query()
                ->where('company_id', $companyId)
                ->whereIn('status', ['draft', 'active', 'paused'])
                ->orderBy('name')
                ->get(),
            'filters' => $filters,
        ]);
    }

    public function store(Request $request, ExtensionAllocator $extensions, ResourceLimitService $limits): RedirectResponse
    {
        $companyId = $request->user()->company_id;
        $limits->assertUsers($companyId);
        $this->normalizeEmail($request);
        $this->normalizeSelections($request);

        $data = $request->validate($this->rules($companyId), $this->validationMessages());
        $this->ensureMembershipRules($data);
        $temporaryPassword = Str::random(20);

        $user = DB::transaction(function () use ($companyId, $data, $extensions, $temporaryPassword): User {
            return User::query()->create([
                'company_id' => $companyId,
                'role_id' => $data['role_id'],
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($temporaryPassword),
                'user_type' => $data['user_type'],
                'extension' => $data['status'] === 'active' ? $extensions->allocate() : null,
                'status' => $data['status'],
            ]);
        });

        $this->syncTeams($user, $data['team_ids'] ?? []);
        $this->syncCampaigns($user, $data['campaign_ids'] ?? []);

        return redirect()
            ->route('tenant.users.index')
            ->with('status', $user->extension
                ? "Usuario criado com sucesso. Ramal telefonico: {$user->extension}."
                : 'Usuario criado inativo, sem ramal reservado.')
            ->with('temporary_credentials', [[
                'email' => $user->email,
                'password' => $temporaryPassword,
            ]]);
    }

    public function update(Request $request, User $user, ExtensionAllocator $extensions, AgentWebRtcService $webRtc): RedirectResponse
    {
        abort_unless($user->company_id === $request->user()->company_id, 404);

        $companyId = $request->user()->company_id;
        $this->normalizeEmail($request);
        $this->normalizeSelections($request);
        $data = $request->validate($this->rules($companyId, $user), $this->validationMessages());
        $this->ensureMembershipRules($data);

        $attributes = [
            'role_id' => $data['role_id'],
            'name' => $data['name'],
            'email' => $data['email'],
            'user_type' => $data['user_type'],
            'status' => $data['status'],
        ];

        $passwordChanged = filled($data['password'] ?? null);

        if ($passwordChanged) {
            $attributes['password'] = Hash::make($data['password']);
        }

        $previousStatus = $user->status;
        $extension = DB::transaction(function () use ($user, $attributes, $data, $extensions): ?string {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($data['status'] === 'inactive') {
                $attributes['extension'] = null;
            } elseif (! $lockedUser->extension) {
                $attributes['extension'] = $extensions->allocate();
            }

            $lockedUser->update($attributes);

            return $lockedUser->extension;
        });

        $user->refresh();

        if ($user->status !== 'active' || $user->user_type !== 'agent') {
            $this->removeWebRtcEndpoint($webRtc, $user);
        }

        $this->syncTeams($user, $data['team_ids'] ?? []);
        $this->syncCampaigns($user, $data['campaign_ids'] ?? []);

        $statusMessage = match (true) {
            $previousStatus !== 'inactive' && $data['status'] === 'inactive' => 'Usuario inativado e ramal liberado com sucesso.',
            $previousStatus === 'inactive' && $data['status'] === 'active' => "Usuario reativado com o ramal {$extension}.",
            default => 'Usuario atualizado com sucesso.',
        };

        return redirect()
            ->route('tenant.users.index')
            ->with('status', $passwordChanged
                ? ($statusMessage === 'Usuario atualizado com sucesso.'
                    ? 'Usuario atualizado e senha alterada com sucesso.'
                    : $statusMessage.' Senha alterada com sucesso.')
                : $statusMessage);
    }

    public function destroy(Request $request, User $user, AgentWebRtcService $webRtc): RedirectResponse
    {
        abort_unless($user->company_id === $request->user()->company_id, 404);

        if ($user->is($request->user())) {
            throw ValidationException::withMessages([
                'user' => 'Voce nao pode inativar o proprio usuario.',
            ]);
        }

        $user->update(['status' => 'inactive', 'extension' => null]);
        $this->removeWebRtcEndpoint($webRtc, $user);

        return redirect()
            ->route('tenant.users.index')
            ->with('status', 'Usuario inativado com sucesso.');
    }

    public function regenerateExtension(Request $request, User $user, ExtensionAllocator $extensions, AgentWebRtcService $webRtc): RedirectResponse
    {
        abort_unless($user->company_id === $request->user()->company_id, 404);

        if ($user->status !== 'active') {
            throw ValidationException::withMessages([
                'extension' => 'Reative o usuario antes de gerar um ramal.',
            ]);
        }

        $this->removeWebRtcEndpoint($webRtc, $user);

        $extension = DB::transaction(function () use ($user, $extensions): string {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($user->status !== 'active') {
                throw ValidationException::withMessages([
                    'extension' => 'Reative o usuario antes de gerar um ramal.',
                ]);
            }

            $user->update(['extension' => $extensions->allocate()]);

            return $user->extension;
        });

        return redirect()
            ->route('tenant.users.index')
            ->with('status', "Novo ramal gerado: {$extension}.");
    }

    public function bulkStore(Request $request, ExtensionAllocator $extensions, ResourceLimitService $limits): RedirectResponse
    {
        $companyId = $request->user()->company_id;
        $this->normalizeSelections($request);
        $data = $request->validate($this->bulkRules($companyId), $this->validationMessages());
        $limits->assertUsers($companyId, (int) $data['quantity']);
        $this->ensureMembershipRules($data);
        $emails = $this->bulkEmails($data['email_base'], (int) $data['quantity']);

        if (User::query()->whereIn('email', $emails)->exists()) {
            throw ValidationException::withMessages([
                'email_base' => 'Ja existe um usuario com um dos e-mails que seriam gerados. Altere o e-mail base ou a quantidade.',
            ]);
        }

        $temporaryCredentials = [];

        DB::transaction(function () use ($companyId, $data, $emails, $extensions, &$temporaryCredentials): void {
            foreach ($emails as $index => $email) {
                $sequence = str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
                $temporaryPassword = Str::random(20);
                $user = User::query()->create([
                    'company_id' => $companyId,
                    'role_id' => $data['role_id'],
                    'name' => trim($data['name_prefix']).' '.$sequence,
                    'email' => $email,
                    'password' => Hash::make($temporaryPassword),
                    'user_type' => $data['user_type'],
                    'extension' => $data['status'] === 'active' ? $extensions->allocate() : null,
                    'status' => $data['status'],
                ]);

                $this->syncTeams($user, $data['team_ids'] ?? []);
                $this->syncCampaigns($user, $data['campaign_ids'] ?? []);
                $temporaryCredentials[] = [
                    'email' => $email,
                    'password' => $temporaryPassword,
                ];
            }
        });

        return redirect()
            ->route('tenant.users.index')
            ->with('status', "{$data['quantity']} usuarios criados com sucesso.")
            ->with('temporary_credentials', $temporaryCredentials);
    }

    public function export(Request $request)
    {
        $companyId = $request->user()->company_id;
        $filename = 'usuarios-'.now()->format('Ymd-His').'.csv';

        return Response::streamDownload(function () use ($companyId): void {
            $stream = fopen('php://output', 'w');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['Nome', 'E-mail', 'Ramal', 'Tipo', 'Perfil', 'Status', 'Equipes', 'Campanhas'], ';');

            User::query()
                ->where('company_id', $companyId)
                ->with(['role', 'assignedTeams', 'assignedCampaigns'])
                ->orderBy('id')
                ->lazyById(200)
                ->each(function (User $user) use ($stream): void {
                    fputcsv($stream, [
                        $this->csvValue($user->name),
                        $this->csvValue($user->email),
                        $this->csvValue($user->extension),
                        $this->userTypeLabel($user->user_type),
                        $this->csvValue($user->role?->name ?? ''),
                        $user->status === 'active' ? 'Ativo' : 'Inativo',
                        $this->csvValue($user->assignedTeams->pluck('name')->join(', ')),
                        $this->csvValue($user->assignedCampaigns->pluck('name')->join(', ')),
                    ], ';');
                });

            fclose($stream);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function rules(int $companyId, ?User $user = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'user_type' => ['required', Rule::in(['agent', 'supervisor', 'company_admin'])],
            'role_id' => ['required', Rule::exists('roles', 'id')->where('company_id', $companyId)],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'team_ids' => ['nullable', 'array'],
            'team_ids.*' => [Rule::exists('teams', 'id')->where('company_id', $companyId)],
            'campaign_ids' => ['nullable', 'array'],
            'campaign_ids.*' => [Rule::exists('campaigns', 'id')->where('company_id', $companyId)],
            'password' => $user
                ? ['nullable', 'string', 'min:8', 'max:255', 'confirmed']
                : ['nullable'],
        ];
    }

    private function normalizeEmail(Request $request): void
    {
        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
        ]);
    }

    private function normalizeSelections(Request $request): void
    {
        foreach (['team_ids', 'campaign_ids'] as $field) {
            $request->merge([
                $field => collect($request->input($field, []))
                    ->filter(fn ($value) => filled($value))
                    ->map(fn ($value) => (int) $value)
                    ->unique()
                    ->values()
                    ->all(),
            ]);
        }
    }

    private function validationMessages(): array
    {
        return [
            'email.unique' => 'Este e-mail ja esta vinculado a outro usuario do sistema.',
        ];
    }

    private function syncTeams(User $user, array $teamIds): void
    {
        if ($user->user_type === 'company_admin') {
            $user->teams()->detach();

            return;
        }

        $user->teams()->sync(
            collect($teamIds)
                ->mapWithKeys(fn ($teamId): array => [(int) $teamId => ['membership_type' => $user->user_type]])
                ->all()
        );
    }

    private function syncCampaigns(User $user, array $campaignIds): void
    {
        if ($user->user_type !== 'agent') {
            $user->assignedCampaigns()->detach();

            return;
        }

        app(CampaignOperationalGuard::class)->assertCampaignAssignment($user, $campaignIds);

        $user->assignedCampaigns()->sync(
            collect($campaignIds)
                ->mapWithKeys(fn ($campaignId): array => [(int) $campaignId => ['company_id' => $user->company_id]])
                ->all()
        );
    }

    private function removeWebRtcEndpoint(AgentWebRtcService $webRtc, User $user): void
    {
        try {
            $webRtc->remove($user);
        } catch (\Throwable $exception) {
            report($exception);
            $user->forceFill(['sip_secret' => null])->saveQuietly();
        }
    }

    private function bulkRules(int $companyId): array
    {
        return [
            'name_prefix' => ['required', 'string', 'max:230'],
            'email_base' => ['required', 'string', 'email', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'confirmed' => ['accepted'],
            'user_type' => ['required', Rule::in(['agent', 'supervisor'])],
            'role_id' => ['required', Rule::exists('roles', 'id')->where('company_id', $companyId)],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'team_ids' => ['nullable', 'array', 'max:20'],
            'team_ids.*' => [Rule::exists('teams', 'id')->where('company_id', $companyId)],
            'campaign_ids' => ['nullable', 'array'],
            'campaign_ids.*' => [Rule::exists('campaigns', 'id')->where('company_id', $companyId)],
        ];
    }

    private function bulkEmails(string $emailBase, int $quantity): array
    {
        [$localPart, $domain] = explode('@', Str::lower(trim($emailBase)), 2);

        return collect(range(1, $quantity))
            ->map(fn (int $number): string => sprintf('%s+%03d@%s', $localPart, $number, $domain))
            ->all();
    }

    private function ensureMembershipRules(array $data): void
    {
        if ($data['user_type'] === 'agent' && count($data['team_ids'] ?? []) > 1) {
            throw ValidationException::withMessages([
                'team_ids' => 'Um agente pode participar de apenas uma equipe.',
            ]);
        }
    }

    private function userTypeLabel(string $type): string
    {
        return [
            'agent' => 'Agente',
            'supervisor' => 'Supervisor',
            'company_admin' => 'Administrador',
        ][$type] ?? $type;
    }

    private function csvValue(?string $value): string
    {
        $value = (string) $value;

        return preg_match('/^[=+\\-@]/', $value) === 1 ? "'{$value}" : $value;
    }
}
