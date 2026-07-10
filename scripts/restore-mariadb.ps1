param(
    [Parameter(Mandatory = $true)]
    [string] $BackupFile,
    [string] $Database,
    [switch] $Force
)

$ErrorActionPreference = 'Stop'

if (-not $Force) {
    throw 'A restauracao substitui dados do banco local. Execute novamente com -Force para confirmar.'
}

$ProjectRoot = Split-Path -Parent $PSScriptRoot
$BackupFile = [System.IO.Path]::GetFullPath($BackupFile)

if (-not (Test-Path -LiteralPath $BackupFile)) {
    throw "Arquivo de backup nao encontrado: $BackupFile"
}

Push-Location $ProjectRoot
try {
    Get-Content -LiteralPath $BackupFile -Raw |
        & docker compose exec -T -e "TARGET_DATABASE=$Database" mysql sh -lc 'exec mariadb -uroot -p"$MYSQL_ROOT_PASSWORD" "${TARGET_DATABASE:-$MYSQL_DATABASE}"'

    if ($LASTEXITCODE -ne 0) {
        throw 'A restauracao do MariaDB falhou.'
    }
} finally {
    Pop-Location
}

Write-Output 'Restauracao concluida.'
