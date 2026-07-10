<?php

namespace App\Support\Tenant;

class PermissionMatrix
{
    public static function modules(): array
    {
        return [
            'dashboard' => ['label' => 'Dashboard', 'permissions' => self::onlyRead('dashboard')],
            'users' => ['label' => 'Usuarios', 'permissions' => self::crud('users')],
            'roles' => ['label' => 'Perfis', 'permissions' => self::crud('roles')],
            'teams' => ['label' => 'Equipes', 'permissions' => self::crud('teams')],
            'lists' => ['label' => 'Listas', 'permissions' => self::crud('lists')],
            'campaigns' => [
                'label' => 'Campanhas',
                'permissions' => [
                    ...self::crud('campaigns'),
                    'operate' => ['key' => 'campaigns.operate', 'label' => 'Operar'],
                ],
            ],
            'agent' => ['label' => 'Agente', 'permissions' => self::actions('agent', [
                'access' => 'Acessar',
            ])],
            'supervision' => ['label' => 'Supervisao', 'permissions' => self::onlyRead('supervision')],
            'reports' => ['label' => 'Relatorios', 'permissions' => self::actions('reports', [
                'read' => 'Ler',
                'export' => 'Exportar',
                'schedules' => 'Agendar',
            ])],
            'telephony' => ['label' => 'Telefonia', 'permissions' => self::actions('telephony', [
                'read' => 'Ler',
                'update' => 'Preparar rota',
            ])],
            'settings' => ['label' => 'Configuracoes', 'permissions' => self::crud('settings')],
            'licenses' => ['label' => 'Licencas', 'permissions' => self::crud('licenses')],
            'notifications' => ['label' => 'Notificacoes', 'permissions' => self::actions('notifications', [
                'read' => 'Ler',
                'update' => 'Marcar como lida',
            ])],
            'audit' => ['label' => 'Auditoria', 'permissions' => self::onlyRead('audit')],
            'integrations' => ['label' => 'Integracoes', 'permissions' => self::onlyRead('integrations')],
        ];
    }

    public static function keys(): array
    {
        return collect(self::modules())
            ->flatMap(fn (array $module) => collect($module['permissions'])->pluck('key'))
            ->values()
            ->all();
    }

    private static function crud(string $module): array
    {
        return [
            'read' => ['key' => "{$module}.read", 'label' => 'Ler'],
            'create' => ['key' => "{$module}.create", 'label' => 'Criar'],
            'update' => ['key' => "{$module}.update", 'label' => 'Editar'],
            'delete' => ['key' => "{$module}.delete", 'label' => 'Excluir/Inativar'],
        ];
    }

    private static function onlyRead(string $module): array
    {
        return ['read' => ['key' => "{$module}.read", 'label' => 'Ler']];
    }

    private static function actions(string $module, array $actions): array
    {
        return collect($actions)
            ->mapWithKeys(fn (string $label, string $action): array => [
                $action => ['key' => "{$module}.{$action}", 'label' => $label],
            ])
            ->all();
    }
}
