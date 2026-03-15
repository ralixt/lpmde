# PoC Kind : RabbitMQ + Keycloak + PostgreSQL

Ce guide décrit une expérimentation « bac à sable » pour valider l'infrastructure dans Kind et le flux asynchrone Symfony Messenger avec RabbitMQ.

## 0) Pré-requis

- Docker Desktop en cours d'exécution
- `kubectl` installé
- `kind` installé
- Dépendances Symfony installées (`composer install`)
- Un conteneur applicatif prêt si votre PHP Windows n'embarque pas l'extension `amqp`

Le workspace contient deja un socle Kind dans `k8s/kind/`. Le cluster observé pendant cette session est `kind-lpmde-sandbox` avec le namespace `lpmde-sandbox`.

## 1) Initialiser le cluster Kind

Depuis la racine du projet :

```powershell
kind create cluster --name lpmde-sandbox --config k8s/kind/kind-cluster-config.yaml
kubectl cluster-info --context kind-lpmde-sandbox
```

Si le cluster existe deja, ne le recreez pas. Repositionnez simplement le contexte :

```powershell
kubectl config use-context kind-lpmde-sandbox
kubectl get nodes
```

Vérifier les nœuds :

```powershell
kubectl get nodes
```

### Point de sauvegarde Git

```powershell
git add k8s/kind/kind-cluster-config.yaml
git commit -m "chore(k8s): ajouter la configuration Kind du bac à sable"
```

## 2) Déployer l'infrastructure dans Kind

1. Copier le secret exemple et adapter les valeurs.

```powershell
Copy-Item k8s/kind/secret.example.yaml k8s/kind/secret.yaml
notepad k8s/kind/secret.yaml
```

2. Appliquer les manifestes avec Kustomize.

```powershell
kubectl apply -k k8s/kind
```

3. Vérifier l'état.

```powershell
kubectl get pods -n lpmde-sandbox
kubectl get svc -n lpmde-sandbox
kubectl get pvc -n lpmde-sandbox
kubectl wait --for=condition=Available deployment/postgres -n lpmde-sandbox --timeout=180s
kubectl wait --for=condition=Available deployment/rabbitmq -n lpmde-sandbox --timeout=240s
kubectl wait --for=condition=Available deployment/keycloak -n lpmde-sandbox --timeout=360s
```

4. Vérifier les logs si un pod tarde a passer en `Running`.

```powershell
kubectl logs -n lpmde-sandbox deploy/postgres --tail=80
kubectl logs -n lpmde-sandbox deploy/rabbitmq --tail=80
kubectl logs -n lpmde-sandbox deploy/keycloak --tail=120
```

### Point de sauvegarde Git

```powershell
git add k8s/kind
git commit -m "feat(k8s): ajouter les manifestes kind pour postgres rabbitmq et keycloak"
```

## 3) Exposer localement RabbitMQ et Keycloak

Ouvrir 2 terminaux dédiés :

```powershell
kubectl port-forward -n lpmde-sandbox svc/rabbitmq 5672:5672 15672:15672
```

```powershell
kubectl port-forward -n lpmde-sandbox svc/keycloak 8080:8080
```

Accès local :

- RabbitMQ UI : http://127.0.0.1:15672
- Keycloak Admin : http://127.0.0.1:8080/admin

Vérification rapide :

```powershell
kubectl get svc -n lpmde-sandbox
```

Dans le cluster, les DNS internes a utiliser par les pods sont `postgres`, `rabbitmq` et `keycloak`.
Depuis ta machine Windows, il faut utiliser `127.0.0.1` avec le `port-forward`.

### Point de sauvegarde Git

```powershell
git add k8s/KIND_POC_GUIDE.md
git commit -m "docs(poc): documenter l exposition locale des services kind"
```

## 4) Configurer Symfony pour le test RabbitMQ

1. Préparer l'environnement local :

```powershell
Copy-Item .env.kind.example .env.local
```

2. Vérifier la variable transport :

```env
MESSENGER_TRANSPORT_DSN=amqp://guest:guest@127.0.0.1:5672/%2f/messages
```

3. Vérifier si votre PHP local supporte AMQP.

```powershell
php -m | findstr /I amqp
```

4. Si `amqp` est absent sur Windows, lancez les commandes Symfony dans le conteneur applicatif deja demarre.
Dans l'environnement observe ici, le conteneur web est `lpmde-web-kind` et il expose l'application sur http://127.0.0.1:8000.

Si vous venez de modifier le code Symfony ou la configuration Messenger, reconstruisez puis redemarrez d'abord le conteneur applicatif. Sinon la nouvelle commande de test et les ajustements de configuration ne seront pas visibles dans le conteneur deja en cours d'execution.

5. Démarrer le worker Symfony.

Option A, depuis l'hôte si `ext-amqp` est disponible :

```powershell
php bin/console messenger:consume async -vv --time-limit=300
```

Option B, depuis le conteneur applicatif si `ext-amqp` n'est pas disponible sur l'hôte :

```powershell
docker exec -it lpmde-web-kind sh -lc "cd /var/www/html && php bin/console messenger:consume async -vv --time-limit=300"
```

## 5) Protocole de test publication / consommation

1. Vérifier que le transport répond.

```powershell
docker exec -it lpmde-web-kind sh -lc "cd /var/www/html && php bin/console messenger:stats"
```

2. Publier un message de test en PHP avec la commande Symfony ajoutée pour le PoC.

Depuis l'hôte si `ext-amqp` est disponible :

```powershell
php bin/console app:dispatch:ghost-alert --location="Grenier" --monster="Banshee"
```

Depuis le conteneur applicatif sinon :

```powershell
docker exec -it lpmde-web-kind sh -lc "cd /var/www/html && php bin/console app:dispatch:ghost-alert --location='Grenier' --monster='Banshee'"
```

3. Vérifier la consommation.

- Le terminal `messenger:consume` doit afficher le traitement du `GhostAlert`.
- L'interface RabbitMQ doit montrer la queue `messages` et l'activité des messages.

4. Option de test complémentaire par HTTP si ton image web est correctement alignée avec son environnement :

```text
http://localhost:8000/test-rabbit
```

Cette route envoie 50 messages `GhostAlert` vers `async`.

5. Vérification fine.

```powershell
docker exec -it lpmde-web-kind sh -lc "cd /var/www/html && php bin/console messenger:stats"
docker exec -it lpmde-web-kind sh -lc "cd /var/www/html && php bin/console debug:messenger"
```

### Point de sauvegarde Git

```powershell
git add .env.kind.example config/packages/messenger.yaml src/Command/DispatchGhostAlertCommand.php k8s/KIND_POC_GUIDE.md
git commit -m "feat(poc): ajouter un protocole de test messenger pour kind"
```

## 6) Difficultés typiques rencontrées avec Kind

1. Exposition des services vers l'hôte

   - Les `Service` de type `ClusterIP` ne sont pas accessibles directement depuis Windows.
   - Solution PoC : `kubectl port-forward` pour RabbitMQ et Keycloak, ou `extraPortMappings` des la creation du cluster si on veut automatiser davantage.

2. DNS et résolution des services

   - Dans le cluster : accès via `postgres`, `rabbitmq`, `keycloak`.
   - Depuis l'hôte : il faut `127.0.0.1` plus `port-forward`, pas les noms DNS Kubernetes.

3. Démarrage dépendant de la base

   - Keycloak est sensible au timing de disponibilité PostgreSQL et RabbitMQ peut redemarrer si les probes sont trop agressives.
   - Vérifier probes + logs : `kubectl logs -n lpmde-sandbox deploy/keycloak -f` et `kubectl logs -n lpmde-sandbox deploy/rabbitmq -f`.

## 7) Limites de Kind vs cluster de production

- Cluster mono-machine, sans haute disponibilité réelle.
- Stockage et réseau simplifiés (pas de storage class managée cloud, pas de load balancer managé).
- Sécurité, observabilité et autoscaling généralement minimalistes dans un PoC.
- Performances non représentatives d'un cluster managé (EKS/AKS/GKE/OpenShift).

### Point de sauvegarde Git

```powershell
git add k8s/KIND_POC_GUIDE.md
git commit -m "docs(rapport): ajouter l analyse des limites et difficultes kind"
```

## 8) Nettoyage de l'expérimentation

```powershell
kind delete cluster --name lpmde-sandbox
```

### Point de sauvegarde Git final

```powershell
git add -A
git commit -m "chore(poc): finaliser la traçabilité et la documentation de l'expérimentation Kind"
```
