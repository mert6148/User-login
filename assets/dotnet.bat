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
            echo string outputPath = @"C:\Users\mertd\OneDrive\Masaüstü\Kullanıcı girişi\print.py";
            echo string code = $@"
        }
    }
    print('Hello from generated print.py!')";
)
