using System;
using UserLogin.Assets;

namespace UserLogin.Tests
{
    class CompilerTest
    {
        static void Main()
        {
            Compiler compiler = new Compiler();
            bool result1 = compiler.Compile("Console.WriteLine(\"Hello\")");
            bool result2 = compiler.Compile("error code");

            Console.WriteLine($"Test 1 (başarılı): {result1}");
            Console.WriteLine($"Test 2 (hatalı): {result2}");

            Console.WriteLine("Hata logları:");
            foreach (var log in compiler.GetLogs())
            {
                Console.WriteLine(log);
            }
        }
    }
}
