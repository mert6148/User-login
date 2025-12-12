<?php
namespace App\Service;

use PDO;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * ProjectDataService
 * - provides storage with encryption for project secret fields
 * - uses env PROJECT_DATA_KEY or a data/.project_key file
 */
class ProjectDataService
{
    private const DATA_DIR = __DIR__ . '/../../data';
    private const DB_FILE = self::DATA_DIR . '/projects.db';
    private const KEY_FILE = self::DATA_DIR . '/.project_key';
    private const CIPHER = 'aes-256-cbc';

    private PDO $pdo;
    private string $key;
    private SessionInterface $session;
    private UserAuthService $users;

    public function __construct(SessionInterface $session, UserAuthService $users)
    {
        $this->session = $session;
        $this->users = $users;
        if (!is_dir(self::DATA_DIR)) {
            @mkdir(self::DATA_DIR, 0700, true);
        }
        $this->pdo = new PDO('sqlite:' . self::DB_FILE);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->key = $this->getEncryptionKey();
        $this->initSchema();
    }

    private function getEncryptionKey(): string
    {
        $key = $this->loadKeyFromEnv() ?? $this->loadKeyFromFile();
        if ($key !== null) {
            return $key;
        }
        return $this->generateAndPersistKey();
    }

    private function loadKeyFromEnv(): ?string
    {
        $envKey = getenv('PROJECT_DATA_KEY');
        if (!$envKey || strlen($envKey) < 32) {
            return null;
        }
        $decoded = base64_decode($envKey);
        return $decoded !== false ? $decoded : $envKey;
    }

    private function loadKeyFromFile(): ?string
    {
        if (!file_exists(self::KEY_FILE)) {
            return null;
        }
        $k = trim(file_get_contents(self::KEY_FILE));
        if ($k === '') {
            return null;
        }
        $decoded = base64_decode($k);
        return $decoded !== false ? $decoded : $k;
    }

    private function generateAndPersistKey(): string
    {
        $key = openssl_random_pseudo_bytes(32);
        file_put_contents(self::KEY_FILE, base64_encode($key));
        @chmod(self::KEY_FILE, 0600);
        return $key;
    }

    private function initSchema(): void
    {
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS projects (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            slug TEXT UNIQUE NOT NULL,
            name TEXT NOT NULL,
            owner TEXT NOT NULL,
            metadata TEXT,
            secret_data TEXT,
            created_at TEXT DEFAULT (datetime('now')),
            updated_at TEXT DEFAULT (datetime('now'))
        )");
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS project_audit (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            project_id INTEGER,
            action TEXT,
            performed_by TEXT,
            details TEXT,
            created_at TEXT DEFAULT (datetime('now'))
        )");
    }

    // En/De-crypt helpers
    private function encrypt(string $plaintext): string
    {
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(self::CIPHER));
        $ciphertext = openssl_encrypt($plaintext, self::CIPHER, $this->key, OPENSSL_RAW_DATA, $iv);
        $hmac = hash_hmac('sha256', $iv . $ciphertext, $this->key, true);
        return base64_encode($iv . $hmac . $ciphertext);
    }

    private function decrypt(string $payload): ?string
    {
        $decoded = base64_decode($payload, true);
        if ($decoded === false || strlen($decoded) < 48) {
            return null; // invalid
        }
        $ivLength = openssl_cipher_iv_length(self::CIPHER);
        $iv = substr($decoded, 0, $ivLength);
        $hmac = substr($decoded, $ivLength, 32);
        $ciphertext = substr($decoded, $ivLength + 32);
        $calculated = hash_hmac('sha256', $iv . $ciphertext, $this->key, true);
        if (!hash_equals($hmac, $calculated)) {
            return null; // tampering
        }
        return openssl_decrypt($ciphertext, self::CIPHER, $this->key, OPENSSL_RAW_DATA, $iv);
    }

    private function audit(string $action, string $details = '', int $projectId = null): void
    {
        $user = $this->users->getCurrentUser();
        $performedBy = $user['username'] ?? 'anonymous';
        $stmt = $this->pdo->prepare('INSERT INTO project_audit (project_id, action, performed_by, details) VALUES (:project_id, :action, :performed_by, :details)');
        $stmt->execute([
            ':project_id' => $projectId,
            ':action' => $action,
            ':performed_by' => $performedBy,
            ':details' => $details,
        ]);
    }

    public function createProject(string $name, string $slug, array $metadata = [], ?string $secret = null): int
    {
        // validate slug
        if (!preg_match('/^[a-z0-9-_]{3,64}$/', $slug)) {
            throw new \InvalidArgumentException('Invalid slug (use a-z0-9-_ and length 3-64)');
        }
        $user = $this->users->getCurrentUser();
        if (!$user) {
            throw new \DomainException('Not authenticated');
        }
        $owner = $user['username'];

        $metadataJson = json_encode($metadata, JSON_UNESCAPED_UNICODE);
        $secretEncrypted = $secret ? $this->encrypt($secret) : null;
        $stmt = $this->pdo->prepare('INSERT INTO projects (slug, name, owner, metadata, secret_data) VALUES (:slug, :name, :owner, :metadata, :secret)');
        $stmt->execute([
            ':slug' => $slug,
            ':name' => $name,
            ':owner' => $owner,
            ':metadata' => $metadataJson,
            ':secret' => $secretEncrypted
        ]);
        $id = (int)$this->pdo->lastInsertId();
        $this->audit($id, 'create', "created by {$owner}");
        return $id;
    }

    public function getProjectById(int $id, bool $decrypt = false): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM projects WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $user = $this->users->getCurrentUser();
        if (!$this->isAdmin($user) && $user['username'] !== $row['owner']) {
            throw new \DomainException('Forbidden');
        }
        $row['metadata'] = $row['metadata'] ? json_decode($row['metadata'], true) : null;
        if ($decrypt && $row['secret_data']) {
            $row['secret_data'] = $this->decrypt($row['secret_data']);
        }
        return $row;
    }

    public function listProjects(bool $all = false): array
    {
        $user = $this->users->getCurrentUser();
        if ($all && !$this->isAdmin($user)) {
            throw new \DomainException('Forbidden');
        }
        if ($all) {
            $stmt = $this->pdo->query('SELECT * FROM projects');
        } else {
            $stmt = $this->pdo->prepare('SELECT * FROM projects WHERE owner = :owner');
            $stmt->execute([':owner' => $user['username']]);
        }
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['metadata'] = $r['metadata'] ? json_decode($r['metadata'], true) : null;
            unset($r['secret_data']);
        }
        return $rows;
    }

    public function updateProjectSecret(int $id, string $secret): void
    {
        $project = $this->getProjectById($id);
        if (!$project) {
            throw new \InvalidArgumentException('Project not found');
        }
        $this->authorizeOwnerOrAdmin($project);
        $enc = $this->encrypt($secret);
        $stmt = $this->pdo->prepare('UPDATE projects SET secret_data = :secret, updated_at = datetime(\'now\') WHERE id = :id');
        $stmt->execute([':secret' => $enc, ':id' => $id]);
        $this->audit($id, 'update-secret', 'secret updated');
    }

    public function deleteProject(int $id): void
    {
        $project = $this->getProjectById($id);
        if (!$project) {
            throw new \InvalidArgumentException('Project not found');
        }
        $this->authorizeOwnerOrAdmin($project);
        $stmt = $this->pdo->prepare('DELETE FROM projects WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $this->audit($id, 'delete', 'project deleted');
    }

    private function isAdmin($user): bool
    {
        return isset($user['role']) && $user['role'] === 'admin';
    }

    private function authorizeOwnerOrAdmin(array $project): void
    {
        $user = $this->users->getCurrentUser();
        if (!$this->isAdmin($user) && $user['username'] !== $project['owner']) {
            throw new \DomainException('Forbidden');
        }
    }
}
