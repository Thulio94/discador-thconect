<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'campaign_id', 'contact_list_id', 'user_id', 'filters', 'affected_count', 'request_key'])]
class MailingRecycle extends Model
{
    protected $casts = ['filters' => 'array'];

    public function campaign(): BelongsTo { return $this->belongsTo(Campaign::class); }
    public function contactList(): BelongsTo { return $this->belongsTo(ContactList::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
