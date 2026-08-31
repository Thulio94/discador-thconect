<?php

namespace App\Http\Controllers;

use App\Support\Auth\RoleHome;
use Illuminate\Http\RedirectResponse;

class LandingController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        return auth()->check()
            ? redirect()->to(RoleHome::route(auth()->user()))
            : redirect()->route('login');
    }
}
