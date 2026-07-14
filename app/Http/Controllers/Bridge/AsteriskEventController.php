<?php

namespace App\Http\Controllers\Bridge;

use App\Http\Controllers\Controller;
use App\Services\Telephony\AsteriskCallEventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AsteriskEventController extends Controller
{
    public function __invoke(Request $request, AsteriskCallEventService $events): JsonResponse
    {
        $payload = $request->validate([
            'company_id' => ['required', 'integer'],
            'call_id' => ['required', 'integer'],
            'event' => ['required', Rule::in(['originate_succeeded', 'originate_failed', 'answered', 'amd', 'hangup'])],
            'action_id' => ['nullable', 'string', 'max:120'],
            'channel' => ['nullable', 'string', 'max:255'],
            'uniqueid' => ['nullable', 'string', 'max:120'],
            'cause' => ['nullable', 'string', 'max:120'],
            'amd_result' => ['nullable', 'string', 'max:120'],
            'payload' => ['nullable', 'array'],
        ]);

        $events->apply($payload);

        return response()->json(['accepted' => true]);
    }
}
