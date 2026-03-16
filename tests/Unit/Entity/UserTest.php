<?php

namespace App\Tests\Unit\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    public function testDefaultRolesContainRoleUser(): void
    {
        $user = new User();
        $this->assertContains('ROLE_USER', $user->getRoles());
    }

    public function testRolesAlwaysContainRoleUser(): void
    {
        $user = new User();
        $user->setRoles(['ROLE_ADMIN']);
        $roles = $user->getRoles();
        $this->assertContains('ROLE_USER', $roles);
        $this->assertContains('ROLE_ADMIN', $roles);
    }

    public function testKeycloakIdGetterSetter(): void
    {
        $user = new User();
        $user->setKeycloakId('abc-123-keycloak-uuid');
        $this->assertSame('abc-123-keycloak-uuid', $user->getKeycloakId());
    }

    public function testEmailGetterSetter(): void
    {
        $user = new User();
        $user->setEmail('test@lpmde.fr');
        $this->assertSame('test@lpmde.fr', $user->getEmail());
        $this->assertSame('test@lpmde.fr', $user->getUserIdentifier());
    }

    public function testGetFullNameWithFirstAndLastName(): void
    {
        $user = new User();
        $user->setUsername('malphas666');
        $user->setFirstName('Malphas');
        $user->setLastName('LaMort');
        $this->assertSame('Malphas LaMort', $user->getFullName());
    }

    public function testGetFullNameFallbackToUsername(): void
    {
        $user = new User();
        $user->setUsername('malphas666');
        $this->assertSame('malphas666', $user->getFullName());
    }

    public function testEraseCredentialsDoesNothing(): void
    {
        $user = new User();
        $user->setEmail('test@lpmde.fr');
        $user->eraseCredentials();
        $this->assertSame('test@lpmde.fr', $user->getEmail());
    }
}
