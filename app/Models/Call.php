<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

#[Fillable(['company_id', 'user_id', 'campaign_id', 'dial_batch_id', 'telephony_route_id', 'channel_reservation_id', 'contact_id', 'contact_phone_id', 'disposition_id', 'disposition_code', 'disposition_name', 'disposition_notes', 'callback_type', 'callback_at', 'service_fields', 'report_snapshot', 'direction', 'status', 'end_reason', 'hangup_party', 'channel', 'telephony_action_id', 'telephony_uniqueid', 'telephony_channel', 'amd_result', 'provider_payload', 'recording_path', 'recording_disk', 'recording_format', 'recording_size', 'recording_available_at', 'phone', 'protocol', 'started_at', 'answered_at', 'ended_at', 'wrap_up_expires_at'])]
class Call extends Model
{
    public const ACTIVE_STATUSES = ['dialing', 'ringing', 'in_progress'];

    protected static function booted(): void
    {
        static::creating(function (Call $call): void {
            if (filled($call->protocol) && ! static::query()->where('protocol', $call->protocol)->exists()) {
                return;
            }

            $prefix = Carbon::parse($call->started_at ?? now())->format('ymd');

            for ($attempt = 0; $attempt < 20; $attempt++) {
                $protocol = $prefix.str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

                if (! static::query()->where('protocol', $protocol)->exists()) {
                    $call->protocol = $protocol;

                    return;
                }
            }

            throw new \RuntimeException('Nao foi possivel gerar um protocolo unico para a chamada.');
        });
    }

    protected $casts = [
        'callback_at' => 'datetime',
        'service_fields' => 'array',
        'report_snapshot' => 'array',
        'provider_payload' => 'array',
        'recording_size' => 'integer',
        'recording_available_at' => 'datetime',
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

    public function telephonyRoute(): BelongsTo
    {
        return $this->belongsTo(TelephonyRoute::class);
    }

    public function callback(): HasOne
    {
        return $this->hasOne(CallCallback::class, 'source_call_id');
    }

    public function channelReservation(): BelongsTo
    {
        return $this->belongsTo(ChannelReservation::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(CallEvent::class)->orderBy('occurred_at')->orderBy('id');
    }
}
