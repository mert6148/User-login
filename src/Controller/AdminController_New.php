<?php

namespace App\Controller;

use App\Service\AdminService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class AdminController extends AbstractController
{
    private AdminService $adminService;
    
    public function __construct(AdminService $adminService)
    {
        $this->adminService = $adminService;
    }
    
    #[Route('/admin/login', name: 'admin_login', methods: ['GET', 'POST'])]
    public function login(Request $request): Response
    {
        if ($this->adminService->isAdmin()) {
            return $this->redirectToRoute('admin_dashboard');
        }
        
        if ($request->isMethod('POST')) {
            $username = $request->request->get('username', '');
            $password = $request->request->get('password', '');
            $role = $request->request->get('role', AdminService::ROLE_ADMIN);
            
            if ($this->adminService->login($username, $password, $role)) {
                return $this->redirectToRoute('admin_dashboard');
            }
            
            return $this->render('admin/login.html.twig', [
                'error' => 'Geçersiz kimlik bilgileri'
            ]);
        }
        
        return $this->render('admin/login.html.twig');
    }
    
    #[Route('/admin', name: 'admin_dashboard', methods: ['GET'])]
    #[Route('/admin/dashboard', name: 'admin_dashboard_alt', methods: ['GET'])]
    public function dashboard(): Response
    {
        $this->requireAdmin();
        
        return $this->render('admin/dashboard.html.twig', [
            'session' => $this->adminService->getSessionInfo(),
            'audit_logs' => $this->adminService->getAuditLogs(20),
            'permissions' => $this->adminService->getPermissions()
        ]);
    }
    
    #[Route('/admin/logout', name: 'admin_logout')]
    public function logout(): Response
    {
        $this->adminService->logout();
        return $this->redirectToRoute('admin_login');
    }
    
    #[Route('/admin/session/extend', name: 'admin_extend_session', methods: ['POST'])]
    public function extendSession(): JsonResponse
    {
        $this->requireAdmin();
        $this->adminService->extendSession();
        return $this->json(['success' => true]);
    }
    
    #[Route('/admin/users', name: 'admin_users')]
    public function users(): Response
    {
        $this->requireAdmin(AdminService::PERM_USER_MANAGE);
        return $this->render('admin/users.html.twig');
    }
    
    #[Route('/admin/logs', name: 'admin_logs')]
    public function logs(Request $request): Response
    {
        $this->requireAdmin(AdminService::PERM_VIEW_LOGS);
        $limit = min((int)$request->query->get('limit', 50), 1000);
        return $this->render('admin/logs.html.twig', [
            'logs' => $this->adminService->getAuditLogs($limit)
        ]);
    }
    
    #[Route('/admin/security', name: 'admin_security')]
    public function security(): Response
    {
        $this->requireAdmin(AdminService::PERM_SECURITY);
        return $this->render('admin/security.html.twig');
    }
    
    #[Route('/admin/database', name: 'admin_database')]
    public function database(): Response
    {
        $this->requireAdmin(AdminService::PERM_DATABASE);
        return $this->render('admin/database.html.twig');
    }
    
    #[Route('/admin/permissions', name: 'admin_permissions')]
    public function permissions(): JsonResponse
    {
        $this->requireAdmin();
        return $this->json([
            'role' => $this->adminService->getAdminRole(),
            'permissions' => $this->adminService->getPermissions()
        ]);
    }
    
    private function requireAdmin(string $permission = null): void
    {
        if (!$this->adminService->isAdmin()) {
            throw $this->createAccessDeniedException('Admin erişimi gerekli');
        }
        
        if ($permission && !$this->adminService->hasPermission($permission)) {
            throw $this->createAccessDeniedException('İzin gerekli');
        }
    }
}
