<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\SystemStatus\SystemStatusService;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpFoundation\Response;

class SystemStatusController extends Controller
{
    public function __construct(
        private readonly SystemStatusService $status,
    ) {}

    public function index(): View
    {
        return view('super-admin.system-status.index', $this->status->snapshot());
    }

    public function poll(): Response
    {
        $html = view('super-admin.system-status.poll', $this->status->snapshot())
            ->fragments(['system-status-summary', 'system-status-components']);

        return response($html)
            ->header('Cache-Control', 'no-store, private')
            ->header('X-Poll-Interval', '30');
    }
}
