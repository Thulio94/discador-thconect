<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'user_id', 'campaign_id', 'started_at', 'ended_at'])]
class AgentCampaignSession extends Model
{
    protected $casts = ['started_at' => 'datetime', 'ended_at' => 'datetime'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function campaign(): BelongsTo { return $this->belongsTo(Campaign::class); }
}
