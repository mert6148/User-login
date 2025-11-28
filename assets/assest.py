"""
User Assets/Attributes Management Module
Gelişmiş kullanıcı varlıkları ve faktörleri yönetimi
"""

import json
from pathlib import Path
from datetime import datetime
from typing import Dict, List, Optional, Any
import sqlite3

# Asset types
ASSET_TYPE_STRING = "string"
ASSET_TYPE_INTEGER = "integer"
ASSET_TYPE_BOOLEAN = "boolean"
ASSET_TYPE_JSON = "json"
ASSET_TYPE_BINARY = "binary"
ASSET_TYPE_FILE = "file"

VALID_ASSET_TYPES = [ASSET_TYPE_STRING, ASSET_TYPE_INTEGER, ASSET_TYPE_BOOLEAN, 
                     ASSET_TYPE_JSON, ASSET_TYPE_BINARY, ASSET_TYPE_FILE]

# Standard asset categories
ASSET_CATEGORY_PROFILE = "profile"          # Ad, E-mail, Telefon vb.
ASSET_CATEGORY_PREFERENCES = "preferences"  # Tema, Dil, Zaman dilimi vb.
ASSET_CATEGORY_SECURITY = "security"        # 2FA, Parola politikası vb.
ASSET_CATEGORY_SYSTEM = "system"            # Sistem bilgileri, IP vb.
ASSET_CATEGORY_CUSTOM = "custom"            # Özel alanlar

VALID_ASSET_CATEGORIES = [ASSET_CATEGORY_PROFILE, ASSET_CATEGORY_PREFERENCES, 
                         ASSET_CATEGORY_SECURITY, ASSET_CATEGORY_SYSTEM, ASSET_CATEGORY_CUSTOM]

# Default user assets schema
DEFAULT_USER_ASSETS = {
    ASSET_CATEGORY_PROFILE: {
        "first_name": {"type": ASSET_TYPE_STRING, "description": "Ad", "required": False},
        "last_name": {"type": ASSET_TYPE_STRING, "description": "Soyad", "required": False},
        "email": {"type": ASSET_TYPE_STRING, "description": "E-posta", "required": False},
        "phone": {"type": ASSET_TYPE_STRING, "description": "Telefon", "required": False},
        "avatar_url": {"type": ASSET_TYPE_STRING, "description": "Avatar URL", "required": False},
        "bio": {"type": ASSET_TYPE_STRING, "description": "Biyografi", "required": False},
        "department": {"type": ASSET_TYPE_STRING, "description": "Bölüm", "required": False},
        "job_title": {"type": ASSET_TYPE_STRING, "description": "Pozisyon", "required": False},
    },
    ASSET_CATEGORY_PREFERENCES: {
        "theme": {"type": ASSET_TYPE_STRING, "description": "Tema (light/dark)", "required": False, "default": "light"},
        "language": {"type": ASSET_TYPE_STRING, "description": "Dil (tr/en)", "required": False, "default": "tr_TR"},
        "timezone": {"type": ASSET_TYPE_STRING, "description": "Zaman dilimi", "required": False, "default": "Europe/Istanbul"},
        "notification_level": {"type": ASSET_TYPE_STRING, "description": "Bildirim seviyesi", "required": False, "default": "medium"},
        "date_format": {"type": ASSET_TYPE_STRING, "description": "Tarih formatı", "required": False, "default": "DD/MM/YYYY"},
    },
    ASSET_CATEGORY_SECURITY: {
        "two_factor_enabled": {"type": ASSET_TYPE_BOOLEAN, "description": "2FA etkin", "required": False, "default": False},
        "two_factor_method": {"type": ASSET_TYPE_STRING, "description": "2FA yöntemi (sms/email/app)", "required": False},
        "password_expires_at": {"type": ASSET_TYPE_STRING, "description": "Parola son geçerlilik tarihi", "required": False},
        "login_attempts": {"type": ASSET_TYPE_INTEGER, "description": "Başarısız giriş sayacı", "required": False, "default": 0},
        "account_locked": {"type": ASSET_TYPE_BOOLEAN, "description": "Hesap kilitli mi?", "required": False, "default": False},
        "last_password_change": {"type": ASSET_TYPE_STRING, "description": "Son parola değişim tarihi", "required": False},
    },
    ASSET_CATEGORY_SYSTEM: {
        "ip_address": {"type": ASSET_TYPE_STRING, "description": "Son bağlantı IP'si", "required": False},
        "user_agent": {"type": ASSET_TYPE_STRING, "description": "User Agent string'i", "required": False},
        "device_info": {"type": ASSET_TYPE_JSON, "description": "Cihaz bilgileri", "required": False},
        "login_count": {"type": ASSET_TYPE_INTEGER, "description": "Toplam giriş sayısı", "required": False, "default": 0},
        "last_login": {"type": ASSET_TYPE_STRING, "description": "Son giriş tarihi", "required": False},
    },
}


class UserAsset:
    """Kullanıcı varlığını temsil eden sınıf"""
    
    def __init__(self, asset_name: str, asset_value: Any, asset_type: str = ASSET_TYPE_STRING, 
                 category: str = ASSET_CATEGORY_CUSTOM, description: str = None):
        self.asset_name = asset_name
        self.asset_value = asset_value
        self.asset_type = asset_type if asset_type in VALID_ASSET_TYPES else ASSET_TYPE_STRING
        self.category = category if category in VALID_ASSET_CATEGORIES else ASSET_CATEGORY_CUSTOM
        self.description = description or f"{asset_name} varlığı"
        self.created_at = datetime.now().isoformat()
        self.updated_at = datetime.now().isoformat()
    
    def to_dict(self):
        """Varlığı dict olarak döndür"""
        return {
            "asset_name": self.asset_name,
            "asset_value": self.asset_value,
            "asset_type": self.asset_type,
            "category": self.category,
            "description": self.description,
            "created_at": self.created_at,
            "updated_at": self.updated_at,
        }
    
    def to_json(self):
        """Varlığı JSON string olarak döndür"""
        return json.dumps(self.to_dict(), ensure_ascii=False)
    
    @staticmethod
    def from_dict(data: Dict):
        """Dict'ten UserAsset oluştur"""
        asset = UserAsset(
            asset_name=data.get("asset_name"),
            asset_value=data.get("asset_value"),
            asset_type=data.get("asset_type", ASSET_TYPE_STRING),
            category=data.get("category", ASSET_CATEGORY_CUSTOM),
            description=data.get("description")
        )
        if "created_at" in data:
            asset.created_at = data["created_at"]
        if "updated_at" in data:
            asset.updated_at = data["updated_at"]
        return asset


class UserAssetManager:
    """Kullanıcı varlıklarını yönetmek için sınıf (database tabanlı)"""
    
    def __init__(self, db_path: str = "login_system.db"):
        self.db_path = db_path
        self._init_table()
    
    def _get_connection(self):
        """Veritabanı bağlantısı al"""
        return sqlite3.connect(self.db_path)
    
    def _init_table(self):
        """user_assets tablosunu oluştur (eğer yoksa)"""
        conn = self._get_connection()
        c = conn.cursor()
        c.execute("""
            CREATE TABLE IF NOT EXISTS user_assets (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                asset_name TEXT NOT NULL,
                asset_value TEXT,
                asset_type TEXT DEFAULT 'string',
                category TEXT DEFAULT 'custom',
                description TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                UNIQUE(user_id, asset_name)
            )
        """)
        conn.commit()
        conn.close()
    
    def set_asset(self, user_id: int, asset_name: str, asset_value: Any, 
                  asset_type: str = ASSET_TYPE_STRING, category: str = ASSET_CATEGORY_CUSTOM,
                  description: str = None) -> bool:
        """Kullanıcı varlığı ayarla (varsa güncelle)"""
        try:
            conn = self._get_connection()
            c = conn.cursor()
            
            # Değeri string'e dönüştür
            if asset_type == ASSET_TYPE_JSON:
                asset_value = json.dumps(asset_value, ensure_ascii=False)
            else:
                asset_value = str(asset_value)
            
            c.execute("""
                INSERT INTO user_assets (user_id, asset_name, asset_value, asset_type, category, description)
                VALUES (?, ?, ?, ?, ?, ?)
                ON CONFLICT(user_id, asset_name) DO UPDATE SET
                    asset_value = ?,
                    asset_type = ?,
                    category = ?,
                    description = ?,
                    updated_at = CURRENT_TIMESTAMP
            """, (user_id, asset_name, asset_value, asset_type, category, description,
                  asset_value, asset_type, category, description))
            
            conn.commit()
            conn.close()
            return True
        except Exception as e:
            print(f"Hata: set_asset başarısız - {e}")
            return False
    
    def get_asset(self, user_id: int, asset_name: str) -> Optional[UserAsset]:
        """Belirtilen varlığı al"""
        try:
            conn = self._get_connection()
            c = conn.cursor()
            c.execute("""
                SELECT asset_name, asset_value, asset_type, category, description, created_at, updated_at
                FROM user_assets
                WHERE user_id = ? AND asset_name = ?
            """, (user_id, asset_name))
            row = c.fetchone()
            conn.close()
            
            if row:
                data = {
                    "asset_name": row[0],
                    "asset_value": row[1],
                    "asset_type": row[2],
                    "category": row[3],
                    "description": row[4],
                    "created_at": row[5],
                    "updated_at": row[6],
                }
                return UserAsset.from_dict(data)
            return None
        except Exception as e:
            print(f"Hata: get_asset başarısız - {e}")
            return None
    
    def get_assets_by_category(self, user_id: int, category: str) -> Dict[str, UserAsset]:
        """Kategoriye göre tüm varlıkları al"""
        try:
            conn = self._get_connection()
            c = conn.cursor()
            c.execute("""
                SELECT asset_name, asset_value, asset_type, category, description, created_at, updated_at
                FROM user_assets
                WHERE user_id = ? AND category = ?
                ORDER BY asset_name
            """, (user_id, category))
            rows = c.fetchall()
            conn.close()
            
            assets = {}
            for row in rows:
                data = {
                    "asset_name": row[0],
                    "asset_value": row[1],
                    "asset_type": row[2],
                    "category": row[3],
                    "description": row[4],
                    "created_at": row[5],
                    "updated_at": row[6],
                }
                assets[row[0]] = UserAsset.from_dict(data)
            return assets
        except Exception as e:
            print(f"Hata: get_assets_by_category başarısız - {e}")
            return {}
    
    def get_all_assets(self, user_id: int) -> Dict[str, Dict[str, UserAsset]]:
        """Tüm kategorilerdeki tüm varlıkları al"""
        try:
            all_assets = {}
            for category in VALID_ASSET_CATEGORIES:
                all_assets[category] = self.get_assets_by_category(user_id, category)
            return all_assets
        except Exception as e:
            print(f"Hata: get_all_assets başarısız - {e}")
            return {}
    
    def delete_asset(self, user_id: int, asset_name: str) -> bool:
        """Varlığı sil"""
        try:
            conn = self._get_connection()
            c = conn.cursor()
            c.execute("""
                DELETE FROM user_assets
                WHERE user_id = ? AND asset_name = ?
            """, (user_id, asset_name))
            conn.commit()
            deleted = c.rowcount > 0
            conn.close()
            return deleted
        except Exception as e:
            print(f"Hata: delete_asset başarısız - {e}")
            return False
    
    def delete_all_assets(self, user_id: int) -> bool:
        """Kullanıcının tüm varlıklarını sil"""
        try:
            conn = self._get_connection()
            c = conn.cursor()
            c.execute("DELETE FROM user_assets WHERE user_id = ?", (user_id,))
            conn.commit()
            conn.close()
            return True
        except Exception as e:
            print(f"Hata: delete_all_assets başarısız - {e}")
            return False


def create_sample_user_assets(asset_manager: UserAssetManager, user_id: int):
    """Örnek kullanıcı varlıkları oluştur"""
    
    # Profile varlıkları
    asset_manager.set_asset(user_id, "first_name", "Ahmet", ASSET_TYPE_STRING, 
                           ASSET_CATEGORY_PROFILE, "Adı")
    asset_manager.set_asset(user_id, "last_name", "Yılmaz", ASSET_TYPE_STRING, 
                           ASSET_CATEGORY_PROFILE, "Soyadı")
    asset_manager.set_asset(user_id, "email", "ahmet@example.com", ASSET_TYPE_STRING, 
                           ASSET_CATEGORY_PROFILE, "E-posta")
    asset_manager.set_asset(user_id, "department", "IT", ASSET_TYPE_STRING, 
                           ASSET_CATEGORY_PROFILE, "Bölüm")
    
    # Preferences varlıkları
    asset_manager.set_asset(user_id, "theme", "dark", ASSET_TYPE_STRING, 
                           ASSET_CATEGORY_PREFERENCES, "Tema")
    asset_manager.set_asset(user_id, "language", "tr_TR", ASSET_TYPE_STRING, 
                           ASSET_CATEGORY_PREFERENCES, "Dil")
    asset_manager.set_asset(user_id, "timezone", "Europe/Istanbul", ASSET_TYPE_STRING, 
                           ASSET_CATEGORY_PREFERENCES, "Zaman dilimi")
    
    # Security varlıkları
    asset_manager.set_asset(user_id, "two_factor_enabled", "true", ASSET_TYPE_BOOLEAN, 
                           ASSET_CATEGORY_SECURITY, "2FA etkin")
    asset_manager.set_asset(user_id, "login_attempts", "0", ASSET_TYPE_INTEGER, 
                           ASSET_CATEGORY_SECURITY, "Başarısız giriş sayacı")
    
    # System varlıkları
    asset_manager.set_asset(user_id, "login_count", "15", ASSET_TYPE_INTEGER, 
                           ASSET_CATEGORY_SYSTEM, "Toplam giriş sayısı")


# CLI Commands (print.py içinde kullanılabilir)
def cli_show_user_assets(username: str, asset_manager: UserAssetManager, users_db=None):
    """CLI: Kullanıcı varlıklarını göster"""
    if not users_db:
        print("Hata: Users database gereklidir")
        return
    
    # Get user_id from username
    try:
        conn = sqlite3.connect("login_system.db")
        c = conn.cursor()
        c.execute("SELECT id FROM users WHERE username = ?", (username,))
        result = c.fetchone()
        conn.close()
        
        if not result:
            print(f"Kullanıcı '{username}' bulunamadı")
            return
        
        user_id = result[0]
        all_assets = asset_manager.get_all_assets(user_id)
        
        print(f"\n=== {username} Kullanıcı Varlıkları ===\n")
        
        for category in VALID_ASSET_CATEGORIES:
            assets = all_assets.get(category, {})
            if assets:
                print(f"--- {category.upper()} ---")
                for asset_name, asset in assets.items():
                    print(f"  {asset_name}: {asset.asset_value} ({asset.asset_type})")
                print()
    except Exception as e:
        print(f"Hata: {e}")


if __name__ == "__main__":
    # Test
    manager = UserAssetManager()
    create_sample_user_assets(manager, 1)
    
    # Display
    print(json.dumps(
        {k: {ak: av.to_dict() for ak, av in v.items()} 
         for k, v in manager.get_all_assets(1).items()},
        ensure_ascii=False, indent=2
    ))
