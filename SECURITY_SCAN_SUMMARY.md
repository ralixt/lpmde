# Résumé scans de sécurité

**Date :** 18/03/2026
**Outil :** Trivy (aquasec/trivy:latest)

## Trivy Filesystem (code applicatif)

- **Cible :** `src/`, `templates/`, `composer.lock`, `package.json`
- **Exclus :** `vendor/`, `node_modules/`
- **Résultat :** **0 vulnérabilité HIGH/CRITICAL**
- **Conclusion :** Code applicatif PHP et dépendances Composer/npm sûrs

## Trivy Image Docker (lpmde:local)

- **Image :** `lpmde:local` (Debian 13 Trixie + PHP 8.4 + Apache)
- **Commande :** `trivy image lpmde:local --severity HIGH,CRITICAL --scanners vuln`
- **Résultat :** **41 HIGH, 0 CRITICAL**

| Package | CVE | Sévérité | Fix disponible |
|---|---|---|---|
| linux-libc-dev | CVE-2025-* / CVE-2026-* | HIGH | Partiel (packages kernel) |
| libc-bin / libc6 | CVE-2026-0861 | HIGH | ✅ 2.41-12+deb13u2 |
| openssh-client | CVE-2026-3497 | HIGH | En cours |

**Aucune CVE** dans le code PHP, les dépendances Composer ou npm.
Toutes les 41 CVE sont sur les packages OS Debian (kernel, glibc, openssh).

## Remédiation proposée

| Priorité | Action | Impact estimé |
|---|---|---|
| Sprint 1 | `apt upgrade` dans le Dockerfile | Corrige les CVE fixées (libc6 → 2.41-12+deb13u2) |
| Sprint 2 | Passer à `php:8.4-fpm-alpine` | Élimine ~35/41 CVE (base Alpine minimaliste) |
| Sprint 3 | Trivy image dans la CI avec seuil CRITICAL | Bloque les déploiements si CVE critique détectée |

## PHPUnit

- **Total :** 52 tests, 105 assertions, **0 échec**
- **Suites :** unit (38), functional (7), e2e (7)
- **Note :** Couverture de code générée en CI (Xdebug) — non disponible en local PHP 8.5
