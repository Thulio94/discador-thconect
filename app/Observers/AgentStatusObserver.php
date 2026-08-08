<?php

namespace App\Observers;

use App\Events\AgentPresenceUpdated;
use App\Models\AgentStatus;

class AgentStatusObserver
{
    public function created(AgentStatus $agentStatus): void
    {
        event(new AgentPresenceUpdated($agentStatus));
    }
}
