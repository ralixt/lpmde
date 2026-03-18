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

## ☸️ Déploiement local — Cluster Kind (bac à sable)

### Architecture déployée

```
Windows Host
├── Docker Desktop
│   ├── lpmde-web-kind       → Symfony app (dev, accès direct :8000)
│   ├── lpmde-prometheus     → Prometheus :9090
│   ├── lpmde-grafana        → Grafana :3000
│   └── lpmde-rabbitmq-exporter :9419
│
└── Kind cluster (kind-lpmde-sandbox)
    └── namespace lpmde-sandbox
        ├── keycloak    → port-forward :8080
        ├── rabbitmq    → port-forward :5672 / :15672
        ├── postgres    (interne)
        └── lpmde-web   → port-forward :8000 (déployé par CI/CD)
```

### Prérequis
- Docker Desktop en cours d'exécution
- `kubectl` installé
- `kind` installé

### 1. Créer le cluster (première fois uniquement)

```bash
kind create cluster --name lpmde-sandbox --config k8s/kind/kind-cluster-config.yaml
```

### 2. Copier et configurer le secret K8s

```bash
cp k8s/kind/secret.example.yaml k8s/kind/secret.yaml
# Éditer secret.yaml avec les vraies valeurs si nécessaire
```

### 3. Déployer tous les services

```bash
kubectl apply -k k8s/kind

# Attendre que tout soit prêt
kubectl wait --for=condition=Available deployment/postgres -n lpmde-sandbox --timeout=180s
kubectl wait --for=condition=Available deployment/rabbitmq -n lpmde-sandbox --timeout=240s
kubectl wait --for=condition=Available deployment/keycloak -n lpmde-sandbox --timeout=360s
kubectl wait --for=condition=Available deployment/lpmde-web -n lpmde-sandbox --timeout=180s

# Vérifier (4 pods attendus)
kubectl get pods -n lpmde-sandbox
```

### 4. Exposer les services (après chaque redémarrage)

```bash
kubectl port-forward -n lpmde-sandbox svc/keycloak 8080:8080 &
kubectl port-forward -n lpmde-sandbox svc/rabbitmq 5672:5672 15672:15672 &
kubectl port-forward -n lpmde-sandbox svc/lpmde-web 8000:80 &
```

### 5. Initialiser la base de données (première fois uniquement)

```bash
kubectl exec -n lpmde-sandbox deploy/lpmde-web -- php bin/console doctrine:migrations:migrate --no-interaction
```

### URLs d'accès

| Service | URL | Credentials |
|---------|-----|-------------|
| Application (K8s) | http://localhost:8000 | testuser / password |
| Keycloak Admin | http://localhost:8080/admin | admin / admin |
| RabbitMQ Management | http://localhost:15672 | guest / guest |

### Monitoring (optionnel)

```bash
docker-compose -f docker-compose.monitoring.yml up -d
# Grafana : http://localhost:3000 (admin / admin)
# Prometheus : http://localhost:9090
```

---

## 🤖 GitHub Self-Hosted Runner

Le pipeline CI/CD utilise un **runner self-hosted** installé sur le PC local pour déployer réellement sur le cluster Kind. Sans lui, les jobs `deploy-staging` et `deploy-production` resteront en attente.

### Installation (une seule fois)

#### 1. Récupérer le token sur GitHub

Aller sur :
```
https://github.com/ralixt/lpmde/settings/actions/runners/new
```
Choisir **Windows** ou **Linux (WSL)** et noter le token affiché.

#### 2a. Installation Windows (PowerShell)

```powershell
mkdir C:\actions-runner && cd C:\actions-runner
Invoke-WebRequest -Uri https://github.com/actions/runner/releases/download/v2.321.0/actions-runner-win-x64-2.321.0.zip -OutFile actions-runner-win-x64.zip
Add-Type -AssemblyName System.IO.Compression.FileSystem
[System.IO.Compression.ZipFile]::ExtractToDirectory("$PWD\actions-runner-win-x64.zip", "$PWD")
.\config.cmd --url https://github.com/ralixt/lpmde --token TOKEN_GITHUB
```

#### 2b. Installation WSL/Linux (alternative)

```bash
mkdir ~/actions-runner && cd ~/actions-runner
curl -o actions-runner-linux-x64.tar.gz -L https://github.com/actions/runner/releases/latest/download/actions-runner-linux-x64-2.321.0.tar.gz
tar xzf actions-runner-linux-x64.tar.gz
./config.sh --url https://github.com/ralixt/lpmde --token TOKEN_GITHUB
```

Lors de la configuration, répondre :
- Runner group : `[Entrée]` (Default)
- Runner name : `lpmde-runner` (ou nom de la machine)
- Labels : `kind,local` (ou `[Entrée]`)
- Work folder : `[Entrée]` (default `_work`)

#### 3. Vérifier que le runner est connecté

Sur GitHub : **Settings → Actions → Runners** → le runner doit apparaître en **Idle** (vert).

### Démarrer le runner (avant chaque session / soutenance)

```powershell
# Windows
cd C:\actions-runner
.\run.cmd
```
```bash
# WSL
cd ~/actions-runner
./run.sh
```

Le terminal doit afficher `Listening for Jobs`. **Laisser ce terminal ouvert** pendant toute la durée du pipeline.

### Flux de déploiement automatique

Dès qu'un push arrive sur `main` :

```
push main
  → build-image-job  : npm build + tests + docker build + push ghcr.io/ralixt/lpmde:latest
  → deploy-production (self-hosted runner sur ton PC) :
      1. Vérification du cluster Kind (kubectl get nodes)
      2. Pull de l'image depuis GHCR
      3. Chargement dans Kind (kind load docker-image)
      4. Déploiement (kubectl apply -k k8s/kind/)
      5. Attente des 4 pods (postgres, rabbitmq, keycloak, lpmde-web)
      6. Smoke test : vérifie que ≥ 4 pods sont Running
```

Pour déclencher un déploiement staging (branche `rag` ou `develop`) :

```bash
git push origin main:rag
```

### Dépannage runner

```powershell
# Voir les jobs en cours
# → Sur GitHub : https://github.com/ralixt/lpmde/actions

# Si le runner ne répond plus, le relancer
.\run.cmd   # Windows
./run.sh    # WSL
```

---

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
