<?php

namespace App\Tests\Functional\Controller;

use App\Entity\TrocAnnonce;
use App\Entity\User;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class TrocControllerTest extends WebTestCase
{
    private static ?int $annonceId = null;

    public static function setUpBeforeClass(): void
    {
        // Créer le schéma de la base de données de test et insérer des données
        $kernel = self::bootKernel();
        $em = $kernel->getContainer()->get('doctrine')->getManager();

        $schemaTool = new SchemaTool($em);
        $classes = $em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($classes);
        $schemaTool->createSchema($classes);

        // Créer un utilisateur de test
        $user = new User();
        $user->setKeycloakId('test-keycloak-id-functional');
        $user->setEmail('functional@lpmde.fr');
        $user->setUsername('FunctionalUser');
        $em->persist($user);
        $em->flush();

        // Créer une annonce de test
        $annonce = new TrocAnnonce();
        $annonce->setTitle('Annonce de test fonctionnel');
        $annonce->setDescription('Description de test pour les tests fonctionnels.');
        $annonce->setCategory('Films');
        $annonce->setType('exchange');
        $annonce->setCondition('bon');
        $annonce->setOwner($user);
        $annonce->initDates();
        $em->persist($annonce);
        $em->flush();

        self::$annonceId = $annonce->getId();
        self::ensureKernelShutdown();
    }

    public function testListingPageReturns200(): void
    {
        $client = static::createClient();
        $client->request('GET', '/troc');
        $this->assertResponseStatusCodeSame(200);
    }

    public function testListingWithCategoryFilter(): void
    {
        $client = static::createClient();
        $client->request('GET', '/troc?category=Films');
        $this->assertResponseStatusCodeSame(200);
    }

    public function testShowExistingAnnonce(): void
    {
        $client = static::createClient();
        $client->request('GET', '/troc/' . self::$annonceId);
        $this->assertResponseStatusCodeSame(200);
    }

    public function testShowNonExistentAnnonceReturns404(): void
    {
        $client = static::createClient();
        $client->request('GET', '/troc/99999');
        $this->assertResponseStatusCodeSame(404);
    }

    public function testNewAnnonceRequiresAuth(): void
    {
        $client = static::createClient();
        $client->request('GET', '/troc/new');
        // Sans session : redirige vers la page de login Keycloak
        $this->assertResponseStatusCodeSame(302);
    }
}
