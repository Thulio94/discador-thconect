<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'user_id', 'campaign_id', 'contact_id', 'contact_phone_id', 'disposition_id', 'disposition_code', 'disposition_name', 'disposition_notes', 'callback_type', 'callback_at', 'service_fields', 'direction', 'status', 'end_reason', 'channel', 'telephony_action_id', 'telephony_uniqueid', 'telephony_channel', 'amd_result', 'provider_payload', 'phone', 'started_at', 'answered_at', 'ended_at', 'wrap_up_expires_at'])]
class Call extends Model
{
    protected $casts = [
        'callback_at' => 'datetime',
        'service_fields' => 'array',
        'provider_payload' => 'array',
        'started_at' => 'datetime',
        'answered_at' => 'datetime',
        'ended_at' => 'datetime',
        'wrap_up_expires_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function contactPhone(): BelongsTo
    {
        return $this->belongsTo(ContactPhone::class);
    }

    public function disposition(): BelongsTo
    {
        return $this->belongsTo(Disposition::class);
    }
}
