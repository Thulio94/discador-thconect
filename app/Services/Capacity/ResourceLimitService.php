<?php

namespace App\Services\Capacity;

use App\Models\Campaign;
use App\Models\CompanyResourceLimit;
use App\Models\CompanyWebhook;
use App\Models\Contact;
use App\Models\ReportExport;
use App\Models\User;
use App\Models\ChannelReservation;
use App\Models\SystemCapacitySetting;
use Illuminate\Validation\ValidationException;

class ResourceLimitService
{
    public function limits(int $companyId): CompanyResourceLimit
    {
        return CompanyResourceLimit::query()->firstOrCreate(['company_id' => $companyId])->refresh();
    }

    public function assertUsers(int $companyId, int $additional = 1): void
    {
        $this->assertBelow('users', User::query()->where('company_id', $companyId)->count() + $additional, $this->limits($companyId)->max_registered_users, 'A protecao de usuarios cadastrados foi atingida. Contate o administrador.');
    }

    public function assertCampaignCanActivate(int $companyId, ?int $exceptId = null): void
    {
        $count = Campaign::query()->where('company_id', $companyId)->where('status', 'active')->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))->count() + 1;
        $this->assertBelow('status', $count, $this->limits($companyId)->max_active_campaigns, 'A protecao de campanhas ativas foi atingida. Contate o administrador.');
    }

    public function assertWebhook(int $companyId): void
    {
        $count = CompanyWebhook::query()->where('company_id', $companyId)->where('status', 'active')->count() + 1;
        $this->assertBelow('status', $count, $this->limits($companyId)->max_active_webhooks, 'A protecao de webhooks ativos foi atingida. Contate o administrador.');
    }

    public function assertExport(int $companyId, string $format): void
    {
        $this->assertBackgroundWorkAllowed('format');
        $limits = $this->limits($companyId);
        $active = ReportExport::query()->where('company_id', $companyId)->whereIn('status', ['queued', 'processing'])->count();
        $this->assertBelow('format', $active + 1, $limits->max_concurrent_exports, 'Ja existem exportacoes em processamento. Aguarde a conclusao.');
    }

    public function assertImport(int $companyId): CompanyResourceLimit
    {
        $this->assertBackgroundWorkAllowed('import_file');
        return $this->limits($companyId);
    }

    private function assertBackgroundWorkAllowed(string $field): void
    {
        $settings = SystemCapacitySetting::current();
        $used = ChannelReservation::query()->whereIn('status', ['reserved', 'active'])->where('expires_at', '>', now())->count();
        if ($settings->origination_blocked || $used >= (int) floor($settings->max_channels * ($settings->critical_percent / 100))) {
            throw ValidationException::withMessages([$field => 'O sistema esta priorizando chamadas em andamento. Tente novamente em alguns minutos.']);
        }
    }

    public function assertContactCapacity(int $companyId, int $additional): void
    {
        $count = Contact::query()->where('company_id', $companyId)->whereNotIn('status', ['exhausted', 'inactive'])->count() + $additional;
        $this->assertBelow('import_file', $count, $this->limits($companyId)->max_active_contacts, 'A protecao de contatos ativos foi atingida. Contate o administrador.');
    }

    private function assertBelow(string $field, int $requested, int $limit, string $message): void
    {
        if ($requested > $limit) throw ValidationException::withMessages([$field => $message]);
    }
}
