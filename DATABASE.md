# Veritabanı Şeması Dokümantasyonu

## Genel Bakış

Bu proje, kullanıcı giriş/çıkış sistemi için bir ilişkisel veritabanı şeması sağlar. Şema SQLite, PostgreSQL ve MySQL ile uyumludur.

## Tablolar

### 1. `users` — Kullanıcı Tablosu

Ana kullanıcı bilgilerini saklar.

| Sütun | Tür | Kısıtlama | Açıklama |
|-------|-----|----------|---------|
| `id` | INTEGER | PRIMARY KEY, AUTO_INCREMENT | Kullanıcı ID'si |
| `username` | TEXT | UNIQUE, NOT NULL | Kullanıcı adı |
| `salt` | TEXT | NOT NULL | PBKDF2 salt (hex) |
| `hash` | TEXT | NOT NULL | PBKDF2 hash (hex) |
| `full_name` | TEXT | NULLABLE | Ad-Soyad |
| `email` | TEXT | UNIQUE, NULLABLE | E-posta adresi |
| `phone` | TEXT | NULLABLE | Telefon numarası |
| `created_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | Oluşturma tarihi |
| `updated_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | Güncellenme tarihi |
| `last_login` | TIMESTAMP | NULLABLE | Son giriş tarihi |
| `is_active` | BOOLEAN | DEFAULT 1 | Hesap aktif mi? |
| `is_admin` | BOOLEAN | DEFAULT 0 | Admin yetkisi |
| `notes` | TEXT | NULLABLE | Notlar |

**Örnek:**
```sql
INSERT INTO users (username, salt, hash, full_name, email, is_admin)
VALUES ('alice', 'a1b2c3...', 'e3b0c4...', 'Alice Example', 'alice@example.com', 0);
```

---

### 2. `user_attributes` — Kullanıcı Faktörleri Tablosu

Kullanıcı özelliklerini ve meta verileri depolar (department, role, theme, timezone, vb.).

| Sütun | Tür | Kısıtlama | Açıklama |
|-------|-----|----------|---------|
| `id` | INTEGER | PRIMARY KEY, AUTO_INCREMENT | Özellik ID'si |
| `user_id` | INTEGER | FOREIGN KEY, NOT NULL | Kullanıcı ID'si |
| `attribute_name` | TEXT | NOT NULL | Özellik adı (department, role, theme, ...) |
| `attribute_value` | TEXT | NULLABLE | Özellik değeri |
| `attribute_type` | TEXT | DEFAULT 'string' | Veri türü (string, integer, boolean, json) |
| `created_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | Oluşturma tarihi |
| `updated_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | Güncellenme tarihi |

**Benzersizlik:** `UNIQUE(user_id, attribute_name)` — her kullanıcı için her özellik adı benzersiz.

**Örnek:**
```sql
INSERT INTO user_attributes (user_id, attribute_name, attribute_value, attribute_type)
VALUES 
  (1, 'department', 'IT', 'string'),
  (1, 'role', 'admin', 'string'),
  (1, 'theme', 'dark', 'string'),
  (1, 'timezone', 'Europe/Istanbul', 'string'),
  (1, 'two_factor_enabled', 'true', 'boolean'),
  (1, 'login_attempts', '0', 'integer');
```

**Yaygın Faktörler:**
- `department` — Bölüm (IT, HR, Finance, ...)
- `role` — Rol (admin, user, manager, ...)
- `theme` — UI teması (light, dark, ...)
- `locale` — Dil/Locale (tr_TR, en_US, ...)
- `timezone` — Zaman dilimi (Europe/Istanbul, ...)
- `notification_level` — Bildirim seviyesi (high, medium, low)
- `two_factor_enabled` — 2FA etkin mi?
- `password_expires_at` — Parola son geçerlilik tarihi
- `login_attempts` — Başarısız giriş sayacı
- `account_locked` — Hesap kilitli mi?

---

### 3. `sessions` — Oturum Tablosu

Aktif ve geçmiş oturumları saklar.

| Sütun | Tür | Kısıtlama | Açıklama |
|-------|-----|----------|---------|
| `id` | TEXT | PRIMARY KEY | Oturum UUID'si |
| `user_id` | INTEGER | FOREIGN KEY, NOT NULL | Kullanıcı ID'si |
| `login_ts` | TIMESTAMP | NOT NULL | Giriş zamanı |
| `logout_ts` | TIMESTAMP | NULLABLE | Çıkış zamanı (NULL = aktif) |
| `system_info` | TEXT | NULLABLE | Sistem bilgileri (JSON) |
| `code_dirs` | TEXT | NULLABLE | Kod dizini bilgileri (JSON) |
| `ip_address` | TEXT | NULLABLE | IP adresi |
| `user_agent` | TEXT | NULLABLE | User Agent string'i |
| `created_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | Kayıt oluşturma tarihi |

**Örnek:**
```sql
INSERT INTO sessions (id, user_id, login_ts, system_info)
VALUES ('02a31bdf-aad7-4868-afd0-9f2d4ae2b47', 1, '2025-11-25 21:52:07', '{"os":"Windows",...}');
```

---

### 4. `login_logs` — Giriş/Çıkış Kayıt Tablosu

Tüm giriş/çıkış olaylarını detaylı biçimde kaydeder.

| Sütun | Tür | Kısıtlama | Açıklama |
|-------|-----|----------|---------|
| `id` | INTEGER | PRIMARY KEY, AUTO_INCREMENT | Kayıt ID'si |
| `timestamp` | TIMESTAMP | NOT NULL, DEFAULT CURRENT_TIMESTAMP | Olay zamanı |
| `event_type` | TEXT | NOT NULL | Olay türü (Giriş, Çıkış, Başarısız giriş) |
| `user_id` | INTEGER | FOREIGN KEY, NULLABLE | Kullanıcı ID'si |
| `username` | TEXT | NULLABLE | Kullanıcı adı |
| `full_name` | TEXT | NULLABLE | Tam ad |
| `system_info` | TEXT | NULLABLE | Sistem bilgileri (JSON) |
| `code_dirs` | TEXT | NULLABLE | Kod dizini bilgileri (JSON) |
| `ip_address` | TEXT | NULLABLE | IP adresi |
| `user_agent` | TEXT | NULLABLE | User Agent string'i |
| `status` | TEXT | DEFAULT 'success' | Durum (success, failed, ...) |
| `error_message` | TEXT | NULLABLE | Hata mesajı |
| `created_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | Kayıt oluşturma tarihi |

**Örnek:**
```sql
INSERT INTO login_logs (event_type, user_id, username, status)
VALUES ('Giriş', 1, 'alice', 'success');

INSERT INTO login_logs (event_type, username, status, error_message)
VALUES ('Başarısız giriş', 'unknown', 'failed', 'Kullanıcı bulunamadı');
```

---

## İndeksler

Sorgulama performansını iyileştirmek için aşağıdaki indeksler oluşturulur:

```sql
CREATE INDEX idx_users_username ON users(username);
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_created_at ON users(created_at);
CREATE INDEX idx_user_attributes_user_id ON user_attributes(user_id);
CREATE INDEX idx_user_attributes_name ON user_attributes(attribute_name);
CREATE INDEX idx_sessions_user_id ON sessions(user_id);
CREATE INDEX idx_sessions_login_ts ON sessions(login_ts);
CREATE INDEX idx_login_logs_timestamp ON login_logs(timestamp);
CREATE INDEX idx_login_logs_user_id ON login_logs(user_id);
CREATE INDEX idx_login_logs_event_type ON login_logs(event_type);
```

---

## İlişkiler (Relationships)

```
users (1) ──┬─→ (N) user_attributes
            ├─→ (N) sessions
            └─→ (N) login_logs
```

- **users → user_attributes**: Bire-çok. Her kullanıcının birden fazla özelliği olabilir.
- **users → sessions**: Bire-çok. Her kullanıcının birden fazla oturumu olabilir.
- **users → login_logs**: Bire-çok. Her kullanıcının birden fazla giriş/çıkış kaydı olabilir.

---

## Görünümler (Views)

### `active_sessions` — Aktif Oturumlar

```sql
SELECT * FROM active_sessions;
```

Çıktı:
```
id                                | username | full_name      | login_ts            | status
02a31bdf-aad7-4868-afd0-9f2d4ae2b47 | admin    | Sistem Yöneticisi | 2025-11-25 21:52:07 | Aktif
```

### `user_login_summary` — Kullanıcı Giriş Özeti

```sql
SELECT * FROM user_login_summary;
```

Çıktı:
```
id | username | full_name      | total_logins | last_login_time     | created_at          | is_active
1  | admin    | Sistem Yöneticisi | 12           | 2025-11-25 21:50:00 | 2025-11-24 10:00:00 | 1
2  | alice    | Alice Example    | 3            | 2025-11-25 21:52:07 | 2025-11-25 10:00:00 | 1
```

---

## Kullanım Örnekleri

### Yeni Kullanıcı Ekleme

```sql
BEGIN TRANSACTION;

-- 1. Kullanıcıyı ekle
INSERT INTO users (username, salt, hash, full_name, email)
VALUES ('bob', 'salt_hex', 'hash_hex', 'Bob Example', 'bob@example.com');

-- 2. Kullanıcı ID'sini al
SELECT last_insert_rowid() as user_id;

-- 3. Faktörleri ekle (user_id = 3 olduğunu varsayarak)
INSERT INTO user_attributes (user_id, attribute_name, attribute_value, attribute_type)
VALUES 
  (3, 'department', 'HR', 'string'),
  (3, 'role', 'user', 'string'),
  (3, 'theme', 'light', 'string');

COMMIT;
```

### Son Giriş Saati Güncelleme

```sql
UPDATE users
SET last_login = CURRENT_TIMESTAMP
WHERE id = 1;
```

### Tüm Başarısız Giriş Denemelerini Getirme

```sql
SELECT timestamp, username, error_message
FROM login_logs
WHERE event_type = 'Başarısız giriş'
ORDER BY timestamp DESC
LIMIT 10;
```

### Belirli Tarih Aralığında Giriş Sayısı

```sql
SELECT username, COUNT(*) as login_count
FROM login_logs
WHERE event_type = 'Giriş'
  AND timestamp BETWEEN '2025-11-25 00:00:00' AND '2025-11-25 23:59:59'
GROUP BY username
ORDER BY login_count DESC;
```

### Kullanıcı Faktörlerini Almak

```sql
SELECT attribute_name, attribute_value
FROM user_attributes
WHERE user_id = 1
ORDER BY attribute_name;
```

---

## Kurulum

### SQLite (Yerel)

```bash
sqlite3 login_system.db < database_schema.sql
```

### PostgreSQL

```bash
psql -U postgres -d login_system -f database_schema.sql
```

### MySQL

```bash
mysql -u root -p login_system < database_schema.sql
```

---

## Notlar

- **Parola Güvenliği**: Parolalar PBKDF2-HMAC-SHA256 ile hash'lenir; salt rastgeledir ve her kullanıcı için benzersizdir.
- **Veri Integriyetesi**: Foreign key kısıtlamaları, bağlantılı verilerin tutarlı kalmasını sağlar.
- **Performans**: İndeksler, yaygın sorgulara hızlı erişim sağlar.
- **Genişletilebilirlik**: `user_attributes` tablosu, ek kullanıcı faktörlerini dinamik olarak depolamaya izin verir.

