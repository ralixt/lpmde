# Guide de déploiement CI/CD

## 📋 Prérequis

Avant de pousser votre code, assurez-vous que tous les fichiers suivants sont présents :

### Fichiers Docker
- ✅ `Dockerfile` - Configuration de l'image Docker
- ✅ `.dockerignore` - Fichiers à exclure du build
- ✅ `docker-compose.yml` - Orchestration des conteneurs
- ✅ `public/.htaccess` - Configuration Apache
- ✅ `.env.example` - Exemple de configuration

### Fichiers de configuration CI/CD
- ✅ `.gitlab-ci.yml` - Pipeline GitLab
- ✅ `.github/workflows/ci-cd.yml` - Pipeline GitHub Actions

## 🚀 Configuration pour GitLab CI/CD

### Variables à configurer dans GitLab

**Settings → CI/CD → Variables** :

#### Variables SonarQube (optionnel)
```
SONAR_HOST_URL = https://votre-sonarqube.com
SONAR_TOKEN = votre_token_sonar
```

#### Variables de déploiement Staging (optionnel)
```
STAGING_SERVER = staging.example.com
STAGING_USER = deploy
STAGING_PATH = /var/www/lpmde
SSH_PRIVATE_KEY = -----BEGIN OPENSSH PRIVATE KEY-----...
```

#### Variables de déploiement Production (optionnel)
```
PRODUCTION_SERVER = production.example.com
PRODUCTION_USER = deploy
PRODUCTION_PATH = /var/www/lpmde
```

### Registry GitLab (automatique)
Le registry GitLab Container est configuré automatiquement :
- `CI_REGISTRY` = registry.gitlab.com
- `CI_REGISTRY_IMAGE` = registry.gitlab.com/votre-groupe/lpmde

## 🔧 Configuration pour GitHub Actions

### Secrets à configurer dans GitHub

**Settings → Secrets and variables → Actions** :

#### Secrets SonarCloud (optionnel)
```
SONAR_TOKEN = votre_token_sonarcloud
SONAR_HOST_URL = https://sonarcloud.io
```

#### Secrets de déploiement Staging (optionnel)
```
STAGING_SERVER = staging.example.com
STAGING_USER = deploy
STAGING_PATH = /var/www/lpmde
SSH_PRIVATE_KEY = -----BEGIN OPENSSH PRIVATE KEY-----...
```

#### Secrets de déploiement Production (optionnel)
```
PRODUCTION_SERVER = production.example.com
PRODUCTION_USER = deploy
PRODUCTION_PATH = /var/www/lpmde
```

### Package Registry GitHub (automatique)
L'image sera publiée sur GitHub Container Registry :
- `ghcr.io/votre-username/lpmde:latest`
- `ghcr.io/votre-username/lpmde:main-sha`

## 📝 Commandes pour déployer

### 1. Vérifier les fichiers
```bash
# Vérifier que tous les fichiers Docker existent
ls -la Dockerfile .dockerignore docker-compose.yml public/.htaccess
```

### 2. Ajouter et commiter
```bash
git add .
git commit -m "feat: add Docker and CI/CD configuration"
```

### 3. Pousser vers GitLab
```bash
git push origin main
```

### 4. Pousser vers GitHub (si configuré)
```bash
git remote add github https://github.com/votre-username/lpmde.git
git push github main
```

## 🔍 Pipeline GitLab CI/CD

### Stages
1. **install** - Installation des dépendances Composer
2. **test** - Exécution des tests et audits
   - `sast-npm-audit` - Audit de sécurité NPM (allow_failure)
   - `sonar-audit` - Analyse SonarQube (allow_failure, nécessite SONAR_HOST_URL)
   - `trivy-fs-audit` - Scan de vulnérabilités (allow_failure)
   - `unit-test` - Tests unitaires avec coverage
   - `e2e-test-job` - Tests E2E
3. **release** - Build de l'image Docker (main/master/develop uniquement)
4. **staging** - Déploiement staging (develop, manuel)
5. **production** - Déploiement production (main/master, manuel)

## 🔍 Pipeline GitHub Actions

### Jobs
1. **install** - Installation des dépendances
2. **Tests parallèles** :
   - `sast-npm-audit` - Audit NPM
   - `sonar-audit` - SonarCloud (si SONAR_TOKEN configuré)
   - `trivy-fs-audit` - Scan Trivy
   - `unit-test` - Tests unitaires
   - `e2e-test` - Tests E2E
3. **build-image** - Build Docker (main/master/develop)
4. **deploy-staging** - Déploiement staging (develop)
5. **deploy-production** - Déploiement production (main/master)

## 🐳 Test local avec Docker

### Build de l'image
```bash
docker build -t lpmde:local .
```

### Lancer avec docker-compose
```bash
# Copier l'exemple d'environnement
cp .env.example .env

# Éditer les variables si nécessaire
nano .env

# Lancer les conteneurs
docker-compose up -d

# Voir les logs
docker-compose logs -f web

# Arrêter
docker-compose down
```

### Accès à l'application
```
http://localhost:8080
```

## ⚠️ Notes importantes

1. **Pas de SONAR_HOST_URL ?** Le job sonar-audit sera automatiquement skippé
2. **Tests E2E** ne sont plus en `allow_failure: true`, ils bloqueront le pipeline en cas d'échec
3. **Coverage** est généré uniquement pour les tests unitaires (pas pour E2E)
4. **Build Docker** se fait uniquement sur les branches main/master/develop
5. **Déploiements** sont manuels et nécessitent la configuration des secrets SSH

## 🔒 Sécurité

- Ne jamais commiter `.env` (déjà dans .gitignore)
- Utiliser des secrets/variables CI pour les informations sensibles
- Les clés SSH privées doivent être ajoutées uniquement dans les variables CI/CD
- Le `APP_SECRET` doit être généré aléatoirement en production

## 📊 Rapports générés

- `var/log/junit.xml` - Résultats des tests (format JUnit)
- `var/log/cobertura.xml` - Rapport de couverture de code
- Artifacts conservés 1 heure dans GitLab
- Artifacts conservés selon configuration GitHub

## 🆘 Troubleshooting

### Le build Docker échoue avec "Dockerfile: no such file or directory"
```bash
# Vérifier que le Dockerfile existe
ls -la Dockerfile

# S'assurer qu'il est bien commité
git add Dockerfile
git commit -m "fix: add Dockerfile"
git push
```

### Les tests échouent avec "PHPUnit warning"
C'est normal si aucun driver de coverage n'est installé pour les tests E2E.
La configuration utilise maintenant `--no-coverage` pour E2E.

### SonarQube échoue avec "URI with undefined scheme"
Vérifiez que `SONAR_HOST_URL` est bien configuré dans les variables CI/CD.
Le pipeline skip automatiquement si non configuré.
