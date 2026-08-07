<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'source_call_id', 'completion_call_id', 'contact_id', 'contact_phone_id', 'campaign_id', 'assigned_user_id', 'phone', 'type', 'status', 'scheduled_at', 'completed_at', 'cancelled_at', 'cancel_reason'])]
class CallCallback extends Model
{
    protected $casts = ['scheduled_at' => 'datetime', 'completed_at' => 'datetime', 'cancelled_at' => 'datetime'];

    public function sourceCall(): BelongsTo
    {
        return $this->belongsTo(Call::class, 'source_call_id');
    }

    public function completionCall(): BelongsTo
    {
        return $this->belongsTo(Call::class, 'completion_call_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function contactPhone(): BelongsTo
    {
        return $this->belongsTo(ContactPhone::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
