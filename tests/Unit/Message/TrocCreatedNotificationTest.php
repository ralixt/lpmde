<?php

namespace App\Tests\Unit\Message;

use App\Message\TrocCreatedNotification;
use PHPUnit\Framework\TestCase;

class TrocCreatedNotificationTest extends TestCase
{
    public function testGetters(): void
    {
        $message = new TrocCreatedNotification(42, 'Figurine Cthulhu', 'Figurines');

        $this->assertSame(42, $message->getAnnonceId());
        $this->assertSame('Figurine Cthulhu', $message->getTitle());
        $this->assertSame('Figurines', $message->getCategory());
    }

    public function testGettersWithDifferentValues(): void
    {
        $message = new TrocCreatedNotification(1, 'Blu-ray The Thing', 'Films');

        $this->assertSame(1, $message->getAnnonceId());
        $this->assertSame('Blu-ray The Thing', $message->getTitle());
        $this->assertSame('Films', $message->getCategory());
    }
}
