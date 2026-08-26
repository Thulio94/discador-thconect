<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'asterisk_node_id',
    'provider',
    'tech',
    'host',
    'sip_port',
    'from_domain',
    'outbound_proxy',
    'transport',
    'authentication_mode',
    'sip_username',
    'sip_password',
    'from_user',
    'contact_user',
    'caller_id',
    'dial_prefix',
    'dialplan_context',
    'dialplan_extension',
    'codecs',
    'dtmf_mode',
    'caller_id_header',
    'qualify_frequency',
    'registration_expiration',
    'priority',
    'max_concurrent_channels',
    'enabled',
    'connection_status',
    'last_checked_at',
    'last_error',
])]
class TelephonyRoute extends Model
{
    protected function casts(): array
    {
        return [
            'sip_password' => 'encrypted',
            'sip_port' => 'integer',
            'qualify_frequency' => 'integer',
            'registration_expiration' => 'integer',
            'priority' => 'integer',
            'enabled' => 'boolean',
            'last_checked_at' => 'datetime',
        ];
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'company_telephony_route')
            ->withTimestamps();
    }

    public function asteriskNode(): BelongsTo
    {
        return $this->belongsTo(AsteriskNode::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(ChannelReservation::class);
    }

    public function isReady(): bool
    {
        return $this->enabled
            && filled($this->host)
            && (! in_array($this->authentication_mode, ['registration', 'digest'], true) || (filled($this->sip_username) && filled($this->sip_password)))
            && filled(config('services.asterisk_bridge.token'))
            && filled(config('services.asterisk_bridge.webhook_secret'));
    }

    public function formattedPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?: '';

        if (str_starts_with($digits, '55') && strlen($digits) >= 12) {
            return ($this->dial_prefix ?: $this->tech).$digits;
        }

        return ($this->dial_prefix ?: $this->tech).'55'.$digits;
    }
}
