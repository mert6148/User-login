<?php

namespace App\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class AdminListCommand extends Command
{
    protected static $defaultName = 'admin:list';
    protected static $defaultDescription = 'Tüm admin kullanıcılarını listele';

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        try {
            $admins = $this->getAdmins();
            
            if (empty($admins)) {
                $io->warning('Hiç admin kullanıcısı bulunamadı');
                return Command::SUCCESS;
            }
            
            $rows = [];
            foreach ($admins as $admin) {
                $rows[] = [
                    $admin['username'],
                    $admin['role'],
                    $admin['email'] ?? '-',
                    $admin['created_at']
                ];
            }
            
            $io->table(
                ['Kullanıcı Adı', 'Rol', 'E-posta', 'Oluşturulma Tarihi'],
                $rows
            );
            
            $io->info(sprintf('Toplam %d admin kullanıcısı bulundu', count($admins)));
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Hata: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
    
    private function getAdmins(): array
    {
        $adminDbPath = __DIR__ . '/../../../login_system.db';
        
        if (!file_exists($adminDbPath)) {
            return [];
        }
        
        $db = new \PDO('sqlite:' . $adminDbPath);
        $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        
        // Admin permissions tablosunu oluştur (yoksa)
        $db->exec('CREATE TABLE IF NOT EXISTS admin_permissions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            role TEXT NOT NULL,
            permissions TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )');
        
        $stmt = $db->query('SELECT username, role, created_at FROM admin_permissions ORDER BY created_at DESC');
        $admins = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Email bilgilerini ekle
        $userDb = new \PDO('sqlite:' . $adminDbPath);
        foreach ($admins as &$admin) {
            $stmt = $userDb->prepare('SELECT email FROM users WHERE username = ?');
            $stmt->execute([$admin['username']]);
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);
            $admin['email'] = $user['email'] ?? null;
        }
        
        return $admins;
    }
}

