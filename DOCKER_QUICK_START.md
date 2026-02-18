# Démarrage rapide avec Docker

## 🚀 Démarrer tous les services

Pour démarrer RabbitMQ, Keycloak et PostgreSQL :

```bash
docker-compose -f docker-compose-keycloak.yml up -d
```

## 📊 Vérifier le statut

```bash
docker-compose -f docker-compose-keycloak.yml ps
```

## 🌐 Accès aux interfaces

- **Keycloak Admin** : http://localhost:8080/admin
  - Username : `admin`
  - Password : `admin`

- **RabbitMQ Management** : http://localhost:15672
  - Username : `guest`
  - Password : `guest`

## 🛑 Arrêter les services

```bash
docker-compose -f docker-compose-keycloak.yml down
```

## 🗑️ Supprimer tout (y compris les données)

```bash
docker-compose -f docker-compose-keycloak.yml down -v
```

## 📝 Commandes utiles

### Voir les logs en temps réel

```bash
# Tous les services
docker-compose -f docker-compose-keycloak.yml logs -f

# Keycloak uniquement
docker-compose -f docker-compose-keycloak.yml logs -f keycloak

# RabbitMQ uniquement
docker-compose -f docker-compose-keycloak.yml logs -f rabbitmq
```

### Redémarrer un service

```bash
docker-compose -f docker-compose-keycloak.yml restart keycloak
docker-compose -f docker-compose-keycloak.yml restart rabbitmq
```

## ⚙️ Configuration après démarrage

Une fois les services démarrés, suivez les instructions dans [KEYCLOAK_SETUP.md](KEYCLOAK_SETUP.md) pour :
1. Configurer le client OAuth dans Keycloak
2. Créer un utilisateur de test
3. Configurer les variables d'environnement Symfony

## 🔍 Vérification

Attendez que tous les services soient "healthy" :

```bash
docker-compose -f docker-compose-keycloak.yml ps
```

Vous devriez voir :
- ✅ `lpmde_rabbitmq` - healthy
- ✅ `lpmde_keycloak` - healthy  
- ✅ `lpmde_postgres` - healthy

Note : Keycloak peut prendre 1-2 minutes pour démarrer complètement.
