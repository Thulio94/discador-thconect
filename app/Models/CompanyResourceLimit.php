<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'company_id', 'max_registered_users', 'max_active_contacts', 'max_import_rows',
    'max_concurrent_imports', 'max_active_campaigns', 'max_active_webhooks',
    'max_concurrent_exports', 'max_csv_rows', 'max_xlsx_rows', 'max_pdf_rows',
    'max_report_range_days', 'max_daily_attempts', 'dialer_weight', 'hot_history_months',
])]
class CompanyResourceLimit extends Model
{
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
