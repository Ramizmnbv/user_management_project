<?php
namespace App\EventSubscriber;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class UserStatusSubscriber implements EventSubscriberInterface
{
    private Security $securityHelper;
    private UrlGeneratorInterface $urlGenerator;
    private EntityManagerInterface $entityManager;
    private TokenStorageInterface $tokenStorage;
    private SessionInterface $session;

    private const ALWAYS_EXCLUDED_ROUTES = [
        'app_login',
        'app_logout',
        'app_register',
        '_wdt',
        '_profiler',
    ];

    public function __construct(
        Security $securityHelper,
        UrlGeneratorInterface $urlGenerator,
        EntityManagerInterface $entityManager,
        TokenStorageInterface $tokenStorage,
        SessionInterface $session
    ) {
        $this->securityHelper = $securityHelper;
        $this->urlGenerator = $urlGenerator;
        $this->entityManager = $entityManager;
        $this->tokenStorage = $tokenStorage;
        $this->session = $session;
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $currentRoute = $request->attributes->get('_route');

        foreach (self::ALWAYS_EXCLUDED_ROUTES as $excludedRoute) {
            if (
                $currentRoute === $excludedRoute ||
                ($excludedRoute === '_profiler' && str_starts_with((string) $currentRoute, '_profiler'))
            ) {
                return;
            }
        }

        $user = $this->securityHelper->getUser();

        if ($user instanceof User) {
            $freshUser = $this->entityManager->getRepository(User::class)->find($user->getId());

            if (!$freshUser) {
                $this->addFlashAndRedirect('warning', 'Your account has been deleted. You may re-register if you wish.', 'app_login', $event);
                return;
            }

            if ($freshUser->isBlocked()) {
                $this->addFlashAndRedirect('danger', 'Your account is currently blocked. Please contact support.', 'app_login', $event);
                return;
            }

            // Optionally update token with fresh user
            // $this->tokenStorage->getToken()?->setUser($freshUser);
        }
    }

    private function addFlashAndRedirect(string $type, string $message, string $route, RequestEvent $event): void
    {
        $this->session->getFlashBag()->add($type, $message);

        // Clear token to log the user out
        $this->tokenStorage->setToken(null);
        $this->session->invalidate();

        $redirectUrl = $this->urlGenerator->generate($route);
        $event->setResponse(new RedirectResponse($redirectUrl));
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 0],
        ];
    }
}
