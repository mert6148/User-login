<?php

namespace App\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class DbStatusCommand extends Command
{
    protected static $defaultName = 'db:status';
    protected static $defaultDescription = 'Veritabanı durumunu göster';

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        try {
            $status = $this->getDatabaseStatus();
            
            $io->title('Veritabanı Durumu');
            
            $io->table(
                ['Özellik', 'Değer'],
                [
                    ['Veritabanı Dosyası', $status['db_file']],
                    ['Dosya Boyutu', $status['file_size']],
                    ['Kullanıcı Sayısı', $status['user_count']],
                    ['Admin Sayısı', $status['admin_count']],
                    ['Session Sayısı', $status['session_count']],
                    ['Durum', $status['status']]
                ]
            );
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Hata: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
    
    private function getDatabaseStatus(): array
    {
        $dbPath = __DIR__ . '/../../../login_system.db';
        
        $status = [
            'db_file' => $dbPath,
            'file_size' => file_exists($dbPath) ? $this->formatBytes(filesize($dbPath)) : 'Bulunamadı',
            'user_count' => 0,
            'admin_count' => 0,
            'session_count' => 0,
            'status' => file_exists($dbPath) ? 'Aktif' : 'Bulunamadı'
        ];
        
        if (!file_exists($dbPath)) {
            return $status;
        }
        
        $db = new \PDO('sqlite:' . $dbPath);
        $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        
        // Kullanıcı sayısı
        $stmt = $db->query('SELECT COUNT(*) FROM users');
        $status['user_count'] = $stmt->fetchColumn();
        
        // Admin sayısı
        try {
            $stmt = $db->query('SELECT COUNT(*) FROM admin_permissions');
            $status['admin_count'] = $stmt->fetchColumn();
        } catch (\Exception $e) {
            $status['admin_count'] = 0;
        }
        
        // Session sayısı
        try {
            $stmt = $db->query('SELECT COUNT(*) FROM sessions WHERE logout_ts IS NULL');
            $status['session_count'] = $stmt->fetchColumn();
        } catch (\Exception $e) {
            $status['session_count'] = 0;
        }
        
        return $status;
    }
    
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}

