**Kısa Açıklama**: Bu dosya `print.py` içindeki CLI komutlarının hızlı kullanımını gösterir.

**Kurulum**:
- Python 3.8+ yüklü olmalı.
- Çalışma dizini: `c:\Users\mertd\OneDrive\Masaüstü\Kullanıcı girişi`

**Kullanım (temel komutlar)**
- `add-user <username> [-p PASSWORD] [-f FULL_NAME]` : Yeni kullanıcı oluşturur. `-p` verilmezse prompt açılır.
- `del-user <username>` : Kullanıcıyı siler.
- `list-users` : Kayıtlı kullanıcıları listeler.
- `login <username> [-p PASSWORD]` : Non-interactive giriş yapar; parola verilmezse prompt açılır.
- `logout [--username USER]` : Aktif oturumu sonlandırır (veya verilen kullanıcı için çıkış kaydı ekler).
- `show-sessions` : Mevcut ve geçmiş oturumları gösterir.
- `show-log` : `login_log.txt` içindeki JSON-lines kayıtlarını okunur formatta gösterir.
- `seed` : Örnek log kayıtları ekler (test amaçlı).
- `migrate` : Legacy insan okunur logları JSON-lines formatına dönüştürür; orijinali `login_log.txt.bak` olarak yedekleyebilir.
- `normalize` : JSON-lines içindeki satır içi yeni satırları ve fazladan önekleri temizler.

**Hızlı PowerShell örnekleri**
- Kullanıcı oluşturma (parola argümanlı):

```powershell
python .\print.py add-user alice -p s3cr3t -f "Alice Example"
```

- Kullanıcı oluşturma (parola prompt ile):

```powershell
python .\print.py add-user bob
# Parola sorulacak, tekrar onaylanacak
```

- Giriş (non-interactive):

```powershell
python .\print.py login alice -p s3cr3t
```

- Giriş (prompt ile):

```powershell
python .\print.py login bob
# Parola prompt ile alınır
```

- Kayıtları ve oturumları görüntüleme:

```powershell
python .\print.py show-log
python .\print.py show-sessions
```

- Legacy logları dönüştürme ve normalize etme (yedekleme yapar):

```powershell
python .\print.py migrate
python .\print.py normalize
```

**Güvenlik Notları**
- Komut satırında parola (`-p`) kullanmak rahat olsa da güvenlik riski (shell history) getirir. Mümkünse parola argümanını kullanmayın; prompt kullanın.
- Parolalar `users.json` içinde `salt` + `hash` formatında saklanır. Eğer eski `password` alanları varsa migration önerilir.

**Dosyalar**
- Log: `login_log.txt` (JSON-lines; bir satır = bir JSON objesi)
- Kullanıcı deposu: `users.json`
- Oturumlar: `sessions.json`

**Hızlı hata ayıklama**
- Eğer `show-log` ham `RAW` satırlar görüyorsanız, önce `python .\print.py migrate` çalıştırın.

---
Bu dosya kısa referans içindir. Daha ayrıntılı değişiklik talepleri veya README biçimlendirmesi isterseniz söyleyin, ben güncellerim.
