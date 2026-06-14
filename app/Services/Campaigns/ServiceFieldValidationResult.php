<?php

namespace App\Services\Campaigns;

final readonly class ServiceFieldValidationResult
{
    public function __construct(
        public array $values,
        public array $errors,
    ) {}

    public function passes(): bool
    {
        return $this->errors === [];
    }
}
