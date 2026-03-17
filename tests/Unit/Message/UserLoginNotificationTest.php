<?php

namespace App\Tests\Unit\Message;

use App\Message\UserLoginNotification;
use PHPUnit\Framework\TestCase;

class UserLoginNotificationTest extends TestCase
{
    public function testUsernameGetter(): void
    {
        $notification = new UserLoginNotification('malphas666');

        $this->assertSame('malphas666', $notification->getUsername());
    }

    public function testLoginTimeIsSetAtConstruction(): void
    {
        $before = new \DateTimeImmutable();
        $notification = new UserLoginNotification('user@lpmde.fr');
        $after = new \DateTimeImmutable();

        $loginTime = $notification->getLoginTime();

        $this->assertInstanceOf(\DateTimeInterface::class, $loginTime);
        $this->assertGreaterThanOrEqual($before->getTimestamp(), $loginTime->getTimestamp());
        $this->assertLessThanOrEqual($after->getTimestamp(), $loginTime->getTimestamp());
    }

    public function testLoginTimeIsDifferentForTwoInstances(): void
    {
        $first = new UserLoginNotification('user1');
        usleep(1000);
        $second = new UserLoginNotification('user2');

        $this->assertLessThanOrEqual(
            $second->getLoginTime()->getTimestamp(),
            $first->getLoginTime()->getTimestamp()
        );
    }
}
