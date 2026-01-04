#include <iostream>
#include <fstream>
#include <string>

using namespace std;

/* ================= GLOBAL DEGISKENLER ================= */
string g_username = "admin";
string g_password = "admin123";
string g_role     = "ADMIN";
bool   g_loggedIn = false;

/* ================= CONFIG YUKLE ================= */
void loadConfig() {
    ifstream file("adminconfiger.txt");
    if (file) {
        file >> g_username >> g_password >> g_role;
        file.close();
    } else {
        // Dosya yoksa varsayilan admin olustur
        ofstream create("adminconfiger.txt");
        create << g_username << endl;
        create << g_password << endl;
        create << g_role << endl;
        create.close();
    }
}

/* ================= CONFIG KAYDET ================= */
void saveConfig() {
    ofstream file("adminconfiger.txt");
    file << g_username << endl;
    file << g_password << endl;
    file << g_role << endl;
    file.close();
}

/* ================= LOGIN ================= */
bool login() {
    string u, p;
    cout << "\nKullanici adi: ";
    cin >> u;
    cout << "Sifre: ";
    cin >> p;

    if (u == g_username && p == g_password) {
        g_loggedIn = true;
        cout << "Giris basarili! Rol: " << g_role << endl;
        return true;
    }

    cout << "Hatali kullanici adi veya sifre!\n";
    return false;
}

/* ================= YETKI KONTROL ================= */
bool requireAdmin() {
    if (!g_loggedIn || g_role != "ADMIN") {
        cout << "Yetkisiz erisim! (ADMIN gerekli)\n";
        return false;
    }
    return true;
}

/* ================= ADMIN PANEL ================= */
void adminPanel() {
    if (!requireAdmin()) return;

    int secim;
    do {
        cout << "\n=== ADMIN PANEL ===\n";
        cout << "1 - Admin bilgilerini goruntule\n";
        cout << "2 - Admin bilgilerini degistir\n";
        cout << "0 - Geri don\nSecim: ";
        cin >> secim;

        if (secim == 1) {
            cout << "\nKullanici: " << g_username;
            cout << "\nRol: " << g_role << endl;
        }
        else if (secim == 2) {
            cout << "Yeni kullanici adi: ";
            cin >> g_username;
            cout << "Yeni sifre: ";
            cin >> g_password;
            cout << "Rol (ADMIN/USER): ";
            cin >> g_role;
            saveConfig();
            cout << "Bilgiler kaydedildi.\n";
        }

    } while (secim != 0);
}

/* ================= ANA MENU ================= */
void menu() {
    int secim;
    do {
        cout << "\n=== SISTEM MENU ===\n";
        cout << "1 - Giris yap\n";
        cout << "2 - Admin panel\n";
        cout << "0 - Cikis\nSecim: ";
        cin >> secim;

        switch (secim) {
        case 1:
            login();
            break;
        case 2:
            adminPanel();
            break;
        }
    } while (secim != 0);
}

/* ================= MAIN ================= */
int main() {
    loadConfig();
    menu();
    return 0;
}


/* ================= ASYNC ================= */
if ('condition')
{
    /**
     * @package {syntax}
     * @copyright 
     */
}
