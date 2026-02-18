# Configuration Keycloak + RabbitMQ

## 🔐 Configuration de l'authentification Keycloak

Cette application utilise Keycloak pour l'authentification OAuth 2.0 / OpenID Connect et RabbitMQ pour la notification asynchrone lors de la connexion.

---

## 📋 Prérequis

- **Keycloak** installé et en cours d'exécution (version 20+)
- **RabbitMQ** installé et en cours d'exécution
- **PHP 8.1+**
- **Symfony 6.4**
- **Composer**

---

## 🚀 Installation de Keycloak

### Option 1 : Docker (Recommandé)

```bash
docker run -d \
  --name keycloak \
  -p 8080:8080 \
  -e KEYCLOAK_ADMIN=admin \
  -e KEYCLOAK_ADMIN_PASSWORD=admin \
  quay.io/keycloak/keycloak:latest \
  start-dev
```

### Option 2 : Installation manuelle

Téléchargez Keycloak depuis [keycloak.org](https://www.keycloak.org/downloads) et lancez :

```bash
cd keycloak-xx.x.x/bin
./kc.sh start-dev
```

Accédez à l'interface admin : `http://localhost:8080`

---

## ⚙️ Configuration de Keycloak

### 1. Créer un Realm (Optionnel)

- Connectez-vous à l'admin console : `http://localhost:8080/admin`
- Username : `admin`, Password : `admin`
- Cliquez sur "Create Realm"
- Nom : `symfony-app` (ou utilisez `master`)

### 2. Créer un Client OAuth

1. Dans votre realm, allez dans **Clients** > **Create Client**
2. Remplissez les informations :
   - **Client type** : `OpenID Connect`
   - **Client ID** : `symfony-app`
   - Cliquez sur **Next**

3. **Capability config** :
   - ✅ Client authentication : **ON**
   - ✅ Authorization : **OFF**
   - ✅ Standard flow : **ON** (Authorization Code)
   - ✅ Direct access grants : **ON** (facultatif)
   - Cliquez sur **Next**

4. **Login settings** :
   - **Valid redirect URIs** : `http://localhost:8000/login/keycloak/callback`
   - **Valid post logout redirect URIs** : `http://localhost:8000/`
   - **Web origins** : `http://localhost:8000`
   - Cliquez sur **Save**

5. Allez dans l'onglet **Credentials**
   - Copiez le **Client Secret**

### 3. Créer un utilisateur de test

1. Allez dans **Users** > **Add user**
2. Remplissez :
   - **Username** : `testuser`
   - **Email** : `test@example.com`
   - **First name** : `Test`
   - **Last name** : `User`
   - ✅ **Email verified** : ON
3. Cliquez sur **Create**
4. Allez dans l'onglet **Credentials**
5. Cliquez sur **Set password**
   - Password : `password`
   - ✅ **Temporary** : OFF
6. Cliquez sur **Save**

---

## 🔧 Configuration de Symfony

### 1. Mettre à jour le fichier `.env`

Modifiez les variables dans votre fichier `.env` :

```env
###> keycloak configuration ###
KEYCLOAK_URL=http://localhost:8080
KEYCLOAK_REALM=master
KEYCLOAK_CLIENT_ID=symfony-app
KEYCLOAK_CLIENT_SECRET=YOUR_CLIENT_SECRET_HERE
KEYCLOAK_REDIRECT_URI=http://localhost:8000/login/keycloak/callback
###< keycloak configuration ###
```

**Important** : Remplacez `YOUR_CLIENT_SECRET_HERE` par la valeur copiée depuis Keycloak.

### 2. Créer la base de données

```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
```

---

## 🐰 Configuration de RabbitMQ

### Installation avec Docker

```bash
docker run -d \
  --name rabbitmq \
  -p 5672:5672 \
  -p 15672:15672 \
  rabbitmq:3-management
```

Interface de gestion : `http://localhost:15672` (guest/guest)

### Démarrer le worker Symfony

```bash
php bin/console messenger:consume async -vv
```

---

## 🎮 Utilisation

### 1. Démarrer le serveur Symfony

```bash
symfony serve
```

Ou :

```bash
php -S localhost:8000 -t public
```

### 2. Tester l'authentification

1. Visitez : `http://localhost:8000/login/keycloak`
2. Cliquez sur "Se connecter avec Keycloak"
3. Connectez-vous avec : `testuser` / `password`
4. Vous serez redirigé vers la page d'accueil avec une notification
5. Une notification RabbitMQ sera envoyée (visible dans le worker)

### 3. Pages disponibles

- **Accueil** : `http://localhost:8000/`
- **Connexion Keycloak** : `http://localhost:8000/login/keycloak`
- **Profil** : `http://localhost:8000/profile` (nécessite connexion)
- **Déconnexion** : `http://localhost:8000/logout`

---

## 🔍 Vérification

### Vérifier que tout fonctionne :

1. **Keycloak** : `http://localhost:8080` doit être accessible
2. **RabbitMQ** : `http://localhost:15672` doit afficher l'interface
3. **Worker Symfony** : Le terminal doit afficher `[OK] Consuming messages from transport "async"`
4. **Symfony** : `http://localhost:8000` doit afficher votre site

---

## 📊 Architecture

```
┌─────────────┐
│   Browser   │
└─────┬───────┘
      │ 1. Click Login
      ↓
┌─────────────┐
│   Symfony   │
└─────┬───────┘
      │ 2. Redirect to Keycloak
      ↓
┌─────────────┐
│  Keycloak   │ ← User authenticates
└─────┬───────┘
      │ 3. Return code
      ↓
┌─────────────┐
│   Symfony   │
│             │ 4. Exchange code for token
│             │ 5. Get user info
│             │ 6. Save user to DB
│             │ 7. Dispatch to RabbitMQ
└─────┬───────┘
      │
      ↓
┌─────────────┐
│  RabbitMQ   │
└─────┬───────┘
      │
      ↓
┌─────────────┐
│   Worker    │ ← Process notification
└─────────────┘
```

---

## 🐛 Dépannage

### Erreur "Invalid redirect_uri"
- Vérifiez que l'URL dans Keycloak correspond exactement à celle dans `.env`
- Vérifiez le protocole (http vs https)

### Erreur "Client secret not provided"
- Assurez-vous d'avoir copié le client secret de Keycloak dans `.env`
- Vérifiez que "Client authentication" est **ON** dans Keycloak

### Le worker ne traite pas les messages
- Vérifiez que RabbitMQ est démarré : `docker ps` ou `service rabbitmq-server status`
- Vérifiez la connexion dans `.env` : `MESSENGER_TRANSPORT_DSN`
- Redémarrez le worker : `php bin/console messenger:consume async -vv`

### L'utilisateur n'est pas créé en base de données
- Vérifiez que les migrations sont exécutées : `php bin/console doctrine:migrations:status`
- Vérifiez les logs : `tail -f var/log/dev.log`

---

## 📝 Notes importantes

1. **Production** : Ne jamais commiter le `.env` avec les vrais secrets
2. **HTTPS** : En production, utilisez HTTPS pour tous les endpoints
3. **Keycloak** : Configurez correctement les CORS et les origines valides
4. **RabbitMQ** : Utilisez des credentials sécurisés en production
5. **Sessions** : Cette implémentation utilise les sessions PHP (à améliorer avec le système de sécurité Symfony complet si nécessaire)

---

## 🚀 Améliorations possibles

- [ ] Implémenter le refresh token
- [ ] Ajouter la déconnexion Keycloak (SSO logout)
- [ ] Créer un authenticator Symfony personnalisé
- [ ] Ajouter des rôles Keycloak
- [ ] Gérer les groupes Keycloak
- [ ] Implémenter le remember me
- [ ] Ajouter la gestion des erreurs OAuth

---

## 📚 Ressources

- [Documentation Keycloak](https://www.keycloak.org/documentation)
- [Symfony Security](https://symfony.com/doc/current/security.html)
- [Symfony Messenger](https://symfony.com/doc/current/messenger.html)
- [OAuth 2.0 RFC](https://tools.ietf.org/html/rfc6749)
- [OpenID Connect](https://openid.net/connect/)

---

## ✅ Checklist de déploiement

- [ ] Keycloak configuré et accessible
- [ ] Client OAuth créé dans Keycloak
- [ ] Utilisateur de test créé
- [ ] Variables `.env` configurées
- [ ] Base de données migrée
- [ ] RabbitMQ démarré
- [ ] Worker Symfony démarré
- [ ] Serveur Symfony démarré
- [ ] Test de connexion réussi
- [ ] Notification RabbitMQ reçue

---

🎉 **Vous êtes prêt !** Connectez-vous et profitez de votre authentification Keycloak avec notifications RabbitMQ !
