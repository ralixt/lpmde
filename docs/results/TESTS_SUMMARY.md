# Résumé Tests PHPUnit

**Date :** 18/03/2026
**Environnement :** PHP 8.5.0, PHPUnit 11.5.55
**Configuration :** phpunit.xml.dist

## Résultats par suite

| Suite | Tests | Assertions | Durée | Résultat |
|---|---|---|---|---|
| unit | 38 | 80 | 0.215s | ✅ OK |
| functional | 7 | 8 | 7.603s | ✅ OK |
| e2e | 7 | 17 | 7.545s | ✅ OK |
| **TOTAL** | **52** | **105** | **23s** | **✅ 0 échec** |

## Note

La couverture de code (coverage) n'est pas disponible en local (pas de driver Xdebug/PCOV installé sur PHP 8.5.0).
La couverture est générée dans le pipeline CI GitHub Actions (ubuntu-latest avec Xdebug activé via `shivammathur/setup-php`).

## Détail des suites

### Unit (38 tests)
- `UserTest` — Entité User (getters/setters, fullName)
- `TrocAnnonceTest` — Entité TrocAnnonce (CRUD champs, statuts)
- `KeycloakServiceTest` — Service OAuth (URLs, token exchange, userinfo)
- `GhostAlertHandlerTest`, `UserLoginNotificationHandlerTest` — Handlers RabbitMQ

### Functional (7 tests)
- `HomeControllerTest` — Page d'accueil accessible (HTTP 200)
- `TrocControllerTest` — Routes /troc, /troc/{id}, redirections auth

### E2E (7 tests)
- `TrocWorkflowTest` — Workflow complet : liste → détail → protection routes privées
- `ExampleE2ETest` — Smoke test naviguation générale
