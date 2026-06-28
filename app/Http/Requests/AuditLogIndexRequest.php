<?php

namespace App\Http\Requests;

use App\Models\Company;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AuditLogIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'company_id' => ['nullable', 'integer', Rule::exists(Company::class, 'id')],
            'global_only' => ['nullable', 'boolean'],
            'actor_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'event' => ['nullable', 'string', 'max:100'],
            'search' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->boolean('global_only') && $this->filled('company_id')) {
                    $validator->errors()->add('global_only', 'Escolha uma empresa ou apenas eventos globais.');
                }
            },
        ];
    }
}
