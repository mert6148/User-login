<?php
// Simple CLI to manage the SQLite users DB used by the dashboard
// Usage: php scripts/manage_users.php init|list|add|setpw|delete <args>

$root = realpath(__DIR__ . '/..');
$dataDir = $root . DIRECTORY_SEPARATOR . 'data';
if (!is_dir($dataDir)) {
    @mkdir($dataDir, 0750, true);
}
$dbPath = $dataDir . DIRECTORY_SEPARATOR . 'users.db';

const DB_NOT_FOUND_MSG = "DB not found. Run 'init' first.";

function usage()
{
    echo "Usage:\n";
    echo "  php scripts/manage_users.php init\n";
    echo "  php scripts/manage_users.php list\n";
    echo "  php scripts/manage_users.php add <username> <password> [role]\n";
    echo "  php scripts/manage_users.php setpw <username> <newpassword>\n";
    echo "  php scripts/manage_users.php delete <username>\n";
    exit(1);
}

function getPdo($dbPath)
{
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $pdo;
}

function initDb($dbPath)
{
    $pdo = getPdo($dbPath);
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password_hash TEXT NOT NULL,
        salt TEXT NOT NULL,
        role TEXT DEFAULT 'user',
        failed_attempts INTEGER DEFAULT 0,
        locked_until INTEGER DEFAULT 0,
        created_at INTEGER NOT NULL
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT,
        ip TEXT,
        user_agent TEXT,
        success INTEGER,
        message TEXT,
        ts INTEGER
    )");
    echo "Initialized DB at: {$dbPath}\n";
}

function pbkdf2Hash($password, $salt)
{
    // Must match UserAuthService.php parameters
    return hash_pbkdf2('sha256', $password, $salt, 100000, 64, false);
}

function addUser($dbPath, $username, $password, $role = 'user')
{
    $username = mb_strtolower(trim($username));
    $salt = bin2hex(random_bytes(16));
    $hash = pbkdf2Hash($password, $salt);
    $pdo = getPdo($dbPath);
    $stmt = $pdo->prepare('INSERT INTO users (username, password_hash, salt, role, created_at) VALUES (:u,:h,:s,:r,:t)');
    $ok = $stmt->execute([':u' => $username, ':h' => $hash, ':s' => $salt, ':r' => $role, ':t' => time()]);
    if ($ok) {
        echo "User '{$username}' added (role={$role}).\n";
    } else {
        echo "Failed to add user '{$username}'.\n";
    }
}

function listUsers($dbPath)
{
    if (!file_exists($dbPath)) {
        echo DB_NOT_FOUND_MSG . "\n";
        return;
    }
    $pdo = getPdo($dbPath);
    $stmt = $pdo->query('SELECT id, username, role, failed_attempts, locked_until, created_at FROM users ORDER BY id');
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (count($rows) === 0) {
        echo "No users found.\n";
        return;
    }
    foreach ($rows as $r) {
        $locked = $r['locked_until'] > time() ? date('c', $r['locked_until']) : '-';
        echo sprintf("%3d | %-20s | %-8s | failed=%d | locked=%s | created=%s\n", $r['id'], $r['username'], $r['role'], $r['failed_attempts'], $locked, date('c', $r['created_at']));
    }
}

function setPassword($dbPath, $username, $password)
{
    if (!file_exists($dbPath)) {
        echo DB_NOT_FOUND_MSG . "\n";
        return;
    }
    $username = mb_strtolower(trim($username));
    $salt = bin2hex(random_bytes(16));
    $hash = pbkdf2Hash($password, $salt);
    $pdo = getPdo($dbPath);
    $stmt = $pdo->prepare('UPDATE users SET password_hash = :h, salt = :s, failed_attempts = 0, locked_until = 0 WHERE username = :u');
    $ok = $stmt->execute([':h' => $hash, ':s' => $salt, ':u' => $username]);
    if ($ok && $stmt->rowCount() > 0) {
        echo "Password updated for {$username}.\n";
    } else {
        echo "User not found: {$username}\n";
    }
}

function deleteUser($dbPath, $username)
{
    if (!file_exists($dbPath)) {
        echo DB_NOT_FOUND_MSG . "\n";
        return;
    }
    $username = mb_strtolower(trim($username));
    $pdo = getPdo($dbPath);
    $stmt = $pdo->prepare('DELETE FROM users WHERE username = :u');
    $stmt->execute([':u' => $username]);
    if ($stmt->rowCount() > 0) {
        echo "Deleted user {$username}.\n";
    } else {
        echo "User not found: {$username}\n";
    }
}

$argv = $_SERVER['argv'];
array_shift($argv); // script name
if (empty($argv)) {
    usage();
}
$cmd = array_shift($argv);
switch ($cmd) {
    case 'init':
        initDb($dbPath);
        break;
    case 'seed':
        // seed [username] [password] [role] [--force]
        $username = $argv[0] ?? 'admin';
        $password = $argv[1] ?? null;
        $role = $argv[2] ?? 'admin';
        $force = in_array('--force', $argv, true);
        // ensure db exists
        initDb($dbPath);
        // load classes: prefer Composer autoload if present, otherwise include service directly
        $autoload = __DIR__ . '/../vendor/autoload.php';
        if (file_exists($autoload)) {
            require_once $autoload;
        } else {
            require_once __DIR__ . '/../src/Service/UserAuthService.php';
        }
        // Instantiate session mock for CLI usage
        $session = new class {
            private $data = [];
            public function set($k, $v) { $this->data[$k] = $v; }
            public function get($k, $d = null) { return $this->data[$k] ?? $d; }
            public function has($k) { return isset($this->data[$k]); }
            public function remove($k) { unset($this->data[$k]); }
            public function invalidate() { $this->data = []; }
        };
        $auth = new \App\Service\UserAuthService($session);
        $res = $auth->seedAdminIfEmpty($username, $password, $role, $force);
        if ($res['created']) {
            echo "Created user: {$res['username']} with password: {$res['password']}\n";
        } else {
            echo "Seed result: {$res['message']}\n";
        }
        break;
    case 'list':
        listUsers($dbPath);
        break;
    case 'add':
        if (count($argv) < 2) { usage(); }
        $username = $argv[0];
        $password = $argv[1];
        $role = $argv[2] ?? 'user';
        addUser($dbPath, $username, $password, $role);
        break;
    case 'setpw':
        if (count($argv) < 2) { usage(); }
        setPassword($dbPath, $argv[0], $argv[1]);
        break;
    case 'delete':
        if (empty($argv)) { usage(); }
        $username = $argv[0];
        $force = in_array('--force', $argv, true);
        // Prefer using UserAuthService to check admin_protected
        $autoload = __DIR__ . '/../vendor/autoload.php';
        if (file_exists($autoload)) {
            require_once $autoload;
            $session = new class {
                private $data = [];
                public function set($k, $v) { $this->data[$k] = $v; }
                public function get($k, $d = null) { return $this->data[$k] ?? $d; }
                public function has($k) { return isset($this->data[$k]); }
                public function remove($k) { unset($this->data[$k]); }
                public function invalidate() { $this->data = []; }
            };
            $auth = new \App\Service\UserAuthService($session);
            if ($auth->isAdminProtected($username) && !$force) {
                echo "User {$username} is admin-protected; use --force to delete.\n";
                break;
            }
        }
        deleteUser($dbPath, $username);
        break;
    default:
        usage();
}
