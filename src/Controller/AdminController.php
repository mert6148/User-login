<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AdminController extends AbstractController
{
    #[Route('/admin', name: 'admin_dashboard')]
    public function dashboard(Request $request): Response
    {
        // Admin doğrulanmış mı?
        if (!$request->getSession()->get('is_admin')) {
            return $this->redirectToRoute('admin_login');
        }

        return $this->render('admin/dashboard.html.twig', [
            'username' => $request->getSession()->get('admin_username'),
        ]);
    }

    #[Route('/admin/login', name: 'admin_login')]
    public function loginForm(): Response
    {
        return $this->render('admin/login.html.twig');
    }

    #[Route('/admin/login/check', name: 'admin_login_check', methods: ['POST'])]
    public function loginCheck(Request $request): Response
    {
        $username = $request->request->get('username');
        $password = $request->request->get('password');

        // Basit authentication (örnek)
        if ($username === 'admin' && $password === '12345') {
            $session = $request->getSession();
            $session->set('is_admin', true);
            $session->set('admin_username', $username);

            return $this->redirectToRoute('admin_dashboard');
        }

        return $this->render('admin/login.html.twig', [
            'error' => 'Geçersiz kullanıcı adı veya şifre.'
        ]);
    }

    #[Route('/admin/logout', name: 'admin_logout')]
    public function logout(Request $request): Response
    {
        $request->getSession()->clear();
        return $this->redirectToRoute('admin_login');
    }
}
