<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'campaign_id', 'user_id', 'campaign_phone_state_id', 'reserved_at', 'released_at'])]
class PreviewLeadReservation extends Model
{
    protected $casts = ['reserved_at' => 'datetime', 'released_at' => 'datetime'];

    public function campaign(): BelongsTo { return $this->belongsTo(Campaign::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function phoneState(): BelongsTo { return $this->belongsTo(CampaignPhoneState::class, 'campaign_phone_state_id'); }
}
