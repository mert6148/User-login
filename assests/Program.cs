using System;
using UserLogin.Assets;

namespace UserLogin
{
    class Program
    {
        

        static void Main(string[] args)
        {
            Console.WriteLine("=== User-login Compiler Test ===");

            // Kullanıcı oluşturma simülasyonu
            string username = "alice";
            string password = "password123";
            Console.WriteLine($"Kullanıcı oluşturuldu: {username}");

            // Compiler örneği
            Compiler compiler = new Compiler();

            // Kod denemeleri
            string code1 = "Console.WriteLine(\"Hello World\");";
            string code2 = "Console.WriteLine(\"Test\") error";

            Console.WriteLine("\n1. Kod 1 derleniyor...");
            if (compiler.Compile(code1))
                compiler.Execute(code1);

            Console.WriteLine("\n2. Kod 2 derleniyor...");
            if (compiler.Compile(code2))
                compiler.Execute(code2);

            // Logları göster
            Console.WriteLine("\n=== Hata Logları ===");
            foreach (var log in compiler.GetLogs())
            {
                Console.WriteLine(log);
            }
        }
    }
}
