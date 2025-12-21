<?php

namespace App\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class KeyGenerateCommand extends Command
{
    protected static $defaultName = 'key:generate';
    protected static $defaultDescription = 'Uygulama anahtarı oluştur';

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        try {
            $key = $this->generateKey();
            
            $io->success('Uygulama anahtarı oluşturuldu');
            $io->text(['Anahtar: ' . $key]);
            
            // .env dosyasına yaz (varsa)
            $envPath = __DIR__ . '/../../../.env';
            if (file_exists($envPath)) {
                $envContent = file_get_contents($envPath);
                if (strpos($envContent, 'APP_KEY=') === false) {
                    file_put_contents($envPath, "\nAPP_KEY=" . $key . "\n", FILE_APPEND);
                    $io->info('Anahtar .env dosyasına eklendi');
                }
            }
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Hata: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
    
    private function generateKey(): string
    {
        return bin2hex(random_bytes(32));
    }
}

