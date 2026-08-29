<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'campaign_id', 'contact_list_id', 'contact_id', 'contact_phone_id', 'status', 'last_result', 'last_disposition_id', 'attempts_count', 'recycle_count', 'last_attempt_at', 'next_attempt_at', 'delivered_at', 'completed_at'])]
class CampaignPhoneState extends Model
{
    public const STATUSES = ['pending', 'dialing', 'delivered', 'abandoned', 'voicemail_pre', 'voicemail_post', 'no_answer', 'failed'];

    protected $casts = [
        'last_attempt_at' => 'datetime',
        'next_attempt_at' => 'datetime',
        'delivered_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function campaign(): BelongsTo { return $this->belongsTo(Campaign::class); }
    public function contact(): BelongsTo { return $this->belongsTo(Contact::class); }
    public function contactPhone(): BelongsTo { return $this->belongsTo(ContactPhone::class); }
    public function contactList(): BelongsTo { return $this->belongsTo(ContactList::class); }
    public function lastDisposition(): BelongsTo { return $this->belongsTo(Disposition::class, 'last_disposition_id'); }
}
