<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurgeAuditLogsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_super_admin;
    }

    public function rules(): array
    {
        return [
            'scope' => ['required', Rule::in(['company', 'global'])],
            'company_id' => [
                'nullable',
                'required_if:scope,company',
                'prohibited_if:scope,global',
                Rule::exists('companies', 'id'),
            ],
            'date_from' => ['required', 'date_format:Y-m-d'],
            'date_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
