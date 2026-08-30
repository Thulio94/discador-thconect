<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecordingBackup extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['uploaded_at' => 'datetime'];
    }

    public function call(): BelongsTo
    {
        return $this->belongsTo(Call::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
