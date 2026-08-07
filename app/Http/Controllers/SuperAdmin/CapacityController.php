<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AsteriskNode;
use App\Models\SystemCapacitySetting;
use App\Services\Capacity\ChannelCapacityService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CapacityController extends Controller
{
    public function index(ChannelCapacityService $capacity): View
    {
        return view('super-admin.capacity.index', $capacity->snapshot());
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'max_channels' => ['required', 'integer', 'min:1', 'max:10000'],
            'warning_percent' => ['required', 'integer', 'min:1', 'max:99'],
            'critical_percent' => ['required', 'integer', 'gt:warning_percent', 'max:99'],
            'cpu_warning_percent' => ['required', 'integer', 'min:1', 'max:99'],
            'cpu_critical_percent' => ['required', 'integer', 'gt:cpu_warning_percent', 'max:99'],
            'memory_warning_percent' => ['required', 'integer', 'min:1', 'max:99'],
            'memory_critical_percent' => ['required', 'integer', 'gt:memory_warning_percent', 'max:99'],
            'database_warning_percent' => ['required', 'integer', 'min:1', 'max:99'],
            'database_critical_percent' => ['required', 'integer', 'gt:database_warning_percent', 'max:99'],
            'telephony_queue_warning_ms' => ['required', 'integer', 'min:100', 'max:60000'],
            'telephony_queue_block_ms' => ['required', 'integer', 'gt:telephony_queue_warning_ms', 'max:120000'],
            'origination_blocked' => ['boolean'],
        ]);
        $data['origination_blocked'] = $request->boolean('origination_blocked');
        SystemCapacitySetting::current()->update($data);

        return back()->with('status', 'Protecao global atualizada. Chamadas existentes nao foram afetadas.');
    }

    public function storeNode(Request $request): RedirectResponse
    {
        AsteriskNode::query()->create($this->nodeData($request));
        return back()->with('status', 'No Asterisk cadastrado.');
    }

    public function updateNode(Request $request, AsteriskNode $asteriskNode): RedirectResponse
    {
        $data = $this->nodeData($request, $asteriskNode);
        if (blank($data['bridge_token'] ?? null)) unset($data['bridge_token']);
        $asteriskNode->update($data);
        return back()->with('status', 'No Asterisk atualizado.');
    }

    private function nodeData(Request $request, ?AsteriskNode $node = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('asterisk_nodes')->ignore($node)],
            'bridge_url' => ['required', 'url', 'max:255'],
            'bridge_token' => ['nullable', 'string', 'max:500'],
            'max_channels' => ['required', 'integer', 'min:1', 'max:5000'],
            'priority' => ['required', 'integer', 'min:1', 'max:1000'],
            'status' => ['required', Rule::in(['online', 'degraded', 'offline', 'maintenance'])],
        ]);
    }
}
