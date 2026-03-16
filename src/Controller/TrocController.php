<?php

namespace App\Controller;

use App\Entity\TrocAnnonce;
use App\Entity\User;
use App\Form\TrocAnnonceType;
use App\Message\TrocCreatedNotification;
use App\Repository\TrocAnnonceRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/troc')]
class TrocController extends AbstractController
{
    #[Route('', name: 'troc_index', methods: ['GET'])]
    public function index(Request $request, TrocAnnonceRepository $repo): Response
    {
        $category = $request->query->get('category');

        if ($category) {
            $annonces = $repo->findActiveByCategory($category);
        } else {
            $annonces = $repo->findActiveAnnonces();
        }

        $categories = ['Figurines', 'Films', 'Jeux', 'BD & Livres', 'Fanzines'];

        return $this->render('troc/index.html.twig', [
            'annonces' => $annonces,
            'categories' => $categories,
            'currentCategory' => $category,
        ]);
    }

    #[Route('/mes-annonces', name: 'troc_mes_annonces', methods: ['GET'])]
    public function mesAnnonces(Request $request, TrocAnnonceRepository $repo, UserRepository $userRepo): Response
    {
        $session = $request->getSession();

        if (!$session->get('is_authenticated')) {
            $this->addFlash('warning', 'Vous devez être connecté pour accéder à vos annonces.');
            return $this->redirectToRoute('app_login_keycloak');
        }

        $userId = $session->get('user_id');
        $user = $userRepo->find($userId);

        $annonces = $user ? $repo->findBy(['owner' => $user], ['createdAt' => 'DESC']) : [];

        return $this->render('troc/mes_annonces.html.twig', [
            'annonces' => $annonces,
        ]);
    }

    #[Route('/new', name: 'troc_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        MessageBusInterface $bus,
        UserRepository $userRepo,
        LoggerInterface $logger,
    ): Response {
        $session = $request->getSession();

        if (!$session->get('is_authenticated')) {
            $this->addFlash('warning', 'Vous devez être connecté pour publier une annonce.');
            return $this->redirectToRoute('app_login_keycloak');
        }

        $annonce = new TrocAnnonce();
        $form = $this->createForm(TrocAnnonceType::class, $annonce);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $userId = $session->get('user_id');
            $user = $userRepo->find($userId);

            if (!$user) {
                $this->addFlash('error', 'Utilisateur introuvable.');
                return $this->redirectToRoute('troc_index');
            }

            $annonce->setOwner($user);
            $em->persist($annonce);
            $em->flush();

            $logger->info('Annonce troc créée', [
                'annonce_id' => $annonce->getId(),
                'user' => $user->getKeycloakId(),
                'category' => $annonce->getCategory(),
                'type' => $annonce->getType(),
            ]);

            $bus->dispatch(new TrocCreatedNotification(
                $annonce->getId(),
                $annonce->getTitle(),
                $annonce->getCategory()
            ));

            $this->addFlash('success', 'Votre annonce a été publiée dans l\'Espace Troc !');
            return $this->redirectToRoute('troc_index');
        }

        return $this->render('troc/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'troc_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id, TrocAnnonceRepository $repo): Response
    {
        $annonce = $repo->find($id);

        if (!$annonce || $annonce->getStatus() !== 'active') {
            throw $this->createNotFoundException('Annonce introuvable.');
        }

        return $this->render('troc/show.html.twig', [
            'annonce' => $annonce,
        ]);
    }

    #[Route('/{id}/edit', name: 'troc_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(int $id, Request $request, TrocAnnonceRepository $repo, EntityManagerInterface $em, UserRepository $userRepo): Response
    {
        $session = $request->getSession();

        if (!$session->get('is_authenticated')) {
            $this->addFlash('warning', 'Vous devez être connecté pour modifier une annonce.');
            return $this->redirectToRoute('app_login_keycloak');
        }

        $annonce = $repo->find($id);

        if (!$annonce) {
            throw $this->createNotFoundException('Annonce introuvable.');
        }

        $userId = $session->get('user_id');
        if ($annonce->getOwner()?->getId() !== $userId) {
            $this->addFlash('error', 'Vous n\'êtes pas autorisé à modifier cette annonce.');
            return $this->redirectToRoute('troc_index');
        }

        $form = $this->createForm(TrocAnnonceType::class, $annonce);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Annonce mise à jour avec succès.');
            return $this->redirectToRoute('troc_show', ['id' => $annonce->getId()]);
        }

        return $this->render('troc/edit.html.twig', [
            'annonce' => $annonce,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'troc_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(int $id, Request $request, TrocAnnonceRepository $repo, EntityManagerInterface $em, UserRepository $userRepo): Response
    {
        $session = $request->getSession();

        if (!$session->get('is_authenticated')) {
            return $this->redirectToRoute('app_login_keycloak');
        }

        $annonce = $repo->find($id);

        if (!$annonce) {
            throw $this->createNotFoundException('Annonce introuvable.');
        }

        $userId = $session->get('user_id');
        if ($annonce->getOwner()?->getId() !== $userId) {
            $this->addFlash('error', 'Vous n\'êtes pas autorisé à supprimer cette annonce.');
            return $this->redirectToRoute('troc_index');
        }

        $em->remove($annonce);
        $em->flush();

        $this->addFlash('success', 'Annonce supprimée.');
        return $this->redirectToRoute('troc_mes_annonces');
    }
}
