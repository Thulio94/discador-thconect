<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'bridge_url', 'bridge_token', 'max_channels', 'current_channels', 'priority', 'status', 'last_heartbeat_at', 'last_error'])]
class AsteriskNode extends Model
{
    protected function casts(): array
    {
        return ['bridge_token' => 'encrypted', 'last_heartbeat_at' => 'datetime'];
    }

    public function routes(): HasMany
    {
        return $this->hasMany(TelephonyRoute::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(ChannelReservation::class);
    }
}
