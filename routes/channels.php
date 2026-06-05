<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('tenant.{companyId}.user.{userId}', function ($user, $companyId, $userId) {
    return (int) $user->company_id === (int) $companyId && (int) $user->id === (int) $userId;
});
