<?php

namespace App\Tests\Unit\Message;

use App\Message\GhostAlert;
use PHPUnit\Framework\TestCase;

class GhostAlertTest extends TestCase
{
    public function testGetters(): void
    {
        $message = new GhostAlert('Cave à vin', 'Poltergeist');

        $this->assertSame('Cave à vin', $message->getLocation());
        $this->assertSame('Poltergeist', $message->getMonsterType());
    }

    public function testGettersWithDifferentValues(): void
    {
        $message = new GhostAlert('Grenier', 'Banshee');

        $this->assertSame('Grenier', $message->getLocation());
        $this->assertSame('Banshee', $message->getMonsterType());
    }

    public function testLocationIsString(): void
    {
        $message = new GhostAlert('Couloir', 'Zombie');

        $this->assertIsString($message->getLocation());
        $this->assertIsString($message->getMonsterType());
    }
}
