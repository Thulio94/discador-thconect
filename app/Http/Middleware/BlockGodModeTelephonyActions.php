<?php

namespace App\Http\Middleware;

use App\Support\GodMode\GodModeContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BlockGodModeTelephonyActions
{
    public function __construct(private readonly GodModeContext $godMode) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_if(
            $this->godMode->isActiveFor($request->user()),
            403,
            'O Modo Deus e destinado ao acompanhamento e nao pode originar ou alterar chamadas.',
        );

        return $next($request);
    }
}
