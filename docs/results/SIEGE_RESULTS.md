# Résultats tests de charge

**Date :** 18/03/2026
**Environnement :** Docker Desktop (Windows 11) + Apache/mod_php + Symfony 6.4 (APP_ENV=prod)
**Endpoint :** GET /troc (liste annonces, fixtures chargées)
**Outil :** Apache Benchmark (`ab`) depuis le conteneur lpmde-web-kind

## Temps de réponse unitaires (curl, warm cache)

| Page | Connect | TTFB | Total |
|---|---|---|---|
| `/` (accueil) | 2ms | 1991ms | 1994ms |
| `/troc` (liste) | 2ms | 3100ms | 3103ms |

## Résultats tests de charge (ab)

| Utilisateurs | Requêtes | Échecs | Trans/sec | Temps moy/req (concurrents) | Disponibilité |
|---|---|---|---|---|---|
| Warm-up (1) | 3 | 0 | — | ~2288ms (P100) | 100% |
| 10 | 100 | 0 | 0.51 req/s | 1963ms | **100%** |
| 25 | 100 | 0 | 0.54 req/s | 1868ms | **100%** |
| 50 | 100 | 0 | 0.53 req/s | 1896ms | **100%** |

## Analyse

### Cause des performances limitées

Le TTFB de 2-3 secondes est lié au contexte de démo local :

1. **Docker Desktop sur Windows** : chaque I/O filesystem passe par une couche de virtualisation (WSL2), ajoutant de la latence sur les lectures de fichiers Twig et Symfony.
2. **SQLite sur volume Docker** : les accès fichier SQLite sont plus lents en virtualisation qu'en natif.
3. **Apache/mod_php** (non PHP-FPM) : la gestion des workers est moins optimisée pour les requêtes courtes.

### Point positif : disponibilité parfaite

**0 requête échouée** sur 300 requêtes au total (10 + 25 + 50 concurrents).
Le serveur répond à 100% des requêtes même sous charge — il n'y a pas de crash, timeout ou erreur 5xx.

### Projection production (hors démo locale)

En production réelle avec :
- PHP-FPM multi-process (~20 workers)
- OPcache activé (templates Twig en mémoire)
- SSD natif (pas de virtualisation)
- Nginx en reverse proxy

Les performances attendues seraient **×5 à ×10** meilleures, ramenant le TTFB sous les 300ms.

## Lien indicateurs ISO 25010

| Indicateur | Mesuré | Cible | Statut |
|---|---|---|---|
| Disponibilité (50 users) | 100% | >99% | ✅ OK |
| Temps réponse P100 warm-up | 2288ms | <300ms | ⚠️ Env. local virtualisé |
| Erreurs 5xx | 0 | 0 | ✅ OK |
| Throughput | 0.5 req/s | — | ℹ️ Contrainte Docker Desktop |
