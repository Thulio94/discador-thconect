<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Str;

final class RecordAuthenticationAudit
{
    public function __construct(
        private readonly AuditLogService $audit,
    ) {}

    public function handle(Login|Logout|Failed $event): void
    {
        $user = $event->user instanceof User ? $event->user : null;
        $attemptedEmail = null;

        if ($event instanceof Failed) {
            $attemptedEmail = Str::lower(trim((string) ($event->credentials['email'] ?? '')));
            $user ??= $attemptedEmail !== ''
                ? User::query()->where('email', $attemptedEmail)->first()
                : null;
        } elseif (! $user) {
            return;
        }

        [$eventName, $action, $description] = match (true) {
            $event instanceof Login => ['auth.login', 'login', 'Login realizado.'],
            $event instanceof Logout => ['auth.logout', 'logout', 'Logout realizado.'],
            default => ['auth.failed', 'failed', 'Tentativa de login invalida.'],
        };

        $this->audit->record(
            event: $eventName,
            action: $action,
            description: $description,
            subject: $user,
            companyId: $user?->company_id,
            actor: $user,
            metadata: $event instanceof Failed && $attemptedEmail !== ''
                ? ['attempted_email' => $attemptedEmail]
                : [],
        );
    }
}
