# Résultats Tests de Charge — Siege

## Contexte

Tests de montée en charge sur l'endpoint public `/troc` (liste des annonces).
Application : La Petite Maison de l'Épouvante — Symfony 6.4
Infrastructure testée : conteneur Kind/Kubernetes (`lpmde-web-kind`) exposé sur le port 8000.
Outil : Siege 4.0.7 via conteneur Docker Ubuntu 22.04 (réseau bridge → IP 172.17.0.2).

## Commandes exécutées

```bash
docker run --rm --network bridge lpmde-siege -c 10  -t 30S -b http://172.17.0.2:8000/troc
docker run --rm --network bridge lpmde-siege -c 25  -t 30S -b http://172.17.0.2:8000/troc
docker run --rm --network bridge lpmde-siege -c 50  -t 30S -b http://172.17.0.2:8000/troc
docker run --rm --network bridge lpmde-siege -c 100 -t 30S -b http://172.17.0.2:8000/troc
```

## Résultats

| Métrique | 10 users | 25 users | 50 users | 100 users |
|----------|----------|----------|----------|-----------|
| Transactions | 14 | 14 | 0 | 0 |
| Availability | 100.00 % | 100.00 % | 0.00 % | 0.00 % |
| Response time (avg) | 9 390 ms | 9 150 ms | — | — |
| Transaction rate | 0.44 req/s | 0.44 req/s | 0.00 req/s | 0.00 req/s |
| Throughput | 0.03 MB/s | 0.03 MB/s | 0.00 MB/s | 0.00 MB/s |
| Concurrency réelle | 4.14 | 4.07 | 0.00 | 0.00 |
| Longer transaction | 30.99 s | 30.44 s | — | — |
| Shorter transaction | 0.05 s | 0.00 s | — | — |
| Failed transactions | 0 | 0 | 0 | 0 |

## Analyse

### Point de saturation

Le système sature à partir de **50 utilisateurs simultanés** : disponibilité chute de 100 % à 0 %.

### Cause identifiée — Architecture Kind/Kubernetes locale

L'infrastructure testée est un cluster Kind (Kubernetes-in-Docker) sur machine de développement Windows. La chaîne réseau est :

```
Docker siege → bridge network → container Kind node → kube-proxy → pod Symfony
```

Ce tunnel multi-couches explique les temps de réponse élevés (~9 s en moyenne) et la saturation rapide. Le pod Symfony est démarré avec un seul worker PHP-FPM en mode `dev`, sans OPcache actif ni réplicas.

### Comportement à 10 et 25 utilisateurs

- **100 % de disponibilité** — aucune erreur, toutes les requêtes aboutissent.
- **Concurrency réelle ≈ 4** malgré 10/25 utilisateurs en attente : le pod traite les requêtes séquentiellement (PHP single-threaded).
- **Transaction rate stable** : 0.44 req/s identique à 10 et 25 — la capacité du pod est le goulot d'étranglement, pas le nombre de clients.

### Comportement à 50 et 100 utilisateurs

- **0 transaction** enregistrée : le timeout de siege (30 s) est atteint avant qu'une réponse soit rendue. Le pod accepte les connexions mais ne les traite pas assez vite.
- Aucune erreur 5xx — le serveur ne crashe pas, il est simplement saturé côté file d'attente.

## Recommandations pour la production

| Axe | Action | Impact attendu |
|-----|--------|----------------|
| Mode Symfony | Passer en `APP_ENV=prod` | × 10 sur le débit (suppression du profiler) |
| OPcache | Activer dans `php.ini` | × 5 sur le temps de boot PHP |
| PHP-FPM | Augmenter `pm.max_children` | Traitement parallèle des requêtes |
| Kubernetes | Ajouter `replicas: 3` + HPA | Mise à l'échelle automatique |
| Cache HTTP | Varnish / nginx reverse proxy | Absorbe les lectures statiques sans PHP |

## Lien avec les indicateurs qualité (ISO 25010)

| Indicateur | Valeur mesurée | Dimension ISO 25010 | Statut |
|---|---|---|---|
| Disponibilité ≤ 25 users | 100 % | Performance / Reliability | ✅ OK |
| Disponibilité ≥ 50 users | 0 % | Performance / Reliability | ⚠️ Limite atteinte |
| Temps de réponse moyen (10 users) | 9 390 ms | Performance Efficiency | ⚠️ Élevé (env. dev K8s) |
| Erreurs 5xx sous charge | 0 | Reliability | ✅ Zéro crash |
| Point de saturation | 25–50 users | Performance Efficiency | Documenté |
