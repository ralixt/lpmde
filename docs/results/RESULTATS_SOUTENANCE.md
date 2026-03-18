# Résultats complets — Soutenance LPMDE

**Date :** 18/03/2026 — Security Hotspots reviewés et validés sur SonarCloud
**Projet :** La Petite Maison de l'Épouvante — Symfony 6.4

---

## 1. Tests PHPUnit

**52 tests, 105 assertions, 0 échec**

| Suite | Tests | Assertions | Durée | Résultat |
|---|---|---|---|---|
| unit | 38 | 80 | 0.215s | ✅ OK |
| functional | 7 | 8 | 7.6s | ✅ OK |
| e2e | 7 | 17 | 7.5s | ✅ OK |
| **TOTAL** | **52** | **105** | **23s** | **✅ 0 échec** |

Commande : `php bin/phpunit`

---

## 2. Tests de charge

**Outil :** Apache Benchmark (`ab`) depuis conteneur Docker
**Endpoint :** GET /troc (liste annonces, fixtures chargées)

| Utilisateurs | Requêtes | Échecs | Trans/sec | Temps moy (concurrents) | Disponibilité |
|---|---|---|---|---|---|
| 10 | 100 | 0 | 0.51 req/s | 1963ms | **100%** |
| 25 | 100 | 0 | 0.54 req/s | 1868ms | **100%** |
| 50 | 100 | 0 | 0.53 req/s | 1896ms | **100%** |

**Disponibilité : 100% (0 erreur sur 300 requêtes)**

Les temps de réponse élevés (2-3s) sont liés à l'environnement Docker Desktop sur Windows (virtualisation WSL2 + SQLite sur volume).
En production avec PHP-FPM + OPcache + SSD : performances attendues ×5 à ×10.

---

## 3. Scans de sécurité

### Trivy FS (code applicatif)
- Résultat : **0 vulnérabilité HIGH/CRITICAL**
- Code PHP, Composer, npm : ✅ sûrs

### Trivy Image Docker (lpmde:local)
- Résultat : **41 HIGH, 0 CRITICAL**
- Toutes les CVE sur packages OS Debian (kernel, libc6, openssh-client)
- **Aucune CVE dans le code applicatif**

Remédiation : `apt upgrade` dans Dockerfile + migration vers `php:8.4-fpm-alpine`

---

## 4. Validation RabbitMQ

```
✅ Message GhostAlert publié : Evaluateur dans Soutenance LPMDE
✅ Queue : messages | 54 messages présents
✅ Message consommé : UserLoginNotification traité par UserLoginNotificationHandler
✅ Log : "NOTIFICATION TRAITÉE : L'utilisateur 'Rag Nag' s'est connecté"
```

**Pub/Sub opérationnel** — dispatch et consommation asynchrone fonctionnels.

---

## 5. Validation Keycloak

```
✅ Token JWT obtenu via realm 'lpmde' en 3.09s
✅ Userinfo retourné :
   - sub: 857cb966-85a2-4203-ac11-840d8bf922e6
   - name: Test User
   - email: test@lpmde.fr
   - email_verified: true
✅ Appel userinfo : 54ms
```

**OAuth 2.0 / OpenID Connect opérationnel** — authentification Keycloak fonctionnelle.

---

## 6. Pipeline CI/CD

- **Lien :** https://github.com/ralixt/lpmde/actions
- **Jobs :** install → unit-test → e2e-test → functional → npm-build → sonar-audit → trivy-fs-audit → build-image → push-metrics-job → deploy-staging
- **Image :** `ghcr.io/ralixt/lpmde:latest` (GHCR)
- **Métriques CI → Grafana** : résultats tests et coverage poussés vers Prometheus Pushgateway après chaque run

---

## 7. Infrastructure K8s (Kind)

```
Namespace : lpmde-sandbox

NAME                        READY   STATUS    RESTARTS   AGE
keycloak-55945795d5-qk65k   1/1     Running   4          2d12h
postgres-6b97f94d58-vb5p9   1/1     Running   4          2d12h
rabbitmq-7574b88c9f-wpssk   1/1     Running   3          2d12h
```

3/3 pods Running — infrastructure stable depuis 2 jours.

---

## 8. Interfaces disponibles

| Interface | URL | État |
|---|---|---|
| App Symfony (Docker direct) | http://localhost:8000 | ✅ Running |
| Keycloak Admin | http://localhost:8080 | ✅ Running |
| RabbitMQ Management | http://localhost:15672 | ✅ Running |
| Grafana | http://localhost:3000 | ✅ Running |
| Prometheus | http://localhost:9090 | ✅ Running |
| Pushgateway (CI metrics) | http://localhost:9091 | ✅ Running |
