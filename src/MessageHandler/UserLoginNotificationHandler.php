<?php

namespace App\MessageHandler;

use App\Message\UserLoginNotification;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class UserLoginNotificationHandler
{
    public function __invoke(UserLoginNotification $notification)
    {
        // Traitement du message de connexion
        // On simule un traitement (par exemple : log, email, etc.)
        sleep(2);

        echo sprintf(
            "✅ NOTIFICATION TRAITÉE : L'utilisateur '%s' s'est connecté à %s\n",
            $notification->getUsername(),
            $notification->getLoginTime()->format('H:i:s')
        );

        // Ici vous pourriez :
        // - Enregistrer dans une base de données
        // - Envoyer un email
        // - Notifier d'autres services
    }
}
