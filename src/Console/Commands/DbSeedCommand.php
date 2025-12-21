<?php

namespace App\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class DbSeedCommand extends Command
{
    protected static $defaultName = 'db:seed';
    protected static $defaultDescription = 'Veritabanını seed verileri ile doldur';

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        try {
            $io->title('Veritabanı Seed');
            
            $result = $this->seedDatabase();
            
            if ($result['success']) {
                $io->success($result['message']);
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
    
    private function seedDatabase(): array
    {
        $dbPath = __DIR__ . '/../../../login_system.db';
        
        $db = new \PDO('sqlite:' . $dbPath);
        $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        
        // Varsayılan admin kullanıcısı (eğer yoksa)
        $stmt = $db->prepare('SELECT id FROM users WHERE username = ?');
        $stmt->execute(['admin']);
        
        if (!$stmt->fetch()) {
            $salt = bin2hex(random_bytes(16));
            $hash = hash('sha256', $salt . 'admin123');
            
            $stmt = $db->prepare('INSERT INTO users (username, salt, hash, email, full_name) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute(['admin', $salt, $hash, 'admin@example.com', 'Admin User']);
            
            // Admin permissions
            $permissions = json_encode(['user:manage', 'logs:view', 'security:manage', 'admin:manage']);
            $stmt = $db->prepare('INSERT INTO admin_permissions (username, role, permissions) VALUES (?, ?, ?)');
            $stmt->execute(['admin', 'super_admin', $permissions]);
        }
        
        return ['success' => true, 'message' => 'Seed verileri başarıyla eklendi'];
    }
}

