<?php
namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/users')]
#[IsGranted('ROLE_USER')] // All actions in this controller require login
class UserManagementController extends AbstractController
{
    private EntityManagerInterface $em;
    private UserRepository $userRepository;

    public function __construct(EntityManagerInterface $em, UserRepository $userRepository)
    {
        $this->em = $em;
        $this->userRepository = $userRepository;
    }

    #[Route('', name: 'app_user_management', methods: ['GET'])]
    public function index(Request $request): Response
    {
        // Default sort: last login time, DESC. Nulls last.
        $sortBy = $request->query->get('sort_by', 'lastLoginTime');
        $sortOrder = strtoupper($request->query->get('sort_order', 'DESC'));
        if (!in_array($sortOrder, ['ASC', 'DESC'])) $sortOrder = 'DESC';

        $allowedSortFields = ['id', 'name', 'email', 'lastLoginTime', 'registrationTime', 'status'];
        if (!in_array($sortBy, $allowedSortFields)) $sortBy = 'lastLoginTime';

        // For lastLoginTime, we want NULLs to be treated as "older" (so last in DESC, first in ASC)
        $qb = $this->userRepository->createQueryBuilder('u');
        if ($sortBy === 'lastLoginTime') {
            if ($sortOrder === 'DESC') {
                $qb->orderBy('CASE WHEN u.lastLoginTime IS NULL THEN 1 ELSE 0 END', 'ASC') // NULLs last
                   ->addOrderBy('u.lastLoginTime', 'DESC');
            } else { // ASC
                $qb->orderBy('CASE WHEN u.lastLoginTime IS NULL THEN 0 ELSE 1 END', 'ASC') // NULLs first
                   ->addOrderBy('u.lastLoginTime', 'ASC');
            }
        } else {
            $qb->orderBy('u.' . $sortBy, $sortOrder);
        }
        $users = $qb->getQuery()->getResult();

        return $this->render('user_management/index.html.twig', [
            'users' => $users,
            'current_user_id' => $this->getUser() ? $this->getUser()->getId() : null,
            'sort_by' => $sortBy,
            'sort_order' => $sortOrder,
        ]);
    }

    #[Route('/actions', name: 'app_user_actions', methods: ['POST'])]
    public function handleActions(Request $request): Response
    {
        $submittedToken = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('user_actions', $submittedToken)) {
            $this->addFlash('danger', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_user_management');
        }

        $action = $request->request->get('action');
        $selectedUserIds = $request->request->all('selected_users'); // Get all selected_users[] values

        if (empty($selectedUserIds)) {
            $this->addFlash('warning', 'No users selected.');
            return $this->redirectToRoute('app_user_management');
        }

        $usersToProcess = $this->userRepository->findBy(['id' => $selectedUserIds]);
        $currentUser = $this->getUser();
        $currentUserModified = false;

        foreach ($usersToProcess as $user) {
            if ($action === 'block') {
                $user->setStatus('blocked');
                $this->addFlash('success', sprintf('User "%s" blocked.', $user->getEmail()));
                if ($user->getId() === $currentUser->getId()) $currentUserModified = true;
            } elseif ($action === 'unblock') {
                $user->setStatus('active');
                $this->addFlash('success', sprintf('User "%s" unblocked.', $user->getEmail()));
                // No special handling if current user unblocks self
            } elseif ($action === 'delete') {
                if ($user->getId() === $currentUser->getId()) $currentUserModified = true;
                $this->em->remove($user);
                $this->addFlash('success', sprintf('User "%s" deleted.', $user->getEmail()));
            }
            if ($action !== 'delete') {
                $this->em->persist($user);
            }
        }
        $this->em->flush();

        // If current user was blocked or deleted, they will be redirected
        // by the UserStatusSubscriber on their next request.
        // No explicit logout here is needed as per "Users shouldn't be 'kicked' right away."

        return $this->redirectToRoute('app_user_management');
    }

    // Dummy home route if needed for base template
    #[Route('/', name: 'app_home_redirect_base', methods: ['GET'])] // Temporary if no other '/' exists
    public function homeRedirectBase(): Response
    {
         return $this->redirectToRoute('app_user_management');
    }
}
