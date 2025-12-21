<?php

namespace App\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class UserDeleteCommand extends Command
{
    protected static $defaultName = 'user:delete';
    protected static $defaultDescription = 'Kullanıcı sil';

    protected function configure(): void
    {
        $this
            ->addArgument('username', InputArgument::REQUIRED, 'Silinecek kullanıcı adı')
            ->setHelp('Bu komut bir kullanıcıyı siler.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $username = $input->getArgument('username');
        
        // Onay iste
        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion(
            sprintf('Kullanıcı "%s" silinsin mi? (evet/hayır): ', $username),
            false
        );
        
        if (!$helper->ask($input, $output, $question)) {
            $io->info('İşlem iptal edildi');
            return Command::SUCCESS;
        }
        
        try {
            $result = $this->deleteUser($username);
            
            if ($result['success']) {
                $io->success(sprintf('Kullanıcı başarıyla silindi: %s', $username));
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
    
    private function deleteUser(string $username): array
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
        if (!$stmt->fetch()) {
            return ['success' => false, 'message' => 'Kullanıcı bulunamadı'];
        }
        
        // Kullanıcıyı sil
        $stmt = $db->prepare('DELETE FROM users WHERE username = ?');
        $stmt->execute([$username]);
        
        return ['success' => true, 'message' => 'Kullanıcı silindi'];
    }
}

