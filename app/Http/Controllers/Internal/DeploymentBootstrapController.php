<?php

namespace App\Http\Controllers\Internal;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Throwable;

class DeploymentBootstrapController
{
    public function __invoke(Request $request): JsonResponse
    {
        $expectedToken = (string) config('deployment.bootstrap_token');
        $providedToken = (string) $request->header('X-Deployment-Bootstrap-Token');

        abort_if(blank($expectedToken) || ! hash_equals($expectedToken, $providedToken), 404);

        try {
            $commands = [
                'migrate' => ['--force' => true],
                'db:seed' => ['--class' => 'PermissionSeeder', '--force' => true],
                'db:seed:super-admin' => ['--class' => 'SuperAdminSeeder', '--force' => true],
            ];

            foreach ($commands as $command => $arguments) {
                $exitCode = Artisan::call($command === 'db:seed:super-admin' ? 'db:seed' : $command, $arguments);

                if ($exitCode !== 0) {
                    return response()->json([
                        'message' => 'Bootstrap do banco falhou.',
                        'command' => $command,
                    ], 500);
                }
            }
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Bootstrap do banco falhou.',
                'exception' => class_basename($exception),
                'details' => config('deployment.bootstrap_diagnostics')
                    ? $exception->getMessage()
                    : null,
            ], 500);
        }

        return response()->json(['message' => 'Bootstrap do banco concluido.']);
    }
}
