<?php

namespace Tests\Unit;

use App\Services\Audit\AuditValueSanitizer;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class AuditValueSanitizerTest extends TestCase
{
    public function test_it_removes_sensitive_keys_recursively(): void
    {
        $result = (new AuditValueSanitizer)->sanitize([
            'name' => 'Cliente',
            'password' => 'nao-gravar',
            'nested' => [
                'api_key' => 'nao-gravar',
                'token_value' => 'nao-gravar',
                'status' => 'active',
            ],
        ]);

        $this->assertSame([
            'name' => 'Cliente',
            'nested' => ['status' => 'active'],
        ], $result);
    }

    public function test_it_normalizes_dates_and_limits_long_strings(): void
    {
        $result = (new AuditValueSanitizer)->sanitize([
            'date' => new DateTimeImmutable('2026-06-28T10:00:00-03:00'),
            'long' => str_repeat('a', 2100),
        ]);

        $this->assertSame('2026-06-28T10:00:00-03:00', $result['date']);
        $this->assertSame(2003, strlen($result['long']));
        $this->assertStringEndsWith('...', $result['long']);
    }
}
