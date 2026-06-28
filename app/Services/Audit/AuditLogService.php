<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CompanyLicense;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

final class AuditLogService
{
    public function __construct(
        private readonly AuditValueSanitizer $sanitizer,
    ) {}

    public function record(
        string $event,
        string $action,
        string $description,
        ?Model $subject = null,
        ?int $companyId = null,
        ?User $actor = null,
        array $oldValues = [],
        array $newValues = [],
        array $metadata = [],
    ): ?AuditLog {
        try {
            $actor ??= Auth::user() instanceof User ? Auth::user() : null;
            $request = app()->bound('request') ? app('request') : null;
            $oldValues = $this->sanitizer->sanitize($oldValues);
            $newValues = $this->sanitizer->sanitize($newValues);
            $metadata = $this->sanitizer->sanitize($metadata);

            return AuditLog::create([
                'company_id' => $companyId,
                'actor_user_id' => $actor?->id,
                'actor_name' => $actor?->name,
                'actor_email' => $actor?->email,
                'event' => $event,
                'action' => $action,
                'subject_type' => $subject?->getMorphClass(),
                'subject_id' => $subject?->getKey(),
                'subject_label' => $this->subjectLabel($subject),
                'description' => $description,
                'old_values' => $oldValues ?: null,
                'new_values' => $newValues ?: null,
                'metadata' => $metadata ?: null,
                'ip_address' => $request instanceof Request ? $request->ip() : null,
                'user_agent' => $request instanceof Request ? $request->userAgent() : null,
                'route_name' => $request instanceof Request ? $request->route()?->getName() : null,
                'http_method' => $request instanceof Request ? $request->method() : null,
                'created_at' => now(),
            ]);
        } catch (Throwable $exception) {
            Log::error('Falha ao registrar auditoria.', [
                'event' => $event,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    public function recordModelChange(Model $model, string $action): ?AuditLog
    {
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return null;
        }

        $prefix = match ($model::class) {
            Company::class => 'company',
            CompanyLicense::class => 'license',
            User::class => 'user',
            default => null,
        };

        if ($prefix === null) {
            return null;
        }

        $newValues = $action === 'created' ? $model->getAttributes() : $model->getChanges();
        unset($newValues['created_at'], $newValues['updated_at']);

        if ($newValues === []) {
            return null;
        }

        $oldValues = [];

        if ($action === 'updated') {
            foreach (array_keys($newValues) as $key) {
                $oldValues[$key] = $model->getOriginal($key);
            }
        }

        if ($action === 'updated'
            && array_key_exists('status', $newValues)
            && $newValues['status'] === 'inactive'
            && ($oldValues['status'] ?? null) !== 'inactive') {
            $action = 'deactivated';
        }

        $companyId = match (true) {
            $model instanceof Company => (int) $model->getKey(),
            isset($model->company_id) => $model->company_id ? (int) $model->company_id : null,
            default => null,
        };

        return $this->record(
            event: "{$prefix}.{$action}",
            action: $action,
            description: $this->description($prefix, $action),
            subject: $model,
            companyId: $companyId,
            oldValues: $oldValues,
            newValues: $newValues,
        );
    }

    private function subjectLabel(?Model $subject): ?string
    {
        return match (true) {
            $subject instanceof Company => $subject->name,
            $subject instanceof CompanyLicense => 'Licenca #'.$subject->getKey(),
            $subject instanceof User => $subject->name.' | '.$subject->email,
            default => $subject ? class_basename($subject).' #'.$subject->getKey() : null,
        };
    }

    private function description(string $prefix, string $action): string
    {
        $subject = match ($prefix) {
            'company' => 'Empresa',
            'license' => 'Licenca',
            'user' => 'Usuario',
        };

        $verb = match ($action) {
            'created' => 'criada',
            'deactivated' => 'inativada',
            default => 'atualizada',
        };

        if ($subject === 'Usuario') {
            $verb = match ($action) {
                'created' => 'criado',
                'deactivated' => 'inativado',
                default => 'atualizado',
            };
        }

        return "{$subject} {$verb}.";
    }
}
