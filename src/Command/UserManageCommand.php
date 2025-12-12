<?php

namespace App\Command;

use App\Service\UserAuthService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

class UserManageCommand extends Command
{
    protected static $defaultName = 'app:user:manage';
    private UserAuthService $authService;

    public function __construct(UserAuthService $authService)
    {
        parent::__construct();
        $this->authService = $authService;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Manage dashboard users (init, list, add, setpw, delete)')
            ->addArgument('action', InputArgument::REQUIRED, 'Action: init|list|add|setpw|delete')
            ->addArgument('username', InputArgument::OPTIONAL, 'Username for add/setpw/delete')
            ->addArgument('password', InputArgument::OPTIONAL, 'Password for add/setpw (if omitted will prompt)')
            ->addOption('role', null, InputOption::VALUE_OPTIONAL, 'Role when adding user', 'user')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force seed or override checks');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $action = $input->getArgument('action');
        $username = $input->getArgument('username');
        $password = $input->getArgument('password');
        $role = $input->getOption('role') ?? 'user';
        // route to handlers to reduce complexity
        switch ($action) {
            case 'init':
                $result = $this->handleInit();
                break;
            case 'list':
                $result = $this->handleList($io);
                break;
            case 'add':
                $result = $this->handleAdd($input, $output, $username, $password, $role);
                break;
            case 'setpw':
                $result = $this->handleSetpw($input, $output, $username, $password);
                break;
            case 'delete':
                $result = $this->handleDelete($io, $username);
                break;
            case 'seed':
                $force = (bool)$input->getOption('force');
                $result = $this->handleSeed($username, $password, $role, $force);
                break;
            default:
                $result = [Command::FAILURE, 'Unknown action. Use init|list|add|setpw|delete|seed'];
                break;
        }

        [$status, $message] = $result;
        if ($message !== null) {
            if ($status === Command::SUCCESS) {
                $io->success($message);
            } else {
                $io->error($message);
            }
        }

        return $status;
    }

    private function handleInit(): array
    {
        $path = $this->authService->initDatabase();
        return [Command::SUCCESS, "Database initialized at: {$path}"];
    }

    private function handleList(SymfonyStyle $io): array
    {
        $rows = $this->authService->listUsers();
        if (empty($rows)) {
            $io->warning('No users found.');
            return [Command::SUCCESS, null];
        }
        $table = [];
        foreach ($rows as $r) {
            $locked = $r['locked_until'] > time() ? date('c', $r['locked_until']) : '-';
            $table[] = [$r['id'], $r['username'], $r['role'], $r['failed_attempts'], $locked, date('c', $r['created_at'])];
        }
        $io->table(['ID', 'Username', 'Role', 'Failed', 'Locked Until', 'Created'], $table);
        return [Command::SUCCESS, null];
    }

    private function handleAdd(InputInterface $input, OutputInterface $output, ?string $username, ?string $password, string $role): array
    {
        if (empty($username)) {
            return [Command::FAILURE, 'Username is required for add'];
        }
        if (empty($password)) {
            $helper = $this->getHelper('question');
            $question = new Question('Password: ');
            $question->setHidden(true);
            $question->setHiddenFallback(false);
            $password = $helper->ask($input, $output, $question);
        }
        $ok = $this->authService->createUser($username, $password, $role);
        if ($ok) {
            return [Command::SUCCESS, "User {$username} created with role {$role}."];
        }
        return [Command::FAILURE, 'Failed to create user (maybe exists).'];
    }

    private function handleSetpw(InputInterface $input, OutputInterface $output, ?string $username, ?string $password): array
    {
        if (empty($username)) {
            return [Command::FAILURE, 'Username is required for setpw'];
        }
        if (empty($password)) {
            $helper = $this->getHelper('question');
            $question = new Question('New password: ');
            $question->setHidden(true);
            $question->setHiddenFallback(false);
            $password = $helper->ask($input, $output, $question);
        }
        $ok = $this->authService->setPasswordForUser($username, $password);
        if ($ok) {
            return [Command::SUCCESS, "Password updated for {$username}."];
        }
        return [Command::FAILURE, 'User not found.'];
    }

    private function handleDelete(SymfonyStyle $io, ?string $username): array
    {
        $status = Command::FAILURE;
        $message = null;
        if (empty($username)) {
            $message = 'Username is required for delete';
            return [$status, $message];
        }
        if (! $io->confirm("Are you sure you want to delete user '{$username}'?", false)) {
            $io->warning('Aborted');
            return [Command::SUCCESS, null];
        }
        $ok = $this->authService->deleteUserByUsername($username);
        if ($ok) {
            $status = Command::SUCCESS;
            $message = "Deleted user {$username}.";
        } else {
            $message = 'User not found.';
        }
        return [$status, $message];
    }

    private function handleSeed(?string $username, ?string $password, string $role, bool $force): array
    {
        // safe default: require env flag unless --force used
        $envAllowed = getenv('USER_LOGIN_DEV_SEED') === '1';
        if (! $envAllowed && ! $force) {
            return [Command::FAILURE, 'Dev seeding disabled. Set USER_LOGIN_DEV_SEED=1 or use --force to override.'];
        }
        $username = $username ?? 'admin';
        $res = $this->authService->seedAdminIfEmpty($username, $password, $role, $force);
        if ($res['created']) {
            return [Command::SUCCESS, "Created user: {$res['username']} with password: {$res['password']}"];
        }
        return [Command::FAILURE, "Seed result: {$res['message']}"];
    }
}
