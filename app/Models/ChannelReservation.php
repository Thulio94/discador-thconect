<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['id', 'company_id', 'campaign_id', 'user_id', 'telephony_route_id', 'asterisk_node_id', 'call_id', 'status', 'expires_at', 'released_at', 'release_reason'])]
class ChannelReservation extends Model
{
    use HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'released_at' => 'datetime'];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function route(): BelongsTo { return $this->belongsTo(TelephonyRoute::class, 'telephony_route_id'); }
    public function node(): BelongsTo { return $this->belongsTo(AsteriskNode::class, 'asterisk_node_id'); }
    public function call(): BelongsTo { return $this->belongsTo(Call::class); }
}
