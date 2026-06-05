<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Contracts\View\View;

class CompanyController extends Controller
{
    public function index(): View
    {
        return view('super-admin.companies.index', [
            'companies' => Company::query()->with('license')->latest()->paginate(12),
        ]);
    }
}
