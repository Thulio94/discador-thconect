<?php

namespace Tests\Unit;

use App\Services\Telephony\BrazilianPhoneNormalizer;
use PHPUnit\Framework\TestCase;

class BrazilianPhoneNormalizerTest extends TestCase
{
    public function test_it_normalizes_brazilian_fixed_and_mobile_numbers(): void
    {
        $phones = new BrazilianPhoneNormalizer;

        $this->assertSame('3133334444', $phones->normalize('(31) 3333-4444'));
        $this->assertSame('31988880001', $phones->normalize('(31) 8888-0001'));
        $this->assertSame('31988880001', $phones->normalize('+55 (31) 98888-0001'));
        $this->assertSame('31999990001', $phones->normalize('55 31 99999-0001'));
    }

    public function test_it_rejects_numbers_outside_the_brazilian_dialer_contract(): void
    {
        $phones = new BrazilianPhoneNormalizer;

        $this->assertNull($phones->normalize('12345'));
        $this->assertNull($phones->normalize('(20) 99999-0001'));
        $this->assertNull($phones->normalize('(31) 91111-0001'));
        $this->assertNull($phones->normalize('319999999999'));
        $this->assertNull($phones->normalize('telefone 31999990001'));
    }
}
