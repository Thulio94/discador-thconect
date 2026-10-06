<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentWebRtcPresence extends Model
{
    protected $table = 'agent_webrtc_presences';

    protected $fillable = ['company_id', 'user_id', 'connection_id', 'registered', 'last_seen_at'];

    protected function casts(): array
    {
        return ['registered' => 'boolean', 'last_seen_at' => 'immutable_datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
