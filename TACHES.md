# PROMPT CLAUDE CODE — Tests complets : Expérimentation + Charge + Scans

## CONFIGURATION GIT
```bash
git config user.name "MON_PRENOM MON_NOM"
git config user.email "MON_EMAIL@example.com"
```

## CONTEXTE

Mon application Symfony 6.4 tourne en dev avec Docker Desktop + Kind. Je dois :
1. Valider l'expérimentation bac à sable (tester RabbitMQ et Keycloak dans K8s Kind)
2. Lancer les tests de charge Siege
3. Lancer les scans de sécurité Trivy

Tout se fait en **environnement de développement** (pas besoin de passer en prod).

---

## PARTIE 1 — VALIDATION EXPÉRIMENTATION BAC À SABLE (section 1.2.2)

L'objectif est de **prouver** que les expérimentations fonctionnent réellement. On doit exécuter les tests et capturer les résultats.

### 1.1 Vérifier que l'environnement tourne

```bash
# Vérifier le cluster K8s Kind
kubectl get pods -n lpmde-sandbox

# Vérifier que les 3 services sont Running
# Attendu : postgres, rabbitmq, keycloak tous en Running

# Vérifier que l'app Symfony tourne
curl -s -o /dev/null -w "HTTP %{http_code} - Total: %{time_total}s\n" http://localhost:8080/
# ou http://localhost:8000/ selon ta config
```

**Trouve le bon port** de l'app Symfony (8080 ou 8000) et utilise-le pour toute la suite.

### 1.2 Test expérimentation RabbitMQ (pub/sub)

On doit prouver que le cycle publication/consommation fonctionne :

```bash
# 1. Vérifier que RabbitMQ est accessible
kubectl port-forward -n lpmde-sandbox svc/rabbitmq 5672:5672 15672:15672 &
sleep 3

# 2. Vérifier l'interface de management RabbitMQ
curl -s -o /dev/null -w "RabbitMQ Management: HTTP %{http_code}\n" http://localhost:15672/

# 3. Publier un message via Symfony Messenger
# Cherche la commande qui dispatch un message (GhostAlert ou TrocCreatedNotification)
php bin/console list | grep dispatch
# OU
docker exec <container_name> php bin/console app:dispatch:ghost-alert 2>&1
# OU si l'app tourne en local :
php bin/console app:dispatch:ghost-alert 2>&1

# 4. Vérifier la queue RabbitMQ (via API management)
curl -s -u guest:guest http://localhost:15672/api/queues/%2f/ | python -c "
import sys,json
queues = json.load(sys.stdin)
for q in queues:
    print(f\"Queue: {q['name']} | Messages: {q.get('messages',0)} | Consumers: {q.get('consumers',0)}\")"

# 5. Consommer le message
docker exec <container_name> php bin/console messenger:consume async --limit=1 -vv 2>&1
# OU en local :
php bin/console messenger:consume async --limit=1 -vv 2>&1

# 6. Re-vérifier la queue (doit être vide)
curl -s -u guest:guest http://localhost:15672/api/queues/%2f/ | python -c "
import sys,json
queues = json.load(sys.stdin)
for q in queues:
    print(f\"Queue: {q['name']} | Messages: {q.get('messages',0)} | Status: {'VIDE ✅' if q.get('messages',0)==0 else 'NON VIDE ⚠️'}\")"
```

**Capture tous les résultats** dans un fichier `RABBITMQ_TEST_RESULTS.txt`.

### 1.3 Test expérimentation Keycloak (OAuth2/OIDC)

```bash
# 1. Exposer Keycloak si pas déjà fait
kubectl port-forward -n lpmde-sandbox svc/keycloak 8080:8080 &
sleep 5

# 2. Test OIDC Discovery
curl -s -w "\n--- Temps: %{time_total}s ---" \
  "http://localhost:8080/realms/symfony-app/.well-known/openid-configuration" \
  | python -c "import sys,json; d=json.loads(sys.stdin.read().split('---')[0]); print('issuer:', d['issuer']); print('token_endpoint:', d['token_endpoint'])"

# 3. Obtenir un token JWT
TOKEN_RESPONSE=$(curl -s -w "\n--- Temps: %{time_total}s ---" \
  -X POST "http://localhost:8080/realms/symfony-app/protocol/openid-connect/token" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "client_id=symfony-app&client_secret=7g4hUZzxEbpEegkTA4v1L8w3RICCbe1x&username=testuser&password=password&grant_type=password&scope=openid profile email")
echo "$TOKEN_RESPONSE"

# 4. Extraire le token
ACCESS_TOKEN=$(echo "$TOKEN_RESPONSE" | head -1 | python -c "import sys,json; print(json.load(sys.stdin)['access_token'])")

# 5. Test userinfo avec le token
curl -s -w "\n--- Temps: %{time_total}s ---" \
  -H "Authorization: Bearer $ACCESS_TOKEN" \
  "http://localhost:8080/realms/symfony-app/protocol/openid-connect/userinfo"

# 6. Décoder le JWT
echo "$ACCESS_TOKEN" | cut -d'.' -f2 | tr '_-' '/+' \
  | awk '{n=length($0)%4; if(n==2)print $0"=="; else if(n==3)print $0"="; else print $0}' \
  | base64 -d 2>/dev/null | python -c "
import sys,json; c=json.load(sys.stdin)
print('sub:', c.get('sub'))
print('username:', c.get('preferred_username'))
print('email:', c.get('email'))
print('issuer:', c.get('iss'))
print('roles:', c.get('realm_access',{}).get('roles',[]))"
```

**Capture tous les résultats** dans un fichier `KEYCLOAK_TEST_RESULTS.txt`.

---

## PARTIE 2 — TESTS DE CHARGE SIEGE

### 2.1 Installer Siege si nécessaire

```bash
# Vérifier si siege est installé
which siege || siege --version

# Si pas installé :
# Sur Ubuntu/WSL : sudo apt-get install siege -y
# Sur Mac : brew install siege
# Sur Windows : utiliser WSL ou installer via chocolatey
```

Si Siege n'est pas installable, utilise `ab` (Apache Benchmark) comme alternative :
```bash
which ab  # souvent préinstallé
```

### 2.2 Préparer l'app (IMPORTANT)

```bash
# Charger les fixtures si pas déjà fait
php bin/console doctrine:fixtures:load --no-interaction 2>/dev/null \
  || docker exec <container> php bin/console doctrine:fixtures:load --no-interaction

# Faire un premier appel pour chauffer le cache Symfony (le premier est toujours lent)
curl -s -o /dev/null -w "Warm-up: %{time_total}s\n" http://localhost:8080/troc
curl -s -o /dev/null -w "Warm-up 2: %{time_total}s\n" http://localhost:8080/troc
# Le 2ème appel devrait être beaucoup plus rapide que le 1er
```

### 2.3 Lancer les tests Siege

**Adapte le port (8080 ou 8000) et l'URL selon ta config.**

```bash
echo "=== Test de charge Siege — $(date) ===" > SIEGE_RESULTS.txt

echo -e "\n--- 10 utilisateurs concurrents ---" >> SIEGE_RESULTS.txt
siege -c 10 -t 30S -b http://localhost:8080/troc 2>&1 | tee -a SIEGE_RESULTS.txt

echo -e "\n--- 25 utilisateurs concurrents ---" >> SIEGE_RESULTS.txt
siege -c 25 -t 30S -b http://localhost:8080/troc 2>&1 | tee -a SIEGE_RESULTS.txt

echo -e "\n--- 50 utilisateurs concurrents ---" >> SIEGE_RESULTS.txt
siege -c 50 -t 30S -b http://localhost:8080/troc 2>&1 | tee -a SIEGE_RESULTS.txt

echo -e "\n--- 100 utilisateurs concurrents ---" >> SIEGE_RESULTS.txt
siege -c 100 -t 30S -b http://localhost:8080/troc 2>&1 | tee -a SIEGE_RESULTS.txt
```

**Si Siege n'est pas dispo, utilise ab (Apache Benchmark) :**
```bash
ab -n 300 -c 10 http://localhost:8080/troc/ > ab_10users.txt 2>&1
ab -n 750 -c 25 http://localhost:8080/troc/ > ab_25users.txt 2>&1
ab -n 1500 -c 50 http://localhost:8080/troc/ > ab_50users.txt 2>&1
ab -n 3000 -c 100 http://localhost:8080/troc/ > ab_100users.txt 2>&1
```

### 2.4 Créer le résumé

Après les tests, crée ou mets à jour `SIEGE_RESULTS.md` avec un tableau récapitulatif :

```markdown
# Résultats tests de charge

**Date :** [date]
**Environnement :** Docker Desktop + Symfony 6.4 (mode dev)
**Endpoint testé :** GET /troc (liste des annonces, 13 fixtures)

| Utilisateurs | Transactions | Trans/sec | Temps moyen | Disponibilité | Plus long |
|---|---|---|---|---|---|
| 10 | ... | ... | ... | ... | ... |
| 25 | ... | ... | ... | ... | ... |
| 50 | ... | ... | ... | ... | ... |
| 100 | ... | ... | ... | ... | ... |

**Analyse :**
- [Commenter les résultats : le P95 reste-t-il < 300ms ?]
- [La disponibilité reste-t-elle > 99% ?]
- [À quel nombre d'utilisateurs commence-t-on à voir des dégradations ?]

**Note :** Tests effectués en environnement de développement (Docker Desktop, mode dev Symfony avec profiler). En production (mode prod, opcache, multi-réplicas K8s), les performances seraient significativement meilleures.
```

---

## PARTIE 3 — SCANS DE SÉCURITÉ

### 3.1 Trivy filesystem

```bash
# Vérifier si trivy est installé
which trivy || trivy --version

# Si pas installé, utilise l'image Docker :
docker run --rm -v $(pwd):/app aquasecurity/trivy:latest fs /app --severity HIGH,CRITICAL --format table 2>&1 | tee trivy_fs_results.txt

# Ou si trivy est installé :
trivy fs . --severity HIGH,CRITICAL --format table 2>&1 | tee trivy_fs_results.txt
```

### 3.2 Trivy image Docker (si possible)

```bash
# Trouver le nom de l'image Docker de l'app
docker images | grep -i lpmde
docker images | grep -i petite

# Scanner l'image
trivy image <NOM_IMAGE>:latest --severity HIGH,CRITICAL --format table 2>&1 | tee trivy_image_results.txt
```

### 3.3 PHPUnit (vérifier que tous les tests passent toujours)

```bash
php bin/phpunit 2>&1 | tee phpunit_results.txt
# OU
docker exec <container> php bin/phpunit 2>&1 | tee phpunit_results.txt
```

---

## PARTIE 4 — COMMIT DES RÉSULTATS

```bash
# Vérifier l'identité Git
git config user.name  # doit être MON nom

# Ajouter les résultats
git add SIEGE_RESULTS.md SIEGE_RESULTS.txt RABBITMQ_TEST_RESULTS.txt KEYCLOAK_TEST_RESULTS.txt trivy_*.txt phpunit_results.txt 2>/dev/null
git commit -m "add: ajout des résultats de tests (charge, expérimentation, sécurité)"
git push
```

---

## ORDRE DE PRIORITÉ

1. **Partie 1** — Validation expérimentation (RabbitMQ + Keycloak) — prouve que le bac à sable marche
2. **Partie 2** — Tests de charge Siege — nécessaire pour la démo et la Phase 3
3. **Partie 3** — Scans Trivy + PHPUnit — confirme l'état sécurité actuel
4. **Partie 4** — Commit tout

## RÈGLES
- **Adapte les commandes** au setup réel (port 8080 ou 8000, nom du container Docker, etc.)
- **Capture TOUT** dans des fichiers texte — on en aura besoin pour les slides
- Si Siege n'est pas installable → utilise `ab` (Apache Benchmark) ou même `curl` en boucle
- Si un test échoue, **documente l'échec** — c'est valorisé dans le rapport d'expérimentation
- Commits en français : `add:`, `test:`, `fix:`