# API ve UI Kurulumu

## Flask REST API Sunucusu

### Gereksinimler
- Python 3.8+
- Flask 2.3+
- Flask-CORS

### Kurulum

```bash
# 1. Bağımlılıkları yükle
pip install -r requirements_api.txt

# 2. API sunucusunu başlat
python api_server.py
```

Sunucu `http://localhost:5000` adresinde çalışacak.

### API Endpoints

#### Health Check
- `GET /api/v1/health` — Sunucu durumu kontrol et

#### Kullanıcı Yönetimi
- `GET /api/v1/users` — Tüm kullanıcıları listele
- `POST /api/v1/users` — Yeni kullanıcı oluştur
- `DELETE /api/v1/users/<username>` — Kullanıcı sil

#### Kimlik Doğrulama
- `POST /api/v1/auth/login` — Giriş yap
- `POST /api/v1/auth/logout` — Çıkış yap

#### Kullanıcı Özellikleri
- `GET /api/v1/users/<username>/attributes` — Tüm özellikleri al
- `GET /api/v1/users/<username>/attributes/<attribute_name>` — Spesifik özelliği al
- `POST /api/v1/users/<username>/attributes` — Özellik ayarla
- `DELETE /api/v1/users/<username>/attributes/<attribute_name>` — Özelliği sil

#### Oturumlar
- `GET /api/v1/sessions` — Tüm oturumları listele
- `POST /api/v1/sessions` — Yeni oturum oluştur
- `POST /api/v1/sessions/<session_id>` — Oturumu sonlandır

#### Loglar
- `GET /api/v1/logs` — Giriş/çıkış loglarını al

### Örnek İstekler

#### Giriş
```bash
curl -X POST http://localhost:5000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"1234"}'
```

Yanıt:
```json
{
  "success": true,
  "message": "Login successful",
  "username": "admin",
  "session_id": "02a31bdf-aad7-4868-afd0-9f2d4ae2b47"
}
```

#### Özellik Ayarla
```bash
curl -X POST http://localhost:5000/api/v1/users/admin/attributes \
  -H "Content-Type: application/json" \
  -d '{"attribute_name":"theme","attribute_value":"dark","attribute_type":"string"}'
```

#### Özellik Al
```bash
curl -X GET http://localhost:5000/api/v1/users/admin/attributes/theme
```

#### Tüm Özellikleri Al
```bash
curl -X GET http://localhost:5000/api/v1/users/admin/attributes
```

---

## Windows Forms UI Uygulaması

### Gereksinimler
- Windows 10+
- .NET Framework 4.7.2+
- Visual Studio 2019+ (geliştirme için)

### Kurulum

#### Opsiyon 1: Visual Studio ile
1. `UserLoginUI.cs` dosyasını Visual Studio'da açın
2. Yeni bir Windows Forms Application projesi oluşturun
3. Kodu projeye yapıştırın
4. Derleme ve çalıştırma

#### Opsiyon 2: Komut Satırı ile
```bash
# Proje dosyası oluştur (.csproj)
dotnet new winforms -n UserLoginUI

# Bağımlılıkları ekle (gerekirse)
dotnet add package System.Net.Http

# Derleme
dotnet build

# Çalıştırma
dotnet run
```

### Özellikler
- **Giriş Formları**: Kullanıcı adı ve şifre ile giriş
- **Çıkış**: Aktif oturumu sonlandır
- **Kullanıcı Özellikleri**: Kullanıcı özelliklerini yönet ve görüntüle
- **REST API İntegrasyonu**: Flask API sunucusu ile iletişim

### Kullanım

1. Flask API sunucusunu başlat: `python api_server.py`
2. Windows Forms uygulamasını çalıştır
3. Kullanıcı adı ve şifre gir
4. "Giriş Yap" butonuna tıkla
5. Başarılı olursa oturum ID gösterilir

---

## Entegrasyon Notları

- **API Sunucusu**: Port 5000 üzerinde çalışır (localhost:5000)
- **CORS**: Tüm originlere izin verir (development için)
- **Veritabanı**: SQLite (`login_system.db`) otomatik oluşturulur
- **Log Dosyaları**: `login_log.txt` (JSON-lines format)

---

## Sorun Giderme

**API sunucusu başlamıyor:**
```bash
# pip paketlerini yükle
pip install flask flask-cors

# print.py dosyasının aynı dizinde olduğunu kontrol et
# API sunucusunu yeniden çalıştır
python api_server.py
```

**UI bağlanamıyor:**
- Flask API sunucusunun çalıştığını kontrol et
- localhost:5000 adresine browser'dan erişip test et
- Windows Firewall konfigürasyonunu kontrol et

---

## Sonraki Adımlar

- JWT token tabanlı kimlik doğrulama ekle
- Database'e login logs yaz
- Advanced UI özellikleri (dashboard, raporlar)
- Mobile API (iOS/Android uygulaması)
