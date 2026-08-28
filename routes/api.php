<?php

use App\Http\Controllers\Internal\DeploymentBootstrapController;
use Illuminate\Support\Facades\Route;

Route::post('/internal/deployment-bootstrap', DeploymentBootstrapController::class)
    ->middleware('throttle:1,1')
    ->name('internal.deployment-bootstrap');
