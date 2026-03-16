# Guide d'installation du PoC Kind pour LPMDE

Guide complet pour lancer le projet LPMDE avec Kubernetes Kind en local.

## Table des matières

1. [Prérequis](#prérequis)
2. [1. Initialisation du cluster Kind](#1-initialisation-du-cluster-kind)
3. [2. Déploiement de l'infrastructure](#2-déploiement-de-linfrastructure)
4. [3. Configuration de l'application](#3-configuration-de-lapplication)
5. [4. Build de l'image Docker](#4-build-de-limage-docker)
6. [5. Lancement du conteneur web](#5-lancement-du-conteneur-web)
7. [6. Port-forwards](#6-port-forwards)
8. [7. Validation et tests](#7-validation-et-tests)
9. [Troubleshooting](#troubleshooting)

---

## Prérequis

- **Docker Desktop** avec Kind support activé
- **kubectl** configuré (généralement inclus avec Docker Desktop)
- **PowerShell 5.1+** (ou cmd/bash - les exemples sont en PowerShell)
- **PHP 8.x** (optionnel, pour développement local sans Docker)
- Répertoire courant: `c:\Users\ragee\Documents\cesi MASTER\2ème année\BLOC3 - Superviser et assurer le développement des applications logicielles\RB3 (individuel)\lpmde-solo\lpmde`

---

## 1. Initialisation du cluster Kind

### Étape 1.1 : Créer et démarrer le cluster

```powershell
# Créer le cluster Kind basé sur la configuration
kind create cluster --config k8s/kind/kind-cluster-config.yaml --name lpmde-sandbox
```

**Sortie attendue :**
```
Creating cluster "lpmde-sandbox" ...
 ✓ Ensuring node image (kindest/node:v1.35.0) 🖼 
 ✓ Preparing nodes 📦
 ✓ Writing configuration 📝
 ✓ Starting control-plane 🕹️
 ✓ Installing CNI 🔌
 ✓ Installing StorageClass 💾
Set kubectl context to "kind-lpmde-sandbox"
Thanks for using kind! 👋
```

### Étape 1.2 : Vérifier que le cluster est Ready

```powershell
kubectl get nodes -n lpmde-sandbox
```

**Sortie attendue :**
```
NAME                      STATUS   ROLES           AGE     VERSION
lpmde-sandbox-control-plane   Ready    control-plane   2m      v1.35.0
```

### Étape 1.3 : Vérifier le contexte Kubernetes

```powershell
kubectl config current-context
```

**Sortie attendue :**
```
kind-lpmde-sandbox
```

---

## 2. Déploiement de l'infrastructure

### Étape 2.1 : Appliquer tous les manifests Kubernetes

```powershell
kubectl apply -k k8s/kind
```

**Sortie attendue :**
```
namespace/lpmde-sandbox created
secret/lpmde-sandbox-secrets created
configmap/rabbitmq-config created
persistentvolumeclaim/postgres-pvc created
deployment.apps/postgres created
service/postgres created
statefulset.apps/rabbitmq created
service/rabbitmq created
deployment.apps/keycloak created
service/keycloak created
```

### Étape 2.2 : Attendre que tous les pods soient Ready

```powershell
# Attendre PostgreSQL
kubectl wait --for=condition=Available deployment/postgres -n lpmde-sandbox --timeout=180s

# Attendre RabbitMQ
kubectl wait --for=condition=Available deployment/rabbitmq -n lpmde-sandbox --timeout=180s

# Attendre Keycloak
kubectl wait --for=condition=Available deployment/keycloak -n lpmde-sandbox --timeout=240s

# Vérifier le statut final
kubectl get pods -n lpmde-sandbox
```

**Sortie attendue :**
```
NAME                        READY   STATUS    RESTARTS   AGE
postgres-5fb5f4c97f-xxxx    1/1     Running   0          2m
rabbitmq-0                  1/1     Running   0          1m30s
keycloak-5f9c5d4779-xxxx    1/1     Running   0          1m
```

### Étape 2.3 : Vérifier les services

```powershell
kubectl get svc -n lpmde-sandbox
```

**Sortie attendue :**
```
NAME           TYPE        CLUSTER-IP      EXTERNAL-IP   PORT(S)
postgres       ClusterIP   10.96.xxx.xxx   <none>        5432/TCP
rabbitmq       ClusterIP   10.96.xxx.xxx   <none>        5672/TCP,15672/TCP
keycloak       ClusterIP   10.96.xxx.xxx   <none>        8080/TCP
```

---

## 3. Configuration de l'application

### Étape 3.1 : Créer `.env.local` avec la configuration PoC

```powershell
# Créer ou remplacer le fichier de configuration
@"
APP_ENV=dev
APP_DEBUG=1

# SQLite pour le PoC local - pas de connexion externes
DATABASE_URL="sqlite:///%kernel.project_dir%/var/data_dev.db"

# RabbitMQ exposé localement via port-forward
# kubectl port-forward -n lpmde-sandbox svc/rabbitmq 5672:5672
MESSENGER_TRANSPORT_DSN=amqp://guest:guest@127.0.0.1:5672/%2f/messages

# Keycloak exposé localement via port-forward
# kubectl port-forward -n lpmde-sandbox svc/keycloak 8080:8080
KEYCLOAK_URL=http://localhost:8080
KEYCLOAK_REALM=symfony-app
KEYCLOAK_CLIENT_ID=symfony-app
KEYCLOAK_CLIENT_SECRET=7g4hUZzxEbpEegkTA4v1L8w3RICCbe1x
KEYCLOAK_REDIRECT_URI=http://localhost:8000/login/keycloak/callback
"@ | Set-Content .env.local -Encoding UTF8
```

### Étape 3.2 : Vérifier le contenu de `.env.local`

```powershell
Get-Content .env.local
```

**Points importants :**
- `MESSENGER_TRANSPORT_DSN`: Pointe vers `127.0.0.1:5672` (accessible via port-forward RabbitMQ)
- `KEYCLOAK_URL`: Pointe vers `localhost:8080` (accessible via port-forward Keycloak)
- `KEYCLOAK_REDIRECT_URI`: Doit correspondre à la configuration dans Keycloak

---

## 4. Build de l'image Docker

### Étape 4.1 : Construire l'image Docker

```powershell
docker build -t lpmde:kind-poc .
```

**Sortie attendue :**
```
[+] Building 180.2s (20/20) FINISHED
 => [internal] load .dockerignore
 => [internal] load build context
 ...
 => => naming to docker.io/library/lpmde:kind-poc
```

### Étape 4.2 : Vérifier l'image

```powershell
docker images --format "{{.Repository}} {{.Tag}} {{.Size}}" | findstr "lpmde kind-poc"
```

**Sortie attendue :**
```
lpmde kind-poc 1.2GB
```

---

## 5. Lancement du conteneur web

### Étape 5.1 : Démarrer le conteneur Docker

```powershell
docker run -d `
  --name lpmde-web-kind `
  -p 8000:8000 `
  -v "${PWD}:/var/www/html" `
  -w /var/www/html `
  lpmde:kind-poc `
  sh -c "php -S 0.0.0.0:8000 -t public"
```

**Sortie attendue :**
```
3c6d98232886 (container ID)
```

### Étape 5.2 : Vérifier que le conteneur est actif

```powershell
docker ps --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}" | findstr "lpmde-web-kind"
```

**Sortie attendue :**
```
lpmde-web-kind   Up 10 seconds   0.0.0.0:8000->8000/tcp
```

### Étape 5.3 : Vérifier que Symfony fonctionne

```powershell
docker exec lpmde-web-kind sh -lc "cd /var/www/html && php bin/console about"
```

**Sortie attendue :**
```
Symfony 6.4.3
```

---

## 6. Port-forwards

### Étape 6.1 : Lancer les port-forwards en arrière-plan

**RabbitMQ AMQP + Management UI :**
```powershell
kubectl port-forward -n lpmde-sandbox svc/rabbitmq 5672:5672 15672:15672 2>&1 &
```

**Keycloak :**
```powershell
kubectl port-forward -n lpmde-sandbox svc/keycloak 8080:8080 2>&1 &
```

### Étape 6.2 : Vérifier les port-forwards

```powershell
Get-NetTCPConnection -LocalPort 5672, 15672, 8080 -ErrorAction SilentlyContinue | 
  Select-Object LocalPort, State
```

**Sortie attendue :**
```
LocalPort State
--------- -----
5672      Listen
15672     Listen
8080      Listen
```

---

## 7. Validation et tests

### Étape 7.1 : Test RabbitMQ (pub/sub)

**Publier un message GhostAlert :**
```powershell
docker exec lpmde-web-kind sh -lc "cd /var/www/html && php bin/console app:dispatch:ghost-alert --location='Cimetière' --monster='Spectre'"
```

**Vérifier les messages en queue :**
```powershell
docker exec lpmde-web-kind sh -lc "cd /var/www/html && php bin/console messenger:stats"
```

**Consommer les messages :**
```powershell
docker exec lpmde-web-kind sh -lc "cd /var/www/html && php bin/console messenger:consume async -vv --limit=1"
```

**Sortie attendue :**
```
[OK] Consuming messages from transport "async".

 [x] GhostAlert [#1]
     👻 ALERTE TRAITÉE

 [OK] Consumed all available messages.
```

### Étape 7.2 : Test Keycloak (accès web)

**Accéder à l'interface Keycloak :**
```
http://localhost:8080
```

**Credentials par défaut :**
- Username: `admin`
- Password: `admin`

**Services à vérifier dans Keycloak :**
1. Realm: `symfony-app` (doit exister)
2. Client: `symfony-app` (doit exister)
3. Redirect URI: `http://localhost:8000/login/keycloak/callback` (doit être configuré dans le client)

### Étape 7.3 : Test OAuth2 (flux complet)

**Accéder à la page de login :**
```
http://localhost:8000/login/keycloak
```

**Actions attendues :**
1. Redirection vers Keycloak login page
2. Saisir les identifiants d'un utilisateur Keycloak
3. Redirection vers `http://localhost:8000/login/keycloak/callback`
4. Créer/synchroniser l'utilisateur dans Symfony
5. Accès à l'application

### Étape 7.4 : Vérifier les logs RabbitMQ

```powershell
kubectl logs -n lpmde-sandbox deploy/rabbitmq --tail=50
```

### Étape 7.5 : Vérifier les logs Keycloak

```powershell
kubectl logs -n lpmde-sandbox deploy/keycloak --tail=50
```

### Étape 7.6 : Vérifier les logs PostgreSQL

```powershell
kubectl logs -n lpmde-sandbox deploy/postgres --tail=50
```

---

## Troubleshooting

### Problème : Port 5672/8080 déjà en utilisation

**Solution :**
```powershell
# Trouver le processus
Get-NetTCPConnection -LocalPort 8080 -ErrorAction SilentlyContinue | 
  Select-Object LocalPort, OwningProcess

# Terminer le processus
Stop-Process -Id <PID> -Force

# Relancer le port-forward
kubectl port-forward -n lpmde-sandbox svc/keycloak 8080:8080
```

### Problème : RabbitMQ en CrashLoopBackOff

**Solution :**
```powershell
# Supprimer les anciens pods et redéployer
kubectl delete pod --all -n lpmde-sandbox --force --grace-period=0

# Redéployer
kubectl apply -k k8s/kind

# Attendre
kubectl wait --for=condition=Available deployment/rabbitmq -n lpmde-sandbox --timeout=240s
```

### Problème : Keycloak prend du temps à démarrer

**Raison :** Keycloak peut prendre 1-2 minutes au premier démarrage.

**Solution :**
```powershell
kubectl logs -n lpmde-sandbox deploy/keycloak -f
# Attendre "Keycloak ... started in..."
```

### Problème : "Failed to connect" à RabbitMQ

**Vérifier :**
1. Port-forward RabbitMQ actif: `kubectl port-forward -n lpmde-sandbox svc/rabbitmq 5672:5672`
2. Credentials corrects dans `.env.local`: `guest:guest`
3. Adresse correcte: `127.0.0.1` (pas `localhost`)

### Problème : "Client not found" ou redirect URI invalide

**Raison :** Configuration Keycloak client incorrecte.

**Solution :**
```powershell
# Accéder à Keycloak
http://localhost:8080

# Aller à Administration > Realms > symfony-app > Clients > symfony-app

# Vérifier :
# - Enabled: ON
# - Standard flow enabled: ON
# - Redirect URIs: http://localhost:8000/login/keycloak/callback
# - Valid Redirect URIs: http://localhost:8000/login/keycloak/callback
```

### Problème : Container Docker ne peut pas joindre Keycloak

**Raison :** Network namespace isolation

**Solution :** (Déjà configuré dans `.env.local`)
- Container ne peut **pas** utiliser `127.0.0.1:8080` (localhost du container)
- Must use `host.docker.internal:8080` **OU** `localhost:8080` via port-forward (en passant par l'hôte)

Configuration actuelle : `KEYCLOAK_URL=http://localhost:8080` avec port-forward actif.

---

## Commandes utiles

### Arrêter tout

```powershell
# Arrêter le container Docker
docker stop lpmde-web-kind

# Supprimer le cluster Kind
kind delete cluster --name lpmde-sandbox

# Terminer les port-forwards
Get-Process kubectl | Stop-Process -Force
```

### Redémarrer tout

```powershell
# Redémarrer container (applique nouvelles config)
docker restart lpmde-web-kind

# Redémarrer cluster
kind delete cluster --name lpmde-sandbox
kind create cluster --config k8s/kind/kind-cluster-config.yaml --name lpmde-sandbox
```

### Monitorer en temps réel

```powershell
# Pods status
kubectl get pods -n lpmde-sandbox -w

# Logs Keycloak
kubectl logs -n lpmde-sandbox deploy/keycloak -f

# Logs RabbitMQ
kubectl logs -n lpmde-sandbox deploy/rabbitmq -f

# Container logs
docker logs lpmde-web-kind -f
```

---

## Récapitulatif de l'architecture

```
┌─────────────────────────────────────────────────────────────┐
│                    Windows Host                              │
│  (localhost:8000, localhost:5672, localhost:15672, localhost:8080)
└─────────────────────────────────────────────────────────────┘
         │ docker run    │ kubectl port-forward
         ▼               ▼
    ┌────────────┐   ┌──────────────────────────────────┐
    │  Docker    │   │    Kind Cluster                  │
    │  Container │   │  (kubernetes-in-docker)          │
    │            │   │                                  │
    │ lpmde-web  │   │  ┌─────────────────────────────┐ │
    │ :kind      │   │  │  lpmde-sandbox namespace    │ │
    │            │   │  │                             │ │
    │ :8000 ──┐  │   │  │  ┌────────────────────────┐ │ │
    │         │  │   │  │  │ PostgreSQL             │ │ │
    │ Symfony │  │   │  │  │ (5432)                 │ │ │
    │ 6.4     │  │   │  │  └────────────────────────┘ │ │
    │         │  │   │  │  ┌────────────────────────┐ │ │
    │         │  │   │  │  │ RabbitMQ               │ │ │
    │         │  │   │  │  │ (5672, 15672)          │ │ │
    │         │  │   │  │  └────────────────────────┘ │ │
    │         │  │   │  │  ┌────────────────────────┐ │ │
    │         │  │   │  │  │ Keycloak               │ │ │
    │         │  │   │  │  │ (8080)                 │ │ │
    │         │  │   │  │  └────────────────────────┘ │ │
    │         │  │   │  └─────────────────────────────┘ │
    │         │  │   └──────────────────────────────────┘
    └────────────┘
```

---

## Bon à savoir

1. **Les données sont effacées au redémarrage** : Ce PoC utilise SQLite (en-mémoire efficacement)
2. **RabbitMQ reset au redémarrage** : Les messages en queue sont perdus
3. **Keycloak reset au redémarrage** : Les utilisateurs et clients Keycloak doivent être recréés
4. **Volume Docker** : Monté pour développement (live reload du code)
5. **Port-forwards** : Nécessaire pour accéder aux services Kind depuis l'hôte Windows

---

**Dernière mise à jour :** 15 mars 2026
