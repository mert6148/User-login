<?php
namespace App\Controller;

use App\Service\ProjectDataService;
use App\Service\UserAuthService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ProjectController extends AbstractController
{
    private ProjectDataService $projects;
    private UserAuthService $users;

    public function __construct(ProjectDataService $projects, UserAuthService $users)
    {
        $this->projects = $projects;
        $this->users = $users;
    }

    #[Route('/dashboard/projects', name: 'dashboard_projects_list', methods: ['GET'])]
    public function list(Request $request): Response
    {
        if (! $this->users->isAuthenticated()) {
            return $this->redirectToRoute('dashboard_login');
        }
        $user = $this->users->getCurrentUser();
        $all = ($user['role'] ?? '') === 'admin';
        $rows = $this->projects->listProjects($all);
        return $this->render('admin/projects.html.twig', [
            'projects' => $rows,
            'user' => $user,
        ]);
    }

    #[Route('/dashboard/projects/new', name: 'dashboard_projects_new', methods: ['GET','POST'])]
    public function create(Request $request): Response
    {
        if (! $this->users->isAuthenticated()) {
            return $this->redirectToRoute('dashboard_login');
        }
        if ($request->isMethod('POST')) {
            $redirect = $this->handleCreatePost($request);
            if ($redirect) {
                return $redirect;
            }
        }
        return $this->render('admin/project_new.html.twig', [
            'user' => $this->users->getCurrentUser(),
        ]);
    }

    private function handleCreatePost(Request $request)
    {
        $name = trim((string)$request->request->get('name'));
        $slug = trim((string)$request->request->get('slug'));
        $metadata = (string)$request->request->get('metadata', '{}');
        $secret = (string)$request->request->get('secret', '');
        $metaArr = json_decode($metadata, true);
        if ($metaArr === null) {
            $this->addFlash('error', 'metadata must be JSON');
            return $this->redirectToRoute('dashboard_projects_new');
        }
        try {
            $this->projects->createProject($name, $slug, $metaArr, $secret ?: null);
            $this->addFlash('success', 'Project created');
            return $this->redirectToRoute('dashboard_projects_list');
        } catch (\Throwable $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('dashboard_projects_new');
        }
    }

    #[Route('/dashboard/projects/{id}', name: 'dashboard_projects_get', methods: ['GET'])]
    public function getOne(Request $request, int $id): Response
    {
        if (! $this->users->isAuthenticated()) {
            return $this->redirectToRoute('dashboard_login');
        }
        try {
            $row = $this->projects->getProjectById($id, true);
        } catch (\Throwable $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('dashboard_projects_list');
        }
        return $this->render('admin/project_view.html.twig', [
            'project' => $row,
            'user' => $this->users->getCurrentUser(),
        ]);
    }
}
