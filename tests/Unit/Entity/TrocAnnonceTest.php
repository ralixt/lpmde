<?php

namespace App\Tests\Unit\Entity;

use App\Entity\TrocAnnonce;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class TrocAnnonceTest extends TestCase
{
    public function testDefaultStatus(): void
    {
        $annonce = new TrocAnnonce();
        $this->assertSame('active', $annonce->getStatus());
    }

    public function testDefaultType(): void
    {
        $annonce = new TrocAnnonce();
        $this->assertSame('exchange', $annonce->getType());
    }

    public function testGettersSetters(): void
    {
        $annonce = new TrocAnnonce();

        $annonce->setTitle('Figurine Cthulhu');
        $this->assertSame('Figurine Cthulhu', $annonce->getTitle());

        $annonce->setDescription('Superbe figurine');
        $this->assertSame('Superbe figurine', $annonce->getDescription());

        $annonce->setCategory('Figurines');
        $this->assertSame('Figurines', $annonce->getCategory());

        $annonce->setType('gift');
        $this->assertSame('gift', $annonce->getType());

        $annonce->setCondition('neuf');
        $this->assertSame('neuf', $annonce->getCondition());

        $annonce->setStatus('closed');
        $this->assertSame('closed', $annonce->getStatus());

        $annonce->setImageUrl('https://example.com/image.jpg');
        $this->assertSame('https://example.com/image.jpg', $annonce->getImageUrl());
    }

    public function testOwnerAssociation(): void
    {
        $annonce = new TrocAnnonce();
        $user = new User();

        $annonce->setOwner($user);
        $this->assertSame($user, $annonce->getOwner());
    }

    public function testCreatedAtAutoSet(): void
    {
        $annonce = new TrocAnnonce();
        $this->assertNull($annonce->getCreatedAt());

        $annonce->initDates();
        $this->assertInstanceOf(\DateTimeImmutable::class, $annonce->getCreatedAt());
    }

    public function testUpdatedAtNullByDefault(): void
    {
        $annonce = new TrocAnnonce();
        $this->assertNull($annonce->getUpdatedAt());
    }

    public function testImageUrlNullable(): void
    {
        $annonce = new TrocAnnonce();
        $annonce->setImageUrl(null);
        $this->assertNull($annonce->getImageUrl());
    }
}
