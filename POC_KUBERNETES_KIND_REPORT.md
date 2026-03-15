# 📋 Synthèse PoC - Infrastructure Kind avec RabbitMQ + Keycloak + PostgreSQL

## ✅ Résumé du succès

Ce PoC valide avec succès le déploiement complet d'une architecture microservices sur **Kind (Kubernetes IN Docker)** avec un cycle pub/sub **RabbitMQ** fonctionnel dans un environnement containerisé.

### Configuration finale validée
- **Cluster Kind** : `kind-lpmde-sandbox` (v1.35.0, 1 nœud control-plane)
- **Namespace** : `lpmde-sandbox`
- **Services** :
  - PostgreSQL 15 (BD cluster pour Keycloak)
  - RabbitMQ 3-management (AMQP + interface web)
  - Keycloak 25.0 (OAuth 2.0 / OpenID Connect)
- **Application** : Symfony 6.4 + Messenger (conteneur Docker intégré)

## 🎯 Validations effectuées

### 1. Infrastructure Kubernetes
```bash
✅ Cluster créé et nœuds prêts
✅ Namespace isolé pour la PoC
✅ Services réseau internes configurés (ClusterIP)
✅ PersistentVolumeClaim pour PostgreSQL (local-path)
✅ Secrets Kubernetes pour authentification
```

### 2. Publication / Consommation RabbitMQ
```
✅ Message publié via Symfony Messenger Command
✅ Message routé vers la queue AMQP "messages"
✅ Consumer consomme et traite le message
✅ Message historisé dans les logs
✅ Queue vérifiée vide après consommation

Statistiques finales : async: 0, failed: 0
```

### 3. Tests de masse
```
✅ 10 messages publiés rapidement (boucle)
✅ Tous traités séquentiellement par le worker
✅ Aucune perte de messages
✅ Gestion des retries automatique (AMQP)
```

## 🔧 Étapes de reproduction

### Prérequis
- Docker Desktop (avec Kind)
- kubectl configuré
- Projet Symfony avec dépendances Composer installées

### 1. Initialiser le cluster Kind
```powershell
kind create cluster --name lpmde-sandbox --config k8s/kind/kind-cluster-config.yaml
kubectl config use-context kind-lpmde-sandbox
```

### 2. Déployer l'infrastructure
```powershell
kubectl apply -k k8s/kind
kubectl wait --for=condition=Available deployment/postgres -n lpmde-sandbox --timeout=180s
kubectl wait --for=condition=Available deployment/rabbitmq -n lpmde-sandbox --timeout=240s
kubectl wait --for=condition=Available deployment/keycloak -n lpmde-sandbox --timeout=360s
```

### 3. Exposer les services localement (2 terminaux)
```powershell
# Terminal 1 : RabbitMQ
kubectl port-forward -n lpmde-sandbox svc/rabbitmq 5672:5672 15672:15672

# Terminal 2 : Keycloak
kubectl port-forward -n lpmde-sandbox svc/keycloak 8080:8080
```

### 4. Configurer Symfony et tester
```powershell
# Créer .env.local (template disponible: .env.kind.example)
# Lancer le worker
docker exec -it lpmde-web-kind sh -c "cd /var/www/html && php bin/console messenger:consume async -vv"

# Dans un autre terminal, publier des messages
docker exec lpmde-web-kind sh -c "cd /var/www/html && php bin/console app:dispatch:ghost-alert"
```

## ⚠️ Difficultés typiques rencontrées

### 1. **Corruption de base Mnesia (RabbitMQ)**
**Symptôme** : Pod RabbitMQ en CrashLoopBackOff avec erreurs Mnesia.
**Cause** : Le restart du pod sans nettoyage fait que la BD interne Mnesia devient corrompue.
**Solution** : Supprimer complètement le pod `kubectl delete pod` pour forcer sa réinitialisation.
**Leçon** : En production, utiliser des **volumes persistants** ou un **StatefulSet** pour RabbitMQ.

### 2. **Timeout des probes Kubernetes trop strict**
**Symptôme** : Services démarrent mais redémarrent constamment (probes failureThreshold atteint).
**Cause** : timeoutSeconds: 1s pour des services lents à répondre (Keycloak, RabbitMQ).
**Solution** : Augmenter les délais (initialDelaySeconds: 30-60s, timeoutSeconds: 5-10s).
**Leçon** : Adapter les probes au comportement réel de l'application (profiler le startup).

### 3. **Encodage fichier .env (BOM UTF-8)**
**Symptôme** : Symfony refuse de charger `.env.local` ("FormatException: Loading files starting with BOM...").
**Cause** : Éditeur (VS Code) crée le fichier avec UTF-8 BOM par défaut.
**Solution** : Créer le fichier direct dans le conteneur Docker via `docker exec` et `echo`.
**Leçon** : Utiliser un .gitignore strict pour `.env.local` et fournir un `.env.example`.

## 📊 Limites de Kind vs production Kubernetes

### 1. **Single Node**
- Kind crée un cluster **single-node** (tout tourne sur le même nœud)
- **Impact** : Pas de test multi-node scheduling, pod affinity, ou failover
- **En prod** : Tester sur 3-5 nœuds minimum avec répartition

### 2. **Pas de réseau distribué**
- Les pods communiquent via **localhost bridgé** dans Docker
- **Impact** : Network policies, service mesh (Istio), et latence réseau ne sont pas testées
- **En prod** : Tester sur un vrai cluster (EKS, AKS, GKE) avec SDN (Calico, Flannel)

### 3. **Volumes locaux seulement**
- Kind utilise des **local-path-provisioner** (disques hôte)
- **Impact** : Pas de test BlockStorage (EBS, Azure Disk) ou shared storage (NFS, EFS)
- **En prod** : Utiliser des CSI drivers pour le cloud (aws-ebs-csi-driver, etc.)

### 4. **Pas de registre Docker intégré**
- Les images sont pulléees du **Docker Hub public**
- **Impact** : Pas de test de registre privé (ECR, ACR, Harbor) ou pull secrets
- **En prod** : Configurer un registre privé et des image pull policies

### 5. **Performance limitée**
- Kind tourne dans un conteneur Docker lui-même
- **Impact** : Les ressources (CPU, RAM) sont limitées par Docker Desktop
- **En prod** : Allouer des ressources réelles et tester les limites (HPA, VPA)

### 6. **TLS et Ingress basiques**
- Kind fournit un **ingress NGINX** simple, pas de gestion de certificats avancée
- **Impact** : Pas de test cert-manager, wildcard certs, ou mTLS
- **En prod** : Déployer cert-manager et configurer des policies de sécurité

## 📚 Points clés pour votre rapport

### Architecture testée
```
┌─────────────────────────────┐
│     Docker Desktop / Kind    │
│  ┌───────────────────────┐   │
│  │   Kubernetes Cluster  │   │
│  │ ┌──────────────────┐  │   │
│  │ │  lpmde-sandbox   │  │   │
│  │ │ ┌──────────────┐ │  │   │
│  │ │ │  PostgreSQL  │ │  │   │
│  │ │ │  RabbitMQ    │ │  │   │
│  │ │ │  Keycloak    │ │  │   │
│  │ │ └──────────────┘ │  │   │
│  │ └──────────────────┘  │   │
│  └───────────────────────┘   │
│ ┌───────────────────────────┐ │
│ │  Docker Container Web App │ │
│ │  (Symfony 6.4 + Messenger)│ │
│ └───────────────────────────┘ │
└─────────────────────────────┘
```

### Flux pub/sub validé
```
1. Docker Container (Symfony)
   └─> PublishMessage(GhostAlert)
   
2. RabbitMQ Cluster (Kind)
   └─> Queue "messages" (AMQP 0.9.1)
   
3. Docker Container (Symfony Worker)
   └─> ConsumeMessage()
   └─> GhostAlertHandler::__invoke()
   └─> ✅ Message treated successfully
```

## ✨ Prochaines étapes pour la production

1. **Persistance** : Ajouter StatefulSet + PVC pour RabbitMQ et PostgreSQL
2. **Scaling** : Tester horizontal pod autoscaling (HPA) + réplicas
3. **Sécurité** : NetworkPolicies, RBAC, Pod Security Policies, mTLS
4. **Monitoring** : Prometheus, Grafana, Jaeger pour la traçabilité distribuée
5. **MultiNode** : Tester sur un cluster réel (EKS, AKS, GKE) avec 3+ nœuds
6. **GitOps** : Intégrer FluxCD ou ArgoCD pour les déploiements
7. **Disaster Recovery** : Réplication multi-zone, backup PostgreSQL automatisé

## 📝 Date et validations

- **Date** : 15 mars 2026 - 21h05
- **Environnement** : Windows 11 + Docker Desktop + Kind 0.20+
- **Validations** :
  - ✅ Cluster créé et stable
  - ✅ 3 services running sans crashloop
  - ✅ Pub/sub RabbitMQ fonctionnel
  - ✅ 11 messages testés avec succès
  - ✅ Zéro perte de messages
