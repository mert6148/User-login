# CS-Script VSCode extension integration

The extension requires CS-Script tools to function properly.
This is the most reliable way of managing their updates independently from the extension releases (e.g. update with new .NET version).

## Installation

1. Install/Update tools
    - Script engine: `dotnet tool install --global cs-script.cli`
    - Syntaxer: `dotnet tool install --global cs-syntaxer`

2. Configure tools
    Execute extension command "CS-Script: Detect and integrate CS-Script"

Note: you need to have .NET SDK installed for using CS-Script (see https://dotnet.microsoft.com/en-us/download)

## Example C# Script
Here is a simple example of a C# script that you can run using CS-Script:

```csharp
using System;

class Program
{
    static void Main()
    {
        Console.WriteLine("Hello, CS-Script!");
    }
}
```

You can save this code in a file with a `.csx` extension (e.g., `HelloWorld.csx`) and run it using the CS-Script CLI:

```bash
csscript HelloWorld.csx
```
