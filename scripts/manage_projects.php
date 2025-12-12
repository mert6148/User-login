<?php
// CLI to manage projects (encrypted at rest) via ProjectDataService
// Usage: php scripts/manage_projects.php init|list|create|get|update-secret|delete <args>

$root = realpath(__DIR__ . '/..');
$dataDir = $root . DIRECTORY_SEPARATOR . 'data';
if (!is_dir($dataDir)) {
    @mkdir($dataDir, 0750, true);
}

function usage()
{
    echo "Usage:\n";
    echo "  php scripts/manage_projects.php init\n";
    echo "  php scripts/manage_projects.php list\n";
    echo "  php scripts/manage_projects.php create <name> <slug> <metadata-json> [secret]\n";
    echo "  php scripts/manage_projects.php get <id>\n";
    echo "  php scripts/manage_projects.php update-secret <id> <secret>\n";
    echo "  php scripts/manage_projects.php delete <id>\n";
    exit(1);
}

$argv = $_SERVER['argv'];
array_shift($argv);
if (empty($argv)) { usage(); }
// Parse the first arg as command
$cmd = array_shift($argv);

// Compose autoload if present
$autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
} else {
    fwrite(STDERR, "Please run composer install to load dependencies (vendor/autoload.php not found).\n");
    exit(1);
}

// Setup a minimal CLI session (Symfony Session)
// minimal session for CLI
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

$session = new Session(new MockArraySessionStorage());

$auth = new \App\Service\UserAuthService($session);
$projects = new \App\Service\ProjectDataService($session, $auth);

// Helper: look for flag and value (e.g. --username bob)
function getFlag(array $args, string $flag): ?string
{
    foreach ($args as $i => $a) {
        if ($a === $flag && isset($args[$i + 1])) {
            return $args[$i + 1];
        }
        // support --flag=value
        if (str_starts_with($a, $flag . '=')) {
            return substr($a, strlen($flag) + 1);
        }
    }
    return null;
}

// Optional inline credentials for single-command authentication
$optUsername = getFlag($argv, '--username') ?: getenv('CLI_USERNAME');
$optPassword = getFlag($argv, '--password') ?: getenv('CLI_PASSWORD');
if ($optUsername && $optPassword) {
    $auth->attemptLogin($optUsername, $optPassword, '127.0.0.1', 'CLI');
}

// remove our flags for command positional args: --username/--password
function stripFlag(array $args, string $flag): array
{
    $out = [];
    foreach ($args as $i => $a) {
        if ($a === $flag) {
            // skip next arg as well
            $i++;
            continue;
        }
        if (str_starts_with($a, $flag . '=')) {
            continue;
        }
        $out[] = $a;
    }
    return $out;
}

$argv = stripFlag($argv, '--username');
$argv = stripFlag($argv, '--password');

switch ($cmd) {
    case 'init':
        // constructor does init, so nothing to do
        echo "Projects DB schema init/exists at data/projects.db\n";
        break;
    case 'login':
        if (count($argv) < 2) { usage(); }
        $username = $argv[0];
        $password = $argv[1];
        $res = $auth->attemptLogin($username, $password, '127.0.0.1', 'CLI');
        if ($res['success']) {
            echo "Login success as {$username}\n";
        } else {
            echo "Login failed: {$res['message']}\n";
        }
        break;
    case 'logout':
        $auth->logout();
        echo "Logged out\n";
        break;
    case 'list':
        $isAuth = $auth->isAuthenticated();
        $u = $auth->getCurrentUser();
        $all = $isAuth && ($u['role'] ?? '') === 'admin';
        if (!$isAuth) {
            echo "Not authenticated. Use 'login' or provide admin credentials.\n";
            echo "Listing will be empty unless you're admin. Use login first.\n";
        }
        $rows = $projects->listProjects($all);
        if (empty($rows)) { echo "No projects found.\n"; break; }
        foreach ($rows as $r) {
            echo sprintf("%3d | %-20s | %-20s | owner=%s | created=%s\n", $r['id'], $r['name'], $r['slug'], $r['owner'], $r['created_at']);
        }
        break;
    case 'create':
        if (count($argv) < 3) { usage(); }
        $name = $argv[0];
        $slug = $argv[1];
        $meta = $argv[2];
        // strip surrounding quotes if present (helpful on Windows shells)
        $meta = trim($meta, "\"' \t\n\r");
        $secret = $argv[3] ?? null;
        // debug: show meta
        //var_dump($meta);
        $m = json_decode($meta, true);
        if ($m === null) {
            echo "Invalid JSON metadata\n";
            // echo debugging info
            //var_dump($meta);
            //var_dump(json_last_error_msg());
            break;
        }
        $id = $projects->createProject($name, $slug, $m, $secret);
        echo "Created project id={$id}\n";
        break;
    case 'get':
        if (empty($argv)) { usage(); }
        $id = (int)$argv[0];
        $row = $projects->getProjectById($id, true);
        if (!$row) { echo "Not found\n"; break; }
        echo json_encode($row, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        break;
    case 'update-secret':
        if (count($argv) < 2) { usage(); }
        $id = (int)$argv[0];
        $secret = $argv[1];
        $projects->updateProjectSecret($id, $secret);
        echo "Updated secret for project {$id}\n";
        break;
    case 'delete':
        if (empty($argv)) { usage(); }
        $id = (int)$argv[0];
        $projects->deleteProject($id);
        echo "Deleted project {$id}\n";
        break;
    default:
        usage();
}
