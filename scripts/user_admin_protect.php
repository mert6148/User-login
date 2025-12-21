<?php
// CLI to protect/unprotect admin accounts and manage lock/unlock
// Usage: php scripts/user_admin_protect.php action username [--minutes 15] [--force]

$root = realpath(__DIR__ . '/..');
$autoload = $root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
if (!file_exists($autoload)) {
    fwrite(STDERR, "Please run composer install to enable CLI commands.\n");
    exit(1);
}
require_once $autoload;

use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

$argv = $_SERVER['argv'];
array_shift($argv);
if (empty($argv)) {
    echo "Usage: php scripts/user_admin_protect.php <action> <username> [--minutes N] [--force]\n";
    exit(1);
}
$action = array_shift($argv);
$username = array_shift($argv) ?? null;
if (!$username) { echo "username required\n"; exit(1); }

// options
$minutes = 15; $force = false;
foreach ($argv as $a) {
    if (str_starts_with($a, '--minutes=')) { $minutes = (int)substr($a, 10); }
    if ($a === '--force' || $a === '-f') { $force = true; }
}

$session = new Session(new MockArraySessionStorage());
$auth = new \App\Service\UserAuthService($session);

switch ($action) {
    case 'protect':
        $ok = $auth->setAdminProtectionState($username, true);
        echo $ok ? "Protected {$username}\n" : "Failed to protect {$username}\n";
        exit($ok ? 0 : 2);
    case 'unprotect':
        $ok = $auth->setAdminProtectionState($username, false);
        echo $ok ? "Unprotected {$username}\n" : "Failed to unprotect {$username}\n";
        exit($ok ? 0 : 2);
    case 'lock':
        $ok = $auth->setUserLockState($username, time() + ($minutes * 60), \App\Service\UserAuthService::MAX_FAILED);
        echo $ok ? "Locked {$username} for {$minutes} minutes\n" : "Failed to lock {$username}\n";
        exit($ok ? 0 : 2);
    case 'unlock':
        $ok = $auth->setUserLockState($username, 0, 0);
        echo $ok ? "Unlocked {$username}\n" : "Failed to unlock {$username}\n";
        exit($ok ? 0 : 2);
    case 'reset-pw':
        $ok = $auth->setMustChangePasswordState($username, true);
        echo $ok ? "Password reset enforced for {$username}\n" : "Failed to enforce password reset\n";
        exit($ok ? 0 : 2);
    case 'status':
        $rows = $auth->listUsers();
        $rows = array_filter($rows, fn($r)=>$r['username']===$username);
        if (empty($rows)) { echo "User not found\n"; exit(2); }
        $r = array_values($rows)[0];
        print_r($r);
        exit(0);
    case 'delete':
        if ($auth->isAdminProtected($username) && !$force) {
            echo "User is admin-protected; use --force to override\n";
            exit(2);
        }
        $ok = $auth->deleteUserByUsername($username);
        echo $ok ? "Deleted {$username}\n" : "Failed to delete {$username}\n";
        exit($ok ? 0 : 2);
    default:
        echo "Unknown action\n";
        exit(1);
}
