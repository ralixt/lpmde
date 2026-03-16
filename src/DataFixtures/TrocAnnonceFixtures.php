<?php

namespace App\DataFixtures;

use App\Entity\TrocAnnonce;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class TrocAnnonceFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // Récupérer ou créer un utilisateur de test
        $userRepo = $manager->getRepository(User::class);
        $user = $userRepo->findOneBy([]);

        if (!$user) {
            $user = new User();
            $user->setKeycloakId('fixture-user-keycloak-id-001');
            $user->setEmail('testuser@lpmde.fr');
            $user->setUsername('Malphas_666');
            $user->setFirstName('Malphas');
            $user->setLastName('LaMort');
            $manager->persist($user);
            $manager->flush();
        }

        $annonces = [
            [
                'title' => 'Figurine Cthulhu édition limitée',
                'description' => "Superbe figurine Cthulhu de la série \"Elder Gods\" édition limitée 2022. Résine peinte à la main, environ 25cm de hauteur. Quelques micro-imperfections sur le socle, rien de visible. Je recherche en échange des figurines Lovecraft ou Dagon de même gamme.",
                'category' => 'Figurines',
                'type' => 'exchange',
                'condition' => 'très bon',
            ],
            [
                'title' => 'Coffret Blu-ray Dario Argento — Giallo Collection',
                'description' => "Coffret collector Dario Argento incluant : Suspiria (1977), Ténèbres (1982), Inferno (1980) et Opera (1987). Édition Arrow Video remasterisée. Livrets inclus. Boîtier légèrement rayé mais disques impeccables.",
                'category' => 'Films',
                'type' => 'exchange',
                'condition' => 'neuf',
            ],
            [
                'title' => 'Jeu de plateau Mysterium',
                'description' => "Mysterium en très bon état. Toutes les cartes présentes (vérifié). Le fantôme doit communiquer à travers des visions onirique... Je le donne car j'ai eu l'extension. Idéal pour 2-7 joueurs.",
                'category' => 'Jeux',
                'type' => 'gift',
                'condition' => 'bon',
            ],
            [
                'title' => 'Collection fanzines LPMdE n°1 à 8',
                'description' => "Collection complète des 8 premiers numéros du fanzine La Petite Maison de l'Épouvante. Contient des illustrations, nouvelles et interviews exclusifs. Légèrement jaunis mais tous lisibles. Je cherche les numéros 9 à 12 en échange.",
                'category' => 'Fanzines',
                'type' => 'exchange',
                'condition' => 'correct',
            ],
            [
                'title' => 'BD Hellboy intégrale T.1 à T.6',
                'description' => "Intégrale Hellboy de Mike Mignola, tomes 1 à 6. Édition française Delcourt. En excellent état, lus une seule fois. Véritable chef-d'œuvre du fantastique — le Big Red à son meilleur. Don pour un vrai passionné.",
                'category' => 'BD & Livres',
                'type' => 'gift',
                'condition' => 'très bon',
            ],
            [
                'title' => 'Masque Jason Voorhees réplique officielle',
                'description' => "Réplique officielle NECA du masque de Jason Voorhees (Friday the 13th Part III). Taille réelle, résine robuste, sangles réglables. Jamais porté, juste exhibé. Parfait pour cosplay ou décoration murale.",
                'category' => 'Figurines',
                'type' => 'exchange',
                'condition' => 'neuf',
            ],
            [
                'title' => 'DVD L\'Exorciste director\'s cut + The Version You\'ve Never Seen',
                'description' => "Double DVD collector de L'Exorciste avec les deux versions (director's cut 2000 + extended 2000). Coffret Warner Bros avec making-of complet. Incontournable du cinéma d'horreur. Don à qui peut me fournir le Blu-ray en échange.",
                'category' => 'Films',
                'type' => 'gift',
                'condition' => 'bon',
            ],
            [
                'title' => 'Jeu Betrayal at House on the Hill (2e édition)',
                'description' => "Betrayal at House on the Hill, 2e édition (Wizards of the Coast). Complet avec tous les tuiles, personnages et le livret des 50 scénarios de terreur. Une partie du plastique est légèrement déformé mais n'affecte pas le jeu.",
                'category' => 'Jeux',
                'type' => 'exchange',
                'condition' => 'très bon',
            ],
            [
                'title' => 'Roman Stephen King — It (édition originale 1987)',
                'description' => "\"Ça\" de Stephen King, édition originale française 1987 (Albin Michel). Couverture rigide, 1356 pages. Le livre a vécu mais reste parfaitement lisible. Petites annotations crayon aux 50 premières pages.",
                'category' => 'BD & Livres',
                'type' => 'exchange',
                'condition' => 'correct',
            ],
            [
                'title' => 'Statuette Pennywise — It Chapter Two',
                'description' => "Statuette officielle Pennywise de \"It Chapitre 2\" (Mezco Toyz). 15cm, peinte en usine. Contient les ballons rouges en accessoire. Boîte d'origine conservée. Recherche échange contre figurine Clown ou horreur similaire.",
                'category' => 'Figurines',
                'type' => 'exchange',
                'condition' => 'neuf',
            ],
            [
                'title' => 'Blu-ray The Thing (Carpenter, 1982) — Édition collector',
                'description' => "\"The Thing\" de John Carpenter en Blu-ray, édition collector Universal. Inclut le documentaire \"Terror Takes Shape\" et les commentaires audio de Carpenter. Disque impeccable, boîtier légèrement abîmé sur un coin.",
                'category' => 'Films',
                'type' => 'exchange',
                'condition' => 'bon',
            ],
            [
                'title' => 'Fanzine Sang & Encre — 15 numéros',
                'description' => "15 numéros du fanzine indépendant Sang & Encre (2015-2020). Entièrement consacré au cinéma de genre français. Illustrations originales, interviews de réalisateurs underground. Rare et difficile à trouver.",
                'category' => 'Fanzines',
                'type' => 'exchange',
                'condition' => 'très bon',
            ],
            [
                'title' => 'Jeu de cartes Arkham Horror LCG — Core Set',
                'description' => "Arkham Horror: The Card Game (Fantasy Flight Games) — boîte de base. Toutes les cartes en pochettes protectrices, jamais utilisées en tournoi. Idéal pour débuter dans l'univers Lovecraftien coopératif.",
                'category' => 'Jeux',
                'type' => 'gift',
                'condition' => 'neuf',
            ],
        ];

        foreach ($annonces as $data) {
            $annonce = new TrocAnnonce();
            $annonce->setTitle($data['title']);
            $annonce->setDescription($data['description']);
            $annonce->setCategory($data['category']);
            $annonce->setType($data['type']);
            $annonce->setCondition($data['condition']);
            $annonce->setStatus('active');
            $annonce->setOwner($user);
            $annonce->initDates();

            $manager->persist($annonce);
        }

        $manager->flush();
    }
}
