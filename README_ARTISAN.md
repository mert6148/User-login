# Artisan CLI - PHP Komut Satırı Aracı

Laravel tarzı Artisan CLI aracı ile PHP kodlarınızı kolayca yönetebilirsiniz.

## Kurulum

```bash
# Composer bağımlılıklarını yükle
composer install

# Artisan dosyasını çalıştırılabilir yap (Linux/macOS)
chmod +x artisan
```

## Kullanım

### Genel Kullanım
```bash
php artisan [komut] [argümanlar] [seçenekler]
```

veya Composer script ile:
```bash
composer run artisan -- [komut]
```

## Komutlar

### Admin Komutları

#### Admin Oluştur
```bash
php artisan admin:create <username> <password> [--role=admin] [--email=email@example.com]
```

Örnek:
```bash
php artisan admin:create admin admin123 --role=super_admin --email=admin@example.com
```

#### Admin Listele
```bash
php artisan admin:list
```

### Kullanıcı Komutları

#### Kullanıcı Oluştur
```bash
php artisan user:create <username> <password> [--email=email@example.com] [--full-name="Tam Ad"]
```

Örnek:
```bash
php artisan user:create john password123 --email=john@example.com --full-name="John Doe"
```

#### Kullanıcı Listele
```bash
php artisan user:list [--limit=50]
```

#### Kullanıcı Sil
```bash
php artisan user:delete <username>
```

Örnek:
```bash
php artisan user:delete john
```

### Veritabanı Komutları

#### Migration Çalıştır
```bash
php artisan db:migrate
```

Veritabanı tablolarını oluşturur veya günceller.

#### Seed Verileri Ekle
```bash
php artisan db:seed
```

Varsayılan admin kullanıcısı ve seed verilerini ekler.

#### Veritabanı Durumu
```bash
php artisan db:status
```

Veritabanı dosyası, boyutu, kullanıcı sayısı gibi bilgileri gösterir.

### Cache Komutları

#### Cache Temizle
```bash
php artisan cache:clear
```

Tüm cache dosyalarını temizler.

#### Config Cache
```bash
php artisan config:cache
```

Konfigürasyon dosyalarını cache'ler.

### Sistem Komutları

#### Anahtar Oluştur
```bash
php artisan key:generate
```

Uygulama için güvenli bir anahtar oluşturur.

#### Route Listele
```bash
php artisan route:list
```

Tüm route'ları listeler.

#### Optimize Et
```bash
php artisan optimize
```

Uygulamayı optimize eder (cache, autoload).

## Komut Listesi

Tüm komutları görmek için:
```bash
php artisan list
```

Belirli bir komutun yardımını görmek için:
```bash
php artisan help [komut]
```

Örnek:
```bash
php artisan help admin:create
```

## Örnekler

### İlk Kurulum
```bash
# 1. Veritabanı migration
php artisan db:migrate

# 2. Seed verileri ekle
php artisan db:seed

# 3. İlk admin kullanıcısı oluştur
php artisan admin:create admin admin123 --role=super_admin

# 4. Uygulama anahtarı oluştur
php artisan key:generate

# 5. Optimize et
php artisan optimize
```

### Günlük Kullanım
```bash
# Yeni kullanıcı ekle
php artisan user:create alice password123 --email=alice@example.com

# Kullanıcıları listele
php artisan user:list --limit=20

# Cache temizle
php artisan cache:clear

# Veritabanı durumunu kontrol et
php artisan db:status
```

## Komut Geliştirme

Yeni komut eklemek için:

1. `src/Console/Commands/` dizininde yeni bir komut sınıfı oluşturun:
```php
<?php

namespace App\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MyCustomCommand extends Command
{
    protected static $defaultName = 'my:command';
    protected static $defaultDescription = 'Açıklama';

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Komut mantığı
        return Command::SUCCESS;
    }
}
```

2. `src/Console/Kernel.php` dosyasına komutu ekleyin:
```php
$app->add(new MyCustomCommand());
```

## Sorun Giderme

### "Class not found" Hatası
```bash
composer dump-autoload
```

### Veritabanı Bulunamadı
```bash
php artisan db:migrate
```

### Permission Denied (Linux/macOS)
```bash
chmod +x artisan
```

## Dosya Yapısı

```
.
├── artisan                    # Ana CLI dosyası
├── src/
│   └── Console/
│       ├── Kernel.php        # Komut kayıt sistemi
│       └── Commands/          # Komut sınıfları
│           ├── AdminCreateCommand.php
│           ├── AdminListCommand.php
│           ├── UserCreateCommand.php
│           ├── UserListCommand.php
│           ├── UserDeleteCommand.php
│           ├── DbMigrateCommand.php
│           ├── DbSeedCommand.php
│           ├── DbStatusCommand.php
│           ├── CacheClearCommand.php
│           ├── ConfigCacheCommand.php
│           ├── KeyGenerateCommand.php
│           ├── RouteListCommand.php
│           └── OptimizeCommand.php
└── composer.json
```

## Lisans

Bu proje MIT lisansı altında lisanslanmıştır.

