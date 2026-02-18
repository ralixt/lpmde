<?php

namespace App\Message;

class UserLoginNotification
{
    private string $username;
    private \DateTimeInterface $loginTime;

    public function __construct(string $username)
    {
        $this->username = $username;
        $this->loginTime = new \DateTimeImmutable();
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getLoginTime(): \DateTimeInterface
    {
        return $this->loginTime;
    }
}
