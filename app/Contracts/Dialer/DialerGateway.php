<?php

namespace App\Contracts\Dialer;

use App\Models\Call;
use App\Services\Dialer\DialerCallRequest;

interface DialerGateway
{
    public function originate(DialerCallRequest $request): Call;

    public function cancel(DialerCallRequest $request, string $reason): Call;

    public function hangup(Call $call): Call;
}
