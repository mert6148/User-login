#!/usr/bin/env python3
"""
config.py

Özellikler:
- API'lara sistematik (periyodik, paralel, kuyruklu) mesaj gönderebilen modül
- Veritabanına bağlanıp tablo listeleyen sistem (MySQL & SQLite destekli)
- Kullanıcılara mesaj gönderen yardımcı fonksiyon
- Kullanıcı giriş/çıkış durumlarını kontrol eden yapı (örnek session store)
- Loglama ve hata yakalama

Bu dosya bağımsız bir modül olarak kullanılabilir.

Kullanım:
    import config
    config.api_send("/notify", {"msg":"test"})
    tables = config.db_list_tables()

Notlar:
- MySQL için: pip install pymysql
- API istekleri için: requests (opsiyonel)
"""

import threading
import time
import json
import queue
import sqlite3
from datetime import datetime
from pathlib import Path
import traceback

# Optional imports
try:
    import pymysql
except Exception:
    pymysql = None

try:
    import requests
except Exception:
    requests = None

# --------------------------- AYARLAR ---------------------------

APP_DIR = Path(__file__).resolve().parent
LOG_FILE = APP_DIR / "config.log"

API_BASE = "http://localhost/api"
API_KEY = "12345"

DB_TYPE = "sqlite"  # sqlite | mysql
SQLITE_PATH = APP_DIR / "data.db"

MYSQL_HOST = "localhost"
MYSQL_USER = "root"
MYSQL_PASS = ""
MYSQL_DB   = "test"

# --------------------------- LOG SİSTEMİ ---------------------------

def log(msg):
    ts = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
    line = f"[{ts}] {msg}\n"
    with open(LOG_FILE, "a", encoding="utf-8") as f:
        f.write(line)

# --------------------------- API SİSTEMİ ---------------------------

api_queue = queue.Queue()
api_thread_started = False


def http_post(path, data=None):
    url = API_BASE.rstrip("/") + path + f"?key={API_KEY}"
    body = data or {}
    try:
        if requests:
            r = requests.post(url, json=body, timeout=10)
            r.raise_for_status()
            return r.json()
        else:
            from urllib import request
            payload = json.dumps(body).encode('utf-8')
            headers = {"Content-Type": "application/json"}
            req = request.Request(url, data=payload, headers=headers)
            with request.urlopen(req, timeout=10) as resp:
                return json.loads(resp.read().decode('utf-8'))
    except Exception as e:
        log(f"API POST HATA: {path} -> {e}")
        return {"error": str(e)}


def api_worker():
    log("API Worker başlatıldı.")
    while True:
        path, data = api_queue.get()
        try:
            log(f"API SEND -> {path} | data={data}")
            res = http_post(path, data)
            log(f"API RESPONSE -> {res}")
        except Exception as e:
            log(f"API worker hata: {e}\n{traceback.format_exc()}")
        finally:
            api_queue.task_done()


def api_send(path, data=None):
    global api_thread_started
    if not api_thread_started:
        t = threading.Thread(target=api_worker, daemon=True)
        t.start()
        api_thread_started = True
    api_queue.put((path, data or {}))

# --------------------------- VERİTABANI SİSTEMİ ---------------------------


def db_connect():
    if DB_TYPE == "sqlite":
        return sqlite3.connect(SQLITE_PATH)
    elif DB_TYPE == "mysql":
        if pymysql is None:
            raise RuntimeError("MySQL için pymysql yüklü değil")
        return pymysql.connect(
            host=MYSQL_HOST,
            user=MYSQL_USER,
            password=MYSQL_PASS,
            database=MYSQL_DB,
            charset="utf8mb4",
            cursorclass=pymysql.cursors.DictCursor
        )
    else:
        raise RuntimeError("Bilinmeyen DB_TYPE")


def db_list_tables():
    try:
        con = db_connect()
        cur = con.cursor()
        if DB_TYPE == "sqlite":
            cur.execute("SELECT name FROM sqlite_master WHERE type='table'")
            rows = [r[0] for r in cur.fetchall()]
        else:  # mysql
            cur.execute("SHOW TABLES")
            rows = [list(r.values())[0] for r in cur.fetchall()]
        con.close()
        log(f"Tablolar: {rows}")
        return rows
    except Exception as e:
        log(f"DB tablo listeleme hata: {e}")
        return []

# --------------------------- KULLANICI MESAJ SİSTEMİ ---------------------------


def user_send_message(user_id: str, message: str):
    """API üzerinden kullanıcıya mesaj gönderir"""
    data = {"user_id": user_id, "message": message}
    api_send("/user/send", data)
    log(f"Kullanıcı mesaj kuyruğa alındı: {user_id} -> {message}")

# --------------------------- KULLANICI GİRİŞ/ÇIKIŞ SİSTEMİ ---------------------------

# Basit session store
active_sessions = {}


def user_login(user_id: str):
    active_sessions[user_id] = {
        "login_time": datetime.now().isoformat()
    }
    log(f"LOGIN -> {user_id}")
    return True


def user_logout(user_id: str):
    if user_id in active_sessions:
        del active_sessions[user_id]
        log(f"LOGOUT -> {user_id}")
        return True
    return False


def user_is_logged(user_id: str) -> bool:
    return user_id in active_sessions

# --------------------------- TEST ---------------------------

if __name__ == "__main__":
    log("config.py test başlatıldı")
    print("Tablolar:", db_list_tables())
    print("Login test ->", user_login("alice"))
    print("Login check ->", user_is_logged("alice"))
    print("Logout ->", user_logout("alice"))
    api_send("/test", {"ok": True})
    time.sleep(1)


# --------------------------- RATE LIMITER ---------------------------
# Token bucket temelinde basit rate limiter
RATE_LIMIT = 10  # saniyede izin verilen istek
RATE_BUCKET = RATE_LIMIT
RATE_LAST = time.time()


def rate_limiter_allow():
    global RATE_BUCKET, RATE_LAST
    now = time.time()
    elapsed = now - RATE_LAST
    RATE_LAST = now
    RATE_BUCKET = min(RATE_LIMIT, RATE_BUCKET + elapsed * RATE_LIMIT)
    if RATE_BUCKET >= 1:
        RATE_BUCKET -= 1
        return True
    return False

# http_post içine entegre
old_http_post = http_post

def http_post(path, data=None):
    if not rate_limiter_allow():
        log(f"RATE LIMIT: {path} reddedildi")
        return {"error": "rate_limited"}
    return old_http_post(path, data)

# --------------------------- MYSQL AUTO-RECONNECT ---------------------------

def mysql_connect_with_retry(max_retry=3):
    if pymysql is None:
        raise RuntimeError("pymysql yok, MySQL kullanılamaz")
    for attempt in range(1, max_retry + 1):
        try:
            return pymysql.connect(
                host=MYSQL_HOST,
                user=MYSQL_USER,
                password=MYSQL_PASS,
                database=MYSQL_DB,
                charset="utf8mb4",
                cursorclass=pymysql.cursors.DictCursor,
                autocommit=True
            )
        except Exception as e:
            log(f"MySQL bağlantı hatası (attempt={attempt}): {e}")
            time.sleep(1)
    raise RuntimeError("MySQL bağlanamadı — tüm denemeler bitti")

# db_connect override
def db_connect():
    if DB_TYPE == "sqlite":
        return sqlite3.connect(SQLITE_PATH)
    elif DB_TYPE == "mysql":
        return mysql_connect_with_retry()
    else:
        raise RuntimeError("Bilinmeyen DB_TYPE")