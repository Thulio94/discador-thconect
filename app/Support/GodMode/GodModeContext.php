<?php

namespace App\Support\GodMode;

use App\Models\Company;
use App\Models\User;

final class GodModeContext
{
    private const SESSION_KEY = 'god_mode.company_id';

    private ?Company $company = null;

    public function enter(Company $company): void
    {
        session()->put(self::SESSION_KEY, $company->id);
        $this->company = $company;
    }

    public function leave(): void
    {
        session()->forget(self::SESSION_KEY);
        $this->company = null;
    }

    public function isActiveFor(?User $user): bool
    {
        return (bool) $user?->is_super_admin && $this->companyId() !== null;
    }

    public function companyId(): ?int
    {
        $companyId = session(self::SESSION_KEY);

        return is_numeric($companyId) && (int) $companyId > 0 ? (int) $companyId : null;
    }

    public function company(): ?Company
    {
        $companyId = $this->companyId();

        if ($companyId === null) {
            return null;
        }

        if ($this->company?->getKey() === $companyId) {
            return $this->company;
        }

        return $this->company = Company::query()->find($companyId);
    }
}
