$ErrorActionPreference = 'Stop'
$ProjectRoot = Split-Path -Parent $PSScriptRoot

Push-Location $ProjectRoot
try {
    & docker compose exec -T app php artisan optimize:clear
    & docker compose exec -T app php artisan config:cache
    & docker compose exec -T app php artisan route:cache
    & docker compose exec -T app php artisan event:cache
    & docker compose exec -T app php artisan view:cache

    if ($LASTEXITCODE -ne 0) {
        throw 'A otimizacao local falhou.'
    }

    & docker compose restart app queue scheduler reverb
    Start-Sleep -Seconds 5
    & docker compose restart nginx
} finally {
    Pop-Location
}

Write-Output 'Caches locais atualizados e processos PHP reiniciados. Execute este script apos alterar PHP, rotas ou configuracoes.'
