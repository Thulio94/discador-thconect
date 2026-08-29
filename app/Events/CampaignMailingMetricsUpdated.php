<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class CampaignMailingMetricsUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable;

    public bool $afterCommit = true;

    public function __construct(public int $companyId, public int $campaignId) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("tenant.{$this->companyId}.campaigns");
    }

    public function broadcastAs(): string
    {
        return 'campaign.mailing.updated';
    }

    public function broadcastWith(): array
    {
        return ['campaign_id' => $this->campaignId];
    }
}
