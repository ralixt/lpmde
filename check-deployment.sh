#!/bin/bash

# Script de vérification avant déploiement CI/CD
# Usage: ./check-deployment.sh

echo "🔍 Vérification des fichiers nécessaires pour le CI/CD..."
echo ""

MISSING_FILES=0

# Fichiers Docker requis
FILES=(
    "Dockerfile"
    ".dockerignore"
    "docker-compose.yml"
    "public/.htaccess"
    ".env.example"
    ".gitlab-ci.yml"
)

echo "📦 Fichiers Docker et CI/CD :"
for file in "${FILES[@]}"; do
    if [ -f "$file" ]; then
        echo "  ✅ $file"
    else
        echo "  ❌ $file MANQUANT"
        MISSING_FILES=$((MISSING_FILES + 1))
    fi
done

echo ""
echo "📝 Fichiers de configuration :"

# Vérifier composer.json
if [ -f "composer.json" ]; then
    echo "  ✅ composer.json"
else
    echo "  ❌ composer.json MANQUANT"
    MISSING_FILES=$((MISSING_FILES + 1))
fi

# Vérifier composer.lock
if [ -f "composer.lock" ]; then
    echo "  ✅ composer.lock"
else
    echo "  ⚠️  composer.lock MANQUANT (exécutez 'composer install')"
fi

# Vérifier phpunit.xml.dist
if [ -f "phpunit.xml.dist" ]; then
    echo "  ✅ phpunit.xml.dist"
else
    echo "  ❌ phpunit.xml.dist MANQUANT"
    MISSING_FILES=$((MISSING_FILES + 1))
fi

echo ""
echo "🧪 Répertoires de tests :"

if [ -d "tests/Unit" ]; then
    echo "  ✅ tests/Unit/"
else
    echo "  ❌ tests/Unit/ MANQUANT"
    MISSING_FILES=$((MISSING_FILES + 1))
fi

if [ -d "tests/E2E" ]; then
    echo "  ✅ tests/E2E/"
else
    echo "  ❌ tests/E2E/ MANQUANT"
    MISSING_FILES=$((MISSING_FILES + 1))
fi

echo ""
echo "📁 Structure Symfony :"

SYMFONY_DIRS=(
    "src"
    "config"
    "public"
    "templates"
    "bin"
)

for dir in "${SYMFONY_DIRS[@]}"; do
    if [ -d "$dir" ]; then
        echo "  ✅ $dir/"
    else
        echo "  ❌ $dir/ MANQUANT"
        MISSING_FILES=$((MISSING_FILES + 1))
    fi
done

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

if [ $MISSING_FILES -eq 0 ]; then
    echo "✅ Tous les fichiers requis sont présents !"
    echo ""
    echo "📋 Prochaines étapes :"
    echo "  1. git add ."
    echo "  2. git commit -m 'feat: add Docker and CI/CD configuration'"
    echo "  3. git push origin main"
    echo ""
    exit 0
else
    echo "❌ $MISSING_FILES fichier(s) manquant(s)"
    echo ""
    echo "⚠️  Créez les fichiers manquants avant de pousser votre code."
    echo ""
    exit 1
fi
