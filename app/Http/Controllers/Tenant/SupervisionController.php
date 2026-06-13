<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Services\Supervision\SupervisionSnapshot;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SupervisionController extends Controller
{
    public function __construct(
        private readonly SupervisionSnapshot $snapshot
    ) {}

    public function index(Request $request): View
    {
        return view(
            'tenant.supervision.index',
            $this->snapshot->forCompany(
                $request->user()->company_id,
                $request->string('status')->toString(),
                $request->integer('team_id') ?: null,
            )
        );
    }

    public function poll(Request $request): Response
    {
        $html = view(
            'tenant.supervision.poll',
            $this->snapshot->forCompany(
                $request->user()->company_id,
                $request->string('status')->toString(),
                $request->integer('team_id') ?: null,
            )
        )->fragments(['supervision-metrics', 'supervision-agents']);

        return response($html)
            ->header('Cache-Control', 'no-store, private')
            ->header('X-Poll-Interval', '10');
    }
}
