<?php

namespace App\Events;

use App\Models\AgentStatus;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AgentPresenceUpdated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(public AgentStatus $agentStatus) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("tenant.{$this->agentStatus->company_id}.supervision");
    }

    public function broadcastAs(): string
    {
        return 'agent.presence.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->agentStatus->user_id,
            'status' => $this->agentStatus->status,
            'changed_at' => $this->agentStatus->started_at?->toIso8601String(),
        ];
    }
}
