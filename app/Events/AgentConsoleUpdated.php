<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class AgentConsoleUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable;

    public bool $afterCommit = true;

    public function __construct(
        public int $companyId,
        public int $userId,
        public ?int $callId,
        public string $change,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("tenant.{$this->companyId}.user.{$this->userId}");
    }

    public function broadcastAs(): string
    {
        return 'agent.console.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'call_id' => $this->callId,
            'change' => $this->change,
        ];
    }
}
