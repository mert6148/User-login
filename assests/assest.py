"""
User Assets/Attributes Management Module
Gelişmiş kullanıcı varlıkları ve faktörleri yönetimi
"""

import json
import re
from pathlib import Path
from datetime import datetime
from typing import Dict, List, Optional, Any
import sqlite3

# Database constants
DEFAULT_DB_PATH = "login_system.db"
PROTECTED_DB_PATH = "protected_assets.db"
SELECT_USER_BY_USERNAME_QUERY = "SELECT id FROM users WHERE username = ?"

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
        "first_name": {"type": ASSET_TYPE_STRING, "description": "Ad", "required": False, "max_length": 100},
        "last_name": {"type": ASSET_TYPE_STRING, "description": "Soyad", "required": False, "max_length": 100},
        "email": {"type": ASSET_TYPE_STRING, "description": "E-posta", "required": False, "max_length": 255, "pattern": r"^[^\s@]+@[^\s@]+\.[^\s@]+$"},
        "phone": {"type": ASSET_TYPE_STRING, "description": "Telefon", "required": False, "max_length": 20},
        "avatar_url": {"type": ASSET_TYPE_STRING, "description": "Avatar URL", "required": False, "max_length": 500},
        "bio": {"type": ASSET_TYPE_STRING, "description": "Biyografi", "required": False, "max_length": 1000},
        "department": {"type": ASSET_TYPE_STRING, "description": "Bölüm", "required": False, "max_length": 100},
        "job_title": {"type": ASSET_TYPE_STRING, "description": "Pozisyon", "required": False, "max_length": 100},
    },
    ASSET_CATEGORY_PREFERENCES: {
        "theme": {"type": ASSET_TYPE_STRING, "description": "Tema (light/dark)", "required": False, "default": "light", "allowed_values": ["light", "dark"]},
        "language": {"type": ASSET_TYPE_STRING, "description": "Dil (tr/en)", "required": False, "default": "tr_TR", "max_length": 10},
        "timezone": {"type": ASSET_TYPE_STRING, "description": "Zaman dilimi", "required": False, "default": "Europe/Istanbul", "max_length": 50},
        "notification_level": {"type": ASSET_TYPE_STRING, "description": "Bildirim seviyesi", "required": False, "default": "medium", "allowed_values": ["low", "medium", "high"]},
        "date_format": {"type": ASSET_TYPE_STRING, "description": "Tarih formatı", "required": False, "default": "DD/MM/YYYY", "max_length": 20},
    },
    ASSET_CATEGORY_SECURITY: {
        "two_factor_enabled": {"type": ASSET_TYPE_BOOLEAN, "description": "2FA etkin", "required": False, "default": False},
        "two_factor_method": {"type": ASSET_TYPE_STRING, "description": "2FA yöntemi (sms/email/app)", "required": False, "allowed_values": ["sms", "email", "app"]},
        "password_expires_at": {"type": ASSET_TYPE_STRING, "description": "Parola son geçerlilik tarihi", "required": False, "max_length": 50},
        "login_attempts": {"type": ASSET_TYPE_INTEGER, "description": "Başarısız giriş sayacı", "required": False, "default": 0, "min_value": 0, "max_value": 10},
        "account_locked": {"type": ASSET_TYPE_BOOLEAN, "description": "Hesap kilitli mi?", "required": False, "default": False},
        "last_password_change": {"type": ASSET_TYPE_STRING, "description": "Son parola değişim tarihi", "required": False, "max_length": 50},
    },
    ASSET_CATEGORY_SYSTEM: {
        "ip_address": {"type": ASSET_TYPE_STRING, "description": "Son bağlantı IP'si", "required": False, "max_length": 45, "pattern": r"^(\d{1,3}\.){3}\d{1,3}$|^([0-9a-fA-F]{1,4}:){7}[0-9a-fA-F]{1,4}$"},
        "user_agent": {"type": ASSET_TYPE_STRING, "description": "User Agent string'i", "required": False, "max_length": 500},
        "device_info": {"type": ASSET_TYPE_JSON, "description": "Cihaz bilgileri", "required": False},
        "login_count": {"type": ASSET_TYPE_INTEGER, "description": "Toplam giriş sayısı", "required": False, "default": 0, "min_value": 0},
        "last_login": {"type": ASSET_TYPE_STRING, "description": "Son giriş tarihi", "required": False, "max_length": 50},
    },
}


class AssetSchemaValidator:
    """Kullanıcı girişlerini kontrol ederken veritabanını korumak için şema validasyon sınıfı"""
    
    def __init__(self, schema: Dict = None):
        """Şema validatörü oluştur"""
        self.schema = schema or DEFAULT_USER_ASSETS
    
    def validate_asset_value(self, category: str, asset_name: str, asset_value: Any, 
                            asset_type: str = None) -> tuple[bool, Optional[str]]:
        """
        Asset değerini şemaya göre doğrula
        Returns: (is_valid, error_message)
        """
        import re
        
        # Kategori kontrolü
        if category not in VALID_ASSET_CATEGORIES:
            return False, f"Geçersiz kategori: {category}"
        
        # Şemada asset var mı kontrol et
        category_schema = self.schema.get(category, {})
        if asset_name not in category_schema:
            # Custom kategoride herhangi bir asset olabilir
            if category != ASSET_CATEGORY_CUSTOM:
                return False, f"Şemada tanımlı olmayan asset: {asset_name}"
        
        field_schema = category_schema.get(asset_name, {})
        
        # Tip kontrolü
        expected_type = field_schema.get("type", asset_type or ASSET_TYPE_STRING)
        if asset_type and asset_type != expected_type:
            return False, f"Beklenen tip: {expected_type}, alınan tip: {asset_type}"
        
        # Tip dönüşümü ve validasyonu
        try:
            validated_value = asset_value
            
            if expected_type == ASSET_TYPE_INTEGER:
                if isinstance(asset_value, str):
                    validated_value = int(asset_value)
                elif not isinstance(asset_value, int):
                    return False, f"Integer tipi bekleniyor, alınan: {type(asset_value).__name__}"
                else:
                    validated_value = asset_value
                
                # Min/Max kontrolü
                if "min_value" in field_schema and validated_value < field_schema["min_value"]:
                    return False, f"Değer minimum {field_schema['min_value']} olmalı"
                if "max_value" in field_schema and validated_value > field_schema["max_value"]:
                    return False, f"Değer maksimum {field_schema['max_value']} olmalı"
            
            elif expected_type == ASSET_TYPE_BOOLEAN:
                if isinstance(asset_value, str):
                    validated_value = asset_value.lower() in ("true", "1", "yes", "on")
                elif not isinstance(asset_value, bool):
                    return False, f"Boolean tipi bekleniyor, alınan: {type(asset_value).__name__}"
                else:
                    validated_value = asset_value
            
            elif expected_type == ASSET_TYPE_STRING:
                if not isinstance(asset_value, str):
                    validated_value = str(asset_value)
                else:
                    validated_value = asset_value
                
                # Max length kontrolü
                if "max_length" in field_schema and len(validated_value) > field_schema["max_length"]:
                    return False, f"String uzunluğu maksimum {field_schema['max_length']} karakter olmalı"
                
                # Pattern kontrolü (regex)
                if "pattern" in field_schema:
                    pattern = field_schema["pattern"]
                    if not re.match(pattern, validated_value):
                        return False, f"Değer pattern'e uymuyor: {pattern}"
                
                # Allowed values kontrolü
                if "allowed_values" in field_schema:
                    if validated_value not in field_schema["allowed_values"]:
                        return False, f"İzin verilen değerler: {field_schema['allowed_values']}"
            
            elif expected_type == ASSET_TYPE_JSON:
                if isinstance(asset_value, str):
                    # JSON string kontrolü
                    json.loads(asset_value)
                    validated_value = asset_value
                elif isinstance(asset_value, (dict, list)):
                    validated_value = asset_value
                else:
                    return False, f"JSON tipi bekleniyor (dict/list/string), alınan: {type(asset_value).__name__}"
            
            # Required kontrolü
            if field_schema.get("required", False) and (validated_value is None or validated_value == ""):
                return False, f"Gerekli alan: {asset_name}"
            
            return True, None
            
        except (ValueError, json.JSONDecodeError) as e:
            error_type = "Tip dönüşüm" if isinstance(e, ValueError) else "Geçersiz JSON formatı"
            return False, f"{error_type} hatası: {str(e)}"
        except Exception as e:
            return False, f"Validasyon hatası: {str(e)}"
    
    def validate_login_assets(self, login_data: Dict[str, Any]) -> tuple[bool, List[str]]:
        """
        Giriş sırasında gelen asset verilerini doğrula
        Returns: (is_valid, error_messages)
        """
        errors = []
        
        # Her kategori için kontrol
        for category, assets in login_data.items():
            if category not in VALID_ASSET_CATEGORIES:
                errors.append(f"Geçersiz kategori: {category}")
                continue
            
            # Kategori içindeki her asset için kontrol
            for asset_name, asset_value in assets.items():
                # Asset tipini şemadan al
                category_schema = self.schema.get(category, {})
                field_schema = category_schema.get(asset_name, {})
                expected_type = field_schema.get("type", ASSET_TYPE_STRING)
                
                is_valid, error_msg = self.validate_asset_value(
                    category, asset_name, asset_value, expected_type
                )
                
                if not is_valid:
                    errors.append(f"{category}.{asset_name}: {error_msg}")
        
        return len(errors) == 0, errors
    
    def sanitize_asset_value(self, asset_value: Any, asset_type: str) -> Any:
        """
        Asset değerini temizle ve güvenli hale getir (SQL injection koruması)
        """
        if asset_type == ASSET_TYPE_STRING:
            # SQL injection karakterlerini temizle
            if isinstance(asset_value, str):
                # Tehlikeli karakterleri kaldır
                dangerous_chars = ["'", '"', ";", "--", "/*", "*/", "xp_", "sp_"]
                for char in dangerous_chars:
                    asset_value = asset_value.replace(char, "")
                return asset_value
            return str(asset_value)
        
        elif asset_type == ASSET_TYPE_INTEGER:
            try:
                return int(asset_value)
            except (ValueError, TypeError):
                return 0
        
        elif asset_type == ASSET_TYPE_BOOLEAN:
            if isinstance(asset_value, str):
                return asset_value.lower() in ("true", "1", "yes", "on")
            return bool(asset_value)
        
        elif asset_type == ASSET_TYPE_JSON:
            if isinstance(asset_value, str):
                try:
                    return json.loads(asset_value)
                except json.JSONDecodeError:
                    return {}
            return asset_value
        
        return asset_value
    
    def get_default_value(self, category: str, asset_name: str) -> Any:
        """Şemadan varsayılan değeri al"""
        category_schema = self.schema.get(category, {})
        field_schema = category_schema.get(asset_name, {})
        return field_schema.get("default", None)


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
    
    def __init__(self, db_path: str = DEFAULT_DB_PATH, enable_validation: bool = True):
        self.db_path = db_path
        self.validator = AssetSchemaValidator() if enable_validation else None
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
                  description: str = None, validate: bool = True) -> tuple[bool, Optional[str]]:
        """
        Kullanıcı varlığı ayarla (varsa güncelle)
        Veritabanını korumak için şema validasyonu yapar
        Returns: (success, error_message)
        """
        try:
            # Şema validasyonu (kullanıcı girişlerini kontrol ederken veritabanını korumak için)
            if validate and self.validator:
                is_valid, error_msg = self.validator.validate_asset_value(
                    category, asset_name, asset_value, asset_type
                )
                if not is_valid:
                    return False, f"Validasyon hatası: {error_msg}"
                
                # Değeri temizle (SQL injection koruması)
                asset_value = self.validator.sanitize_asset_value(asset_value, asset_type)
            
            conn = self._get_connection()
            c = conn.cursor()
            
            # Değeri string'e dönüştür (parametreli sorgu kullanarak SQL injection koruması)
            if asset_type == ASSET_TYPE_JSON:
                if not isinstance(asset_value, str):
                    asset_value = json.dumps(asset_value, ensure_ascii=False)
            elif asset_type == ASSET_TYPE_BOOLEAN:
                if isinstance(asset_value, bool):
                    asset_value = "true" if asset_value else "false"
                else:
                    asset_value = str(asset_value)
            elif asset_type == ASSET_TYPE_INTEGER:
                asset_value = str(asset_value)
            else:
                asset_value = str(asset_value)
            
            # Parametreli sorgu kullanarak SQL injection koruması
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
            return True, None
        except sqlite3.IntegrityError as e:
            error_msg = f"Veritabanı bütünlük hatası: {str(e)}"
            print(f"Hata: {error_msg}")
            return False, error_msg
        except Exception as e:
            error_msg = f"set_asset başarısız - {str(e)}"
            print(f"Hata: {error_msg}")
            return False, error_msg
    
    def set_assets_from_login(self, user_id: int, login_data: Dict[str, Any]) -> tuple[bool, List[str]]:
        """
        Giriş sırasında gelen asset verilerini toplu olarak kaydet
        Kullanıcı girişlerini kontrol ederken veritabanını korumak için şema validasyonu yapar
        Returns: (success, error_messages)
        """
        if not self.validator:
            return False, ["Validatör aktif değil"]
        
        # Önce tüm verileri doğrula
        is_valid, errors = self.validator.validate_login_assets(login_data)
        if not is_valid:
            return False, errors
        
        # Tüm veriler geçerliyse kaydet
        failed_assets = []
        
        for category, assets in login_data.items():
            for asset_name, asset_value in assets.items():
                # Şemadan tip bilgisini al
                category_schema = self.validator.schema.get(category, {})
                field_schema = category_schema.get(asset_name, {})
                asset_type = field_schema.get("type", ASSET_TYPE_STRING)
                description = field_schema.get("description", None)
                
                success, error_msg = self.set_asset(
                    user_id, asset_name, asset_value, 
                    asset_type, category, description, 
                    validate=False  # Zaten validate_login_assets'te doğrulandı
                )
                
                if not success:
                    failed_assets.append(f"{category}.{asset_name}: {error_msg}")
        
        if failed_assets:
            return False, failed_assets
        
        return True, []
    
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
        conn = sqlite3.connect(DEFAULT_DB_PATH)
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


def create_protected_assets_database(db_path: str = PROTECTED_DB_PATH, backup_enabled: bool = True):
    """
    CLI komutları kullanırken if ve for döngüsü kullanarak 
    kullanıcı varlıklarını koruyan veritabanı oluştur
    
    Args:
        db_path: Veritabanı dosya yolu
        backup_enabled: Yedekleme aktif mi?
    """
    try:
        conn = sqlite3.connect(db_path)
        c = conn.cursor()
        
        # Kullanıcı varlıkları koruma tablosu
        c.execute("""
            CREATE TABLE IF NOT EXISTS protected_user_assets (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                asset_name TEXT NOT NULL,
                asset_value TEXT,
                asset_type TEXT DEFAULT 'string',
                category TEXT DEFAULT 'custom',
                description TEXT,
                is_protected BOOLEAN DEFAULT 1,
                protection_level TEXT DEFAULT 'standard',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                last_backup_at TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                UNIQUE(user_id, asset_name)
            )
        """)
        
        # Koruma log tablosu
        c.execute("""
            CREATE TABLE IF NOT EXISTS asset_protection_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                asset_name TEXT NOT NULL,
                action_type TEXT NOT NULL,
                old_value TEXT,
                new_value TEXT,
                protection_status TEXT,
                timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        """)
        
        # Yedekleme tablosu
        if backup_enabled:
            c.execute("""
                CREATE TABLE IF NOT EXISTS asset_backups (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    asset_name TEXT NOT NULL,
                    backup_data TEXT NOT NULL,
                    backup_type TEXT DEFAULT 'full',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                )
            """)
        
        # İndeksler oluştur
        c.execute("CREATE INDEX IF NOT EXISTS idx_protected_user_id ON protected_user_assets(user_id)")
        c.execute("CREATE INDEX IF NOT EXISTS idx_protected_asset_name ON protected_user_assets(asset_name)")
        c.execute("CREATE INDEX IF NOT EXISTS idx_protection_logs_user_id ON asset_protection_logs(user_id)")
        c.execute("CREATE INDEX IF NOT EXISTS idx_protection_logs_timestamp ON asset_protection_logs(timestamp)")
        
        conn.commit()
        conn.close()
        
        print(f"✓ Korumalı veritabanı oluşturuldu: {db_path}")
        return True
        
    except sqlite3.Error as e:
        print(f"✗ Veritabanı oluşturma hatası: {e}")
        return False
    except Exception as e:
        print(f"✗ Beklenmeyen hata: {e}")
        return False


def protect_user_assets_cli(asset_manager: UserAssetManager, username: str = None):
    """
    CLI komutları kullanırken if ve for döngüsü ile 
    kullanıcı varlıklarını koruma sistemi
    """
    # Veritabanı bağlantısı
    db_path = PROTECTED_DB_PATH
    
    # Eğer veritabanı yoksa oluştur
    if not Path(db_path).exists():
        print("Korumalı veritabanı bulunamadı, oluşturuluyor...")
        if not create_protected_assets_database(db_path):
            print("Veritabanı oluşturulamadı!")
            return False
    
    try:
        conn = sqlite3.connect(db_path)
        c = conn.cursor()
        
        # Tüm kullanıcıları kontrol et
        users_conn = sqlite3.connect(asset_manager.db_path)
        users_c = users_conn.cursor()
        
        # Kullanıcı listesi al
        if username:
            users_c.execute("SELECT id, username FROM users WHERE username = ?", (username,))
        else:
            users_c.execute("SELECT id, username FROM users")
        users = users_c.fetchall()
        
        users_conn.close()
        
        # Her kullanıcı için varlıkları koru
        protected_count = 0
        for user_id, user_name in users:
            # Kullanıcının tüm varlıklarını al
            all_assets = asset_manager.get_all_assets(user_id)
            
            # Her kategori için kontrol et
            for category in VALID_ASSET_CATEGORIES:
                category_assets = all_assets.get(category, {})
                
                # Kategori içindeki her varlık için koruma uygula
                for asset_name, asset in category_assets.items():
                    # Eğer varlık zaten korunuyorsa atla
                    c.execute("""
                        SELECT id FROM protected_user_assets 
                        WHERE user_id = ? AND asset_name = ?
                    """, (user_id, asset_name))
                    
                    existing = c.fetchone()
                    
                    # Eğer varlık korunmuyorsa koruma ekle
                    if not existing:
                        # Koruma seviyesi belirle
                        protection_level = "high"
                        if category == ASSET_CATEGORY_SECURITY:
                            protection_level = "critical"
                        elif category == ASSET_CATEGORY_SYSTEM:
                            protection_level = "high"
                        elif category == ASSET_CATEGORY_PROFILE:
                            protection_level = "standard"
                        else:
                            protection_level = "standard"
                        
                        # Korumalı varlığı kaydet
                        c.execute("""
                            INSERT INTO protected_user_assets 
                            (user_id, asset_name, asset_value, asset_type, category, 
                             description, is_protected, protection_level)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                        """, (user_id, asset_name, asset.asset_value, asset.asset_type,
                              category, asset.description, 1, protection_level))
                        
                        # Koruma logu kaydet
                        c.execute("""
                            INSERT INTO asset_protection_logs 
                            (user_id, asset_name, action_type, new_value, protection_status)
                            VALUES (?, ?, ?, ?, ?)
                        """, (user_id, asset_name, "PROTECT", asset.asset_value, protection_level))
                        
                        protected_count += 1
                    else:
                        # Mevcut korumalı varlığı güncelle
                        c.execute("""
                            UPDATE protected_user_assets 
                            SET asset_value = ?, updated_at = CURRENT_TIMESTAMP
                            WHERE user_id = ? AND asset_name = ?
                        """, (asset.asset_value, user_id, asset_name))
                        
                        # Güncelleme logu kaydet
                        c.execute("""
                            INSERT INTO asset_protection_logs 
                            (user_id, asset_name, action_type, new_value, protection_status)
                            VALUES (?, ?, ?, ?, ?)
                        """, (user_id, asset_name, "UPDATE", asset.asset_value, "active"))
        
        conn.commit()
        conn.close()
        
        print(f"✓ {protected_count} varlık korundu")
        if username:
            print(f"✓ Kullanıcı '{username}' varlıkları korundu")
        else:
            print("✓ Tüm kullanıcı varlıkları korundu")
        
        return True
        
    except sqlite3.Error as e:
        print(f"✗ Veritabanı hatası: {e}")
        return False
    except Exception as e:
        print(f"✗ Beklenmeyen hata: {e}")
        return False


def restore_protected_assets(asset_manager: UserAssetManager, username: str, 
                            backup_date: str = None):
    """
    Korumalı varlıkları geri yükle
    """
    db_path = PROTECTED_DB_PATH
    
    if not Path(db_path).exists():
        print("Korumalı veritabanı bulunamadı!")
        return False
    
    try:
        # Kullanıcı ID'sini al
        users_conn = sqlite3.connect(asset_manager.db_path)
        users_c = users_conn.cursor()
        users_c.execute(SELECT_USER_BY_USERNAME_QUERY, (username,))
        result = users_c.fetchone()
        users_conn.close()
        
        if not result:
            print(f"Kullanıcı '{username}' bulunamadı!")
            return False
        
        user_id = result[0]
        
        # Korumalı varlıkları al
        conn = sqlite3.connect(db_path)
        c = conn.cursor()
        
        if backup_date:
            c.execute("""
                SELECT asset_name, asset_value, asset_type, category, description
                FROM protected_user_assets
                WHERE user_id = ? AND updated_at <= ?
            """, (user_id, backup_date))
        else:
            c.execute("""
                SELECT asset_name, asset_value, asset_type, category, description
                FROM protected_user_assets
                WHERE user_id = ?
            """, (user_id,))
        
        protected_assets = c.fetchall()
        conn.close()
        
        # Varlıkları geri yükle
        restored_count = 0
        for asset_name, asset_value, asset_type, category, description in protected_assets:
            success, _ = asset_manager.set_asset(
                user_id, asset_name, asset_value, asset_type, category, description
            )
            if success:
                restored_count += 1
        
        print(f"✓ {restored_count} varlık geri yüklendi")
        return True
        
    except Exception as e:
        print(f"✗ Geri yükleme hatası: {e}")
        return False


def show_protection_status(username: str = None):
    """
    Koruma durumunu göster
    """
    db_path = PROTECTED_DB_PATH
    
    if not Path(db_path).exists():
        print("Korumalı veritabanı bulunamadı!")
        return
    
    try:
        conn = sqlite3.connect(db_path)
        c = conn.cursor()
        
        if username:
            # Kullanıcı ID'sini al
            users_conn = sqlite3.connect(DEFAULT_DB_PATH)
            users_c = users_conn.cursor()
            users_c.execute(SELECT_USER_BY_USERNAME_QUERY, (username,))
            result = users_c.fetchone()
            users_conn.close()
            
            if not result:
                print(f"Kullanıcı '{username}' bulunamadı!")
                return
            
            user_id = result[0]
            
            c.execute("""
                SELECT asset_name, category, protection_level, is_protected, updated_at
                FROM protected_user_assets
                WHERE user_id = ?
                ORDER BY category, asset_name
            """, (user_id,))
        else:
            c.execute("""
                SELECT user_id, asset_name, category, protection_level, is_protected, updated_at
                FROM protected_user_assets
                ORDER BY user_id, category, asset_name
            """)
        
        protected_assets = c.fetchall()
        conn.close()
        
        if not protected_assets:
            print("Korumalı varlık bulunamadı!")
            return
        
        print("\n=== Koruma Durumu ===\n")
        
        # Kategori bazında grupla
        categories = {}
        for asset in protected_assets:
            if username:
                asset_name, category, protection_level, is_protected, _ = asset
            else:
                user_id, asset_name, category, protection_level, is_protected, _ = asset
            
            if category not in categories:
                categories[category] = []
            categories[category].append(asset)
        
        # Her kategori için göster
        for category in VALID_ASSET_CATEGORIES:
            if category in categories:
                print(f"--- {category.upper()} ---")
                for asset in categories[category]:
                    if username:
                        asset_name, _, protection_level, is_protected, _ = asset
                        print(f"  {asset_name}: ", end="")
                    else:
                        user_id, asset_name, _, protection_level, is_protected, _ = asset
                        print(f"  [User ID: {user_id}] {asset_name}: ", end="")
                    
                    status = "✓ Korunuyor" if is_protected else "✗ Korunmuyor"
                    print(f"{status} (Seviye: {protection_level})")
                print()
        
    except Exception as e:
        print(f"✗ Hata: {e}")

def main():
    """Ana fonksiyon - Şema validatörü örnek kullanımı"""
    
    # UserAssetManager örneği oluştur
    manager = UserAssetManager()
    
    # Test varlıkları
    test_assets = {
        ASSET_CATEGORY_PROFILE: {
            "first_name": "Ahmet",
            "last_name": "Yılmaz",
            "email": "ahmet@example.com",
        },
        ASSET_CATEGORY_PREFERENCES: {
            "theme": "dark",
            "language": "tr_TR",
        },
        ASSET_CATEGORY_SECURITY: {
            "two_factor_enabled": "true",
            "login_attempts": "0",
        },
    }
    
    # Test kullanıcı ID'si
    test_user_id = 1
    
    print("=" * 60)
    print("Şema Validatörü - If Döngüsü Örneği")
    print("=" * 60)
    
    # 1. Tekil varlık doğrulaması
    print("\n[1] Tekil Varlık Doğrulaması:")
    print("-" * 60)
    
    categories_to_check = [
        (ASSET_CATEGORY_PROFILE, "email", "test@example.com"),
        (ASSET_CATEGORY_PREFERENCES, "theme", "dark"),
        (ASSET_CATEGORY_SECURITY, "two_factor_enabled", "true"),
    ]
    
    for category, asset_name, asset_value in categories_to_check:
        is_valid, error_msg = validate_schema_with_conditions(
            manager, category, asset_name, asset_value
        )
        
        status = "✓ BAŞARILI" if is_valid else "✗ HATA"
        print(f"{status} | {category}.{asset_name} = {asset_value}")
        if error_msg:
            print(f"        Hata: {error_msg}")
    
    # 2. Toplu varlık doğrulaması
    print("\n[2] Toplu Varlık Doğrulaması:")
    print("-" * 60)
    
    is_valid, errors = validate_user_assets_batch(manager, test_assets)
    
    if is_valid:
        print("✓ Tüm varlıklar başarıyla doğrulandı!")
    else:
        print(f"✗ {len(errors)} hata bulundu:")
        for error in errors:
            print(f"  - {error}")
    
    # 3. Doğrulanmış varlıkları kaydet
    print("\n[3] Doğrulanmış Varlıkları Kaydet:")
    print("-" * 60)
    
    success, failed = process_validated_assets(
        manager, test_user_id, test_assets
    )
    
    if success:
        print(f"✓ Tüm varlıklar başarıyla kaydedildi (User ID: {test_user_id})")
    else:
        print(f"✗ {len(failed)} varlık kaydedilemedi:")
        for error in failed:
            print(f"  - {error}")
    
    # 4. Örnek varlıkları oluştur
    print("\n[4] Örnek Varlıkları Oluştur:")
    print("-" * 60)
    
    create_sample_user_assets(manager, test_user_id)
    print(f"✓ Örnek varlıklar oluşturuldu (User ID: {test_user_id})")
    
    # 5. Kaydedilmiş varlıkları göster
    print("\n[5] Kaydedilmiş Varlıkları Göster:")
    print("-" * 60)
    
    all_assets = manager.get_all_assets(test_user_id)
    
    for category in VALID_ASSET_CATEGORIES:
        category_assets = all_assets.get(category, {})
        if category_assets:
            print(f"\n--- {category.upper()} ---")
            for asset_name, asset in category_assets.items():
                print(f"  {asset_name}: {asset.asset_value}")
    
    print("\n" + "=" * 60)
    print("Doğrulama tamamlandı")
    print("=" * 60)


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

def name():
    """Şema validasyon modülünün adını döndür"""
    return "verify_user_assets"


def validate_schema_with_conditions(asset_manager: UserAssetManager, 
                                     category: str, asset_name: str, asset_value: Any,
                                     asset_type: str = ASSET_TYPE_STRING) -> tuple[bool, Optional[str]]:
    """
    Şema validatörü kullanarak if döngüsü ile varlık doğrulaması yap
    
    Args:
        asset_manager: UserAssetManager örneği
        category: Varlık kategorisi
        asset_name: Varlık adı
        asset_value: Varlık değeri
        asset_type: Varlık tipi (default: string)
    
    Returns:
        (is_valid, error_message)
    """
    # Önceden kontrol
    if not asset_manager or not asset_manager.validator:
        return False, "Validatör aktif değil"
    
    validator = asset_manager.validator
    
    # Ön validasyon kontrolleri
    if category not in VALID_ASSET_CATEGORIES:
        return False, f"Geçersiz kategori: {category}"
    
    if asset_value is None:
        return False, f"{asset_name} değeri boş olamaz"
    
    if asset_type not in VALID_ASSET_TYPES:
        return False, f"Geçersiz varlık tipi: {asset_type}"
    
    # Şema validatörü ile doğrulama
    return validator.validate_asset_value(category, asset_name, asset_value, asset_type)


def validate_user_assets_batch(asset_manager: UserAssetManager,
                               assets_dict: Dict[str, Dict[str, Any]]) -> tuple[bool, List[str]]:
    """
    If döngüsü kullanarak toplu varlık doğrulaması yap
    
    Args:
        asset_manager: UserAssetManager örneği
        assets_dict: {kategori: {varlık_adı: varlık_değeri}} yapısında veriler
    
    Returns:
        (all_valid, error_messages)
    """
    errors = []
    validator = asset_manager.validator if asset_manager else None
    
    if not validator:
        return False, ["Validatör aktif değil"]
    
    # Kategoriler üzerinde döngü
    for category in VALID_ASSET_CATEGORIES:
        if category not in assets_dict:
            continue
        
        category_assets = assets_dict[category]
        
        # Her kategorideki varlıklar üzerinde döngü
        if not isinstance(category_assets, dict):
            errors.append(f"{category}: Geçersiz veri tipi")
            continue
        
        for asset_name, asset_value in category_assets.items():
            # Asset tipini şemadan al
            category_schema = validator.schema.get(category, {})
            field_schema = category_schema.get(asset_name, {})
            asset_type = field_schema.get("type", ASSET_TYPE_STRING)
            
            # Değeri doğrula
            is_valid, error_msg = validator.validate_asset_value(
                category, asset_name, asset_value, asset_type
            )
            
            if not is_valid:
                errors.append(f"{category}.{asset_name}: {error_msg}")
    
    return len(errors) == 0, errors


def process_validated_assets(asset_manager: UserAssetManager, user_id: int,
                             assets_dict: Dict[str, Dict[str, Any]]) -> tuple[bool, List[str]]:
    """
    If döngüsü ile doğrulanmış varlıkları kaydet
    
    Args:
        asset_manager: UserAssetManager örneği
        user_id: Kullanıcı ID'si
        assets_dict: Doğrulanacak varlıklar
    
    Returns:
        (success, error_messages)
    """
    # Önce doğrulama yap
    is_valid, validation_errors = validate_user_assets_batch(asset_manager, assets_dict)
    
    if not is_valid:
        return False, validation_errors
    
    # Doğrulanmış varlıkları kaydet
    failed_assets = []
    validator = asset_manager.validator
    
    for category in VALID_ASSET_CATEGORIES:
        if category not in assets_dict:
            continue
        
        category_assets = assets_dict[category]
        
        if not isinstance(category_assets, dict):
            continue
        
        for asset_name, asset_value in category_assets.items():
            # Şemadan tip ve açıklamasını al
            if not validator:
                failed_assets.append(f"{category}.{asset_name}: Validatör bulunamadı")
                continue
            
            category_schema = validator.schema.get(category, {})
            field_schema = category_schema.get(asset_name, {})
            asset_type = field_schema.get("type", ASSET_TYPE_STRING)
            description = field_schema.get("description", None)
            
            # Asset'i kaydet
            success, error_msg = asset_manager.set_asset(
                user_id, asset_name, asset_value,
                asset_type, category, description,
                validate=True
            )
            
            if not success:
                failed_assets.append(f"{category}.{asset_name}: {error_msg}")
    
    if failed_assets:
        return False, failed_assets
    
    return True, []