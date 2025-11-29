using System;
using System.Collections.Generic;
using System.IO;
using System.Reflection;
using Microsoft.CodeAnalysis;
using Microsoft.CodeAnalysis.CSharp;

namespace UserLogin.Assets
{
    /// <summary>
    /// Gerçek Roslyn derleyici entegre edilen Compiler sınıfı.
    /// </summary>
    public class Compiler
    {
        public bool DebugMode { get; set; }

        private readonly List<string> _logs = new();

        public Compiler(bool debug = false)
        {
            DebugMode = debug;
        }

        // --------------------------------------------------------------
        //  ROSLYN — GERÇEK ZAMANLI C# DERLEME
        // --------------------------------------------------------------
        public bool CompileReal(string sourceCode, out Assembly compiledAssembly)
        {
            compiledAssembly = null;

            if (string.IsNullOrWhiteSpace(sourceCode))
            {
                LogError("Derlenecek C# kodu boş olamaz.");
                return false;
            }

            LogInfo("Roslyn derleyicisi çalıştırılıyor...");

            var syntaxTree = CSharpSyntaxTree.ParseText(sourceCode);

            string assemblyName = Path.GetRandomFileName();

            var references = new List<MetadataReference>
            {
                MetadataReference.CreateFromFile(typeof(object).Assembly.Location),
                MetadataReference.CreateFromFile(typeof(Console).Assembly.Location),
                MetadataReference.CreateFromFile(typeof(Enumerable).Assembly.Location),
            };

            var compilation = CSharpCompilation.Create(
                assemblyName,
                new[] { syntaxTree },
                references,
                new CSharpCompilationOptions(OutputKind.ConsoleApplication)
            );

            using var ms = new MemoryStream();
            var result = compilation.Emit(ms);

            if (!result.Success)
            {
                foreach (var diag in result.Diagnostics)
                {
                    if (diag.Severity == DiagnosticSeverity.Error)
                        LogError($"Derleme hatası: {diag.GetMessage()}");
                }

                return false;
            }

            ms.Seek(0, SeekOrigin.Begin);
            compiledAssembly = Assembly.Load(ms.ToArray());

            LogInfo("Gerçek derleme başarılı.");
            return true;
        }

        // --------------------------------------------------------------
        //  COMPILE + RUN PIPELINE (GERÇEK ÇALIŞTIRMA)
        // --------------------------------------------------------------
        public string RunRealCode(string sourceCode)
        {
            if (!CompileReal(sourceCode, out Assembly assembly))
            {
                return "[HATA] Kod derlenemedi.";
            }

            LogInfo("Derlenen kod çalıştırılıyor...");

            try
            {
                // EntryPoint varsa çalıştırılır (Main metodu)
                var entryPoint = assembly.EntryPoint;

                if (entryPoint == null)
                {
                    LogError("Çalıştırılabilir giriş noktası (Main) bulunamadı.");
                    return "Çalıştırılamadı: Main metodu yok.";
                }

                object result = entryPoint.Invoke(null, new object[] { Array.Empty<string>() });

                return result?.ToString() ?? "Kod çalıştı, çıktı yok.";
            }
            catch (Exception ex)
            {
                LogError($"Çalıştırma hatası: {ex.Message}");
                return $"Çalıştırma hatası: {ex.Message}";
            }
        }

        // --------------------------------------------------------------
        //  LOG SISTEMI
        // --------------------------------------------------------------
        private void LogInfo(string msg)
        {
            string log = $"[INFO] {msg}";
            _logs.Add(log);
            if (DebugMode) Console.WriteLine(log);
        }

        private void LogError(string msg)
        {
            string log = $"[ERROR] {msg}";
            _logs.Add(log);
            Console.WriteLine(log);
        }

        public IReadOnlyList<string> GetLogs() => _logs.AsReadOnly();

        public void ClearLogs() => _logs.Clear();
    }
}
