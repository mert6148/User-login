<?php

namespace App\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class DbMigrateCommand extends Command
{
    protected static $defaultName = 'db:migrate';
    protected static $defaultDescription = 'Veritabanı migrationlarını çalıştır';

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        try {
            $io->title('Veritabanı Migration');
            
            $result = $this->runMigrations();
            
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
    
    private function runMigrations(): array
    {
        $dbPath = __DIR__ . '/../../../login_system.db';
        
        $db = new \PDO('sqlite:' . $dbPath);
        $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        
        // Users tablosu
        $db->exec('CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            salt TEXT NOT NULL,
            hash TEXT NOT NULL,
            email TEXT,
            full_name TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )');
        
        // Sessions tablosu
        $db->exec('CREATE TABLE IF NOT EXISTS sessions (
            id TEXT PRIMARY KEY,
            username TEXT NOT NULL,
            login_ts TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            logout_ts TIMESTAMP,
            FOREIGN KEY (username) REFERENCES users(username)
        )');
        
        // Admin permissions tablosu
        $db->exec('CREATE TABLE IF NOT EXISTS admin_permissions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            role TEXT NOT NULL,
            permissions TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )');
        
        return ['success' => true, 'message' => 'Migrationlar başarıyla çalıştırıldı'];
    }
}

