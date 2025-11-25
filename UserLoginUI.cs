using System;
using System.Collections.Generic;
using System.ComponentModel;
using System.Data;
using System.Drawing;
using System.Linq;
using System.Text;
using System.Threading.Tasks;
using System.Windows.Forms;
using System.Net.Http;
using System.Net.Http.Json;

namespace UserLoginUI
{
    /// <summary>
    /// Main Login Form - REST API üzerinden kullanıcı girişi ve yönetimi
    /// </summary>
    public partial class LoginForm : Form
    {
        private static readonly HttpClient client = new HttpClient();
        private const string API_BASE_URL = "http://localhost:5000/api/v1";
        private string currentUsername = null;

        public LoginForm()
        {
            InitializeComponent();
            client.BaseAddress = new Uri(API_BASE_URL);
        }

        private void LoginForm_Load(object sender, EventArgs e)
        {
            this.Text = "User Login System";
            this.Size = new Size(500, 400);
            this.StartPosition = FormStartPosition.CenterScreen;
            
            // Username Label & TextBox
            Label usernameLabel = new Label() { Text = "Kullanıcı Adı:", Left = 20, Top = 20, Width = 100 };
            TextBox usernameTextBox = new TextBox() { Name = "usernameTextBox", Left = 130, Top = 20, Width = 300 };
            
            // Password Label & TextBox
            Label passwordLabel = new Label() { Text = "Şifre:", Left = 20, Top = 60, Width = 100 };
            TextBox passwordTextBox = new TextBox() { Name = "passwordTextBox", Left = 130, Top = 60, Width = 300, UseSystemPasswordChar = true };
            
            // Login Button
            Button loginButton = new Button() { Text = "Giriş Yap", Left = 130, Top = 110, Width = 100 };
            loginButton.Click += async (s, e) => await LoginButtonClick(usernameTextBox.Text, passwordTextBox.Text);
            
            // Logout Button
            Button logoutButton = new Button() { Text = "Çıkış Yap", Left = 240, Top = 110, Width = 100 };
            logoutButton.Click += async (s, e) => await LogoutButtonClick();
            
            // Status Label
            Label statusLabel = new Label() { Name = "statusLabel", Text = "Hazır", Left = 20, Top = 160, Width = 410, Height = 30 };
            
            // Add controls to form
            this.Controls.Add(usernameLabel);
            this.Controls.Add(usernameTextBox);
            this.Controls.Add(passwordLabel);
            this.Controls.Add(passwordTextBox);
            this.Controls.Add(loginButton);
            this.Controls.Add(logoutButton);
            this.Controls.Add(statusLabel);
        }

        private async Task LoginButtonClick(string username, string password)
        {
            if (string.IsNullOrEmpty(username) || string.IsNullOrEmpty(password))
            {
                MessageBox.Show("Kullanıcı adı ve şifre boş olamaz!", "Hata", MessageBoxButtons.OK, MessageBoxIcon.Warning);
                return;
            }

            try
            {
                var loginData = new { username = username, password = password };
                var response = await client.PostAsJsonAsync("/auth/login", loginData);

                if (response.IsSuccessStatusCode)
                {
                    var result = await response.Content.ReadAsAsync<dynamic>();
                    currentUsername = username;
                    MessageBox.Show($"Giriş başarılı! Oturum: {result.session_id}", "Başarı", MessageBoxButtons.OK, MessageBoxIcon.Information);
                    this.Text = $"User Login System - Merhaba {username}";
                }
                else
                {
                    MessageBox.Show("Hatalı kullanıcı adı veya şifre!", "Hata", MessageBoxButtons.OK, MessageBoxIcon.Error);
                }
            }
            catch (Exception ex)
            {
                MessageBox.Show($"API Hatası: {ex.Message}", "Hata", MessageBoxButtons.OK, MessageBoxIcon.Error);
            }
        }

        private async Task LogoutButtonClick()
        {
            if (string.IsNullOrEmpty(currentUsername))
            {
                MessageBox.Show("Henüz giriş yapmadınız!", "Uyarı", MessageBoxButtons.OK, MessageBoxIcon.Warning);
                return;
            }

            try
            {
                var logoutData = new { username = currentUsername };
                var response = await client.PostAsJsonAsync("/auth/logout", logoutData);

                if (response.IsSuccessStatusCode)
                {
                    MessageBox.Show("Çıkış yapıldı!", "Başarı", MessageBoxButtons.OK, MessageBoxIcon.Information);
                    currentUsername = null;
                    this.Text = "User Login System";
                }
            }
            catch (Exception ex)
            {
                MessageBox.Show($"API Hatası: {ex.Message}", "Hata", MessageBoxButtons.OK, MessageBoxIcon.Error);
            }
        }
    }

    /// <summary>
    /// User Attributes Form - Kullanıcı özelliklerini yönetme
    /// </summary>
    public partial class UserAttributesForm : Form
    {
        private static readonly HttpClient client = new HttpClient();
        private const string API_BASE_URL = "http://localhost:5000/api/v1";
        private string username;

        public UserAttributesForm(string username)
        {
            InitializeComponent();
            this.username = username;
            client.BaseAddress = new Uri(API_BASE_URL);
        }

        private void UserAttributesForm_Load(object sender, EventArgs e)
        {
            this.Text = $"Kullanıcı Özellikleri - {username}";
            this.Size = new Size(600, 500);
            this.StartPosition = FormStartPosition.CenterScreen;

            // Attribute Name Label & TextBox
            Label attrNameLabel = new Label() { Text = "Özellik Adı:", Left = 20, Top = 20, Width = 100 };
            TextBox attrNameTextBox = new TextBox() { Name = "attrNameTextBox", Left = 130, Top = 20, Width = 250 };

            // Attribute Value Label & TextBox
            Label attrValueLabel = new Label() { Text = "Değer:", Left = 20, Top = 60, Width = 100 };
            TextBox attrValueTextBox = new TextBox() { Name = "attrValueTextBox", Left = 130, Top = 60, Width = 250 };

            // Attribute Type Label & ComboBox
            Label attrTypeLabel = new Label() { Text = "Tür:", Left = 20, Top = 100, Width = 100 };
            ComboBox attrTypeComboBox = new ComboBox() { Name = "attrTypeComboBox", Left = 130, Top = 100, Width = 250 };
            attrTypeComboBox.Items.AddRange(new[] { "string", "integer", "boolean", "json" });
            attrTypeComboBox.SelectedIndex = 0;

            // Set Button
            Button setButton = new Button() { Text = "Özellik Ayarla", Left = 130, Top = 150, Width = 100 };
            setButton.Click += async (s, e) => await SetAttributeClick(attrNameTextBox.Text, attrValueTextBox.Text, attrTypeComboBox.SelectedItem.ToString());

            // Attributes ListBox
            ListBox attributesListBox = new ListBox() { Name = "attributesListBox", Left = 20, Top = 200, Width = 540, Height = 250 };

            // Refresh Button
            Button refreshButton = new Button() { Text = "Yenile", Left = 20, Top = 460, Width = 100 };
            refreshButton.Click += async (s, e) => await LoadAttributesClick(attributesListBox);

            this.Controls.Add(attrNameLabel);
            this.Controls.Add(attrNameTextBox);
            this.Controls.Add(attrValueLabel);
            this.Controls.Add(attrValueTextBox);
            this.Controls.Add(attrTypeLabel);
            this.Controls.Add(attrTypeComboBox);
            this.Controls.Add(setButton);
            this.Controls.Add(attributesListBox);
            this.Controls.Add(refreshButton);

            // Load initial attributes
            _ = LoadAttributesClick(attributesListBox);
        }

        private async Task SetAttributeClick(string name, string value, string type)
        {
            if (string.IsNullOrEmpty(name) || string.IsNullOrEmpty(value))
            {
                MessageBox.Show("Özellik adı ve değer boş olamaz!", "Hata", MessageBoxButtons.OK, MessageBoxIcon.Warning);
                return;
            }

            try
            {
                var attrData = new { attribute_name = name, attribute_value = value, attribute_type = type };
                var response = await client.PostAsJsonAsync($"/users/{username}/attributes", attrData);

                if (response.IsSuccessStatusCode)
                {
                    MessageBox.Show($"Özellik '{name}' başarıyla ayarlandı!", "Başarı", MessageBoxButtons.OK, MessageBoxIcon.Information);
                }
                else
                {
                    MessageBox.Show("Özellik ayarlanamadı!", "Hata", MessageBoxButtons.OK, MessageBoxIcon.Error);
                }
            }
            catch (Exception ex)
            {
                MessageBox.Show($"Hata: {ex.Message}", "Hata", MessageBoxButtons.OK, MessageBoxIcon.Error);
            }
        }

        private async Task LoadAttributesClick(ListBox listBox)
        {
            try
            {
                var response = await client.GetAsync($"/users/{username}/attributes");
                if (response.IsSuccessStatusCode)
                {
                    var result = await response.Content.ReadAsAsync<dynamic>();
                    listBox.Items.Clear();
                    
                    var attrs = (System.Collections.Generic.Dictionary<string, dynamic>)result.attributes;
                    foreach (var attr in attrs)
                    {
                        listBox.Items.Add($"{attr.Key}: {attr.Value.value} ({attr.Value.type})");
                    }
                }
            }
            catch (Exception ex)
            {
                MessageBox.Show($"Hata: {ex.Message}", "Hata", MessageBoxButtons.OK, MessageBoxIcon.Error);
            }
        }
    }

    static class Program
    {
        [STAThread]
        static void Main()
        {
            Application.EnableVisualStyles();
            Application.SetCompatibleTextRenderingDefault(false);
            Application.Run(new LoginForm());
        }
    }
}
