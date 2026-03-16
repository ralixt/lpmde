# PROMPT CLAUDE CODE — Vérification et mise en place du pipeline CI/CD GitHub Actions

## CONFIGURATION GIT
Rappel : tous les commits doivent être à MON nom.
```bash
git config user.name "MON_PRENOM MON_NOM"
git config user.email "MON_EMAIL@example.com"
```

## CONTEXTE

Mon projet Symfony 6.4 "La Petite Maison de l'Épouvante" a besoin d'un pipeline CI/CD GitHub Actions fonctionnel. D'après un rapport d'analyse antérieur, un fichier workflow existait, mais je ne suis pas sûr qu'il soit en place et fonctionnel sur le repo GitHub.

Le pipeline doit correspondre à cette structure en 4 stages :
1. **install** — installation des dépendances (Composer, npm)
2. **test** — 5 jobs en parallèle : e2e-test-job, sast-npm-audit, sonar-audit, trivy-fs-audit, unit-test
3. **release** — build-image-job (Docker build + push vers GHCR)
4. **staging** — deploy-staging-job (déploiement)

## MISSION 1 : DIAGNOSTIC COMPLET

Vérifie tout et donne-moi un rapport clair :

```bash
# 1. Vérifier si le dossier .github/workflows existe
ls -la .github/workflows/ 2>/dev/null
find . -name "*.yml" -path "*github*" -o -name "*.yaml" -path "*github*" 2>/dev/null

# 2. Si un workflow existe, affiche son contenu
cat .github/workflows/*.yml 2>/dev/null
cat .github/workflows/*.yaml 2>/dev/null

# 3. Vérifier le remote GitHub
git remote -v

# 4. Vérifier la branche actuelle
git branch -a

# 5. Vérifier si des workflows ont déjà tourné (via les logs git)
git log --oneline --all | head -20

# 6. Vérifier les fichiers de config CI existants
ls -la .gitlab-ci.yml 2>/dev/null
ls -la Makefile 2>/dev/null
ls -la docker-compose*.yml 2>/dev/null
ls -la Dockerfile* 2>/dev/null
```

Donne-moi un rapport structuré :
- ✅ Ce qui existe
- ❌ Ce qui manque
- ⚠️ Ce qui existe mais ne fonctionne pas

## MISSION 2 : CRÉER / CORRIGER LE WORKFLOW

Si le workflow n'existe pas ou est incomplet, crée le fichier `.github/workflows/ci.yml` avec cette structure exacte :

```yaml
name: CI/CD Pipeline

on:
  push:
    branches: [main, develop]
  pull_request:
    branches: [main]

jobs:
  # ═══════════════════════════════════════
  # STAGE 1 : INSTALL
  # ═══════════════════════════════════════
  install:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          extensions: amqp, pdo_sqlite, intl
          coverage: xdebug

      - name: Cache Composer
        uses: actions/cache@v4
        with:
          path: vendor
          key: composer-${{ hashFiles('composer.lock') }}

      - name: Install Composer dependencies
        run: composer install --prefer-dist --no-progress --no-interaction

      - name: Cache npm
        uses: actions/cache@v4
        with:
          path: node_modules
          key: npm-${{ hashFiles('package-lock.json') }}

      - name: Install npm dependencies
        run: npm ci --if-present

      - name: Upload vendor artifact
        uses: actions/upload-artifact@v4
        with:
          name: vendor
          path: vendor/

      - name: Upload node_modules artifact
        uses: actions/upload-artifact@v4
        with:
          name: node_modules
          path: node_modules/

  # ═══════════════════════════════════════
  # STAGE 2 : TESTS (5 jobs en parallèle)
  # ═══════════════════════════════════════
  unit-test:
    needs: install
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          extensions: amqp, pdo_sqlite, intl
          coverage: xdebug
      - uses: actions/download-artifact@v4
        with:
          name: vendor
          path: vendor/
      - name: Run unit tests
        run: php bin/phpunit --testsuite=unit
        env:
          APP_ENV: test

  e2e-test-job:
    needs: install
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          extensions: amqp, pdo_sqlite, intl
      - uses: actions/download-artifact@v4
        with:
          name: vendor
          path: vendor/
      - name: Run functional tests
        run: php bin/phpunit --testsuite=functional
        env:
          APP_ENV: test

  sast-npm-audit:
    needs: install
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/download-artifact@v4
        with:
          name: node_modules
          path: node_modules/
      - name: NPM Audit
        run: npm audit --audit-level=high || true

  sonar-audit:
    needs: install
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
        with:
          fetch-depth: 0
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          extensions: amqp, pdo_sqlite, intl
          coverage: xdebug
      - uses: actions/download-artifact@v4
        with:
          name: vendor
          path: vendor/
      - name: Run tests with coverage
        run: php bin/phpunit --coverage-clover=coverage.xml || true
        env:
          APP_ENV: test
      - name: SonarCloud Scan
        uses: SonarSource/sonarcloud-github-action@v3
        env:
          SONAR_TOKEN: ${{ secrets.SONAR_TOKEN }}
          GITHUB_TOKEN: ${{ secrets.GITHUB_TOKEN }}

  trivy-fs-audit:
    needs: install
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: Run Trivy FS scan
        uses: aquasecurity/trivy-action@master
        with:
          scan-type: 'fs'
          scan-ref: '.'
          severity: 'HIGH,CRITICAL'
          exit-code: '1'

  # ═══════════════════════════════════════
  # STAGE 3 : RELEASE (build Docker + push GHCR)
  # ═══════════════════════════════════════
  build-image-job:
    needs: [unit-test, e2e-test-job, sast-npm-audit, trivy-fs-audit]
    runs-on: ubuntu-latest
    if: github.ref == 'refs/heads/main' || github.ref == 'refs/heads/develop'
    permissions:
      contents: read
      packages: write
    steps:
      - uses: actions/checkout@v4

      - name: Log in to GHCR
        uses: docker/login-action@v3
        with:
          registry: ghcr.io
          username: ${{ github.actor }}
          password: ${{ secrets.GITHUB_TOKEN }}

      - name: Build and push Docker image
        uses: docker/build-push-action@v6
        with:
          context: .
          push: true
          tags: |
            ghcr.io/${{ github.repository }}:${{ github.sha }}
            ghcr.io/${{ github.repository }}:latest

  # ═══════════════════════════════════════
  # STAGE 4 : STAGING (déploiement)
  # ═══════════════════════════════════════
  deploy-staging-job:
    needs: build-image-job
    runs-on: ubuntu-latest
    if: github.ref == 'refs/heads/main'
    steps:
      - uses: actions/checkout@v4
      - name: Deploy to staging
        run: |
          echo "🚀 Déploiement staging"
          echo "Image: ghcr.io/${{ github.repository }}:${{ github.sha }}"
          echo "Note: Le déploiement réel nécessite la configuration des secrets SSH"
          echo "Pour un déploiement K8s: kubectl set image deployment/lpmde-web lpmde-web=ghcr.io/${{ github.repository }}:${{ github.sha }}"
          # TODO: Ajouter les commandes de déploiement réelles
          # ssh user@server "docker pull ghcr.io/..." ou kubectl apply
```

**IMPORTANT** : Adapte ce template en fonction de ce qui existe déjà dans le projet. Si un workflow existe mais est incomplet, complète-le plutôt que de le remplacer. Vérifie notamment :
- La version PHP utilisée (8.2 ou 8.4 ?)
- Les extensions PHP nécessaires (vérifie composer.json)
- L'existence d'un fichier `phpunit.xml.dist` ou `phpunit.xml` avec les testsuites `unit` et `functional`
- L'existence d'un `sonar-project.properties` (nécessaire pour SonarCloud)
- L'existence d'un `Dockerfile` (nécessaire pour le stage release)

## MISSION 3 : VÉRIFIER LA CONFIG PHPUnit

Les testsuites `unit` et `functional` doivent exister dans `phpunit.xml.dist` :

```xml
<testsuites>
    <testsuite name="unit">
        <directory>tests/Unit</directory>
    </testsuite>
    <testsuite name="functional">
        <directory>tests/Functional</directory>
    </testsuite>
</testsuites>
```

Si ce n'est pas le cas, corrige le fichier.

## MISSION 4 : VÉRIFIER LES FICHIERS NÉCESSAIRES

Vérifie que ces fichiers existent et sont corrects :

```bash
# SonarCloud
cat sonar-project.properties 2>/dev/null
# Doit contenir : sonar.organization, sonar.projectKey, sonar.sources, sonar.tests

# Dockerfile
cat Dockerfile 2>/dev/null
# Doit exister pour le stage release

# docker-compose
cat docker-compose.yml 2>/dev/null
```

Si `sonar-project.properties` n'existe pas, crée-le avec les valeurs adaptées au projet.

## MISSION 5 : PUSH ET VÉRIFIER

```bash
git add .github/workflows/ci.yml
git commit -m "add: mise en place du pipeline CI/CD GitHub Actions"
git push origin main  # ou develop, selon ta branche
```

Après le push, dis-moi :
- L'URL du repo GitHub (pour que j'aille vérifier les Actions)
- Le résultat du `git push`

## RÈGLES
- Commits en français : `add:`, `fix:`, `config:`
- Vérifie `git config user.name` avant chaque commit
- Si SonarCloud n'est pas configuré (pas de SONAR_TOKEN dans les secrets GitHub), mets le job `sonar-audit` en `continue-on-error: true`
- Le stage `deploy-staging-job` peut rester en "echo" pour le moment — les consignes disent qu'on n'est pas pénalisé si le déploiement ne fonctionne pas dans le pipeline