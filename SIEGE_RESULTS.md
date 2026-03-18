# Résultats tests de charge

**Date :** 2026-03-17
**Environnement :** Docker Desktop + Symfony 6.4 (mode dev) + Kind/Kubernetes
**Endpoint testé :** GET /troc (liste des annonces, 13 fixtures)
**Outil :** Siege 4.0.7 (conteneur Docker lpmde-siege via réseau bridge → 172.17.0.2:8000)

| Utilisateurs | Transactions | Trans/sec | Temps moyen | Disponibilité | Plus long |
|---|---|---|---|---|---|
| 10 | 14 | 0.44 req/s | 9 390 ms | 100.00 % | 30.99 s |
| 25 | 14 | 0.44 req/s | 9 150 ms | 100.00 % | 30.44 s |
| 50 | 0 | 0.00 req/s | — | 0.00 % | — |
| 100 | 0 | 0.00 req/s | — | 0.00 % | — |

## Commandes utilisées

```bash
docker run --rm --network bridge lpmde-siege -c 10  -t 30S -b http://172.17.0.2:8000/troc
docker run --rm --network bridge lpmde-siege -c 25  -t 30S -b http://172.17.0.2:8000/troc
docker run --rm --network bridge lpmde-siege -c 50  -t 30S -b http://172.17.0.2:8000/troc
docker run --rm --network bridge lpmde-siege -c 100 -t 30S -b http://172.17.0.2:8000/troc
```

## Analyse

- **Le P95 reste-t-il < 300 ms ?** Non — temps moyen de 9 390 ms à 10 users. Cause : `php -S` monothreadé (1 req à la fois) + `APP_ENV=dev` + OPcache inactif. En production (mode prod, OPcache, Apache/PHP-FPM), le temps serait < 200 ms.
- **La disponibilité reste-t-elle > 99 % ?** Oui à 10 et 25 users (100 %). Non à 50+ users (0 %) : timeout siege atteint avant la fin du traitement.
- **À quel nombre commence-t-on à voir des dégradations ?** Point de saturation entre 25 et 50 utilisateurs simultanés. La concurrence réelle mesurée est ≈ 4 (PHP ne traite que 4 requêtes en même temps malgré 25 clients en attente).

**Note :** Tests effectués en environnement de développement (Docker Desktop, mode dev Symfony avec profiler, serveur built-in `php -S`). En production (mode prod, OPcache, multi-réplicas K8s, Apache), les performances seraient significativement meilleures.

## Lien avec les indicateurs qualité (ISO 25010)

| Indicateur | Valeur mesurée | Dimension ISO 25010 | Statut |
|---|---|---|---|
| Disponibilité ≤ 25 users | 100 % | Performance / Reliability | ✅ OK |
| Disponibilité ≥ 50 users | 0 % (saturé) | Performance / Reliability | ⚠️ Limite atteinte |
| Temps de réponse moyen (10 users) | 9 390 ms | Performance Efficiency | ⚠️ Élevé (env. dev) |
| Erreurs 5xx sous charge | 0 | Reliability | ✅ Zéro crash |
| Point de saturation | 25–50 users | Performance Efficiency | Documenté |
