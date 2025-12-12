<?php

namespace App\Controller;

use App\Service\UserAuthService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;

class DashboardUserController extends AbstractController
{
    private UserAuthService $authService;

    public function __construct(UserAuthService $authService)
    {
        $this->authService = $authService;
    }

    #[Route('/dashboard/login', name: 'dashboard_login', methods: ['GET','POST'])]
    public function login(Request $request): Response
    {
        if ($this->authService->isAuthenticated()) {
            return $this->redirectToRoute('dashboard_home');
        }

        $error = null;
        if ($request->isMethod('POST')) {
            $username = trim((string)$request->request->get('username', ''));
            $password = (string)$request->request->get('password', '');

            // Rate-limit / lockout handled inside auth service
            $result = $this->authService->attemptLogin($username, $password, $request->getClientIp(), $request->headers->get('User-Agent', ''));

            if ($result['success']) {
                // redirect to dashboard
                return $this->redirectToRoute('dashboard_home');
            }

            $error = $result['message'] ?? 'Geçersiz kimlik bilgileri';
        }

        return $this->render('admin/login.html.twig', [
            'error' => $error,
        ]);
    }

    #[Route('/dashboard/logout', name: 'dashboard_logout', methods: ['POST','GET'])]
    public function logout(Request $request): RedirectResponse
    {
        $this->authService->logout();
        return $this->redirectToRoute('dashboard_login');
    }

    #[Route('/dashboard', name: 'dashboard_home', methods: ['GET'])]
    public function home(Request $request): Response
    {
        if (! $this->authService->isAuthenticated()) {
            return $this->redirectToRoute('dashboard_login');
        }

        $user = $this->authService->getCurrentUser();
        return $this->render('admin/dashboard.html.twig', [
            'user' => $user,
        ]);
    }
}