<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Session\SessionInterface;

class UserAuthService
{
    private string $dbPath;
    private \PDO $pdo;
    private SessionInterface $session;

    // Security parameters
    private const PBKDF2_ALGO = 'sha256';
    private const PBKDF2_ITER = 100000;
    private const PBKDF2_LEN = 64; // bytes
    private const MAX_FAILED = 5;
    private const LOCK_MINUTES = 15;

    public function __construct(SessionInterface $session)
    {
        $this->session = $session;
        $root = realpath(__DIR__ . '/../../') ?: __DIR__ . '/../../';
        $dataDir = $root . '/data';
        if (!is_dir($dataDir)) {
            @mkdir($dataDir, 0750, true);
        }
        $this->dbPath = $dataDir . '/users.db';
        $this->pdo = new \PDO('sqlite:' . $this->dbPath);
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->initSchema();
    }

    private function initSchema(): void
    {
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            salt TEXT NOT NULL,
            role TEXT DEFAULT 'user',
            failed_attempts INTEGER DEFAULT 0,
            locked_until INTEGER DEFAULT 0,
            admin_protected INTEGER DEFAULT 0,
            must_change_password INTEGER DEFAULT 0,
            created_at INTEGER NOT NULL
        )");

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT,
            ip TEXT,
            user_agent TEXT,
            success INTEGER,
            message TEXT,
            ts INTEGER
        )");
        // Ensure protection columns exist for older DBs
        $this->ensureAdminProtectionColumns();
    }

    /**
     * Ensure additional columns exist for admin protection for existing DBs
     */
    private function ensureAdminProtectionColumns(): void
    {
        $cols = [];
        $stmt = $this->pdo->query("PRAGMA table_info('users')");
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $col) {
            $cols[] = $col['name'];
        }
        if (!in_array('admin_protected', $cols, true)) {
            $this->pdo->exec("ALTER TABLE users ADD COLUMN admin_protected INTEGER DEFAULT 0");
        }
        if (!in_array('must_change_password', $cols, true)) {
            $this->pdo->exec("ALTER TABLE users ADD COLUMN must_change_password INTEGER DEFAULT 0");
        }
    }

    // NOTE: auto-seed removed. Use CLI/console to create initial admin:
    // php scripts/manage_users.php add admin StrongP@ssw0rd admin
    // or: php bin/console app:user:manage add admin

    public function createUser(string $username, string $password, string $role = 'user'): bool
    {
        $username = mb_strtolower(trim($username));
        $salt = bin2hex(random_bytes(16));
        $hash = $this->pbkdf2Hash($password, $salt);
        $now = time();

        $stmt = $this->pdo->prepare('INSERT INTO users (username, password_hash, salt, role, created_at) VALUES (:u,:h,:s,:r,:t)');
        return (bool)$stmt->execute([':u' => $username, ':h' => $hash, ':s' => $salt, ':r' => $role, ':t' => $now]);
    }

    private function pbkdf2Hash(string $password, string $salt): string
    {
        return hash_pbkdf2(self::PBKDF2_ALGO, $password, $salt, self::PBKDF2_ITER, self::PBKDF2_LEN, false);
    }

    public function attemptLogin(string $username, string $password, ?string $ip = null, ?string $ua = null): array
    {
        $usernameKey = mb_strtolower(trim($username));
        $now = time();

        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE username = :u LIMIT 1');
        $stmt->execute([':u' => $usernameKey]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);

        $response = ['success' => false, 'message' => 'Geçersiz kimlik bilgileri'];

        if (! $user) {
            $this->recordAttempt($usernameKey, $ip, $ua, 0, 'User not found');
            // fall through to single return
        } else {
            // Locked?
            $lockedUntil = (int)$user['locked_until'];
            if ($lockedUntil > $now) {
                $minutes = ceil(($lockedUntil - $now) / 60);
                $msg = "Hesap geçici olarak kilitlendi. {$minutes} dakika sonra tekrar deneyin.";
                $this->recordAttempt($usernameKey, $ip, $ua, 0, 'Account locked');
                $response['message'] = $msg;
            } else {
                $computed = $this->pbkdf2Hash($password, $user['salt']);

                // Timing-safe compare
                $ok = hash_equals($user['password_hash'], $computed);

                if ($ok) {
                    // reset failed attempts
                    $upd = $this->pdo->prepare('UPDATE users SET failed_attempts = 0, locked_until = 0 WHERE id = :id');
                    $upd->execute([':id' => $user['id']]);

                    // set session
                    $this->session->set('auth_user_id', $user['id']);
                    $this->session->set('auth_username', $user['username']);
                    $this->recordAttempt($usernameKey, $ip, $ua, 1, 'Login success');
                    $response['success'] = true;
                    $response['message'] = 'OK';
                } else {
                    // failed
                    $failed = (int)$user['failed_attempts'] + 1;
                    $newLockedUntil = 0;
                    $msg = 'Geçersiz kimlik bilgileri';
                    if ($failed >= self::MAX_FAILED) {
                        $newLockedUntil = $now + (self::LOCK_MINUTES * 60);
                        $msg = 'Çok fazla başarısız giriş denemesi. Hesap kilitlendi.';
                    }

                    $upd = $this->pdo->prepare('UPDATE users SET failed_attempts = :f, locked_until = :l WHERE id = :id');
                    $upd->execute([':f' => $failed, ':l' => $newLockedUntil, ':id' => $user['id']]);
                    $this->recordAttempt($usernameKey, $ip, $ua, 0, 'Invalid password');
                    $response['message'] = $msg;
                }
            }
        }

        return $response;
    }

    private function recordAttempt(?string $username, ?string $ip, ?string $ua, int $success, string $message): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO login_attempts (username, ip, user_agent, success, message, ts) VALUES (:u,:ip,:ua,:s,:m,:t)');
        $stmt->execute([
            ':u' => $username,
            ':ip' => $ip,
            ':ua' => $ua,
            ':s' => $success,
            ':m' => $message,
            ':t' => time(),
        ]);
    }

    public function isAuthenticated(): bool
    {
        return $this->session->has('auth_user_id');
    }

    public function getCurrentUser(): ?array
    {
        if (! $this->isAuthenticated()) {
            return null;
        }
        $id = $this->session->get('auth_user_id');
        $stmt = $this->pdo->prepare('SELECT id, username, role, created_at FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $user ?: null;
    }

    public function logout(): void
    {
        $this->session->remove('auth_user_id');
        $this->session->remove('auth_username');
        $this->session->invalidate();
    }

    // Public helpers for CLI / admin tooling
    public function initDatabase(): string
    {
        $this->initSchema();
        return $this->dbPath;
    }

    public function listUsers(): array
    {
        $stmt = $this->pdo->query('SELECT id, username, role, failed_attempts, locked_until, admin_protected, must_change_password, created_at FROM users ORDER BY id');
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function setUserLockState(string $username, ?int $lockedUntil = null, int $failedAttempts = 0): bool
    {
        $username = mb_strtolower(trim($username));
        $l = $lockedUntil ?? 0;
        $stmt = $this->pdo->prepare('UPDATE users SET failed_attempts = :f, locked_until = :l WHERE username = :u');
        $stmt->execute([':f' => $failedAttempts, ':l' => $l, ':u' => $username]);
        return $stmt->rowCount() > 0;
    }

    public function setAdminProtectionState(string $username, bool $on): bool
    {
        $username = mb_strtolower(trim($username));
        $v = $on ? 1 : 0;
        $stmt = $this->pdo->prepare('UPDATE users SET admin_protected = :v WHERE username = :u');
        $stmt->execute([':v' => $v, ':u' => $username]);
        return $stmt->rowCount() > 0;
    }

    public function isAdminProtected(string $username): bool
    {
        $username = mb_strtolower(trim($username));
        $stmt = $this->pdo->prepare('SELECT admin_protected FROM users WHERE username = :u LIMIT 1');
        $stmt->execute([':u' => $username]);
        $r = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$r) {
            return false;
        }
        return (int)$r['admin_protected'] === 1;
    }

    public function setMustChangePasswordState(string $username, bool $on): bool
    {
        $username = mb_strtolower(trim($username));
        $v = $on ? 1 : 0;
        $stmt = $this->pdo->prepare('UPDATE users SET must_change_password = :v WHERE username = :u');
        $stmt->execute([':v' => $v, ':u' => $username]);
        return $stmt->rowCount() > 0;
    }

    public function setPasswordForUser(string $username, string $password): bool
    {
        $username = mb_strtolower(trim($username));
        $salt = bin2hex(random_bytes(16));
        $hash = $this->pbkdf2Hash($password, $salt);
        $stmt = $this->pdo->prepare('UPDATE users SET password_hash = :h, salt = :s, failed_attempts = 0, locked_until = 0 WHERE username = :u');
        $stmt->execute([':h' => $hash, ':s' => $salt, ':u' => $username]);
        return $stmt->rowCount() > 0;
    }

    public function deleteUserByUsername(string $username): bool
    {
        $username = mb_strtolower(trim($username));
        $stmt = $this->pdo->prepare('DELETE FROM users WHERE username = :u');
        $stmt->execute([':u' => $username]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Dev-only helper: seed an admin user if the users table is empty.
     * Returns array with keys: created(bool), username, password (if created), message
     */
    public function seedAdminIfEmpty(string $username = 'admin', ?string $password = null, string $role = 'admin', bool $force = false): array
    {
        $stmt = $this->pdo->query('SELECT COUNT(*) as c FROM users');
        $count = (int)$stmt->fetchColumn();
        if ($count === 0 || $force) {
            $username = mb_strtolower(trim($username));
            if ($password === null) {
                // generate a secure random password
                $password = bin2hex(random_bytes(8)); // 16 hex chars ~ 128 bits
            }
            $ok = $this->createUser($username, $password, $role);
            if ($ok) {
                return ['created' => true, 'username' => $username, 'password' => $password, 'message' => 'Admin user created'];
            }
            return ['created' => false, 'username' => $username, 'password' => null, 'message' => 'Failed to create admin user'];
        }
        return ['created' => false, 'username' => $username, 'password' => null, 'message' => 'Users already exist'];
    }
}
