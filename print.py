# print.py
# Gelişmiş kullanıcı girişi + sistem bilgisi + giriş/çıkış kayıt sistemi

import platform
import getpass  # Şifre girişini daha güvenli yapmak için eklenmiştir
import json
import sys
from pathlib import Path
from datetime import datetime
import os

LOG_FILE = "login_log.txt"
# Event type constants (lint ve tekrar kullanım için)
EVENT_LOGIN = "Giriş"
EVENT_LOGOUT = "Çıkış"
EVENT_FAILED = "Başarısız giriş"

# Basit kullanıcı deposu (örnek). Gerçek uygulamada güvenli bir user store kullanın.
USERS = {
    "admin": {"password": "1234", "full_name": "Sistem Yöneticisi"},
}

def _acquire_file_lock(f):
    """Cross-platform file lock (best-effort).

    Uses msvcrt on Windows and fcntl on POSIX. This is a best-effort lock to
    reduce concurrent write races; failures are ignored to avoid crashing the app.
    """
    try:
        if os.name == "nt":
            import msvcrt

            # lock 1 byte at current position
            msvcrt.locking(f.fileno(), msvcrt.LK_LOCK, 1)
        else:
            import fcntl

            fcntl.flock(f.fileno(), fcntl.LOCK_EX)
    except Exception:
        # best-effort: ignore locking errors
        pass


def _release_file_lock(f):
    try:
        if os.name == "nt":
            import msvcrt

            f.seek(0)
            msvcrt.locking(f.fileno(), msvcrt.LK_UNLCK, 1)
        else:
            import fcntl

            fcntl.flock(f.fileno(), fcntl.LOCK_UN)
    except Exception:
        pass


def log_event(event_type, username="Bilinmiyor", full_name="Bilinmiyor", system=None, code_dirs=None):
    """Kimin ne zaman giriş/çıkış yaptığını kaydeder.

    Opsiyonel olarak `system` ve `code_dirs` bilgileri verilebilir; bunlar log içine
    JSON formatında eklenir.
    """
    def _sanitize(v):
        """Basit sanitize: stringlerde yeni satırları tek satıra indirir, dict/list üzerinde rekürsif çalışır."""
        if isinstance(v, str):
            return v.replace("\n", " ").replace("\r", " ")
        if isinstance(v, dict):
            return {k: _sanitize(val) for k, val in v.items()}
        if isinstance(v, list):
            return [_sanitize(x) for x in v]
        return v

    # JSON-only (JSON-lines) format: her kayıt tek satır JSON olarak kaydedilir.
    time_str = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    event_obj = {
        "timestamp": time_str,
        "event": event_type,
        "username": username,
        "full_name": full_name,
        "system": _sanitize(system) if system is not None else None,
        "code_dirs": _sanitize(code_dirs) if code_dirs is not None else None,
    }

    line = json.dumps(event_obj, ensure_ascii=False) + "\n"
    try:
        # Atomic-ish append with file locking: lock, write, flush, fsync, unlock
        with open(LOG_FILE, "a", encoding="utf-8") as f:
            _acquire_file_lock(f)
            try:
                f.write(line)
                f.flush()
                try:
                    os.fsync(f.fileno())
                except Exception:
                    # fsync may not be available on some platforms; ignore if fails
                    pass
            finally:
                _release_file_lock(f)
    except Exception:
        # Eğer dosyaya yazma başarısız olursa basit bir yedek hatırla
        try:
            with open(LOG_FILE, "a", encoding="utf-8", errors="ignore") as f:
                _acquire_file_lock(f)
                try:
                    f.write(json.dumps({"timestamp": time_str, "event": "error_writing_log"}, ensure_ascii=False) + "\n")
                    f.flush()
                finally:
                    _release_file_lock(f)
        except Exception:
            # tam bir başarısızlık: yazmayı atla (uyarı logu konsola)
            print("Hata: log dosyasına yazılamadı.")


def login(max_attempts: int = 3) -> bool:
    """Etkileşimli giriş.

    - Boş kullanıcı adı girişini engeller.
    - `USERS` içinde tanımlı parolalarla eşleştirir (örnek).
    - max_attempts başarısız denemeden sonra reddeder.
    Döndürür: True (başarılı) / False (başarısız)
    """
    print("--- Kullanıcı Girişi ---")

    attempts = 0
    while attempts < max_attempts:
        username = input("Kullanıcı adını girin: ").strip()
        if not username:
            print("Kullanıcı adı boş olamaz. Tekrar deneyin.")
            attempts += 1
            continue

        password = getpass.getpass("Şifrenizi girin: ")

        # Basit doğrulama: USERS sözlüğü
        user = USERS.get(username)
        if user and password == user.get("password"):
            # Eğer kullanıcı deposunda tam isim varsa kullan, yoksa iste
            full_name = user.get("full_name")
            if not full_name:
                full_name = input("Ad-Soyad giriniz: ").strip() or "Bilinmiyor"
            print("Giriş başarılı!")

            # Başarılı girişte sistem bilgilerini ve kod dizinlerini toplayıp loga yaz
            system = gather_system_info()
            code_dirs = list_code_directories(system.get("cwd", "."))
            log_event(EVENT_LOGIN, username, full_name, system=system, code_dirs=code_dirs)
            return True

        attempts += 1
        print(f"Hatalı kullanıcı adı veya şifre! Kalan deneme: {max_attempts - attempts}")

    # Tüm denemeler başarısız
    log_event(EVENT_FAILED)
    return False


def logout(username="admin", full_name="Tanımsız"):
    log_event(EVENT_LOGOUT, username, full_name)
    print("Çıkış yapıldı.")


def system_info():
    """Ekrana sistem bilgilerini yazdırır ve aynı zamanda dict döndürür."""
    info = gather_system_info()
    print("--- Sistem Bilgileri ---")
    print(f"İşletim Sistemi      : {info.get('os')}")
    print(f"Sürüm                : {info.get('os_version')}")
    print(f"Makine Türü          : {info.get('machine')}")
    print(f"İşlemci              : {info.get('processor')}")
    print(f"Platform Tanımı      : {info.get('platform')}")
    return info


def show_log():
    print("--- Giriş/Çıkış Kayıtları (JSON-lines) ---")
    if not os.path.exists(LOG_FILE):
        print("Henüz kayıt bulunmuyor.")
        return

    with open(LOG_FILE, "r", encoding="utf-8") as f:
        for i, line in enumerate(f, start=1):
            line = line.strip()
            if not line:
                continue
            try:
                obj = json.loads(line)
                # Basit okunur gösterim: zaman, event, kullanıcı
                ts = obj.get("timestamp")
                ev = obj.get("event")
                user = obj.get("username")
                full = obj.get("full_name")
                print(f"[{i}] {ts} - {ev} - {user} ({full})")
                # Eğer detay istenirse, indented JSON göster
                details = {}
                if obj.get("system"):
                    details["system"] = obj["system"]
                if obj.get("code_dirs"):
                    details["code_dirs"] = obj["code_dirs"]
                if details:
                    print(json.dumps(details, ensure_ascii=False, indent=2))
            except Exception:
                # JSON parse edilemezse ham satırı göster
                print(f"[RAW {i}] {line}")


def gather_system_info():
    """Sistemle ilgili temel bilgileri toplar."""
    try:
        processor = platform.processor() or ""
        # Tek satıra indir ve uzunsa kısalt
        processor = processor.replace("\n", " ").replace("\r", " ").strip()
        if len(processor) > 200:
            processor = processor[:197] + "..."

        info = {
            "os": platform.system(),
            "os_version": platform.version(),
            "machine": platform.machine(),
            "processor": processor,
            "platform": platform.platform(),
            "python": sys.version.split()[0],
            "cwd": os.getcwd(),
        }
    except Exception:
        info = {"error": "could not gather system info"}
    return info


def list_code_directories(root="."):
    """Verilen kök dizin altındaki üst seviye dizinlerdeki Python dosyalarını listeler.

    Dönen yapı: { 'dirname': [ 'file1.py', ... ], 'root_py_files': [...] }
    Sadece örnekleme amaçlı: her dizinden en fazla 10 dosya listelenir.
    """
    root_path = Path(root)
    result = {}
    try:
        # Top-level python dosyaları: name + size
        top_py = []
        for p in sorted(root_path.glob("*.py")):
            try:
                stat = p.stat()
                top_py.append({"name": p.name, "size": stat.st_size})
            except Exception:
                top_py.append({"name": p.name, "size": None})

        if top_py:
            result["root_py_files"] = top_py

        # Alt dizinlerdeki .py dosyalarını (en fazla 10 tane örnek) listele
        for p in sorted(root_path.iterdir()):
            if p.is_dir():
                try:
                    py_files = []
                    for x in sorted(p.rglob("*.py")):
                        rel = str(x.relative_to(root_path))
                        py_files.append(rel)
                        if len(py_files) >= 10:
                            break
                    if py_files:
                        result[p.name] = py_files
                except PermissionError:
                    result[p.name] = "permission_denied"
                except Exception:
                    result[p.name] = "error"
    except Exception:
        return {"error": "could not list code directories"}
    return result


def migrate_logs(src=LOG_FILE):
    """Mevcut `login_log.txt` içindeki karışık formatlı kayıtları JSON-lines formatına dönüştürür.

    - Mevcut JSON satırlarını olduğu gibi korur.
    - İnsan okunur blokları (başlangıç satırı: [timestamp] - ...) ve takip eden
      `  Sistem: ...` / `  KodDizinleri: ...` bloklarını birleştirip JSON objesi
      haline getirir.
    - Parse edilemeyen satırlar için `raw` alanı ile bir kayıt oluşturur.
    """
    if not os.path.exists(src):
        print(f"Kaynak dosya bulunamadı: {src}")
        return 0

    with open(src, "r", encoding="utf-8") as f:
        lines = [l.rstrip("\n") for l in f]

    events = []
    i = 0
    n = len(lines)
    while i < n:
        line = lines[i].strip()
        if not line:
            i += 1
            continue

        # Eğer satır bir JSON ise direkt parse et
        try:
            obj = json.loads(line)
            events.append(obj)
            i += 1
            continue
        except Exception:
            pass

        # İnsan okunur başlık satırı: [timestamp] - EVENT - Kullanıcı: X, Ad-Soyad: Y
        if line.startswith("[") and "]" in line:
            try:
                ts_part, after = line.split("]", 1)
                timestamp = ts_part.lstrip("[")
                after = after.strip()
                parts = [p.strip() for p in after.split(" - ") if p.strip()]
                event = parts[0] if parts else None
                username = None
                full_name = None
                if len(parts) > 1:
                    # beklenen format: Kullanıcı: X, Ad-Soyad: Y
                    rest = parts[1]
                    if "Kullanıcı:" in rest:
                        try:
                            user_part = rest.split("Kullanıcı:", 1)[1].strip()
                            if "," in user_part:
                                username = user_part.split(",", 1)[0].strip()
                                # sonrasında Ad-Soyad kısmı olabilir
                                if "Ad-Soyad:" in user_part:
                                    full_name = user_part.split("Ad-Soyad:", 1)[1].strip()
                            else:
                                username = user_part.strip()
                        except Exception:
                            pass

                # Bakalım takip eden satırlarda Sistem veya KodDizinleri var mı
                system = None
                code_dirs = None
                j = i + 1
                while j < n and lines[j].startswith("  "):
                    l = lines[j].lstrip()
                    if l.startswith("Sistem:"):
                        try:
                            jsonpart = l.split("Sistem:", 1)[1].strip()
                            system = json.loads(jsonpart)
                        except Exception:
                            # belki json part satır kırılmıştır; topla birkaç satırı birleştir
                            buff = l.split("Sistem:", 1)[1]
                            k = j + 1
                            while k < n and not lines[k].lstrip().startswith("KodDizinleri:") and not lines[k].startswith("["):
                                buff += lines[k]
                                k += 1
                            try:
                                system = json.loads(buff)
                                j = k - 1
                            except Exception:
                                system = None
                    elif l.startswith("KodDizinleri:"):
                        try:
                            jsonpart = l.split("KodDizinleri:", 1)[1].strip()
                            code_dirs = json.loads(jsonpart)
                        except Exception:
                            # benzer şekilde birleştir
                            buff = l.split("KodDizinleri:", 1)[1]
                            k = j + 1
                            while k < n and not lines[k].startswith("["):
                                buff += lines[k]
                                k += 1
                            try:
                                code_dirs = json.loads(buff)
                                j = k - 1
                            except Exception:
                                code_dirs = None
                    j += 1

                events.append({
                    "timestamp": timestamp,
                    "event": event,
                    "username": username,
                    "full_name": full_name,
                    "system": system,
                    "code_dirs": code_dirs,
                })
                # atla bloktaki satırlar
                i = j
                continue
            except Exception:
                # parse hatası
                events.append({"raw": line})
                i += 1
                continue

        # Aksi halde ham satırı kayıt olarak ekle
        events.append({"raw": line})
        i += 1

    # Şimdi events listesini JSON-lines formatında dosyaya yaz
    bakup = src + ".bak"
    try:
        os.replace(src, bakup)
    except Exception:
        # yedekleme başarısız olursa devam etmeden önce uyar
        print("Uyarı: mevcut dosya yedeklenemedi; üzerine yazılıyor")

    with open(src, "w", encoding="utf-8") as out:
        for ev in events:
            out.write(json.dumps(ev, ensure_ascii=False) + "\n")

    return len(events)


def normalize_jsonlines(src=LOG_FILE):
    """Mevcut JSON-lines dosyasını okuyup string alanlardaki newline'ları kaldırır ve
    `event` alanındaki leading '-' veya fazla boşlukları temizler.
    Döndürür: işlenen satır sayısı.
    """
    if not os.path.exists(src):
        return 0
    out_events = []
    with open(src, "r", encoding="utf-8") as f:
        for line in f:
            line = line.strip()
            if not line:
                continue
            try:
                obj = json.loads(line)
                # sanitize basic string fields
                for key in ("event", "username", "full_name"):
                    if key in obj and isinstance(obj[key], str):
                        obj[key] = obj[key].replace("\n", " ").replace("\r", " ").strip()
                        # remove leading hyphen and spaces
                        if obj[key].startswith("-"):
                            obj[key] = obj[key].lstrip("- ")

                # sanitize nested system fields strings
                if isinstance(obj.get("system"), dict):
                    for k, v in list(obj["system"].items()):
                        if isinstance(v, str):
                            obj["system"][k] = v.replace("\n", " ").replace("\r", " ").strip()

                out_events.append(obj)
            except Exception:
                # keep unparsable lines as raw
                out_events.append({"raw": line})

    # overwrite file with sanitized JSON-lines
    with open(src, "w", encoding="utf-8") as out:
        for ev in out_events:
            out.write(json.dumps(ev, ensure_ascii=False) + "\n")

    return len(out_events)


def seed_logs():
    """Örnek log kayıtları ekler (test/seeding amaçlı)."""
    sample = [
        (EVENT_LOGIN, "admin", "Sistem Yöneticisi"),
        (EVENT_LOGOUT, "admin", "Sistem Yöneticisi"),
        (EVENT_LOGIN, "mert", "Mert Doğanay"),
        (EVENT_FAILED, "unknown", "Bilinmiyor"),
    ]
    for ev, user, name in sample:
        log_event(ev, user, name)
    print(f"{len(sample)} adet örnek kayıt '{LOG_FILE}' dosyasına eklendi.")


def main():
    while True:
        print("=== Menü ===")
        print("1) Giriş yap ve sistem bilgisi göster")
        print("2) Kayıtları görüntüle")
        print("3) Çıkış yap")
        print("4) Programı kapat")
        print("5) Örnek log kayıtları oluştur (seed)")

        choice = input("Seçiminiz (1-4): ")

        if choice == "1":
            if login():
                system_info()
        elif choice == "2":
            show_log()
        elif choice == "3":
            logout()
        elif choice == "4":
            print("Programdan çıkılıyor...")
            break
        elif choice == "5":
            seed_logs()
        else:
            print("Geçersiz seçim yaptınız.")


if __name__ == "__main__":
    import sys

    # Eğer komut satırından 'seed' verilmişse otomatik olarak örnek kayıtlar ekle
    if len(sys.argv) > 1 and sys.argv[1].lower() in ("seed", "--seed"):
        seed_logs()
    else:
        main()
