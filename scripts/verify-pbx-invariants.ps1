param(
    [switch]$RunTests
)

$ErrorActionPreference = 'Stop'
$root = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path

function Read-RepoFile([string]$Path) {
    $fullPath = Join-Path $root $Path
    if (-not (Test-Path -LiteralPath $fullPath)) {
        throw "Arquivo critico ausente: $Path"
    }

    return [IO.File]::ReadAllText($fullPath)
}

function Assert-Match([string]$Content, [string]$Pattern, [string]$Message) {
    if ($Content -notmatch $Pattern) {
        throw $Message
    }
}

function Assert-NotMatch([string]$Content, [string]$Pattern, [string]$Message) {
    if ($Content -match $Pattern) {
        throw $Message
    }
}

$dialplan = Read-RepoFile '.docker/asterisk/config/extensions.conf'
$pjsip = Read-RepoFile '.docker/asterisk/config/pjsip.conf'
$entrypoint = Read-RepoFile '.docker/asterisk/entrypoint.sh'
$gateway = Read-RepoFile 'app/Services/Dialer/AsteriskDialerGateway.php'
$bridge = Read-RepoFile 'services/asterisk-bridge/cmd/bridge/main.go'
$appDockerfile = Read-RepoFile '.docker/app/Dockerfile.production'
$appEntrypoint = Read-RepoFile '.docker/entrypoint.production.sh'
$nginxDockerfile = Read-RepoFile '.docker/nginx/Dockerfile.production'
$databaseConfig = Read-RepoFile 'config/database.php'
$localCompose = Read-RepoFile 'docker-compose.yml'
$productionCompose = Read-RepoFile 'docker-compose.production.yml'

Assert-Match $dialplan '\[thconect-outbound\]' 'Contexto protegido thconect-outbound ausente.'
Assert-Match $dialplan 'REGEX\("\^t\[0-9\]\+-e\[0-9\]\{4,5\}\$"' 'Validacao do endpoint tecnico do agente ausente.'
Assert-Match $dialplan 'Dial\(PJSIP/\$\{THCONECT_AGENT_ENDPOINT\},40,' 'O agente deve tocar por no maximo 40 segundos.'
Assert-Match $dialplan 'Set\(SPYGROUP=agent-\$\{ARG1\}\)' 'O grupo isolado de supervisao do agente esta ausente.'

$mixMonitorIndex = $dialplan.IndexOf('MixMonitor(', [StringComparison]::Ordinal)
$dialIndex = $dialplan.IndexOf('Dial(PJSIP/${THCONECT_AGENT_ENDPOINT},40,', [StringComparison]::Ordinal)
if ($mixMonitorIndex -lt 0 -or $dialIndex -lt 0 -or $mixMonitorIndex -gt $dialIndex) {
    throw 'MixMonitor deve ser iniciado antes do Dial do agente.'
}

Assert-Match $pjsip '#include pjsip\.d/thconect-routes\.conf' 'Include das rotas PJSIP ausente.'
Assert-Match $entrypoint 'protocol=ws' 'Transporte WebSocket do WebRTC ausente.'
Assert-Match $entrypoint '#include pjsip\.d/thconect-agents\.conf' 'Include dos agentes WebRTC ausente.'
Assert-Match $gateway 'formattedPhone\(\$request->phone\).*@thconect-route-' 'A discagem nao usa a rota e o numero normalizado no servidor.'
Assert-Match $gateway '\$request->companyId.*-e.*\$agentExtension' 'O endpoint WebRTC nao esta isolado por empresa.'
Assert-Match $bridge 'request\.AgentEndpoint != technicalEndpoint\(request\.CompanyID, request\.AgentExtension\)' 'O bridge nao valida empresa e ramal do endpoint.'
Assert-Match $bridge '"Timeout":\s*"45000"' 'O Originate AMI precisa de timeout finito.'
Assert-Match $appDockerfile 'chmod -R u\+rwX,g\+rwX storage bootstrap/cache' 'A imagem de producao precisa permitir que o Laravel grave em storage e bootstrap/cache.'
Assert-Match $appEntrypoint 'mkdir -p storage/app storage/framework/cache' 'O entrypoint de producao precisa preparar volumes Laravel vazios.'
Assert-Match $appEntrypoint 'if \[ "\$\{1:-\}" = "php-fpm" \]; then' 'O PHP-FPM precisa iniciar seu processo mestre com as permissoes apropriadas.'
Assert-Match $appEntrypoint 'RUN_MIGRATIONS_ON_BOOT' 'O bootstrap de banco de producao precisa ser executado antes do PHP-FPM.'
Assert-Match $productionCompose 'RUN_MIGRATIONS_ON_BOOT:\s*"true"' 'A aplicacao de producao precisa habilitar o bootstrap do banco.'
Assert-Match $nginxDockerfile 'COPY public /var/www/html/public' 'A imagem Nginx precisa incluir os arquivos publicos do Laravel.'
Assert-Match $nginxDockerfile 'COPY --from=assets /app/public/build /var/www/html/public/build' 'A imagem Nginx precisa incluir os assets compilados do Vite.'
Assert-Match $databaseConfig "env\('REDIS_CLIENT', 'predis'\)" 'A imagem de producao precisa usar o cliente Redis incluido pelo Composer.'

Assert-Match $localCompose 'discador_asterisk_recordings:/var/spool/asterisk/monitor/thconect' 'Volume local de gravacoes nao esta montado no Asterisk.'
Assert-Match $productionCompose 'pbx_recordings:/var/spool/asterisk/monitor/thconect' 'Volume de gravacoes de producao nao esta montado no Asterisk.'
Assert-Match $productionCompose 'BRIDGE_STATE_KEY:\s*"\$\{ASTERISK_BRIDGE_STATE_KEY\}"' 'A chave de estado do bridge deve ser obrigatoria em producao.'
Assert-NotMatch $localCompose '(?m)^\s*-\s*["'']?5060:5060/udp' 'Nao publique UDP 5060 no host do ambiente local.'
Assert-NotMatch $productionCompose '(?m)^\s*-\s*["'']?5060:5060/udp' 'Nao publique UDP 5060 no host de producao.'

if ($RunTests) {
    Push-Location $root
    try {
        & docker compose exec -T app php artisan test --filter=TelephonyProductionReadinessTest
        if ($LASTEXITCODE -ne 0) {
            throw 'Os testes criticos de telefonia falharam.'
        }

        if (Get-Command go -ErrorAction SilentlyContinue) {
            Push-Location (Join-Path $root 'services/asterisk-bridge')
            try {
                & go test ./...
                if ($LASTEXITCODE -ne 0) {
                    throw 'Os testes do bridge Asterisk falharam.'
                }
            } finally {
                Pop-Location
            }
        }
    } finally {
        Pop-Location
    }
}

Write-Output 'PBX invariants: OK'
