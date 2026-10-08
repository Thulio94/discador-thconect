<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
    'preflight_config_hash',
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
            && (filled($this->tech) || filled($this->dial_prefix))
            && (! in_array($this->authentication_mode, ['registration', 'digest'], true) || (filled($this->sip_username) && filled($this->sip_password)))
            && filled(config('services.asterisk_bridge.token'))
            && filled(config('services.asterisk_bridge.webhook_secret'));
    }

    public function preflightFingerprint(): string
    {
        return hash_hmac('sha256', json_encode([
            'asterisk_node_id' => $this->asterisk_node_id,
            'tech' => $this->tech,
            'dial_prefix' => $this->dial_prefix,
            'host' => $this->host,
            'sip_port' => $this->sip_port ?: ($this->transport === 'tls' ? 5061 : 5060),
            'from_domain' => $this->from_domain ?: $this->host,
            'outbound_proxy' => $this->outbound_proxy,
            'transport' => $this->transport,
            'authentication_mode' => $this->authentication_mode,
            'sip_username' => $this->sip_username,
            'sip_password' => $this->sip_password,
            'from_user' => $this->from_user,
            'contact_user' => $this->contact_user,
            'caller_id' => $this->caller_id,
            'dialplan_context' => $this->dialplan_context,
            'dialplan_extension' => $this->dialplan_extension,
            'codecs' => $this->codecs,
            'dtmf_mode' => $this->dtmf_mode ?: 'rfc4733',
            'caller_id_header' => $this->caller_id_header ?: 'none',
            'qualify_frequency' => $this->qualify_frequency ?? 15,
            'registration_expiration' => $this->registration_expiration ?? 3600,
        ], JSON_THROW_ON_ERROR), (string) config('app.key'));
    }

    public function hasSuccessfulSipPreflight(): bool
    {
        if ($this->connection_status !== 'pending_test'
            || $this->last_checked_at === null
            || filled($this->last_error)) {
            return false;
        }

        if (filled($this->preflight_config_hash)) {
            return hash_equals((string) $this->preflight_config_hash, $this->preflightFingerprint());
        }

        return $this->last_checked_at->greaterThanOrEqualTo($this->updated_at);
    }

    public function canStartOutboundCall(): bool
    {
        return $this->enabled
            && ($this->connection_status === 'online' || $this->hasSuccessfulSipPreflight());
    }

    public function formattedPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?: '';
        $e164 = str_starts_with($digits, '55') && strlen($digits) >= 12
            ? $digits
            : '55'.$digits;

        if (filled($this->tech)) {
            return $this->tech.$e164;
        }

        return (filled($this->dial_prefix) ? $this->dial_prefix : '').substr($e164, 2);
    }
}
