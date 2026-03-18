# Démarrage local — LPMDE (environnement Kind)

Guide de démarrage quotidien après redémarrage du PC.
Pour une **première installation**, voir [KIND_SETUP_GUIDE.md](KIND_SETUP_GUIDE.md).

---

## Architecture en jeu

```
Windows Host
├── Docker Desktop
│   ├── lpmde-web-kind       → Symfony app   :8000
│   ├── lpmde-prometheus     → Prometheus     :9090
│   ├── lpmde-grafana        → Grafana        :3000
│   └── lpmde-rabbitmq-exporter              :9419
│
└── Kind cluster (kind-lpmde-sandbox)
    └── namespace lpmde-sandbox
        ├── keycloak   → port-forward :8080
        ├── rabbitmq   → port-forward :5672 / :15672
        └── postgres   (interne uniquement)
```

---

## Démarrage rapide (PC redémarré)

### Étape 1 — Vérifier Docker Desktop

S'assurer que Docker Desktop est démarré et que le cluster Kind est toujours là :

```bash
docker info --format "Docker: {{.ServerVersion}}" 2>/dev/null
kubectl get nodes --context kind-lpmde-sandbox
```

Sortie attendue :
```
NAME                          STATUS   ROLES           AGE
lpmde-sandbox-control-plane   Ready    control-plane   Xd
```

> Si le cluster n'existe pas : `kind create cluster --name lpmde-sandbox --config k8s/kind/kind-cluster-config.yaml && kubectl apply -k k8s/kind`

---

### Étape 2 — Vérifier les pods Kind

```bash
kubectl get pods -n lpmde-sandbox
```

Sortie attendue (tous `Running`) :
```
NAME                        READY   STATUS    RESTARTS
keycloak-xxxxx              1/1     Running   X
postgres-xxxxx              1/1     Running   X
rabbitmq-xxxxx              1/1     Running   X
```

> Si un pod est en `CrashLoopBackOff` : `kubectl delete pod -n lpmde-sandbox <nom-du-pod>` (se recrée automatiquement)

---

### Étape 3 — Démarrer le conteneur Symfony

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

Les port-forwards ne survivent pas au redémarrage du PC — à relancer à chaque fois.

```bash
# Keycloak (OAuth2/OIDC)
kubectl port-forward -n lpmde-sandbox svc/keycloak 8080:8080 > /tmp/keycloak-pf.log 2>&1 &

# RabbitMQ (AMQP + Management UI)
kubectl port-forward -n lpmde-sandbox svc/rabbitmq 5672:5672 15672:15672 > /tmp/rabbitmq-pf.log 2>&1 &
```

Vérification :
```bash
curl -s -o /dev/null -w "Keycloak HTTP %{http_code}\n" http://localhost:8080/realms/symfony-app/.well-known/openid-configuration
curl -s -o /dev/null -w "RabbitMQ HTTP %{http_code}\n" http://localhost:15672/
# Attendu : HTTP 200 sur les deux
```

---

### Étape 5 — Démarrer la stack monitoring

```bash
docker-compose -f docker-compose.monitoring.yml up -d
```

Vérification :
```bash
docker ps --filter "name=lpmde-" --format "{{.Names}}\t{{.Status}}"
```

Sortie attendue :
```
lpmde-grafana               Up X minutes
lpmde-prometheus            Up X minutes
lpmde-rabbitmq-exporter     Up X minutes (healthy)
lpmde-web-kind              Up X minutes
```

---

## Interfaces disponibles

| Service | URL | Credentials |
|---------|-----|-------------|
| Application Symfony | http://localhost:8000 | testuser / password (Keycloak) |
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

---

## Arrêt propre

```bash
# Arrêter les port-forwards
kill $(pgrep -f "kubectl port-forward") 2>/dev/null

# Arrêter le conteneur Symfony
docker stop lpmde-web-kind

# Arrêter la stack monitoring
docker-compose -f docker-compose.monitoring.yml down
```

> Le cluster Kind reste actif (données persistées). Il redémarrera automatiquement avec Docker Desktop au prochain boot.

---

## Infos importantes

- **Keycloak realm** : `symfony-app` | Client : `symfony-app` | Secret : `7g4hUZzxEbpEegkTA4v1L8w3RICCbe1x`
- **`.env.local`** : doit être créé dans le conteneur (sans BOM UTF-8) — voir [KEYCLOAK_EXPERIMENTATION.md §3.2](KEYCLOAK_EXPERIMENTATION.md)
- **`host.docker.internal`** : utilisé par le conteneur Symfony pour accéder aux port-forwards (pas `localhost`)
- **`php -S`** (serveur built-in) : single-threaded, dev mode — lent par conception. Saturation à ~25 utilisateurs simultanés (voir [SIEGE_RESULTS.md](SIEGE_RESULTS.md))
- **Données SQLite** : `var/data_dev.db` — persistées dans le volume Docker

---

*Dernière mise à jour : 18 mars 2026*
