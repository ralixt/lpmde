# Tests de charge — Espace Troc (Siege)

## Contexte

Tests de montée en charge sur l'endpoint public `/troc` (liste des annonces).
Application : La Petite Maison de l'Épouvante — Symfony 6.4
Infrastructure cible : conteneur Docker (port 8080), fixtures chargées (13 annonces).

## Prérequis

```bash
# L'application doit tourner avec les fixtures chargées
docker-compose up -d
php bin/console doctrine:fixtures:load --no-interaction

# Vérifier que l'app répond
curl -s -o /dev/null -w "HTTP %{http_code}" http://localhost:8080/troc
# → HTTP 200
```

## Commandes exécutées

```bash
siege -c 10  -t 30S http://localhost:8080/troc 2>&1 | tee siege_10users.txt
siege -c 25  -t 30S http://localhost:8080/troc 2>&1 | tee siege_25users.txt
siege -c 50  -t 30S http://localhost:8080/troc 2>&1 | tee siege_50users.txt
siege -c 100 -t 30S http://localhost:8080/troc 2>&1 | tee siege_100users.txt
```

## Résultats

| Utilisateurs | Transactions | Trans/sec | Temps moy. (ms) | Disponibilité | Longest (s) |
|:---:|---:|---:|---:|---:|---:|
| 10 | — | — | — | — % | — |
| 25 | — | — | — | — % | — |
| 50 | — | — | — | — % | — |
| 100 | — | — | — | — % | — |

> **Note :** Les résultats réels seront renseignés lors de l'exécution avec l'application déployée.
> Le test requiert Siege installé sur la machine hôte (`sudo apt install siege` ou `brew install siege`).

## Interprétation attendue

- **Disponibilité ≥ 99%** pour 10-25 utilisateurs simultanés → seuil acceptable en production
- **Trans/sec** : indicateur de la capacité de traitement (cible : > 10 req/s pour une app Symfony simple)
- **Temps moyen** : la page `/troc` effectue une requête SQL (findActiveAnnonces) — latence cible < 200ms
- **100 utilisateurs** : Symfony single-threaded (PHP-FPM requis pour la concurrence) — dégradation attendue

## Lien avec les indicateurs qualité (ISO 25010)

| Indicateur | Dimension ISO 25010 | Objectif |
|---|---|---|
| Disponibilité ≥ 99% (10 users) | Performance / Reliability | SLA minimal |
| Temps de réponse moyen < 200ms | Performance Efficiency | Expérience utilisateur |
| Absence d'erreurs 5xx | Reliability | Zéro crash sous charge normale |
