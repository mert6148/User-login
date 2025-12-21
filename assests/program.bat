echo Starting the program...
echo -----------------------------------------
chcp 65001 >nul
REM =============================================================
REM ===================  DERLEYİCİ KONTROLÜ  =====================
REM =============================================================

REM Assets klasöründeki dotnet.bat dosyasını kontrol et
if not exist "assests\dotnet.bat" (
    echo [ERROR] assests\dotnet.bat dosyası bulunamadı! Çıkılıyor...
    pause
    exit /b
)

REM Derleyici kontrol ediliyor...
if not exist dotnet.bat (
    echo [ERROR] dotnet.bat dosyası bulunamadı! Çıkılıyor...
    pause
    exit /b
)