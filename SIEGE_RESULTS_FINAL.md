# Résultats Tests de Charge — Siege

## Contexte

Tests de montée en charge sur l'endpoint public `/troc` (liste des annonces).
Application : La Petite Maison de l'Épouvante — Symfony 6.4
Infrastructure cible : conteneur Docker (port 8080), fixtures chargées (13 annonces).

## Commandes utilisées

```bash
siege -c 10  -t 30S -b http://localhost:8080/troc 2>&1 | tee siege_10.txt
siege -c 25  -t 30S -b http://localhost:8080/troc 2>&1 | tee siege_25.txt
siege -c 50  -t 30S -b http://localhost:8080/troc 2>&1 | tee siege_50.txt
siege -c 100 -t 30S -b http://localhost:8080/troc 2>&1 | tee siege_100.txt
```

## Résultats

| Métrique | 10 users | 25 users | 50 users | 100 users |
|----------|----------|----------|----------|-----------|
| Transactions | — | — | — | — |
| Availability | — % | — % | — % | — % |
| Response time (avg) | — ms | — ms | — ms | — ms |
| Transaction rate | — req/s | — req/s | — req/s | — req/s |
| Longest transaction | — s | — s | — s | — s |
| Failed transactions | — | — | — | — |

> **Note :** Siege n'est pas disponible sur la machine de développement Windows.
> Pour exécuter les tests, utiliser WSL (`sudo apt-get install siege`) ou Docker :
>
> ```bash
> # Via WSL
> sudo apt-get install siege
> siege -c 10 -t 30S -b http://localhost:8080/troc
>
> # Via Docker
> docker run --rm --network host yokogawa/siege -c 10 -t 30S -b http://localhost:8080/troc
> ```

## Analyse

- **P95 estimé :** < 200ms (cible pour une app Symfony simple avec SQLite)
- **Seuil indicateur qualité (< 300ms) :** À vérifier lors de l'exécution
- **Point de saturation identifié :** Dégradation attendue à partir de 50-100 users (PHP single-threaded sans PHP-FPM)
- **Recommandations :** Activer PHP-FPM + OPcache pour la production (déjà configuré dans le Dockerfile)

## Lien avec les indicateurs qualité (ISO 25010)

| Indicateur | Dimension ISO 25010 | Objectif |
|---|---|---|
| Disponibilité ≥ 99% (10 users) | Performance / Reliability | SLA minimal |
| Temps de réponse moyen < 200ms | Performance Efficiency | Expérience utilisateur |
| Absence d'erreurs 5xx | Reliability | Zéro crash sous charge normale |
| Transaction rate > 10 req/s | Performance Efficiency | Capacité minimale en production |
