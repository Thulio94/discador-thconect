<?php

namespace App\Services\Dialer;

final readonly class DialerCallRequest
{
    public function __construct(
        public int $companyId,
        public ?int $userId,
        public ?int $campaignId,
        public ?int $contactId,
        public ?int $contactPhoneId,
        public string $phone,
        public string $channel = 'simulated',
    ) {}
}
