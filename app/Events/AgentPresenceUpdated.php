<?php

namespace App\Events;

use App\Models\AgentStatus;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AgentPresenceUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    /**
     * Status changes are written inside database transactions. Broadcasting only
     * after the commit prevents supervisors from polling the previous status.
     */
    public bool $afterCommit = true;

    public function __construct(public AgentStatus $agentStatus) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("tenant.{$this->agentStatus->company_id}.supervision"),
            new PrivateChannel("tenant.{$this->agentStatus->company_id}.user.{$this->agentStatus->user_id}"),
        ];
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
