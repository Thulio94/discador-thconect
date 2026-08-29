<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('tenant.{companyId}.user.{userId}', function ($user, $companyId, $userId) {
    return (int) $user->company_id === (int) $companyId && (int) $user->id === (int) $userId;
});

Broadcast::channel('tenant.{companyId}.supervision', function ($user, $companyId) {
    if ((int) $user->company_id === (int) $companyId && $user->hasPermission('supervision.read')) {
        return true;
    }

    return $user->is_super_admin
        && app(\App\Support\GodMode\GodModeContext::class)->companyId() === (int) $companyId;
});

Broadcast::channel('tenant.{companyId}.campaigns', function ($user, $companyId) {
    return (int) $user->company_id === (int) $companyId
        && $user->hasPermission('campaigns.read');
});
