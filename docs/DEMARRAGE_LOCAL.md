# Démarrage local — LPMDE (environnement Kind)

Guide de démarrage quotidien après redémarrage du PC.
Pour une **première installation**, voir [KIND_SETUP_GUIDE.md](KIND_SETUP_GUIDE.md).
Pour le déploiement CI/CD et le self-hosted runner, voir [DEPLOYMENT.md](DEPLOYMENT.md).

---

## Architecture en jeu

```
Windows Host
├── Docker Desktop
│   ├── lpmde-web-kind       → Symfony app (dev local, :8000)
│   ├── lpmde-prometheus     → Prometheus :9090
│   ├── lpmde-grafana        → Grafana :3000
│   └── lpmde-rabbitmq-exporter :9419
│
├── GitHub Self-hosted Runner (actions-runner/)
│   └── Écoute les jobs GitHub Actions → déploie sur Kind
│
└── Kind cluster (kind-lpmde-sandbox)
    └── namespace lpmde-sandbox
        ├── keycloak    → port-forward :8080
        ├── rabbitmq    → port-forward :5672 / :15672
        ├── postgres    (interne)
        └── lpmde-web   → port-forward :8001 (déployé par CI/CD)
```

---

## Démarrage rapide (PC redémarré)

### Étape 1 — Vérifier Docker Desktop

```bash
docker info --format "Docker: {{.ServerVersion}}" 2>/dev/null
kubectl get nodes --context kind-lpmde-sandbox
```

Sortie attendue :
```
NAME                          STATUS   ROLES           AGE
lpmde-sandbox-control-plane   Ready    control-plane   Xd
```

> Si le cluster n'existe pas :
> ```bash
> kind create cluster --name lpmde-sandbox --config k8s/kind/kind-cluster-config.yaml
> kubectl apply -k k8s/kind
> ```

---

### Étape 2 — Vérifier les pods Kind

```bash
kubectl get pods -n lpmde-sandbox
```

Sortie attendue (4 pods `Running`) :
```
NAME                        READY   STATUS    RESTARTS
keycloak-xxxxx              1/1     Running   X
lpmde-web-xxxxx             1/1     Running   X
postgres-xxxxx              1/1     Running   X
rabbitmq-xxxxx              1/1     Running   X
```

> Si un pod est en `CrashLoopBackOff` :
> ```bash
> kubectl delete pod -n lpmde-sandbox <nom-du-pod>
> ```
> (il se recrée automatiquement)

---

### Étape 3 — Démarrer le conteneur Symfony (dev local)

Pour le développement local sans CI/CD :
```bash
docker start lpmde-web-kind
```

Vérification :
```bash
curl -s -o /dev/null -w "Symfony HTTP %{http_code}\n" http://localhost:8000/
# Attendu : Symfony HTTP 200
```

---

### Étape 4 — Lancer les port-forwards Kind

Les port-forwards ne survivent pas au redémarrage — à relancer à chaque fois.

```bash
# Keycloak (OAuth2/OIDC)
kubectl port-forward -n lpmde-sandbox svc/keycloak 8080:8080 > /tmp/keycloak-pf.log 2>&1 &

# RabbitMQ (AMQP + Management UI)
kubectl port-forward -n lpmde-sandbox svc/rabbitmq 5672:5672 15672:15672 > /tmp/rabbitmq-pf.log 2>&1 &

# App Symfony déployée par CI/CD (pod lpmde-web dans Kind)
kubectl port-forward -n lpmde-sandbox svc/lpmde-web 8001:80 > /tmp/lpmde-web-pf.log 2>&1 &
```

Vérification :
```bash
curl -s -o /dev/null -w "Keycloak HTTP %{http_code}\n" http://localhost:8080/realms/symfony-app/.well-known/openid-configuration
curl -s -o /dev/null -w "RabbitMQ HTTP %{http_code}\n" http://localhost:15672/
curl -s -o /dev/null -w "App K8s HTTP %{http_code}\n" http://localhost:8001/
```

---

### Étape 5 — Démarrer le self-hosted runner (si démo CI/CD)

Le runner doit tourner pour que les jobs `deploy-staging` et `deploy-production` s'exécutent.

```powershell
# Windows (PowerShell)
cd actions-runner
.\run.cmd
```
```bash
# WSL
cd ~/actions-runner
./run.sh
```

Le terminal doit afficher **`Listening for Jobs`**. Laisser ce terminal ouvert.

> Voir [DEPLOYMENT.md — GitHub Self-Hosted Runner](DEPLOYMENT.md) pour l'installation initiale.

---

### Étape 6 — Démarrer la stack monitoring (optionnel)

```bash
docker-compose -f docker-compose.monitoring.yml up -d
```

---

## Interfaces disponibles

| Service | URL | Credentials |
|---------|-----|-------------|
| App Symfony (dev local) | http://localhost:8000 | testuser / password (Keycloak) |
| App Symfony (pod K8s) | http://localhost:8001 | testuser / password (Keycloak) |
| Keycloak Admin | http://localhost:8080/admin | admin / admin |
| RabbitMQ Management | http://localhost:15672 | guest / guest |
| Prometheus | http://localhost:9090 | — |
| Grafana | http://localhost:3000 | admin / admin |

---

## Tester que tout fonctionne

### Authentification Keycloak
```
http://localhost:8000/login/keycloak
→ Connectez-vous avec testuser / password
```

### RabbitMQ — publier un message de test
```bash
docker exec lpmde-web-kind sh -c "php /var/www/html/bin/console app:dispatch:ghost-alert"
# Vérifier dans http://localhost:15672 → Queues → messages ready
```

### Consommer les messages
```bash
docker exec lpmde-web-kind sh -c "php /var/www/html/bin/console messenger:consume async --limit=1 -vv"
```

### Déclencher un déploiement CI/CD (demo soutenance)
```bash
# Assurer que le runner tourne (étape 5), puis :
git commit --allow-empty -m "demo: déclencher le pipeline"
git push origin main
# → GitHub Actions → build → deploy-production sur le runner local
```

---

## Arrêt propre

```bash
# Arrêter les port-forwards
kill $(pgrep -f "kubectl port-forward") 2>/dev/null

# Arrêter les conteneurs Docker dev
docker stop lpmde-web-kind

# Arrêter la stack monitoring
docker-compose -f docker-compose.monitoring.yml down

# Arrêter le runner : Ctrl+C dans le terminal du runner
```

> Le cluster Kind reste actif (données persistées) et redémarre automatiquement avec Docker Desktop.

---

## Infos importantes

- **Keycloak realm** : `symfony-app` | Client : `symfony-app` | Secret : `7g4hUZzxEbpEegkTA4v1L8w3RICCbe1x`
- **Pod lpmde-web** : déployé par CI/CD, utilise SQLite (`var/data.db`) — migrations à lancer manuellement la première fois :
  ```bash
  kubectl exec -n lpmde-sandbox deploy/lpmde-web -- php bin/console doctrine:migrations:migrate --no-interaction
  ```
- **`host.docker.internal`** : utilisé par le conteneur dev pour accéder aux port-forwards
- **Données SQLite** : `var/data_dev.db` dans le volume Docker dev, `var/data.db` dans le pod K8s

---

*Dernière mise à jour : 18 mars 2026*
