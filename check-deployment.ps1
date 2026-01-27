# Script de vérification avant déploiement CI/CD
# Usage: .\check-deployment.ps1

Write-Host "🔍 Vérification des fichiers nécessaires pour le CI/CD..." -ForegroundColor Cyan
Write-Host ""

$MISSING_FILES = 0

# Fichiers Docker requis
$FILES = @(
    "Dockerfile",
    ".dockerignore",
    "docker-compose.yml",
    "public\.htaccess",
    ".env.example",
    ".gitlab-ci.yml"
)

Write-Host "📦 Fichiers Docker et CI/CD :" -ForegroundColor Yellow
foreach ($file in $FILES) {
    if (Test-Path $file) {
        Write-Host "  ✅ $file" -ForegroundColor Green
    } else {
        Write-Host "  ❌ $file MANQUANT" -ForegroundColor Red
        $MISSING_FILES++
    }
}

Write-Host ""
Write-Host "📝 Fichiers de configuration :" -ForegroundColor Yellow

# Vérifier composer.json
if (Test-Path "composer.json") {
    Write-Host "  ✅ composer.json" -ForegroundColor Green
} else {
    Write-Host "  ❌ composer.json MANQUANT" -ForegroundColor Red
    $MISSING_FILES++
}

# Vérifier composer.lock
if (Test-Path "composer.lock") {
    Write-Host "  ✅ composer.lock" -ForegroundColor Green
} else {
    Write-Host "  ⚠️  composer.lock MANQUANT (exécutez 'composer install')" -ForegroundColor Yellow
}

# Vérifier phpunit.xml.dist
if (Test-Path "phpunit.xml.dist") {
    Write-Host "  ✅ phpunit.xml.dist" -ForegroundColor Green
} else {
    Write-Host "  ❌ phpunit.xml.dist MANQUANT" -ForegroundColor Red
    $MISSING_FILES++
}

Write-Host ""
Write-Host "🧪 Répertoires de tests :" -ForegroundColor Yellow

if (Test-Path "tests\Unit") {
    Write-Host "  ✅ tests\Unit\" -ForegroundColor Green
} else {
    Write-Host "  ❌ tests\Unit\ MANQUANT" -ForegroundColor Red
    $MISSING_FILES++
}

if (Test-Path "tests\E2E") {
    Write-Host "  ✅ tests\E2E\" -ForegroundColor Green
} else {
    Write-Host "  ❌ tests\E2E\ MANQUANT" -ForegroundColor Red
    $MISSING_FILES++
}

Write-Host ""
Write-Host "📁 Structure Symfony :" -ForegroundColor Yellow

$SYMFONY_DIRS = @(
    "src",
    "config",
    "public",
    "templates",
    "bin"
)

foreach ($dir in $SYMFONY_DIRS) {
    if (Test-Path $dir) {
        Write-Host "  ✅ $dir\" -ForegroundColor Green
    } else {
        Write-Host "  ❌ $dir\ MANQUANT" -ForegroundColor Red
        $MISSING_FILES++
    }
}

Write-Host ""
Write-Host "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" -ForegroundColor Gray

if ($MISSING_FILES -eq 0) {
    Write-Host "✅ Tous les fichiers requis sont présents !" -ForegroundColor Green
    Write-Host ""
    Write-Host "📋 Prochaines étapes :" -ForegroundColor Cyan
    Write-Host "  1. git add ." -ForegroundColor White
    Write-Host "  2. git commit -m 'feat: add Docker and CI/CD configuration'" -ForegroundColor White
    Write-Host "  3. git push origin main" -ForegroundColor White
    Write-Host ""
    exit 0
} else {
    Write-Host "❌ $MISSING_FILES fichier(s) manquant(s)" -ForegroundColor Red
    Write-Host ""
    Write-Host "Creez les fichiers manquants avant de pousser votre code." -ForegroundColor Yellow
    Write-Host ""
    exit 1
}
