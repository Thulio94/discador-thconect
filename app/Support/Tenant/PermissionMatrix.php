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
            'licenses' => ['label' => 'Licencas', 'permissions' => self::crud('licenses')],
            'notifications' => ['label' => 'Notificacoes', 'permissions' => self::onlyRead('notifications')],
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
}
