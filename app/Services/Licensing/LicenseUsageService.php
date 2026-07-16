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
        return $this->forCompanies(collect([$company]))[$company->id];
    }

    /**
     * @param  iterable<Company>  $companies
     * @return array<int, array<string, mixed>>
     */
    public function forCompanies(iterable $companies): array
    {
        $companies = collect($companies)->values();
        $companies->each(fn (Company $company) => $company->loadMissing('license'));
        $companyIds = $companies->pluck('id');

        if ($companyIds->isEmpty()) {
            return [];
        }

        $availableAgents = User::query()
            ->whereIn('company_id', $companyIds)
            ->where('status', 'active')
            ->where('user_type', 'agent')
            ->whereHas('currentAgentStatus', fn ($query) => $query->where('status', 'available'))
            ->selectRaw('company_id, count(*) as aggregate')
            ->groupBy('company_id')
            ->pluck('aggregate', 'company_id');

        $activeCalls = Call::query()
            ->whereIn('company_id', $companyIds)
            ->where('status', 'in_progress')
            ->selectRaw('company_id, count(*) as aggregate')
            ->groupBy('company_id')
            ->pluck('aggregate', 'company_id');

        return $companies->mapWithKeys(function (Company $company) use ($availableAgents, $activeCalls): array {
            $license = $company->license;
            $availableAgentCount = (int) ($availableAgents[$company->id] ?? 0);
            $activeCallCount = (int) ($activeCalls[$company->id] ?? 0);
            $callsPerAvailableAgent = max(1, (int) ($license?->max_calls_per_available_agent ?? 1));
            $agentCallCapacity = $availableAgentCount * $callsPerAvailableAgent;
            $contractedCallLimit = $license?->max_concurrent_calls;
            $availableAgentLimit = $license?->max_concurrent_agents;
            $effectiveCallLimit = $contractedCallLimit === null
                ? $agentCallCapacity
                : min($agentCallCapacity, $contractedCallLimit);

            return [$company->id => [
                'license' => $license,
                'available_agents' => $availableAgentCount,
                'active_calls' => $this->metric($activeCallCount, $contractedCallLimit),
                'available_agent_slots' => $this->metric($availableAgentCount, $availableAgentLimit),
                'agent_call_capacity' => $agentCallCapacity,
                'effective_call_limit' => $effectiveCallLimit,
                'calls_per_available_agent' => $callsPerAvailableAgent,
                'status' => $this->worstStatus([
                    $this->status($activeCallCount, $contractedCallLimit),
                    $this->status($availableAgentCount, $availableAgentLimit),
                ]),
            ]];
        })->all();
    }

    public function overview(): array
    {
        $companies = Company::query()
            ->with('license')
            ->where('status', 'active')
            ->get();

        $summaries = collect($this->forCompanies($companies));
        $licenses = $companies->pluck('license')->filter();

        return [
            'companies' => $companies->count(),
            'available_agents' => $summaries->sum('available_agents'),
            'available_agent_slots' => $this->metric(
                $summaries->sum('available_agent_slots.current'),
                $this->sumNullableLimit($licenses, 'max_concurrent_agents')
            ),
            'active_calls' => $this->metric(
                $summaries->sum('active_calls.current'),
                $this->sumNullableLimit($licenses, 'max_concurrent_calls')
            ),
            'status' => $this->worstStatus($summaries->pluck('status')->all()),
        ];
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
