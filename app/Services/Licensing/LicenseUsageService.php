<?php

namespace App\Services\Licensing;

use App\Models\Call;
use App\Models\Company;
use App\Models\CompanyLicense;
use App\Models\User;

class LicenseUsageService
{
    public function forCompany(Company $company): array
    {
        $company->loadMissing('license');

        $license = $company->license;
        $activeUsers = $this->activeUsers($company->id);
        $availableAgents = $this->availableAgents($company->id);
        $activeCalls = $this->activeCalls($company->id);
        $callsPerAvailableAgent = max(1, (int) ($license?->max_calls_per_available_agent ?? 1));
        $agentCallCapacity = $availableAgents * $callsPerAvailableAgent;
        $contractedCallLimit = $license?->max_concurrent_calls;
        $effectiveCallLimit = $contractedCallLimit === null
            ? $agentCallCapacity
            : min($agentCallCapacity, $contractedCallLimit);

        return [
            'license' => $license,
            'users' => $this->metric($activeUsers, $license?->max_users),
            'available_agents' => $this->metric($availableAgents, $license?->max_concurrent_agents),
            'active_calls' => $this->metric($activeCalls, $contractedCallLimit),
            'agent_call_capacity' => $agentCallCapacity,
            'effective_call_limit' => $effectiveCallLimit,
            'calls_per_available_agent' => $callsPerAvailableAgent,
            'status' => $this->worstStatus([
                $this->status($activeUsers, $license?->max_users),
                $this->status($availableAgents, $license?->max_concurrent_agents),
                $this->status($activeCalls, $contractedCallLimit),
            ]),
        ];
    }

    public function overview(): array
    {
        $companies = Company::query()
            ->with('license')
            ->where('status', 'active')
            ->get();

        $summaries = $companies->map(fn (Company $company): array => $this->forCompany($company));
        $licenses = $companies->pluck('license')->filter();

        return [
            'companies' => $companies->count(),
            'users' => $this->metric(
                $summaries->sum('users.current'),
                $this->sumNullableLimit($licenses, 'max_users')
            ),
            'available_agents' => $this->metric(
                $summaries->sum('available_agents.current'),
                $this->sumNullableLimit($licenses, 'max_concurrent_agents')
            ),
            'active_calls' => $this->metric(
                $summaries->sum('active_calls.current'),
                $this->sumNullableLimit($licenses, 'max_concurrent_calls')
            ),
            'status' => $this->worstStatus($summaries->pluck('status')->all()),
        ];
    }

    private function activeUsers(int $companyId): int
    {
        return User::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->count();
    }

    private function availableAgents(int $companyId): int
    {
        return User::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->where('user_type', 'agent')
            ->whereHas('currentAgentStatus', fn ($query) => $query->where('status', 'available'))
            ->count();
    }

    private function activeCalls(int $companyId): int
    {
        return Call::query()
            ->where('company_id', $companyId)
            ->where('status', 'in_progress')
            ->count();
    }

    private function metric(int $current, ?int $limit): array
    {
        return [
            'current' => $current,
            'limit' => $limit,
            'percent' => $limit && $limit > 0 ? min(999, (int) round(($current / $limit) * 100)) : null,
            'status' => $this->status($current, $limit),
        ];
    }

    private function status(int $current, ?int $limit): string
    {
        if ($limit === null || $limit <= 0) {
            return 'unlimited';
        }

        if ($current > $limit) {
            return 'exceeded';
        }

        if ($current >= (int) ceil($limit * 0.9)) {
            return 'warning';
        }

        return 'ok';
    }

    /**
     * @param  iterable<CompanyLicense>  $licenses
     */
    private function sumNullableLimit(iterable $licenses, string $field): ?int
    {
        $sum = 0;
        $hasLimit = false;

        foreach ($licenses as $license) {
            if ($license->{$field} === null) {
                continue;
            }

            $hasLimit = true;
            $sum += (int) $license->{$field};
        }

        return $hasLimit ? $sum : null;
    }

    private function worstStatus(array $statuses): string
    {
        foreach (['exceeded', 'warning', 'ok'] as $status) {
            if (in_array($status, $statuses, true)) {
                return $status;
            }
        }

        return 'unlimited';
    }
}
