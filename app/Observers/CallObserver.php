<?php

namespace App\Observers;

use App\Events\AgentConsoleUpdated;
use App\Models\Call;

class CallObserver
{
    public function saved(Call $call): void
    {
        if (! $call->user_id || (! $call->wasRecentlyCreated && ! $call->wasChanged([
            'status',
            'answered_at',
            'ended_at',
            'disposition_id',
            'callback_at',
            'recording_available_at',
        ]))) {
            return;
        }

        event(new AgentConsoleUpdated(
            companyId: (int) $call->company_id,
            userId: (int) $call->user_id,
            callId: (int) $call->id,
            change: $call->wasRecentlyCreated ? 'call.created' : 'call.updated',
        ));
    }
}
