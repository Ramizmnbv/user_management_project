<?php
namespace App\EventSubscriber;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Bundle\SecurityBundle\Security; // Correct Security class
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;


class UserStatusSubscriber implements EventSubscriberInterface
{
    private Security $securityHelper; // Use new Security helper
    private UrlGeneratorInterface $urlGenerator;
    private EntityManagerInterface $entityManager;
    private TokenStorageInterface $tokenStorage;


    // Routes to ALWAYS exclude from the check
    private const ALWAYS_EXCLUDED_ROUTES = [
        'app_login',
        'app_logout', // Logout must be accessible to terminate session
        'app_register',
        '_wdt', // Symfony Web Debug Toolbar
        '_profiler', // Symfony Profiler and its sub-routes
    ];

    public function __construct(Security $securityHelper, UrlGeneratorInterface $urlGenerator, EntityManagerInterface $entityManager, TokenStorageInterface $tokenStorage)
    {
        $this->securityHelper = $securityHelper;
        $this->urlGenerator = $urlGenerator;
        $this->entityManager = $entityManager;
        $this->tokenStorage = $tokenStorage;
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) { return; }

        $request = $event->getRequest();
        $currentRoute = $request->attributes->get('_route');

        // Check if the current route or its prefix is in the excluded list
        foreach (self::ALWAYS_EXCLUDED_ROUTES as $excludedRoute) {
            if ($currentRoute === $excludedRoute || ($excludedRoute === '_profiler' && str_starts_with((string)$currentRoute, '_profiler'))) {
                return;
            }
        }

        $user = $this->securityHelper->getUser();

        if ($user instanceof User) {
            // Re-fetch user from DB to get the LATEST status, token might be stale.
            // This is important if another session/admin blocked/deleted the user.
            $freshUser = $this->entityManager->getRepository(User::class)->find($user->getId());

            if (!$freshUser) { // User has been deleted from DB
                $this->addFlashAndRedirect($request, 'warning', 'Your account has been deleted. You may re-register if you wish.', 'app_login', $event);
                return;
            }

            if ($freshUser->isBlocked()) { // User is marked as blocked
                 $this->addFlashAndRedirect($request, 'danger', 'Your account is currently blocked. Please contact support.', 'app_login', $event);
                 return;
            }
            // If user exists and is not blocked, update the user object in the token if necessary
            // This ensures other parts of the app use the fresh user state if it changed (e.g., roles, name)
            // However, for status check, the freshUser check above is sufficient for redirection.
            // $this->tokenStorage->getToken()?->setUser($freshUser); // This could be done if other attributes need to be live
        }
        // If no user is logged in and the route is protected, Symfony's firewall will handle redirection.
    }

    private function addFlashAndRedirect(Request $request, string $type, string $message, string $route, RequestEvent $event): void
    {
        $request->getSession()->getFlashBag()->add($type, $message);

        // Invalidate the current session and token to ensure user is logged out
        // This is crucial for blocked/deleted users.
        $token = $this->tokenStorage->getToken();
        if ($token) {
            $this->tokenStorage->setToken(null); // Clear the token
        }
        // $request->getSession()->invalidate(); // This logs out fully.

        $event->setResponse(new RedirectResponse($this->urlGenerator->generate($route)));
    }


    public static function getSubscribedEvents(): array
    {
        return [
            // Priority should be after authentication but before controller execution.
            // Firewall listener is typically 8. RouterListener is 32.
            // We want this to run after user is potentially authenticated by firewall.
            KernelEvents::REQUEST => ['onKernelRequest', 0], // Adjust priority if needed
        ];
    }
}
