<?php

namespace App\Tests\E2E;

use App\Entity\TrocAnnonce;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Test E2E : parcours complet de l'Espace Troc
 * Simule un utilisateur qui navigue, consulte les annonces et vérifie les accès.
 */
class TrocWorkflowTest extends WebTestCase
{
    private static ?int $annonceId = null;

    public static function setUpBeforeClass(): void
    {
        $kernel = self::bootKernel();
        $em = $kernel->getContainer()->get('doctrine')->getManager();

        $schemaTool = new SchemaTool($em);
        $classes = $em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($classes);
        $schemaTool->createSchema($classes);

        $user = new User();
        $user->setKeycloakId('e2e-keycloak-id');
        $user->setEmail('e2e@lpmde.fr');
        $user->setUsername('E2EUser');
        $em->persist($user);
        $em->flush();

        $annonce = new TrocAnnonce();
        $annonce->setTitle('Annonce E2E — Figurine Alien');
        $annonce->setDescription('Test E2E : figurine collector en très bon état.');
        $annonce->setCategory('Figurines');
        $annonce->setType('exchange');
        $annonce->setCondition('bon');
        $annonce->setOwner($user);
        $annonce->initDates();
        $em->persist($annonce);
        $em->flush();

        self::$annonceId = $annonce->getId();
        self::ensureKernelShutdown();
    }

    /**
     * E2E Scénario 1 : Un visiteur consulte les annonces publiques
     */
    public function testVisitorCanBrowseAnnonces(): void
    {
        $client = static::createClient();

        // Étape 1 : Accéder à la page d'accueil
        $client->request('GET', '/');
        $this->assertResponseIsSuccessful();

        // Étape 2 : Naviguer vers la liste des annonces
        $client->request('GET', '/troc');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('body');

        // Étape 3 : Filtrer par catégorie Figurines
        $client->request('GET', '/troc?category=Figurines');
        $this->assertResponseIsSuccessful();

        // Étape 4 : Filtrer par Films
        $client->request('GET', '/troc?category=Films');
        $this->assertResponseIsSuccessful();
    }

    /**
     * E2E Scénario 2 : Un visiteur non authentifié ne peut pas créer d'annonce
     */
    public function testVisitorCannotCreateAnnonce(): void
    {
        $client = static::createClient();
        $client->request('GET', '/troc/new');

        $statusCode = $client->getResponse()->getStatusCode();
        $this->assertTrue(
            in_array($statusCode, [302, 401, 403]),
            "Attendu une redirection ou un refus, reçu $statusCode"
        );
    }

    /**
     * E2E Scénario 3 : Consultation d'une annonce spécifique
     */
    public function testVisitorCanViewAnnonceDetail(): void
    {
        $client = static::createClient();
        $client->request('GET', '/troc/' . self::$annonceId);
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Annonce E2E');
    }

    /**
     * E2E Scénario 4 : Une annonce inexistante retourne 404
     */
    public function testNonExistentAnnonceReturns404(): void
    {
        $client = static::createClient();
        $client->request('GET', '/troc/99999');
        $this->assertResponseStatusCodeSame(404);
    }

    /**
     * E2E Scénario 5 : Le flux complet de navigation est cohérent
     */
    public function testFullNavigationFlow(): void
    {
        $client = static::createClient();

        // Accueil → Liste troc → Filtre → Détail → Retour liste
        $client->request('GET', '/');
        $this->assertResponseIsSuccessful();

        $client->request('GET', '/troc');
        $this->assertResponseIsSuccessful();

        $client->request('GET', '/troc?category=Jeux');
        $this->assertResponseIsSuccessful();

        $client->request('GET', '/troc/' . self::$annonceId);
        $this->assertResponseIsSuccessful();

        $client->request('GET', '/troc');
        $this->assertResponseIsSuccessful();
    }

    /**
     * E2E Scénario 6 : La page de connexion Keycloak est accessible
     */
    public function testLoginPageIsAccessible(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login/keycloak');
        $this->assertResponseIsSuccessful();
    }
}
