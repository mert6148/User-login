<?php

namespace App\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class UserCreateCommand extends Command
{
    protected static $defaultName = 'user:create';
    protected static $defaultDescription = 'Yeni kullanıcı oluştur';

    protected function configure(): void
    {
        $this
            ->addArgument('username', InputArgument::REQUIRED, 'Kullanıcı adı')
            ->addArgument('password', InputArgument::REQUIRED, 'Şifre')
            ->addOption('email', 'e', InputOption::VALUE_OPTIONAL, 'E-posta adresi')
            ->addOption('full-name', 'f', InputOption::VALUE_OPTIONAL, 'Tam ad')
            ->setHelp('Bu komut yeni bir kullanıcı oluşturur.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $username = $input->getArgument('username');
        $password = $input->getArgument('password');
        $email = $input->getOption('email');
        $fullName = $input->getOption('full-name');
        
        try {
            $result = $this->createUser($username, $password, $email, $fullName);
            
            if ($result['success']) {
                $io->success(sprintf('Kullanıcı başarıyla oluşturuldu: %s', $username));
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
    
    private function createUser(string $username, string $password, ?string $email, ?string $fullName): array
    {
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
        
        // Email ve full_name ekle (varsa)
        if ($email || $fullName) {
            $updates = [];
            $params = [];
            
            if ($email) {
                $updates[] = 'email = ?';
                $params[] = $email;
            }
            if ($fullName) {
                $updates[] = 'full_name = ?';
                $params[] = $fullName;
            }
            
            $params[] = $username;
            $stmt = $db->prepare('UPDATE users SET ' . implode(', ', $updates) . ' WHERE username = ?');
            $stmt->execute($params);
        }
        
        return ['success' => true, 'message' => 'Kullanıcı oluşturuldu'];
    }
}

