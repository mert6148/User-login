@echo off
echo "A project related to user input, created with Python and C#"
echo "Author: Mert Doğanay"
echo "GitHub: https://github.com/mert6148/User-login.git"

REM ===== 1) Virtual Environment Setup =====
if not exist venv (
    echo [SETUP] Sanal ortam oluşturuluyor...
    python -m venv venv
    call venv\Scripts\activate.bat
    pip install --upgrade pip
    byte-compile requirements.in -o requirements.txt
)

call venv\Scripts\activate.bat
pip install -r requirements.txt


REM =============================================================
REM ===============  3. MADDE — GELİŞTİRİLMİŞ COMPILER  =========
REM =============================================================

echo -----------------------------------------
echo [RUN] Derleyici kontrol ediliyor...
echo -----------------------------------------

REM compiler.py var mı?
if not exist compiler.py (
    echo [WARN] compiler.py bulunamadı, otomatik oluşturuluyor...
    echo from datetime import datetime>compiler.py
    echo import os>>compiler.py
    echo output_path = r"C:\Users\mertd\OneDrive\Masaüstü\User-login\print.py">>compiler.py
    echo code = f'''>>compiler.py
    echo # Otomatik üretilen dosya — derleyici tarafindan oluşturuldu>>compiler.py
    echo # Derleme zamani: {datetime.now().strftime("%Y-%m-%d %H:%M:%S")}>>compiler.py
    echo>>compiler.py
    echo def generated_output():>>compiler.py
    echo     print("Bu çıktılar compiler.py ile otomatik oluşturuldu.")>>compiler.py
    echo>>compiler.py
    echo if __name__ == "__main__":>>compiler.py
    echo     generated_output()>>compiler.py
    echo '''>>compiler.py
    echo with open(output_path, "w", encoding="utf-8") as f:>>compiler.py
    echo     f.write(code)>>compiler.py
    echo print("[COMPILER] print.py olusturuldu:", output_path)>>compiler.py
)

echo [RUN] compiler.py çalıştırılıyor...
python compiler.py

IF %ERRORLEVEL% NEQ 0 (
    echo [ERROR] Derleyici bir hata oluşturdu! Çıkılıyor...
    pause
    exit /b
)

REM print.py gerçekten oluşmuş mu?
if not exist "C:\Users\mertd\OneDrive\Masaüstü\User-login\print.py" (
    echo [ERROR] print.py dosyası OLUŞMADI!
    echo Derleyicide bir sorun var. İşlem durduruluyor.
    pause
    exit /b
)

echo [SUCCESS] Derleyici başarıyla çalıştı ve print.py üretildi.
echo -----------------------------------------


REM =============================================================
REM ===============  DB DASHBOARD ENTEGRASYONU  ==================
REM =============================================================

echo [RUN] DB Dashboard başlatılıyor...

REM Flask kurulu mu kontrol et
pip install flask --disable-pip-version-check >nul 2>&1

REM Dashboard’ı yeni bir pencerede çalıştır
start "" python dashboard.py --open

REM Dashboard açılana kadar kısa bekleme
timeout /t 1 >nul

REM Giriş ekranını varsayılan tarayıcıda aç
start "" http://localhost:5000

REM Ana programa devam etmeden önce kısa bir bekleme
timeout /t 2 >nul

REM Sonraki adımlara devam edilecek...
timeout /t 3 >nul

REM Dashboard entegrasyonu tamamlandı
echo [SUCCESS] DB Dashboard başlatıldı ve tarayıcıda açıldı.

REM ===================== MAIN PROGRAM =======================
echo [RUN] main.py çalıştırılıyor...
python main.py
pause
