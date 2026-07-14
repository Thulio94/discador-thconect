<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'company_id',
    'driver',
    'trunk_label',
    'outbound_route_label',
    'caller_id',
    'ami_host',
    'ami_port',
    'ami_username',
    'ami_secret',
    'outbound_channel_template',
    'dialplan_context',
    'dialplan_extension',
    'bridge_enabled',
    'connection_status',
    'last_checked_at',
    'last_error',
])]
class CompanyTelephonyConfiguration extends Model
{
    protected function casts(): array
    {
        return [
            'ami_port' => 'integer',
            'ami_username' => 'encrypted',
            'ami_secret' => 'encrypted',
            'bridge_enabled' => 'boolean',
            'last_checked_at' => 'datetime',
        ];
    }

    public function isReadyForAsterisk(): bool
    {
        return $this->driver === 'asterisk_ami'
            && $this->bridge_enabled
            && filled($this->ami_host)
            && filled($this->ami_username)
            && filled($this->ami_secret)
            && filled($this->outbound_channel_template)
            && filled($this->dialplan_context)
            && filled($this->dialplan_extension)
            && filled(config('services.asterisk_bridge.token'))
            && filled(config('services.asterisk_bridge.webhook_secret'));
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
