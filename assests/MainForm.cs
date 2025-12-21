using System;
using System.Data;
using System.Text;
using System.Windows.Forms;

public class MainForm : Form
{
    private Button btnScan;
    private ListBox lstServers;
    private Label lblServer;
    private TextBox txtDatabaseName;
    private Label lblDbName;
    private Button btnCreateDb;
    private CheckBox chkWindowsAuth;
    private TextBox txtUsername;
    private TextBox txtPassword;
    private Label lblUsername;
    private Label lblPassword;
    private Label lblStatus;

    public MainForm()
    {
        InitializeComponent();
    }

    private void InitializeComponent()
    {
        this.Text = "DB Oluşturucu - Ağ & Framework Kontrolleri";
        this.Width = 700;
        this.Height = 420;
        this.StartPosition = FormStartPosition.CenterScreen;

        btnScan = new Button { Left = 12, Top = 12, Width = 120, Text = "Sunucuları Tara" };
        btnScan.Click += BtnScan_Click;

        lstServers = new ListBox { Left = 12, Top = 50, Width = 450, Height = 200 };

        lblServer = new Label { Left = 480, Top = 50, Width = 180, Text = "Seçili Sunucu:" };

        lblDbName = new Label { Left = 480, Top = 90, Text = "Veritabanı Adı:" };
        txtDatabaseName = new TextBox { Left = 480, Top = 110, Width = 180, Text = "YeniVeritabani" };

        chkWindowsAuth = new CheckBox { Left = 480, Top = 150, Text = "Windows Authentication (Integrated Security)", Checked = true };
        chkWindowsAuth.CheckedChanged += (s, e) =>
        {
            txtUsername.Enabled = txtPassword.Enabled = !chkWindowsAuth.Checked;
        };

        lblUsername = new Label { Left = 480, Top = 180, Text = "Kullanıcı:" };
        txtUsername = new TextBox { Left = 480, Top = 200, Width = 180, Enabled = false };

        lblPassword = new Label { Left = 480, Top = 235, Text = "Parola:" };
        txtPassword = new TextBox { Left = 480, Top = 255, Width = 180, Enabled = false, UseSystemPasswordChar = true };

        btnCreateDb = new Button { Left = 480, Top = 295, Width = 180, Text = "Veritabanı Oluştur" };
        btnCreateDb.Click += BtnCreateDb_Click;

        lblStatus = new Label { Left = 12, Top = 270, Width = 450, Height = 120, Text = "Durum: Hazır", AutoSize = false };

        this.Controls.AddRange(new Control[] {
            btnScan, lstServers, lblServer, lblDbName, txtDatabaseName,
            chkWindowsAuth, lblUsername, txtUsername, lblPassword, txtPassword,
            btnCreateDb, lblStatus
        });

        lstServers.DoubleClick += (s, e) =>
        {
            if (lstServers.SelectedItem != null)
            {
                lblServer.Text = "Seçili Sunucu: " + lstServers.SelectedItem.ToString();
            }
        };
    }

    private void BtnScan_Click(object sender, EventArgs e)
    {
        try
        {
            lblStatus.Text = "Durum: Sunucular taranıyor...";
            lstServers.Items.Clear();

            DataTable table = DbManager.GetLocalAndNetworkSqlInstances();
            var sb = new StringBuilder();
            foreach (DataRow row in table.Rows)
            {
                string server = row["ServerName"]?.ToString();
                string instance = row["InstanceName"]?.ToString();
                string display = string.IsNullOrEmpty(instance) ? server : $"{server}\\{instance}";
                lstServers.Items.Add(display);
            }

            lblStatus.Text = $"Durum: {lstServers.Items.Count} sunucu bulundu.";
        }
        catch (Exception ex)
        {
            lblStatus.Text = "Hata: " + ex.Message;
        }
    }

    private void BtnCreateDb_Click(object sender, EventArgs e)
    {
        try
        {
            if (lstServers.SelectedItem == null)
            {
                MessageBox.Show("Lütfen listeden bir sunucu seçin (çift tıklayabilirsiniz).", "Sunucu Seçilmedi", MessageBoxButtons.OK, MessageBoxIcon.Warning);
                return;
            }

            string selectedServer = lstServers.SelectedItem.ToString();
            string dbName = txtDatabaseName.Text.Trim();
            bool useWindowsAuth = chkWindowsAuth.Checked;
            string user = txtUsername.Text;
            string pwd = txtPassword.Text;

            string cs = DbManager.BuildMasterConnectionString(selectedServer, useWindowsAuth, user, pwd);

            lblStatus.Text = "Durum: Veritabanı oluşturuluyor...";
            // CreateDatabaseOnServer çağrısı synchronous olarak yapılır — UI kilitlenmesin isterseniz Task.Run ile arkaplanda çalıştırabilirsiniz.
            // Ancak burada örnek amaçlı doğrudan çağırıyoruz (küçük DB'ler için genelde hızlıdır).
            DbManager.CreateDatabaseOnServer(cs, dbName);

            lblStatus.Text = $"Durum: '{dbName}' veritabanı {selectedServer} üzerinde oluşturuldu.";
            MessageBox.Show($"'{dbName}' başarıyla oluşturuldu.", "Tamam", MessageBoxButtons.OK, MessageBoxIcon.Information);
        }
        catch (Exception ex)
        {
            lblStatus.Text = "Hata: " + ex.Message;
            MessageBox.Show("Veritabanı oluşturulurken hata: " + ex.Message, "Hata", MessageBoxButtons.OK, MessageBoxIcon.Error);
        }
    }
}
