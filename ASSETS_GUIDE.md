# Kullanıcı Varlıkları (User Assets) - Yönetim Rehberi

## Genel Bakış

Kullanıcı varlıkları (assets) sistemi, login/logout olaylarının ötesinde, kullanıcılar hakkında yapılandırılabilir meta-veri saklayabilen esnek bir framework sağlar. Her varlık kategorize edilmiş, türe sahip ve zaman damgası ile takip edilir.

### Temel Özellikler

- **5 Kategoriye Ayrılmış**: profile, preferences, security, system, custom
- **6 Veri Türü**: string, integer, boolean, json, binary, file
- **Veritabanı Destekli**: SQLite `user_attributes` tablosunda kalıcı depolama
- **CLI Entegre**: argparse aracılığıyla 5 yeni komut: `set-asset`, `get-asset`, `show-assets`, `delete-asset`
- **REST API Ready**: Flask API endpoints tüm operasyonları destekler
- **Python Modülü**: `assets/assest.py` UserAssetManager sınıfı ile

---

## CLI Komutları

### 1. set-asset - Varlık Ayarla

Kullanıcının bir varlığını ayarlar (oluştur veya güncelle).

**Söz Dizimi:**
```bash
python print.py set-asset <username> <asset_name> <asset_value> [--type TYPE] [--category CATEGORY]
```

**Parametreler:**
- `username`: Hedef kullanıcı adı
- `asset_name`: Varlık adı (örn: "theme", "timezone")
- `asset_value`: Varlık değeri
- `--type` / `-t`: Veri türü (varsayılan: "string")
  - Seçenekler: string, integer, boolean, json, binary, file
- `--category` / `-c`: Varlık kategorisi (varsayılan: "custom")
  - Seçenekler: profile, preferences, security, system, custom

**Örnekler:**
```bash
# Basit string varlık (varsayılan)
python print.py set-asset john theme dark

# Tercih kategorisinde
python print.py set-asset john theme dark -c preferences

# Integer türü
python print.py set-asset john login_count 42 -t integer -c system

# Boolean türü
python print.py set-asset john two_factor_enabled true -t boolean -c security
```

---

### 2. get-asset - Varlık Al

Kullanıcının belirli bir varlığını alır ve JSON formatında gösterir.

**Söz Dizimi:**
```bash
python print.py get-asset <username> <asset_name>
```

**Çıktı:**
```json
{
  "asset_name": "theme",
  "asset_value": "dark",
  "asset_type": "string",
  "category": "preferences",
  "description": "theme varlığı",
  "created_at": "2025-11-25 19:19:08",
  "updated_at": "2025-11-25 19:19:08"
}
```

**Örnek:**
```bash
python print.py get-asset john theme
```

---

### 3. show-assets - Tüm Varlıkları Göster

Kullanıcının tüm varlıklarını kategoriye göre sıralanmış şekilde gösterir.

**Söz Dizimi:**
```bash
python print.py show-assets <username> [--category CATEGORY]
```

**Parametreler:**
- `username`: Hedef kullanıcı adı
- `--category` / `-c`: (Opsiyonel) Belirli kategoriyi filtrele

**Çıktı:** Kategoriye göre yapılandırılmış JSON

**Örnekler:**
```bash
# Tüm varlıkları göster
python print.py show-assets john

# Sadece tercih varlıklarını göster
python print.py show-assets john -c preferences
```

---

### 4. delete-asset - Varlık Sil

Kullanıcının belirli bir varlığını siler.

**Söz Dizimi:**
```bash
python print.py delete-asset <username> <asset_name>
```

**Örnek:**
```bash
python print.py delete-asset john login_count
```

---

## Varlık Kategorileri

### profile (Profil)
Kullanıcının temel kişisel bilgileri.

**Örnek varlıklar:**
- first_name: Adı
- last_name: Soyadı
- email: E-posta adresi
- phone: Telefon numarası
- avatar_url: Avatar URL'si

---

### preferences (Tercihler)
Kullanıcı kişiselleştirme seçenekleri.

**Örnek varlıklar:**
- theme: Tema (dark, light)
- language: Dil kodu (tr, en)
- timezone: Zaman dilimi
- font_size: Yazı tipi boyutu
- notifications_enabled: Bildirimler açık/kapalı

---

### security (Güvenlik)
Güvenlik ve kimlik doğrulama bilgileri.

**Örnek varlıklar:**
- two_factor_enabled: 2FA etkin mi
- last_password_change: Son şifre değişikliği
- ip_whitelist: İzin verilen IP'ler
- security_questions: Güvenlik soruları

---

### system (Sistem)
Sistem tarafından yönetilen meta-veriler.

**Örnek varlıklar:**
- login_count: Toplam giriş sayısı
- total_sessions: Toplam oturum sayısı
- last_activity: Son etkinlik zamanı
- failed_login_attempts: Başarısız giriş sayısı

---

### custom (Özel)
Uygulamaya özel varlıklar.

**Örnek varlıklar:**
- department: Departman
- manager_id: Yönetici kimliği
- project_assignment: Proje ataması

---

## Veri Türleri

| Tür | Açıklama | Örnek |
|-----|----------|-------|
| string | Metin | "dark", "John Smith" |
| integer | Tam sayı | 42, 156 |
| boolean | Doğru/Yanlış | true, false |
| json | JSON nesnesi | {"key": "value"} |
| binary | İkili veri | (base64 kodlanmış) |
| file | Dosya yolu | "/path/to/file" |

---

## Kullanım Örnekleri

### Senaryo 1: Yeni Kullanıcı Profili Oluşturma

```bash
# Kullanıcı oluştur
python print.py add-user sarah password123 -f "Sarah Johnson"

# Profil varlıklarını ayarla
python print.py set-asset sarah first_name Sarah -c profile
python print.py set-asset sarah last_name Johnson -c profile
python print.py set-asset sarah email sarah@example.com -c profile

# Tercihlerini ayarla
python print.py set-asset sarah theme light -c preferences
python print.py set-asset sarah language en -c preferences
python print.py set-asset sarah timezone America/New_York -c preferences

# Güvenlik ayarlarını ayarla
python print.py set-asset sarah two_factor_enabled true -t boolean -c security

# Profili görüntüle
python print.py show-assets sarah
```

### Senaryo 2: Kullanıcı Etkinliğini İzleme

```bash
# Sistem varlıklarını güncelle
python print.py set-asset john login_count 42 -t integer -c system
python print.py set-asset john total_sessions 156 -t integer -c system
python print.py set-asset john last_activity "2025-11-25 19:30:00" -c system

# Sistem varlıklarını kontrol et
python print.py show-assets john -c system
```

### Senaryo 3: Başarılı Giriş Sonrası Varlık Güncelleme

```bash
# Kullanıcı giriş yaptı - sistemi güncelle
python print.py login john -p password123

# Sonra:
python print.py set-asset john last_activity "2025-11-25 20:00:00" -c system
python print.py set-asset john login_count 43 -t integer -c system
```

---

## Python API (Programmatic Kullanım)

### UserAssetManager Sınıfı

```python
from assets.assest import UserAssetManager

# Manager'ı başlat
manager = UserAssetManager("login_system.db")

# Varlık ayarla
manager.set_asset(user_id, "theme", "dark", "string", "preferences")

# Varlık al
asset = manager.get_asset(user_id, "theme")
if asset:
    print(f"Değer: {asset.asset_value}")

# Kategoriye göre al
prefs = manager.get_assets_by_category(user_id, "preferences")
for name, asset in prefs.items():
    print(f"{name}: {asset.asset_value}")

# Tüm varlıkları al
all_assets = manager.get_all_assets(user_id)
for category, assets_dict in all_assets.items():
    print(f"{category}: {len(assets_dict)} varlık")

# Varlık sil
manager.delete_asset(user_id, "theme")

# Tüm varlıkları sil
manager.delete_all_assets(user_id)
```

---

## REST API Endpointleri

### POST /api/v1/users/{username}/attributes

Kullanıcıya varlık ekle.

```bash
curl -X POST http://localhost:5000/api/v1/users/john/attributes \
  -H "Content-Type: application/json" \
  -d '{
    "asset_name": "theme",
    "asset_value": "dark",
    "asset_type": "string",
    "category": "preferences"
  }'
```

### GET /api/v1/users/{username}/attributes

Kullanıcının varlıklarını al.

```bash
curl http://localhost:5000/api/v1/users/john/attributes
```

### GET /api/v1/users/{username}/attributes/{asset_name}

Belirli bir varlığı al.

```bash
curl http://localhost:5000/api/v1/users/john/attributes/theme
```

### DELETE /api/v1/users/{username}/attributes/{asset_name}

Varlığı sil.

```bash
curl -X DELETE http://localhost:5000/api/v1/users/john/attributes/theme
```

---

## Veritabanı Şeması

Tüm varlıklar SQLite `user_attributes` tablosunda saklanır:

```sql
CREATE TABLE user_attributes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    asset_name TEXT NOT NULL,
    asset_value TEXT,
    asset_type TEXT DEFAULT 'string',
    category TEXT DEFAULT 'custom',
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE(user_id, asset_name)
);

CREATE INDEX idx_user_attributes_user_id ON user_attributes(user_id);
CREATE INDEX idx_user_attributes_category ON user_attributes(category);
```

---

## Hata Yönetimi

### Yaygın Hatalar ve Çözümleri

**"Kullanıcı bulunamadı"**
- Kullanıcı adının doğru olduğunu kontrol edin
- Önce `python print.py list-users` ile kullanıcıları listeleyin

**"Varlık bulunamadı"**
- Varlık adı doğru yazıldığından emin olun (büyük/küçük harf duyarlı)
- Varlığın mevcut olduğunu kontrol etmek için `show-assets` kullanın

**"Hata: Assets modülü mevcut değil"**
- `assets/assest.py` dosyasının mevcut olduğunu kontrol edin
- Python ortamını yeniden başlatmayı deneyin

---

## Test

Kapsamlı test paketi `test_assets.py` içinde bulunur:

```bash
python test_assets.py
```

Test şu işlemleri doğrular:
- Profil varlıkları oluşturma ve alma
- Tercih varlıkları ayarlama
- Güvenlik varlıkları yönetimi
- Sistem varlıklarını güncelleme
- Kategoriye göre filtreleme
- Varlık silme

---

## En İyi Uygulamalar

1. **Kategori Kullanımı**: Varlıkları uygun kategorilere atayın; bu sorguları hızlandırır
2. **Tutarlı Adlandırma**: Varlık adlarında tutarlı bir şema kullanın (snake_case)
3. **Veri Türleri**: Başlangıçta doğru veri türünü seçin, güncellemeler tür korur
4. **Güvenlik**: `security` kategorisini hassas bilgiler için kullanın
5. **Sistem Meta**: Otomatik işlemler için `system` kategorisini kullanın

---

## Gelecek Geliştirmeler

- [ ] Varlık şablonları ve varsayılan değerleri
- [ ] Toplu işlemler (bulk operations)
- [ ] Varlık versiyonlama
- [ ] Varlık erişim denetimi (ACL)
- [ ] Dış kaynaklardan varlık senkronizasyonu
- [ ] Varlık iş akışı ve onay sistemi

---

## İletişim ve Destek

Sorularınız veya önerileriseniz, lütfen projede issue açın veya pull request gönderin.

GitHub: https://github.com/mert6148/User-login

---

**Son Güncelleme**: 25 Kasım 2025  
**Versiyon**: 1.0
