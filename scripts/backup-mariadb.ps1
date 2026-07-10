param(
    [string] $Destination = (Join-Path $PSScriptRoot '..\storage\backups'),
    [string] $Database
)

$ErrorActionPreference = 'Stop'
$ProjectRoot = Split-Path -Parent $PSScriptRoot
$Destination = [System.IO.Path]::GetFullPath($Destination)
New-Item -ItemType Directory -Force -Path $Destination | Out-Null

$Timestamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$BackupFile = Join-Path $Destination "discador-thconect-$Timestamp.sql"

Push-Location $ProjectRoot
try {
    & docker compose exec -T -e "TARGET_DATABASE=$Database" mysql sh -lc 'exec mariadb-dump --single-transaction --routines --triggers -uroot -p"$MYSQL_ROOT_PASSWORD" "${TARGET_DATABASE:-$MYSQL_DATABASE}"' |
        Out-File -FilePath $BackupFile -Encoding utf8

    if ($LASTEXITCODE -ne 0) {
        throw 'O backup do MariaDB falhou.'
    }
} finally {
    Pop-Location
}

Write-Output "Backup criado em: $BackupFile"
