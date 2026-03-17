<?php

namespace App\Tests\Unit\MessageHandler;

use App\Message\TrocCreatedNotification;
use App\MessageHandler\TrocCreatedNotificationHandler;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class TrocCreatedNotificationHandlerTest extends TestCase
{
    public function testInvokeLogsInfo(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with(
                'Nouvelle annonce troc créée via RabbitMQ',
                [
                    'annonce_id' => 42,
                    'title'      => 'Figurine Cthulhu',
                    'category'   => 'Figurines',
                ]
            );

        $handler = new TrocCreatedNotificationHandler($logger);
        $message = new TrocCreatedNotification(42, 'Figurine Cthulhu', 'Figurines');

        $handler($message);
    }

    public function testInvokeWithDifferentMessage(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with(
                'Nouvelle annonce troc créée via RabbitMQ',
                [
                    'annonce_id' => 7,
                    'title'      => 'Blu-ray The Thing',
                    'category'   => 'Films',
                ]
            );

        $handler = new TrocCreatedNotificationHandler($logger);
        $message = new TrocCreatedNotification(7, 'Blu-ray The Thing', 'Films');

        $handler($message);
    }
}
