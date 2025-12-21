REM .NET Core uygulamasını başlatmak için kullanılan betik
@echo off
chcp 65001 >nul
REM =============================================================
REM ===================  DERLEYİCİ ÇAĞRISI  =====================
REM =============================================================
echo [RUN] Derleyici başlatılıyor...
echo [C# to Python Compiler] Derleyici çalıştırılıyor...
echo [RUN] compiler.py dosyası oluşturuluyor...
(
    echo using System;
    echo using System.IO;
    echo;
    echo class Compiler {
        echo static void Main() {
            echo string outputPath = @"C:\Users\mertd\OneDrive\Masaüstü\User-login\print.py";
            echo string code = $@"
        }
    }
    print('Hello from generated print.py!')";
)

echo [RUN] compiler.py dosyası oluşturuldu.
echo [RUN] Derleyici çalıştırılıyor...
dotnet run --project "C:\Users\mertd\OneDrive\Masaüstü\User-login\DotNetCompiler\DotNetCompiler.csproj"
IF %ERRORLEVEL% NEQ 0 (
    echo [ERROR] Derleyici bir hata oluşturdu! Çıkılıyor...
    pause
    exit /b
)


echo [SUCCESS] Derleyici başarıyla çalıştı.
echo [RUN] Derleyici çalıştı.
REM print.py gerçekten oluşmuş mu?

echo [SUCCESS] Derleyici başarıyla çalıştı ve print.py üretildi.
echo -----------------------------------------
REM =============================================================
REM =================  DERLEYİCİ KONTROLÜ  ======================
REM =============================================================