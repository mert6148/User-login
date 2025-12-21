<?php

namespace App\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ConfigCacheCommand extends Command
{
    protected static $defaultName = 'config:cache';
    protected static $defaultDescription = 'Konfigürasyon dosyalarını cache\'le';

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        try {
            $this->cacheConfig();
            $io->success('Konfigürasyon başarıyla cache\'lendi');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Hata: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
    
    private function cacheConfig(): void
    {
        // Konfigürasyon cache'leme işlemi
        $configPath = __DIR__ . '/../../../config';
        $cachePath = __DIR__ . '/../../../bootstrap/cache';
        
        if (!is_dir($cachePath)) {
            mkdir($cachePath, 0755, true);
        }
        
        // Örnek: config dosyalarını serialize edip cache'e yaz
        $config = [
            'app_name' => 'User Login System',
            'version' => '1.0.0',
            'cache_time' => time()
        ];
        
        file_put_contents($cachePath . '/config.php', '<?php return ' . var_export($config, true) . ';');
    }
}

