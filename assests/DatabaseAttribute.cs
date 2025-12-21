using System;

[AttributeUsage(AttributeTargets.All, Inherited = false, AllowMultiple = true)]
sealed class DatabaseAttribute : Attribute
{
    readonly string positionalString;

    // Positional argument
    public DatabaseAttribute(string positionalString)
    {
        this.positionalString = positionalString;
        // Öznitelik sadece veri taşır; burada herhangi bir çalışma zamanı işlemi yapmayacağız.
        // TODO yerine bırakılmış NotImplementedException kaldırıldı ve constructor tamamlandı.
    }

    public string PositionalString
    {
        get { return positionalString; }
    }

    // Named argument
    public int NamedInt { get; set; }
}
