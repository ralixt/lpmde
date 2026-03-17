#!/usr/bin/env pwsh
<#
.SYNOPSIS
Automatise le lancement complet du PoC Kind pour LPMDE

.DESCRIPTION
Ce script :
1. Crée le cluster Kind (si nécessaire)
2. Déploie l'infrastructure (PostgreSQL, RabbitMQ, Keycloak)
3. Lance les port-forwards en arrière-plan
4. Redémarre le conteneur Docker web
5. Affiche le statut final

.EXAMPLE
.\start-kind-dev.ps1
#>

param(
    [switch]$SkipKindCluster = $false,
    [switch]$SkipInfrastructure = $false,
    [switch]$SkipDocker = $false
)

# Couleurs pour le output
$Colors = @{
    Success = 'Green'
    Warning = 'Yellow'
    Error = 'Red'
    Info = 'Cyan'
}

function Write-Status {
    param([string]$Message, [string]$Status = 'Info')
    $Color = $Colors[$Status] ?? 'White'
    Write-Host "[$(Get-Date -Format 'HH:mm:ss')] $Message" -ForegroundColor $Color
}

Write-Status "=== Démarrage du PoC Kind pour LPMDE ===" -Status Help

# ============================================================================
# 1. VÉRIFIER LES PRÉREQUIS
# ============================================================================
Write-Status "Vérification des prérequis..." -Status Info

$Prerequisites = @{
    'Docker Desktop' = 'docker'
    'kubectl' = 'kubectl'
    'kind' = 'kind'
}

foreach ($Name in $Prerequisites.Keys) {
    $Cmd = $Prerequisites[$Name]
    try {
        $_ = & $Cmd --version 2>$null
        Write-Status "✓ $Name found" -Status Success
    }
    catch {
        Write-Status "✗ $Name NOT found - Install required!" -Status Error
        exit 1
    }
}

# ============================================================================
# 2. CRÉER/VÉRIFIER LE CLUSTER KIND
# ============================================================================
if (-not $SkipKindCluster) {
    Write-Status "Cluster Kind..." -Status Info
    
    $ClusterExists = & kind get clusters 2>$null | Select-String 'lpmde-sandbox'
    
    if ($ClusterExists) {
        Write-Status "✓ Cluster 'lpmde-sandbox' exists" -Status Success
    }
    else {
        Write-Status "Creating cluster 'lpmde-sandbox'..." -Status Warning
        & kind create cluster --config k8s/kind/kind-cluster-config.yaml --name lpmde-sandbox 2>&1 | ForEach-Object {
            if ($_ -match 'error|failed') { Write-Status $_ -Status Error }
            else { Write-Status $_ -Status Info }
        }
    }
    
    # Vérifier que le cluster est ready
    $MaxAttempts = 30
    $Attempt = 0
    while ($Attempt -lt $MaxAttempts) {
        $NodeStatus = & kubectl get nodes --no-headers 2>$null | Select-String 'Ready'
        if ($NodeStatus) {
            Write-Status "✓ Cluster is Ready" -Status Success
            break
        }
        $Attempt++
        Start-Sleep -Seconds 2
    }
    
    if ($Attempt -eq $MaxAttempts) {
        Write-Status "✗ Cluster failed to become Ready" -Status Error
        exit 1
    }
}

# ============================================================================
# 3. DÉPLOYER L'INFRASTRUCTURE KUBERNETES
# ============================================================================
if (-not $SkipInfrastructure) {
    Write-Status "Kubernetes infrastructure..." -Status Info
    
    Write-Status "Applying manifests..." -Status Warning
    & kubectl apply -k k8s/kind 2>&1 | ForEach-Object {
        if ($_ -match 'created|unchanged') { Write-Status $_ -Status Success }
        else { Write-Status $_ -Status Info }
    }
    
    Write-Status "Waiting for PostgreSQL..." -Status Warning
    & kubectl wait --for=condition=Available deployment/postgres -n lpmde-sandbox --timeout=180s 2>$null
    Write-Status "✓ PostgreSQL ready" -Status Success
    
    Write-Status "Waiting for RabbitMQ..." -Status Warning
    & kubectl wait --for=condition=Available deployment/rabbitmq -n lpmde-sandbox --timeout=180s 2>$null
    Write-Status "✓ RabbitMQ ready" -Status Success
    
    Write-Status "Waiting for Keycloak..." -Status Warning
    & kubectl wait --for=condition=Available deployment/keycloak -n lpmde-sandbox --timeout=240s 2>$null
    Write-Status "✓ Keycloak ready" -Status Success
}

# ============================================================================
# 4. VÉRIFIER LE .env.local
# ============================================================================
Write-Status "Configuration Symfony..." -Status Info

if (-not (Test-Path '.env.local')) {
    Write-Status "Creating .env.local..." -Status Warning
    @"
APP_ENV=dev
APP_DEBUG=1
DATABASE_URL="sqlite:///%kernel.project_dir%/var/data_dev.db"
MESSENGER_TRANSPORT_DSN=amqp://guest:guest@host.docker.internal:5672/%2f/messages
KEYCLOAK_URL=http://localhost:8080
KEYCLOAK_INTERNAL_URL=http://host.docker.internal:8080
KEYCLOAK_REALM=symfony-app
KEYCLOAK_CLIENT_ID=symfony-app
KEYCLOAK_CLIENT_SECRET=7g4hUZzxEbpEegkTA4v1L8w3RICCbe1x
KEYCLOAK_REDIRECT_URI=http://localhost:8000/login/keycloak/callback
"@ | Set-Content '.env.local' -Encoding UTF8
    Write-Status "✓ .env.local created" -Status Success
} else {
    Write-Status "✓ .env.local exists" -Status Success
}

# ============================================================================
# 5. LANCER LES PORT-FORWARDS (ARRIÈRE-PLAN)
# ============================================================================
Write-Status "Kubernetes port-forwards..." -Status Info

# Tuer les anciens port-forwards pour cette session
Get-Process 'kubectl' -ErrorAction SilentlyContinue | 
    Where-Object { $_.CommandLine -match 'port-forward.*rabbitmq|keycloak' } | 
    Stop-Process -Force -ErrorAction SilentlyContinue

Start-Sleep -Seconds 1

# Lancer RabbitMQ port-forward
Write-Status "Starting RabbitMQ port-forward (5672, 15672)..." -Status Warning
Start-Process -NoNewWindow -FilePath 'kubectl' -ArgumentList `
    'port-forward', '-n', 'lpmde-sandbox', 'svc/rabbitmq', '5672:5672', '15672:15672'

# Lancer Keycloak port-forward
Write-Status "Starting Keycloak port-forward (8080)..." -Status Warning
Start-Process -NoNewWindow -FilePath 'kubectl' -ArgumentList `
    'port-forward', '-n', 'lpmde-sandbox', 'svc/keycloak', '8080:8080'

Start-Sleep -Seconds 2

# Vérifier que les ports sont en Listen
$Ports = @(5672, 15672, 8080)
$AllListening = $true
foreach ($Port in $Ports) {
    $Conn = Get-NetTCPConnection -LocalPort $Port -ErrorAction SilentlyContinue
    if ($Conn -and $Conn.State -eq 'Listen') {
        Write-Status "✓ Port $Port listening" -Status Success
    } else {
        Write-Status "⚠ Port $Port NOT listening yet (may still be starting)" -Status Warning
        $AllListening = $false
    }
}

# ============================================================================
# 6. LANCER LE CONTENEUR DOCKER
# ============================================================================
if (-not $SkipDocker) {
    Write-Status "Docker container..." -Status Info
    
    # Arrêter/nettoyer l'ancien conteneur
    Write-Status "Cleaning up old container..." -Status Warning
    & docker stop lpmde-web-kind 2>$null
    & docker rm lpmde-web-kind 2>$null
    Start-Sleep -Seconds 1
    
    # Vérifier que l'image existe
    $ImageExists = & docker images --format "{{.Repository}}:{{.Tag}}" 2>$null | Select-String 'lpmde:kind-poc'
    if (-not $ImageExists) {
        Write-Status "Docker image 'lpmde:kind-poc' NOT found!" -Status Error
        Write-Status "Building Docker image..." -Status Warning
        & docker build -t lpmde:kind-poc . 2>&1 | Select-Object -Last 5 | ForEach-Object { Write-Status $_ -Status Info }
    }
    
    # Lancer le conteneur
    Write-Status "Starting container 'lpmde-web-kind'..." -Status Warning
    $ContainerId = & docker run -d `
        --name lpmde-web-kind `
        -p 8000:8000 `
        -v "${PWD}:/var/www/html" `
        -w /var/www/html `
        lpmde:kind-poc `
        sh -c "php -S 0.0.0.0:8000 -t public"
    
    if ($LASTEXITCODE -eq 0) {
        Write-Status "✓ Container started: $($ContainerId.Substring(0, 12))" -Status Success
        Start-Sleep -Seconds 2
        
        # Vérifier Symfony
        Write-Status "Checking Symfony..." -Status Warning
        $About = & docker exec lpmde-web-kind sh -lc "cd /var/www/html && php bin/console about 2>&1" 2>$null
        if ($About -match 'Symfony') {
            Write-Status "✓ Symfony is operational" -Status Success
        } else {
            Write-Status "⚠ Symfony check failed" -Status Warning
        }
    } else {
        Write-Status "✗ Failed to start container" -Status Error
        exit 1
    }
}

# ============================================================================
# 7. AFFICHER LE RÉSUMÉ
# ============================================================================
Write-Status "=== Setup Complete ===" -Status Success
Write-Host ""
Write-Host "✅ Services are running:" -ForegroundColor Green
Write-Host "   🌐 Application    : http://localhost:8000" -ForegroundColor Cyan
Write-Host "   🔐 Keycloak       : http://localhost:8080 (admin/admin)" -ForegroundColor Cyan
Write-Host "   📊 RabbitMQ Mgmt  : http://localhost:15672 (guest/guest)" -ForegroundColor Cyan
Write-Host ""
Write-Host "📌 Important:" -ForegroundColor Yellow
Write-Host "   • Keep kubectl port-forward processes running" -ForegroundColor Yellow
Write-Host "   • Docker container continues running in background" -ForegroundColor Yellow
Write-Host ""
Write-Host "Useful commands:" -ForegroundColor Yellow
Write-Host "   kubectl get pods -n lpmde-sandbox" -ForegroundColor Gray
Write-Host "   docker logs -f lpmde-web-kind" -ForegroundColor Gray
Write-Host "   docker exec lpmde-web-kind sh -lc 'php bin/console messenger:stats'" -ForegroundColor Gray
Write-Host ""
