<?php
namespace App\Command;

use App\Service\ProjectDataService;
use App\Service\UserAuthService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpFoundation\Session\Session;

class ProjectManageCommand extends Command
{
    protected static $defaultName = 'app:project:manage';
    private ProjectDataService $service;
    private UserAuthService $users;

    public function __construct(ProjectDataService $service, UserAuthService $users)
    {
        $this->service = $service;
        $this->users = $users;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Manage projects (create/list/update/delete)')
            ->addArgument('action', InputArgument::REQUIRED, 'Action to run: init|create|list|get|update-secret|delete')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Project name')
            ->addOption('slug', null, InputOption::VALUE_REQUIRED, 'Slug for project (a-z0-9-_ 3-64)')
            ->addOption('metadata', null, InputOption::VALUE_REQUIRED, 'metadata JSON string')
            ->addOption('secret', null, InputOption::VALUE_REQUIRED, 'secret value')
            ->addOption('id', null, InputOption::VALUE_REQUIRED, 'Project id for get/update/delete');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $action = $input->getArgument('action');
        $status = null;
        switch ($action) {
            case 'init':
                $status = $this->handleInit($io);
                break;
            case 'create':
                $status = $this->handleCreate($input, $io);
                break;
            case 'list':
                $status = $this->handleList($io);
                break;
            case 'get':
                $status = $this->handleGet($input, $io);
                break;
            case 'update-secret':
                $status = $this->handleUpdateSecret($input, $io);
                break;
            case 'delete':
                $status = $this->handleDelete($input, $io);
                break;
            default:
                $io->error('Unknown action');
                $status = Command::FAILURE;
                break;
        }
        return $status ?? Command::FAILURE;
    }

    private function handleInit(SymfonyStyle $io): int
    {
        // calling the constructor ensures schema exists
        $io->success('Project DB schema initialized (or already present).');
        return Command::SUCCESS;
    }

    private function handleCreate(InputInterface $input, SymfonyStyle $io): int
    {
        $name = $input->getOption('name');
        $slug = $input->getOption('slug');
        $metadata = $input->getOption('metadata') ?: '{}';
        $secret = $input->getOption('secret');
        if (!$name || !$slug) {
            $io->error('name and slug are required');
            return Command::FAILURE;
        }
        $metaArr = json_decode($metadata, true);
        if ($metaArr === null) {
            $io->error('metadata must be valid JSON');
            return Command::FAILURE;
        }
        $id = $this->service->createProject($name, $slug, $metaArr, $secret);
        $io->success("Created project $name ($slug) id=$id");
        return Command::SUCCESS;
    }

    private function handleList(SymfonyStyle $io): int
    {
        $rows = $this->service->listProjects($this->users->getCurrentUser()['role'] === 'admin');
        if (empty($rows)) {
            $io->text('No projects');
            return Command::SUCCESS;
        }
        $io->table(['id', 'name', 'slug', 'owner', 'created_at'], array_map(function ($r) {
            return [$r['id'], $r['name'], $r['slug'], $r['owner'], $r['created_at']];
        }, $rows));
        return Command::SUCCESS;
    }

    private function handleGet(InputInterface $input, SymfonyStyle $io): int
    {
        $id = $input->getOption('id');
        if (!$id) {
            $io->error('id is required');
            return Command::FAILURE;
        }
        $row = $this->service->getProjectById((int)$id, true);
        if (!$row) {
            $io->error('Not found');
            return Command::FAILURE;
        }
        $io->writeln(json_encode($row, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return Command::SUCCESS;
    }

    private function handleUpdateSecret(InputInterface $input, SymfonyStyle $io): int
    {
        $id = $input->getOption('id');
        $secret = $input->getOption('secret');
        if (!$id || !$secret) {
            $io->error('id and secret are required');
            return Command::FAILURE;
        }
        $this->service->updateProjectSecret((int)$id, $secret);
        $io->success('secret updated');
        return Command::SUCCESS;
    }

    private function handleDelete(InputInterface $input, SymfonyStyle $io): int
    {
        $id = $input->getOption('id');
        if (!$id) {
            $io->error('id required');
            return Command::FAILURE;
        }
        $this->service->deleteProject((int)$id);
        $io->success('deleted');
        return Command::SUCCESS;
    }
}
