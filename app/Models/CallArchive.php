<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['company_id', 'period_start', 'period_end', 'disk', 'path', 'records_count', 'checksum', 'archived_at'])]
class CallArchive extends Model
{
    protected function casts(): array { return ['period_start' => 'date', 'period_end' => 'date', 'archived_at' => 'datetime']; }
}
