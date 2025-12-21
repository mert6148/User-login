<?php

namespace App\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class UserListCommand extends Command
{
    protected static $defaultName = 'user:list';
    protected static $defaultDescription = 'Tüm kullanıcıları listele';

    protected function configure(): void
    {
        $this->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Maksimum kayıt sayısı', 50);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $limit = (int)$input->getOption('limit');
        
        try {
            $users = $this->getUsers($limit);
            
            if (empty($users)) {
                $io->warning('Hiç kullanıcı bulunamadı');
                return Command::SUCCESS;
            }
            
            $rows = [];
            foreach ($users as $user) {
                $rows[] = [
                    $user['username'],
                    $user['email'] ?? '-',
                    $user['full_name'] ?? '-',
                    $user['created_at'] ?? '-'
                ];
            }
            
            $io->table(
                ['Kullanıcı Adı', 'E-posta', 'Tam Ad', 'Oluşturulma Tarihi'],
                $rows
            );
            
            $io->info(sprintf('Toplam %d kullanıcı gösteriliyor', count($users)));
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Hata: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
    
    private function getUsers(int $limit): array
    {
        $dbPath = __DIR__ . '/../../../login_system.db';
        
        if (!file_exists($dbPath)) {
            return [];
        }
        
        $db = new \PDO('sqlite:' . $dbPath);
        $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        
        $stmt = $db->prepare('SELECT username, email, full_name, created_at FROM users ORDER BY created_at DESC LIMIT ?');
        $stmt->execute([$limit]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}

