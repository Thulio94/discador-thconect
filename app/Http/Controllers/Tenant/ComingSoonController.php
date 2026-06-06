<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ComingSoonController extends Controller
{
    public function __invoke(Request $request): View
    {
        $module = $request->route('module', 'integrations');

        $modules = [
            'campaigns' => ['section' => 'Operacao', 'title' => 'Campanhas'],
            'agent' => ['section' => 'Operacao', 'title' => 'Agente'],
            'supervision' => ['section' => 'Operacao', 'title' => 'Supervisao'],
            'reports' => ['section' => 'Analise', 'title' => 'Relatorios'],
            'telephony' => ['section' => 'Telefonia', 'title' => 'Telefonia'],
            'settings' => ['section' => 'Configuracoes', 'title' => 'Configuracoes'],
            'integrations' => ['section' => 'Configuracoes', 'title' => 'Integracoes'],
        ];

        return view('tenant.coming-soon.module', [
            'module' => $modules[$module] ?? $modules['integrations'],
        ]);
    }
}
