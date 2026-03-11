# Guide de démo pour BRYAN — La Petite Maison de l'Épouvante

Ce document liste toutes les commandes à exécuter lors de la présentation, dans l'ordre recommandé, avec une explication de ce que chaque commande démontre.

---

## Prérequis — Démarrage des serveurs

Avant toute démonstration, ouvrir **deux terminaux PowerShell** à la racine du projet.

**Terminal 1 — Serveur principal (port 8000)**
```powershell
php -S localhost:8000 -t public
```
> Démarre le serveur PHP intégré qui reçoit les requêtes des utilisateurs. C'est le point d'entrée de l'application.

**Terminal 2 — Services internes (port 8001)**
```powershell
php -S localhost:8001 -t public
```
> Simule les micro-services internes (`/internal/users` et `/internal/subscription`). Le BFF interroge ce serveur en arrière-plan.

---

## 1. Tests automatisés

### 1.1 Lancer la suite de tests complète
```powershell
php bin/phpunit --no-coverage
```
> Exécute les **59 tests** unitaires et E2E. Valide le bon fonctionnement de toutes les couches : entités, services HTTP, API Gateway, contrôleurs, messagerie.
>
> **Résultat attendu :** `OK (59 tests, 186 assertions)`

---

### 1.2 Rapport de couverture de code
```powershell
php bin/phpunit --coverage-text
```
> Génère la couverture ligne par ligne dans la console. Montre le pourcentage de code testé par classe, méthode et ligne.
>
> **Résultat attendu :**
> ```
> Classes: 78.95% (15/19)
> Methods: 74.60% (47/63)
> Lines:   59.11% (146/247)
> ```

### 1.3 Rapport de couverture HTML (optionnel, plus visuel)
```powershell
php bin/phpunit --coverage-html=var/coverage/html
Start-Process var/coverage/html/index.html
```
> Génère un rapport HTML navigable et l'ouvre dans le navigateur. Permet de voir en couleur chaque ligne couverte (vert) ou non couverte (rouge).

---

## 2. API Gateway / BFF (Backend For Frontend)

### 2.1 Appel de l'endpoint d'agrégation
```powershell
Invoke-RestMethod -Uri "http://localhost:8000/api/profile/1" -Method GET
```
> Interroge l'endpoint BFF `GET /api/profile/{id}`. Ce endpoint appelle en parallèle :
> - le service utilisateur (`/internal/users/1`)
> - le service abonnement (`/internal/subscription/1`)
>
> Il agrège les deux réponses et les retourne dans un seul JSON.
>
> **Résultat attendu :**
> ```json
> {
>   "user": { "id": 1, "name": "Bryan Joubert", "email": "..." },
>   "subscription": { "userId": 1, "plan": "Premium", "status": "active" },
>   "errors": []
> }
> ```

### 2.2 Vérifier le code HTTP de la réponse (200 vs 206)
```powershell
$r = Invoke-WebRequest -Uri "http://localhost:8000/api/profile/1" -UseBasicParsing
"HTTP $($r.StatusCode)"
```
> - **HTTP 200** : les deux services ont répondu correctement.
> - **HTTP 206** : un service est en erreur, réponse partielle (le champ `errors` contiendra le détail).

---

## 3. Cache

### 3.1 Mesurer le gain de performance du cache
```powershell
# Premier appel (cache MISS — les services sont interrogés)
Measure-Command { Invoke-RestMethod -Uri "http://localhost:8000/api/profile/1" } | Select TotalMilliseconds

# Deuxième appel (cache HIT — réponse servie depuis le cache, 60s TTL)
Measure-Command { Invoke-RestMethod -Uri "http://localhost:8000/api/profile/1" } | Select TotalMilliseconds
```
> Démontre le cache mis en place dans `ApiAggregator`. Le deuxième appel est significativement plus rapide car les données sont mises en cache pendant 60 secondes. En cas d'erreur d'un service, la réponse n'est PAS mise en cache.

---

## 4. Logs structurés (Observabilité)

### 4.1 Consulter les logs applicatifs
```powershell
Get-Content var/log/dev.log | Select-String "BFF" | Select-Object -Last 10
```
> Affiche les lignes de log générées par l'API Gateway. Démontre l'observabilité : chaque appel de service est tracé avec son contexte (`user_id`).
>
> **Résultat attendu :**
> ```
> app.INFO: BFF: utilisateur récupéré {"user_id":1}
> app.INFO: BFF: abonnement récupéré {"user_id":1}
> ```

### 4.2 Voir les logs d'erreur (cas dégradé)
```powershell
# Arrêter le serveur 8001, puis appeler le BFF :
Invoke-WebRequest -Uri "http://localhost:8000/api/profile/1" -UseBasicParsing
Get-Content var/log/dev.log | Select-String "erreur" | Select-Object -Last 5
```
> Démontre la résilience : si un micro-service est indisponible, le BFF retourne une réponse partielle (HTTP 206) et logue l'erreur avec le contexte.

---

## 5. Messagerie asynchrone (RabbitMQ / Symfony Messenger)

### 5.1 Déclencher une notification de connexion
```powershell
Invoke-WebRequest -Uri "http://localhost:8000/do-login" -Method POST `
  -Body "username=BryanDemo" `
  -ContentType "application/x-www-form-urlencoded" `
  -UseBasicParsing
```
> Simule une connexion utilisateur. Le contrôleur envoie un message `UserLoginNotification` via Symfony Messenger. En mode `sync://` (configuré dans `.env.local`), le message est traité immédiatement.

### 5.2 Vérifier les logs du handler Messenger
```powershell
Get-Content var/log/dev.log | Select-String "UserLogin" | Select-Object -Last 10
```
> **Résultat attendu :**
> ```
> messenger.INFO: Sending message App\Message\UserLoginNotification
> messenger.INFO: Received message App\Message\UserLoginNotification
> app.INFO: UserLoginNotification reçue {"username":"BryanDemo","login_time":"..."}
> app.INFO: UserLoginNotification traitée {"username":"BryanDemo"}
> ```

### 5.3 Envoyer 50 GhostAlerts en rafale
```powershell
Invoke-WebRequest -Uri "http://localhost:8000/test-rabbit" -UseBasicParsing
```
> Envoie 50 messages `GhostAlert` en une seule requête. Démontre la capacité de la file de messages à absorber une rafale de messages asynchrones.

---

## 6. Tests de charge (k6)

### 6.1 Smoke test — validation rapide (30 secondes, 1 utilisateur virtuel)
```powershell
k6 run load-tests/smoke-test.js
```
> Vérifie que toutes les routes clés répondent correctement avant de lancer un test de charge complet. Teste : accueil, produits, services internes.
>
> **Résultat attendu :** `✓ 35/35 checks, 0.00% errors, p(95)<2000ms`

### 6.2 Test de charge complet — montée en charge progressive (~5 minutes)
```powershell
k6 run load-tests/load-test.js
```
> Simule une montée en charge progressive de 0 à 100 utilisateurs virtuels, puis un soak test à 20 VUs pendant 2 minutes. Mesure le comportement de l'application sous charge.
>
> **Seuils configurés :**
> - Taux d'erreur < 5%
> - p(95) durée requête < 500ms
> - p(95) durée BFF < 800ms

### 6.3 Test de pic — surge soudain (200 utilisateurs simultanés)
```powershell
k6 run load-tests/spike-test.js
```
> Simule un pic soudain de trafic (0 → 200 VUs en 10 secondes). Teste la résistance de l'application à une surcharge imprévue.

---

## 7. Docker

### 7.1 Build de l'image Docker
```powershell
docker build -t lpmde:latest .
```
> Compile l'application dans une image Docker production (PHP 8.2 + Apache). L'image inclut toutes les dépendances Composer et la configuration OPcache.

### 7.2 Lancer l'application dans un conteneur
```powershell
docker run --rm -p 8088:80 `
  -e APP_ENV=prod `
  -e APP_SECRET=demo_secret_minimum_32_chars_ok `
  -e DATABASE_URL="sqlite:////var/www/html/var/test.db" `
  --name lpmde_demo `
  lpmde:latest
```
> Démarre l'application dans un conteneur isolé, accessible sur `http://localhost:8088/`.

### 7.3 Vérifier que le conteneur répond
```powershell
Invoke-WebRequest -Uri "http://localhost:8088/" -UseBasicParsing | Select StatusCode
```

### 7.4 Arrêter le conteneur
```powershell
docker stop lpmde_demo
```

---

## 8. Qualité du code — SonarQube (CI uniquement)

> Cette étape s'exécute automatiquement dans le pipeline CI/CD. Pour illustrer en démo :

```powershell
# Générer les rapports de couverture nécessaires à SonarQube
php bin/phpunit --coverage-cobertura=var/log/cobertura.xml --log-junit=var/log/junit.xml

# Lancer l'analyse (nécessite SonarQube ou SonarCloud configuré)
# sonar-scanner -Dsonar.host.url=http://localhost:9000 -Dsonar.token=MON_TOKEN
```
> Le fichier `sonar-project.properties` à la racine configure le projet : sources, tests, rapports de couverture. Le pipeline GitLab CI et GitHub Actions exécutent automatiquement cette analyse à chaque `git push`.

---

## 9. Pipeline CI/CD

### Déclencher le pipeline
```powershell
git add .
git commit -m "démo evaluation"
git push
```
> Un `git push` déclenche automatiquement :
> 1. **install** — `composer install`
> 2. **test** — audit npm, SonarQube, Trivy (vulnérabilités), tests unitaires + E2E avec couverture
> 3. **release** — build et push de l'image Docker
> 4. **staging** — déploiement automatique (si branche `main`)
> 5. **production** — déploiement Kubernetes (manuel)

---

## Résumé des résultats attendus

| Démonstration | Résultat clé |
|---------------|-------------|
| Tests | `OK (59 tests, 186 assertions)` |
| Couverture | `78.95% classes, 59.11% lines` |
| BFF | HTTP 200 avec user + subscription agrégés |
| Cache | 2e appel ~10x plus rapide que le 1er |
| Logs | Traces structurées avec contexte métier |
| k6 smoke | `35/35 checks ✓, 0% errors` |
| k6 load | p(95) < 500ms, erreurs < 5% |
| Docker | Image 1.19 GB, conteneur HTTP 200 |
| Messenger | 4 lignes de log par message traité |
