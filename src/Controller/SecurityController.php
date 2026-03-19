<?php

namespace App\Controller;

use App\Entity\User;
use App\Message\UserLoginNotification;
use App\Repository\UserRepository;
use App\Service\KeycloakService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

class SecurityController extends AbstractController
{
    #[Route('/login/keycloak', name: 'app_login_keycloak')]
    public function loginKeycloak(): Response
    {
        return $this->render('security/login_keycloak.html.twig');
    }

    #[Route('/login/keycloak/redirect', name: 'app_login_keycloak_redirect')]
    public function redirectToKeycloak(KeycloakService $keycloakService, Request $request): Response
    {
        // Générer un state pour la sécurité CSRF
        $state = bin2hex(random_bytes(32));
        $session = $request->getSession();
        $session->set('oauth2_state', $state);

        // Rediriger vers Keycloak
        $authUrl = $keycloakService->getAuthorizationUrl($state);
        return $this->redirect($authUrl);
    }

    #[Route('/login/keycloak/callback', name: 'app_login_keycloak_callback')]
    public function callbackKeycloak(
        Request $request,
        KeycloakService $keycloakService,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        MessageBusInterface $bus
    ): Response {
        $session = $request->getSession();
        $code = $request->query->get('code');
        $state = $request->query->get('state');
        $storedState = $session->get('oauth2_state');

        // Vérifier le state pour la sécurité CSRF
        if (!$code || !$state || $state !== $storedState) {
            $this->addFlash('error', 'Erreur de connexion Keycloak : état invalide');
            return $this->redirectToRoute('app_login_keycloak');
        }

        try {
            // Échanger le code contre un access token
            $tokenData = $keycloakService->getAccessToken($code);
            $accessToken = $tokenData['access_token'];

            // Récupérer les informations de l'utilisateur
            $userInfo = $keycloakService->getUserInfo($accessToken);

            // Chercher ou créer l'utilisateur
            $user = $userRepository->findOneByKeycloakId($userInfo['sub']);

            // Si pas trouvé par keycloakId, chercher par email
            if (!$user && isset($userInfo['email'])) {
                $user = $userRepository->findOneByEmail($userInfo['email']);
            }

            if (!$user) {
                $user = new User();
                $user->setKeycloakId($userInfo['sub']);
            } else {
                $user->setKeycloakId($userInfo['sub']);
            }

            // Mettre à jour les informations de l'utilisateur
            $email = $userInfo['email'] ?? ($userInfo['sub'] . '@keycloak.noemail');
            $user->setEmail($email);
            $user->setUsername($userInfo['preferred_username'] ?? $email);
            $user->setFirstName($userInfo['given_name'] ?? '');
            $user->setLastName($userInfo['family_name'] ?? '');

            try {
                $entityManager->persist($user);
                $entityManager->flush();
            } catch (\Exception $e) {
                if (!str_contains($e->getMessage(), 'UNIQUE constraint')) {
                    throw $e;
                }
                // Race condition : l'utilisateur a été créé par une requête concurrente
                $entityManager->clear();
                $user = $userRepository->findOneByEmail($email)
                    ?? $userRepository->findOneByKeycloakId($userInfo['sub']);
                if (!$user) {
                    throw $e;
                }
            }

            // Stocker l'utilisateur en session (authentification manuelle)
            $session->set('user_id', $user->getId());
            $session->set('user_email', $user->getEmail());
            $session->set('user_name', $user->getFullName());
            $session->set('is_authenticated', true);

            // Envoyer la notification via RabbitMQ
            $bus->dispatch(new UserLoginNotification($user->getFullName()));

            // Message de succès
            $this->addFlash('success', sprintf(
                "Bienvenue %s ! Vous êtes connecté avec Keycloak. Notification envoyée par RabbitMQ.",
                $user->getFullName()
            ));

            return $this->redirectToRoute('homepage');

        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la connexion Keycloak : ' . $e->getMessage());
            return $this->redirectToRoute('app_login_keycloak');
        }
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(Request $request): Response
    {
        $session = $request->getSession();
        $userName = $session->get('user_name', 'utilisateur');

        // Nettoyer la session
        $session->invalidate();

        $this->addFlash('info', sprintf('Au revoir %s ! Vous avez été déconnecté.', $userName));

        return $this->redirectToRoute('homepage');
    }

    #[Route('/profile', name: 'app_profile')]
    public function profile(Request $request): Response
    {
        $session = $request->getSession();

        if (!$session->get('is_authenticated')) {
            $this->addFlash('warning', 'Vous devez être connecté pour accéder à cette page.');
            return $this->redirectToRoute('app_login_keycloak');
        }

        return $this->render('security/profile.html.twig', [
            'user_name' => $session->get('user_name'),
            'user_email' => $session->get('user_email'),
        ]);
    }
}
