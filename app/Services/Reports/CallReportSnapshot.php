<?php

namespace App\Services\Reports;

use App\Models\Call;
use App\Models\Team;

final class CallReportSnapshot
{
    public function capture(Call $call): void
    {
        $call->loadMissing(['user', 'campaign', 'contact.contactList', 'telephonyRoute']);
        $teamNames = $call->user_id
            ? Team::query()->where('company_id', $call->company_id)->whereHas('members', fn ($query) => $query->whereKey($call->user_id))->orderBy('name')->pluck('name')
            : [];
        $teamNames = is_array($teamNames) ? $teamNames : $teamNames->all();
        $call->update(['report_snapshot' => [
            'agent_name' => $call->user?->name,
            'agent_extension' => $call->user?->extension,
            'campaign_name' => $call->campaign?->name,
            'contact_name' => $call->contact?->name,
            'list_name' => $call->contact?->contactList?->name,
            'team_names' => $teamNames,
            'route_name' => $call->telephonyRoute?->name,
        ]]);
    }
}
