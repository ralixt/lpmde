<?php

namespace App\MessageHandler;

use App\Message\TrocCreatedNotification;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class TrocCreatedNotificationHandler
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function __invoke(TrocCreatedNotification $message): void
    {
        $this->logger->info('Nouvelle annonce troc créée via RabbitMQ', [
            'annonce_id' => $message->getAnnonceId(),
            'title' => $message->getTitle(),
            'category' => $message->getCategory(),
        ]);
    }
}
