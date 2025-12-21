using System;
using System.Data;
using System.Data.Sql;
using System.Data.SqlClient;

public class DbManager
{
    /// <summary>
    /// Ağdaki SQL Server örneklerini döner.
    /// </summary>
    public static DataTable GetLocalAndNetworkSqlInstances()
    {
        // SqlDataSourceEnumerator ile tarama
        var instance = SqlDataSourceEnumerator.Instance;
        DataTable table = instance.GetDataSources();
        // table kolonları: ServerName, InstanceName, IsClustered, Version
        return table;
    }

    /// <summary>
    /// Veritabanı oluşturur. connectionToMaster: master veritabanına bağlanacak şekilde oluşturulmuş bir connection string olmalı.
    /// </summary>
    public static void CreateDatabaseOnServer(string connectionToMaster, string newDatabaseName)
    {
        if (string.IsNullOrWhiteSpace(newDatabaseName))
            throw new ArgumentException("Veritabanı adı boş olamaz.", nameof(newDatabaseName));

        string sql = $"IF DB_ID(N'{EscapeSqlIdentifier(newDatabaseName)}') IS NULL CREATE DATABASE [{newDatabaseName}]";

        using (var conn = new SqlConnection(connectionToMaster))
        using (var cmd = new SqlCommand(sql, conn))
        {
            conn.Open();
            cmd.CommandTimeout = 120; // büyük veritabanı için yeterli süre
            cmd.ExecuteNonQuery();
        }
    }

    /// <summary>
    /// Basit SQL identifier kaçış — köşeli parantezlerle sarmala.
    /// </summary>
    private static string EscapeSqlIdentifier(string identifier)
    {
        // Çok basit bir kaçış: içerideki ]'leri ]] ile değiştir
        return identifier?.Replace("]", "]]");
    }

    /// <summary>
    /// Master veritabanına bağlantı dizesi oluşturur.
    /// Eğer Windows Authentication isteniyorsa username/password kullanılmaz.
    /// </summary>
    public static string BuildMasterConnectionString(string server, bool useWindowsAuth, string username = null, string password = null)
    {
        var sb = new SqlConnectionStringBuilder
        {
            DataSource = server,
            InitialCatalog = "master",
            IntegratedSecurity = useWindowsAuth,
            ConnectTimeout = 15,
            MultipleActiveResultSets = false
        };

        if (!useWindowsAuth)
        {
            sb.UserID = username ?? throw new ArgumentException("Kullanıcı adı gerekli", nameof(username));
            sb.Password = password ?? throw new ArgumentException("Parola gerekli", nameof(password));
        }

        return sb.ToString();
    }
}
