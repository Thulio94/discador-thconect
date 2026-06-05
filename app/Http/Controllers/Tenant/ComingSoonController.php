<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class ComingSoonController extends Controller
{
    public function __invoke(): View
    {
        return view('tenant.coming-soon.integrations');
    }
}
