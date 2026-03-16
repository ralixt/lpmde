# Expérimentation bac à sable : Déploiement d'un serveur d'autorisation (Keycloak) sous Kubernetes

> Rapport d'expérimentation — Bloc RB3 individuel
> Projet : La Petite Maison de l'Épouvante (LPMDE)
> Date : 16 mars 2026
> Contexte : deuxième expérimentation bac à sable, complémentaire au PoC RabbitMQ (15 mars 2026)

---

## 1. Environnement de test

### 1.1 Infrastructure Kubernetes

| Composant | Valeur |
|-----------|--------|
| Outil d'orchestration | Kind (Kubernetes IN Docker) v0.20+ |
| Version Kubernetes | v1.35.0 (`kindest/node:v1.35.0`) |
| Cluster | `kind-lpmde-sandbox` |
| Nœuds | 1 control-plane (single-node) |
| Namespace | `lpmde-sandbox` |
| OS hôte | Windows 11 + Docker Desktop |
| Shell utilisé | Git Bash (bash, syntaxe Unix) |
| Manifestes | `k8s/kind/` (Kustomize, 8 ressources) |

### 1.2 Images Docker déployées

| Service | Image | Rôle |
|---------|-------|------|
| Keycloak | `quay.io/keycloak/keycloak:25.0` | Serveur IAM OAuth2/OIDC |
| PostgreSQL | `postgres:16-alpine` | Base de données de Keycloak |
| RabbitMQ | `rabbitmq:3-management` | Broker AMQP (déjà validé au PoC RabbitMQ) |

### 1.3 Configuration Keycloak déployée

| Paramètre | Valeur | Justification |
|-----------|--------|---------------|
| Mode démarrage | `start-dev` (HTTP) | PoC — TLS géré en production |
| Hostname fixé | `--hostname=localhost` | Fixe l'`iss` du JWT indépendamment de l'URL d'accès |
| `hostname-strict` | `false` | Accepte les connexions via port-forward Kind |
| Admin credentials | `admin:admin` (K8s Secret) | Injecté via `k8s/kind/secret.yaml` |
| Realm testé | `symfony-app` | Realm dédié à l'application |
| Client | `symfony-app` (confidentiel) | Standard flow + Direct Access Grants |
| Client Secret | `7g4hUZzxEbpEegkTA4v1L8w3RICCbe1x` | Partagé avec `.env.local` |
| Redirect URI | `http://localhost:8000/login/keycloak/callback` | Callback Symfony |

### 1.4 Ressources K8s du pod Keycloak

| Ressource | Request | Limit |
|-----------|---------|-------|
| CPU | 500m | 1000m |
| RAM | 768Mi | 1200Mi |

*Keycloak est le service le plus gourmand — 3× les resources de PostgreSQL et RabbitMQ.*

### 1.5 Outils utilisés pour les tests

- `kubectl` — gestion du cluster, port-forward, inspection des pods
- `curl` avec `-w "%{time_total}"` — mesure de latence HTTP
- `base64 -d` + `python` — décodage des JWT (disponibles nativement dans Git Bash)
- API REST Admin Keycloak — création du realm, client, utilisateur de test

---

## 2. Étapes clés pour reproduire l'expérimentation

### Prérequis

- Docker Desktop en cours d'exécution
- `kubectl` installé et configuré
- `kind` installé
- Cluster déployé :

```bash
kind create cluster --name lpmde-sandbox --config k8s/kind/kind-cluster-config.yaml
kubectl apply -k k8s/kind
kubectl wait --for=condition=Available deployment/keycloak -n lpmde-sandbox --timeout=360s
```

Vérification de l'état initial :

```bash
kubectl get pods -n lpmde-sandbox
# NAME                        READY   STATUS    RESTARTS
# keycloak-55945795d5-qk65k   1/1     Running   1 (9h ago)
# postgres-6b97f94d58-vb5p9   1/1     Running   1 (9h ago)
# rabbitmq-7574b88c9f-wpssk   1/1     Running   0
```

### Étape 1 — Vérifier l'occupation du port 8080

```bash
netstat -ano | grep ":8080" | grep "LISTENING"
# Si occupé, tuer le processus ou utiliser un port alternatif (--address=localhost:8081)
```

*Note : lors de l'expérimentation, un port-forward résiduel d'une session précédente (PID 24604)
occupait déjà le port 8080. Keycloak était donc directement accessible, ce qui a permis de
passer immédiatement aux tests sans relancer le port-forward.*

### Étape 2 — Exposer Keycloak via port-forward

```bash
kubectl port-forward -n lpmde-sandbox svc/keycloak 8080:8080 >/dev/null 2>&1 &
KEYCLOAK_PF_PID=$!
sleep 21   # 5s ouverture du tunnel + ~16s warm-up de l'API admin Keycloak
curl -s -o /dev/null -w "HTTP %{http_code}\n" http://localhost:8080/realms/master
# Attendu : HTTP 200
```

### Étape 3 — Obtenir un token admin (realm master)

```bash
ADMIN_TOKEN=$(curl -s \
  -X POST "http://localhost:8080/realms/master/protocol/openid-connect/token" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "client_id=admin-cli&username=admin&password=admin&grant_type=password" \
  | python -c "import sys,json; print(json.load(sys.stdin)['access_token'])")
echo "${ADMIN_TOKEN:0:60}..."   # doit afficher eyJ...
```

*Credentials `admin:admin` injectés depuis `k8s/kind/secret.yaml` via variables d'environnement.*

### Étape 4 — Vérifier / Créer le realm `symfony-app`

```bash
REALM_STATUS=$(curl -s -o /dev/null -w "%{http_code}" \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  "http://localhost:8080/admin/realms/symfony-app")
echo "Realm HTTP: $REALM_STATUS"   # 200 = existe déjà, 404 = à créer

if [ "$REALM_STATUS" = "404" ]; then
  curl -s -X POST "http://localhost:8080/admin/realms" \
    -H "Authorization: Bearer $ADMIN_TOKEN" -H "Content-Type: application/json" \
    -d '{"realm":"symfony-app","enabled":true,"sslRequired":"none",
         "loginWithEmailAllowed":true,"duplicateEmailsAllowed":false}'
fi
```

*Résultat observé : HTTP 200 — le realm persistait depuis le PoC précédent grâce au PVC PostgreSQL.*

### Étape 5 — Vérifier / Créer le client `symfony-app`

```bash
CLIENT_COUNT=$(curl -s \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  "http://localhost:8080/admin/realms/symfony-app/clients?clientId=symfony-app" \
  | python -c "import sys,json; print(len(json.load(sys.stdin)))")

if [ "$CLIENT_COUNT" = "0" ]; then
  curl -s -X POST "http://localhost:8080/admin/realms/symfony-app/clients" \
    -H "Authorization: Bearer $ADMIN_TOKEN" -H "Content-Type: application/json" \
    -d '{
      "clientId":"symfony-app","enabled":true,"publicClient":false,
      "secret":"7g4hUZzxEbpEegkTA4v1L8w3RICCbe1x",
      "standardFlowEnabled":true,"directAccessGrantsEnabled":true,
      "redirectUris":["http://localhost:8000/login/keycloak/callback"],
      "webOrigins":["http://localhost:8000"]
    }'
fi
```

*Résultat observé : 1 client trouvé — configuration validée :*

```
clientId:                  symfony-app
publicClient:              False
standardFlowEnabled:       True
directAccessGrantsEnabled: True
redirectUris:              ['http://localhost:8000/login/keycloak/callback']
```

### Étape 6 — Créer l'utilisateur de test `testuser`

```bash
USER_COUNT=$(curl -s \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  "http://localhost:8080/admin/realms/symfony-app/users?username=testuser&exact=true" \
  | python -c "import sys,json; print(len(json.load(sys.stdin)))")

if [ "$USER_COUNT" = "0" ]; then
  curl -s -o /dev/null -w "HTTP %{http_code}\n" \
    -X POST "http://localhost:8080/admin/realms/symfony-app/users" \
    -H "Authorization: Bearer $ADMIN_TOKEN" -H "Content-Type: application/json" \
    -d '{"username":"testuser","email":"test@lpmde.fr","firstName":"Test","lastName":"User",
         "enabled":true,"emailVerified":true,
         "credentials":[{"type":"password","value":"password","temporary":false}]}'
fi
```

*Résultat observé : HTTP 201 — utilisateur créé avec succès.*

### Étape 7 — Test du endpoint OIDC Discovery

```bash
curl -s -w "\n---TIMING--- total:%{time_total}s" \
  "http://localhost:8080/realms/symfony-app/.well-known/openid-configuration" \
  | python -c "
import sys; raw=sys.stdin.read(); parts=raw.split('\n---TIMING---')
import json; d=json.loads(parts[0])
print('issuer:            ', d['issuer'])
print('token_endpoint:    ', d['token_endpoint'])
print('userinfo_endpoint: ', d['userinfo_endpoint'])
print('grant_types:       ', d.get('grant_types_supported',[]))
if len(parts)>1: print(parts[1])"
```

**Résultat obtenu :**
```
issuer:             http://localhost:8080/realms/symfony-app
token_endpoint:     http://localhost:8080/realms/symfony-app/protocol/openid-connect/token
userinfo_endpoint:  http://localhost:8080/realms/symfony-app/protocol/openid-connect/userinfo
grant_types:        ['authorization_code', 'implicit', 'refresh_token', 'password',
                     'client_credentials', 'urn:ietf:params:oauth:grant-type:device_code', ...]
---TIMING--- total:0.297184s
```

### Étape 8 — Test du token endpoint (mesure de latence)

```bash
TOKEN_RESPONSE=$(curl -s \
  -w "\n---TIMING--- total:%{time_total}s connect:%{time_connect}s ttfb:%{time_starttransfer}s" \
  -X POST "http://localhost:8080/realms/symfony-app/protocol/openid-connect/token" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "client_id=symfony-app&client_secret=7g4hUZzxEbpEegkTA4v1L8w3RICCbe1x \
      &username=testuser&password=password&grant_type=password&scope=openid profile email")
echo "$TOKEN_RESPONSE"
```

**Résultat obtenu :**
```
token_type:       Bearer
expires_in:       300 s
refresh_expires_in: 1800 s
scope:            openid email profile
access_token:     eyJhbGciOiJSUzI1NiIsInR5cCIgOiAiSldUIiwia2lkIiA6ICJ...
refresh_token:    eyJhbGciOiJIUzUxMiIsInR5cCIgOiAiSldUIiwi...
---TIMING--- total:0.581366s connect:0.001637s ttfb:0.581323s
```

### Étape 9 — Décodage des claims JWT

```bash
JWT_PAYLOAD=$(echo "$ACCESS_TOKEN" | cut -d'.' -f2 | tr '_-' '/+' \
  | awk '{n=length($0)%4; if(n==2)print $0"=="; else if(n==3)print $0"="; else print $0}')
echo "$JWT_PAYLOAD" | base64 -d 2>/dev/null | python -c "
import sys,json; c=json.load(sys.stdin)
print('sub:               ', c.get('sub'))
print('preferred_username:', c.get('preferred_username'))
print('email:             ', c.get('email'))
print('iss:               ', c.get('iss'))
print('azp:               ', c.get('azp'))
print('realm_access:      ', c.get('realm_access'))"
```

**Résultat obtenu :**
```
sub:                857cb966-85a2-4203-ac11-840d8bf922e6
preferred_username: testuser
email:              test@lpmde.fr
iss:                http://localhost:8080/realms/symfony-app
azp:                symfony-app
realm_access:       {'roles': ['default-roles-symfony-app', 'offline_access', 'uma_authorization']}
```

*Point clé : l'`iss` est bien `http://localhost:8080/realms/symfony-app` — cohérent avec
le flag `--hostname=localhost`. C'est ce que la `KeycloakService.php` de l'application
Symfony utilise pour valider les tokens côté serveur.*

### Étape 10 — Test du endpoint userinfo

```bash
curl -s -w "\n---TIMING--- total:%{time_total}s" \
  -H "Authorization: Bearer $ACCESS_TOKEN" \
  "http://localhost:8080/realms/symfony-app/protocol/openid-connect/userinfo"
```

**Résultat obtenu :**
```json
{
  "sub": "857cb966-85a2-4203-ac11-840d8bf922e6",
  "email_verified": true,
  "name": "Test User",
  "preferred_username": "testuser",
  "given_name": "Test",
  "family_name": "User",
  "email": "test@lpmde.fr"
}
---TIMING--- total:0.019720s
```

### Étape 11 — Test du refresh de token

```bash
curl -s -w "\n---TIMING--- total:%{time_total}s" \
  -X POST "http://localhost:8080/realms/symfony-app/protocol/openid-connect/token" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "client_id=symfony-app&client_secret=7g4hUZzxEbpEegkTA4v1L8w3RICCbe1x \
      &grant_type=refresh_token&refresh_token=$REFRESH_TOKEN"
```

**Résultat obtenu :**
```
Nouveau access_token: eyJhbGciOiJSUzI1NiIsInR5cCIgOiAiSldUIiwia2lkIiA6ICJKR...
expires_in: 300 s
---TIMING--- total:0.045730s
```

---

## 3. Difficultés rencontrées

### 3.1 Timeouts des probes Kubernetes (difficulté majeure)

**Symptôme** : Lors du déploiement initial, le pod Keycloak repassait en `NotReady` puis
`CrashLoopBackOff` en boucle, même après avoir affiché une ligne de log `started in X.XXXs`.

**Cause** : Les probes configurées par défaut (`initialDelaySeconds: 10`, `failureThreshold: 3`,
`timeoutSeconds: 1`) ne tiennent pas compte du temps de démarrage réel de Keycloak 25 avec
un backend PostgreSQL :
- Connexion initiale à PostgreSQL : ~2-5 secondes
- Initialisation du schéma Flyway (première exécution) : ~10-20 secondes
- Chargement des realms, clients et providers : ~5 secondes supplémentaires

Résultat : Kubernetes déclarait le pod `unhealthy` et le redémarrait avant qu'il ne soit
réellement prêt, créant une boucle infinie.

**Solution appliquée** dans `k8s/kind/keycloak-deployment.yaml` :
```yaml
startupProbe:
  httpGet: { path: /health/started, port: management }  # port 9000
  initialDelaySeconds: 30
  periodSeconds: 10
  timeoutSeconds: 5
  failureThreshold: 18   # fenêtre max = 18 × 10s = 180s

readinessProbe:
  httpGet: { path: /health/ready, port: management }
  initialDelaySeconds: 60
  periodSeconds: 10
  failureThreshold: 12   # fenêtre max = 120s

livenessProbe:
  httpGet: { path: /health/live, port: management }
  initialDelaySeconds: 120
  periodSeconds: 15
  failureThreshold: 6
```

**Leçon** : Toujours profiler le démarrage réel d'un service avant de définir les probes.
La commande `kubectl logs -f deploy/keycloak -n lpmde-sandbox` permet d'observer la ligne
`Keycloak 25.0.6 on JVM ... started in 16.084s` et de calibrer les délais en conséquence.
Les probes kubernetes ne sont pas de simples timeouts : elles pilotent le routage du trafic.

---

### 3.2 Encodage UTF-8 BOM sur Windows

**Symptôme** : Au démarrage de Symfony depuis un conteneur Docker, le chargement de `.env.local`
échouait avec l'exception :
```
Symfony\Component\Dotenv\Exception\FormatException:
Loading files starting with a BOM is not supported.
```

**Cause** : VS Code (et Notepad sur Windows) sauvegarde par défaut les fichiers texte avec
un BOM UTF-8 (séquence d'octets `EF BB BF` en début de fichier). Le composant Dotenv de
Symfony rejette explicitement ce format, contrairement à la plupart des éditeurs Windows
qui l'ignorent silencieusement.

**Solution** : Créer `.env.local` directement dans le conteneur Docker via `docker exec`
pour éviter le BOM :
```bash
docker exec lpmde-web-kind sh -c "cat > /var/www/html/.env.local << 'EOF'
APP_ENV=dev
KEYCLOAK_REALM=symfony-app
...
EOF"
```

Alternativement, configurer VS Code en UTF-8 sans BOM :
`Paramètres → Files: Encoding → utf8` (et non `utf8bom`).

**Leçon** : Sur les projets multi-OS (Windows/Linux), versionner un `.env.example` en
UTF-8 sans BOM et documenter ce piège dans le README. Ajouter une vérification BOM dans
le pipeline CI (`file -bi .env.local | grep -v "charset=utf-8"`).

---

### 3.3 Incohérence de l'issuer JWT entre navigateur et conteneur Docker

**Symptôme** : L'authentification fonctionnait depuis le navigateur (redirection OAuth2
complète), mais la validation du token côté Symfony retournait `401 Unauthorized` lors
de l'appel à l'endpoint `/userinfo`. Les logs montraient une erreur d'issuer mismatch.

**Cause** : Sans configuration explicite de l'hostname, Keycloak génère l'`iss` (issuer)
du JWT en fonction de l'URL utilisée pour la requête de token :
- Le navigateur appelle via `localhost:8080` (port-forward) → `iss = http://localhost:8080/...`
- L'application Symfony (dans un conteneur Docker) appelle via `host.docker.internal:8080`
  → `iss = http://host.docker.internal:8080/...`

Le token obtenu par le navigateur avait un `iss` différent de l'URL utilisée par Symfony
pour valider → rejet systématique.

**Solution** : L'argument `--hostname=localhost` dans le Deployment Keycloak fixe l'issuer
à `http://localhost:8080/realms/symfony-app` quelle que soit l'URL d'accès. Le fichier
`.env.local` distingue les deux URLs :
```
KEYCLOAK_URL=http://localhost:8080          # utilisé par le navigateur (redirections)
KEYCLOAK_INTERNAL_URL=http://host.docker.internal:8080  # utilisé par Symfony (appels serveur)
```

**Leçon** : Dans toute architecture multi-réseau (navigateur + backend + K8s), fixer
explicitement l'issuer OIDC. Cette distinction `KEYCLOAK_URL` vs `KEYCLOAK_INTERNAL_URL`
est le pattern correct pour les déploiements hybrides Docker+K8s.

---

### 3.4 Dépendance d'initialisation PostgreSQL → Keycloak

**Symptôme** : Au premier démarrage du cluster, Keycloak crashait avec des erreurs
JDBC/Flyway en tentant de se connecter à PostgreSQL :
```
FATAL: database "keycloak" does not exist
org.flywaydb.core.api.exception.FlywayException: Unable to obtain connection
```

**Cause** : Kubernetes démarre les pods en parallèle sans garantie d'ordre. Le pod Keycloak
tentait de se connecter à PostgreSQL avant que ce dernier n'ait terminé son `initdb` et
créé la base de données `keycloak` (processus qui prend ~5-10 secondes).

**Solution** : La `startupProbe` avec `initialDelaySeconds: 30` laisse le temps à
PostgreSQL de s'initialiser. Cette solution est *implicite* — une solution *explicite*
(recommandée en production) serait un `initContainer` :
```yaml
initContainers:
  - name: wait-for-postgres
    image: busybox
    command: ['sh', '-c', 'until nc -z postgres 5432; do sleep 2; done']
```
Ce pattern est d'ailleurs déjà présent dans `k8s/deployment.yaml` pour l'application Symfony.

**Leçon** : L'ordre de démarrage des pods est non-déterministe dans Kubernetes. Toujours
prévoir des mécanismes de retry explicites (initContainers) ou des probes généreuses pour
les services avec dépendances d'initialisation.

---

## 4. Limites identifiées

### 4.1 Cluster single-node — pas de test de haute disponibilité

Kind déploie un unique nœud `control-plane`. En conséquence, les tests suivants sont
impossibles dans ce PoC :
- Pod scheduling multi-nœuds (node affinity, anti-affinity)
- Simulation de failover (nœud en panne, pod eviction)
- Test de resource quotas réalistes entre namespaces concurrents

**En production** : cluster à 3+ nœuds minimum (EKS, AKS, GKE, Scaleway Kapsule).

### 4.2 HTTP uniquement — absence de TLS

Keycloak tourne en mode `start-dev` avec HTTP. En production :
- HTTPS est **obligatoire** pour OAuth2/OIDC (RFC 6749 §10.3)
- Les tokens ne doivent transiter que sur des connexions chiffrées (vol de token impossible)
- `cert-manager` + Let's Encrypt (ou certificat OVH/Scaleway) serait nécessaire

### 4.3 Stockage non persistant entre recréations de cluster

Kind utilise `local-path-provisioner` (volumes sur le disque hôte). La destruction du
cluster détruit les données Keycloak (realms, clients, utilisateurs). En production :
- PVC sur stockage managé (EBS, Azure Disk, Scaleway Block Storage)
- Backup/restore PostgreSQL automatisé

### 4.4 Réseau simplifié — pas de Service Mesh ni de Network Policies

- Les `NetworkPolicy` K8s ne sont pas appliquées (CNI basique de Kind)
- Pas de chiffrement mTLS entre pods (Istio/Linkerd requis en production)
- La latence réseau entre pods est artificielle (tout dans le même process Docker)

### 4.5 Performances non représentatives

Docker Desktop alloue des ressources limitées. La JVM de Keycloak est contrainte à
768Mi-1200Mi RAM. Les mesures de latence obtenues (~0.5s pour `/token`) sont indicatives
d'un environnement local — en production avec JVM tuning et CPU dédié, les temps seraient
de l'ordre de 50-150ms.

### 4.6 Registre Docker public uniquement

Les images sont tirées depuis Docker Hub / quay.io sans registre privé. En production :
registre privé (GHCR, ECR, Harbor) avec scan de vulnérabilités intégré et `imagePullSecrets`.

---

## 5. Résultats et justification de l'adoption

### 5.1 Mesures de performance

| Endpoint | Opération | Temps mesuré (`curl -w "%{time_total}"`) |
|----------|-----------|------------------------------------------|
| `/.well-known/openid-configuration` | OIDC Discovery | **0.297 s** |
| `/protocol/openid-connect/token` | Login (Direct Grant) | **0.581 s** |
| `/protocol/openid-connect/userinfo` | Récupération profil | **0.020 s** |
| `/protocol/openid-connect/token` | Refresh token | **0.046 s** |

*Mesures effectuées en local via port-forward Kind sur Windows 11 + Docker Desktop.
L'overhead du port-forward ajoute ~1-5ms par rapport à un accès direct réseau.
Les temps `/userinfo` et `/refresh` sont remarquablement bas (~20-50ms) car Keycloak
dispose des claims en mémoire une fois le token initial validé.*

### 5.2 Flux OAuth2/OIDC validés

| Flux | Standard RFC/OIDC | Statut |
|------|-------------------|--------|
| Authorization Code Flow | RFC 6749 §4.1 | ✅ Validé (navigateur + callback Symfony) |
| Direct Access Grant (ROPC) | RFC 6749 §4.3 | ✅ Validé (tests curl) |
| Token Refresh | RFC 6749 §6 | ✅ Validé |
| OIDC UserInfo Endpoint | OIDC Core §5.3 | ✅ Validé |
| OIDC Discovery | OIDC Discovery 1.0 §4 | ✅ Validé |

### 5.3 Interopérabilité dans le cluster

Cette expérimentation confirme la coexistence de Keycloak avec les autres composants
déjà validés lors du PoC RabbitMQ (15 mars 2026) :

```
Cluster Kind (lpmde-sandbox)
├── PostgreSQL 16    ← backend de données Keycloak (BDD keycloak)
├── RabbitMQ 3       ← bus de messages pour les notifications de login
└── Keycloak 25      ← serveur IAM OAuth2/OIDC

Hôte Windows (via port-forward)
├── :8080 → Keycloak  (navigateur + curl)
├── :5672 → RabbitMQ  (AMQP, Symfony Messenger)
└── :15672 → RabbitMQ Management UI

Conteneur Docker (Symfony)
├── KEYCLOAK_URL=localhost:8080            (redirections navigateur)
├── KEYCLOAK_INTERNAL_URL=host.docker.internal:8080  (appels serveur)
└── MESSENGER_TRANSPORT_DSN=amqp://guest:guest@host.docker.internal:5672
```

La notification de connexion utilisateur déclenche un message `UserLoginNotification`
publié dans RabbitMQ depuis `LoginController` — les deux composants (Keycloak + RabbitMQ)
cohabitent donc dans le même namespace et communiquent de manière indépendante.

### 5.4 Justification de l'adoption — lien ISO 25010

#### Indicateur de qualité 1 : Sécurité (Security)

L'adoption de Keycloak élimine le stockage de secrets utilisateurs dans la base de données
applicative. Dans l'architecture initiale, les hashes bcrypt étaient stockés dans la table
`users` de l'application Symfony. Toute fuite de la base de données applicative exposait
des hashes crackables.

Avec Keycloak :
- Les credentials ne quittent jamais le serveur IAM
- La base applicative LPMDE ne stocke que l'identifiant `keycloak_id` (UUID opaque)
- **Résultat : 0 secret utilisateur dans la base de données applicative**
- Une fuite de la BDD LPMDE ne compromet aucun credential OAuth2

Cette approche répond directement à l'indicateur qualité ISO 25010 : **"0 vulnérabilité
critique liée à la gestion des identités"**.

#### Indicateur de qualité 2 : Maintenabilité (Maintainability)

L'authentification est entièrement découplée du code applicatif Symfony :
- `KeycloakService.php` : adaptateur de ~110 lignes, remplaçable sans impact sur le métier
- Ajout d'un 2FA, d'un SSO Google, ou d'une intégration LDAP : configuration Keycloak
  uniquement, **0 ligne de code Symfony à modifier**
- L'interface admin Keycloak permet aux ops de gérer les utilisateurs sans déploiement

#### Indicateur de qualité 3 : Fiabilité (Reliability)

- Keycloak 25.0 est une version maintenue par Red Hat (cycle de support 2 ans)
- Les probes Kubernetes (startup/readiness/liveness) garantissent l'absence de trafic
  vers un pod non-prêt
- Le mécanisme de `refresh_token` (1800s) évite les déconnexions lors de sessions longues
- La persistance via PostgreSQL assure la durabilité de la configuration (realm, clients,
  utilisateurs) entre les redémarrages du pod Keycloak

### 5.5 Conclusion

L'expérimentation confirme que Keycloak 25.0, déployé sur Kind avec PostgreSQL comme backend,
fournit un serveur IAM conforme OAuth2/OIDC (RFC 6749, OIDC Core 1.0). Les 5 flux testés
fonctionnent correctement avec des temps de réponse satisfaisants pour un environnement de
développement local.

Les 4 difficultés documentées (probe timing, BOM UTF-8, hostname OIDC, ordering PostgreSQL)
sont toutes des problèmes d'adaptation à l'environnement Kind/Windows — aucune n'est une
limitation intrinsèque du protocole OAuth2/OIDC ou de Keycloak.

Cette expérimentation valide l'adoption de Keycloak comme serveur d'autorisation central
pour le projet LPMDE. En production (Scaleway Kapsule ou OVH Managed Kubernetes + TLS
cert-manager + stockage persistant), Keycloak est une solution mature, interopérable et
auditable pour externaliser l'IAM d'une application Symfony.

---

*Document complémentaire : voir `POC_KUBERNETES_KIND_REPORT.md` pour le PoC RabbitMQ
(expérimentation bac à sable n°1 — 15 mars 2026)*
