<?php

namespace App\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class AdminCreateCommand extends Command
{
    protected static $defaultName = 'admin:create';
    protected static $defaultDescription = 'Yeni admin kullanıcısı oluştur';

    protected function configure(): void
    {
        $this
            ->addArgument('username', InputArgument::REQUIRED, 'Admin kullanıcı adı')
            ->addArgument('password', InputArgument::REQUIRED, 'Admin şifresi')
            ->addOption('role', 'r', InputOption::VALUE_OPTIONAL, 'Rol (admin, super_admin)', 'admin')
            ->addOption('email', 'e', InputOption::VALUE_OPTIONAL, 'E-posta adresi')
            ->setHelp('Bu komut yeni bir admin kullanıcısı oluşturur.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $username = $input->getArgument('username');
        $password = $input->getArgument('password');
        $role = $input->getOption('role');
        $email = $input->getOption('email');
        
        try {
            // Admin oluşturma işlemi
            $result = $this->createAdmin($username, $password, $role, $email);
            
            if ($result['success']) {
                $io->success(sprintf('Admin kullanıcısı başarıyla oluşturuldu: %s (Rol: %s)', $username, $role));
                return Command::SUCCESS;
            } else {
                $io->error($result['message']);
                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $io->error('Hata: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
    
    private function createAdmin(string $username, string $password, string $role, ?string $email): array
    {
        // Veritabanı bağlantısı
        $dbPath = __DIR__ . '/../../../login_system.db';
        
        if (!file_exists($dbPath)) {
            return ['success' => false, 'message' => 'Veritabanı bulunamadı'];
        }
        
        $db = new \PDO('sqlite:' . $dbPath);
        $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        
        // Kullanıcı kontrolü
        $stmt = $db->prepare('SELECT id FROM users WHERE username = ?');
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'Kullanıcı zaten mevcut'];
        }
        
        // Şifre hashleme
        $salt = bin2hex(random_bytes(16));
        $hash = hash('sha256', $salt . $password);
        
        // Kullanıcı oluştur
        $stmt = $db->prepare('INSERT INTO users (username, salt, hash, created_at) VALUES (?, ?, ?, datetime("now"))');
        $stmt->execute([$username, $salt, $hash]);
        
        // Admin permissions tablosuna ekle
        $adminDbPath = __DIR__ . '/../../../login_system.db';
        $adminDb = new \PDO('sqlite:' . $adminDbPath);
        $adminDb->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        
        // Admin permissions tablosunu oluştur (yoksa)
        $adminDb->exec('CREATE TABLE IF NOT EXISTS admin_permissions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            role TEXT NOT NULL,
            permissions TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )');
        
        $permissions = json_encode(['user:manage', 'logs:view', 'security:manage']);
        $stmt = $adminDb->prepare('INSERT OR REPLACE INTO admin_permissions (username, role, permissions) VALUES (?, ?, ?)');
        $stmt->execute([$username, $role, $permissions]);
        
        // Email ekle (varsa)
        if ($email) {
            $stmt = $db->prepare('UPDATE users SET email = ? WHERE username = ?');
            $stmt->execute([$email, $username]);
        }
        
        return ['success' => true, 'message' => 'Admin oluşturuldu'];
    }
}

