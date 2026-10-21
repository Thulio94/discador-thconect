<?php

namespace Tests\Feature;

use App\Jobs\DispatchPowerCampaigns;
use App\Jobs\RunPowerCampaign;
use App\Models\Campaign;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schedule;
use Tests\TestCase;

class PowerCampaignDispatchQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatcher_queues_only_active_power_campaigns_on_telephony(): void
    {
        Queue::fake();

        $company = Company::create(['name' => 'Empresa de teste do discador', 'status' => 'active']);
        $activePower = Campaign::create([
            'company_id' => $company->id,
            'name' => 'Power ativo',
            'dialing_mode' => 'power',
            'status' => 'active',
        ]);
        $activePreview = Campaign::create([
            'company_id' => $company->id,
            'name' => 'Preview ativo',
            'dialing_mode' => 'preview',
            'status' => 'active',
        ]);
        $pausedPower = Campaign::create([
            'company_id' => $company->id,
            'name' => 'Power pausado',
            'dialing_mode' => 'power',
            'status' => 'paused',
        ]);

        (new DispatchPowerCampaigns)->handle();

        Queue::assertPushedOn('telephony', RunPowerCampaign::class, fn (RunPowerCampaign $job): bool => $job->campaignId === $activePower->id);
        Queue::assertNotPushed(RunPowerCampaign::class, fn (RunPowerCampaign $job): bool => $job->campaignId === $activePreview->id);
        Queue::assertNotPushed(RunPowerCampaign::class, fn (RunPowerCampaign $job): bool => $job->campaignId === $pausedPower->id);
        Queue::assertPushed(RunPowerCampaign::class, 1);
    }

    public function test_scheduler_runs_the_dispatcher_directly_and_only_queues_campaign_jobs(): void
    {
        Queue::fake();

        $company = Company::create(['name' => 'Empresa de teste do scheduler', 'status' => 'active']);
        $campaign = Campaign::create([
            'company_id' => $company->id,
            'name' => 'Power agendado',
            'dialing_mode' => 'power',
            'status' => 'active',
        ]);

        $event = collect(Schedule::events())
            ->first(fn ($event): bool => $event->description === 'dialer:dispatch-power-campaigns');

        $this->assertNotNull($event);
        $this->assertTrue($event->isRepeatable());

        $event->run(app());

        Queue::assertPushedOn('telephony', RunPowerCampaign::class, fn (RunPowerCampaign $job): bool => $job->campaignId === $campaign->id);
        Queue::assertNotPushed(DispatchPowerCampaigns::class);
    }

    public function test_only_one_power_campaign_job_can_be_queued_per_campaign(): void
    {
        Queue::fake();
        Cache::flush();

        $company = Company::create(['name' => 'Empresa de teste de deduplicacao', 'status' => 'active']);
        $campaign = Campaign::create([
            'company_id' => $company->id,
            'name' => 'Power sem duplicidade',
            'dialing_mode' => 'power',
            'status' => 'active',
        ]);

        RunPowerCampaign::dispatch($campaign->id)->onQueue('telephony');
        RunPowerCampaign::dispatch($campaign->id)->onQueue('telephony');

        Queue::assertPushed(RunPowerCampaign::class, 1);
    }
}
