<?php

namespace App\Tests\Unit\Command;

use App\Command\DispatchGhostAlertCommand;
use App\Message\GhostAlert;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class DispatchGhostAlertCommandTest extends TestCase
{
    public function testExecuteWithDefaultOptions(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (GhostAlert $alert) {
                return $alert->getLocation() === 'Cave a vin'
                    && $alert->getMonsterType() === 'Poltergeist';
            }))
            ->willReturn(new Envelope(new GhostAlert('Cave a vin', 'Poltergeist')));

        $command = new DispatchGhostAlertCommand($bus);
        $tester  = new CommandTester($command);

        $exitCode = $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Poltergeist', $tester->getDisplay());
        $this->assertStringContainsString('Cave a vin', $tester->getDisplay());
    }

    public function testExecuteWithCustomOptions(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (GhostAlert $alert) {
                return $alert->getLocation() === 'Grenier'
                    && $alert->getMonsterType() === 'Banshee';
            }))
            ->willReturn(new Envelope(new GhostAlert('Grenier', 'Banshee')));

        $command = new DispatchGhostAlertCommand($bus);
        $tester  = new CommandTester($command);

        $exitCode = $tester->execute([
            '--location' => 'Grenier',
            '--monster'  => 'Banshee',
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Banshee', $tester->getDisplay());
        $this->assertStringContainsString('Grenier', $tester->getDisplay());
    }

    public function testCommandReturnsSuccess(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->method('dispatch')
            ->willReturn(new Envelope(new GhostAlert('x', 'y')));

        $command = new DispatchGhostAlertCommand($bus);
        $tester  = new CommandTester($command);

        $this->assertSame(Command::SUCCESS, $tester->execute([]));
    }
}
