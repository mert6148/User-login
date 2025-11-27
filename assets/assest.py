#!/usr/bin/env python3
"""Test script for UserAssetManager and CLI integration."""

import print as p
import json

# Import constants from assets module
if p.ASSETS_AVAILABLE:
    from assets.assest import (
        ASSET_TYPE_STRING, ASSET_TYPE_INTEGER, ASSET_TYPE_BOOLEAN,
        ASSET_CATEGORY_PROFILE, ASSET_CATEGORY_PREFERENCES, 
        ASSET_CATEGORY_SECURITY, ASSET_CATEGORY_SYSTEM, ASSET_CATEGORY_CUSTOM,
        UserAssetManager
    )


def test_asset_management():
    """Test comprehensive asset management workflow."""
    
    print("=== Kullanıcı Varlıkları (Assets) Test Scripti ===\n")
    
    # Initialize database
    p.init_db()
    p.load_user_store()
    p.load_sessions()
    
    # Create test user
    print("1. Kullanıcı oluşturuluyor...")
    ok = p.create_user("alice", "password123", "Alice Smith")
    print(f"   Sonuç: {'Başarılı' if ok else 'Başarısız'}")
    
    # Get user ID
    conn = p.get_db_connection()
    c = conn.cursor()
    c.execute("SELECT id FROM users WHERE username = 'alice'")
    user = c.fetchone()
    conn.close()
    
    if not user:
        print("Hata: Kullanıcı oluşturulamadı!")
        return
    
    user_id = user[0]
    print(f"   Kullanıcı ID: {user_id}\n")
    
    # Initialize asset manager
    asset_mgr = UserAssetManager(p.DB_FILE) if p.ASSETS_AVAILABLE else None
    if not asset_mgr:
        print("Hata: Assets modülü mevcut değil!")
        return
    
    # Test 1: Set profile attributes
    print("2. Profil varlıkları ayarlanıyor...")
    profile_attrs = {
        "first_name": "Alice",
        "last_name": "Smith",
        "email": "alice@example.com",
        "phone": "+1-555-0101",
    }
    for attr_name, attr_value in profile_attrs.items():
        ok = asset_mgr.set_asset(user_id, attr_name, attr_value, 
                                 ASSET_TYPE_STRING, ASSET_CATEGORY_PROFILE)
        print(f"   {attr_name}: {'✓' if ok else '✗'}")
    
    # Test 2: Set preference attributes
    print("\n3. Tercih varlıkları ayarlanıyor...")
    pref_attrs = {
        "theme": "dark",
        "language": "tr",
        "timezone": "Europe/Istanbul",
        "font_size": 14,
    }
    for attr_name, attr_value in pref_attrs.items():
        asset_type = ASSET_TYPE_INTEGER if isinstance(attr_value, int) else ASSET_TYPE_STRING
        ok = asset_mgr.set_asset(user_id, attr_name, str(attr_value), 
                                 asset_type, ASSET_CATEGORY_PREFERENCES)
        print(f"   {attr_name}: {'✓' if ok else '✗'}")
    
    # Test 3: Set security attributes
    print("\n4. Güvenlik varlıkları ayarlanıyor...")
    security_attrs = {
        "two_factor_enabled": "true",
        "last_password_change": "2025-11-20",
    }
    for attr_name, attr_value in security_attrs.items():
        asset_type = ASSET_TYPE_BOOLEAN if "true" in str(attr_value).lower() else ASSET_TYPE_STRING
        ok = asset_mgr.set_asset(user_id, attr_name, str(attr_value), 
                                 asset_type, ASSET_CATEGORY_SECURITY)
        print(f"   {attr_name}: {'✓' if ok else '✗'}")
    
    # Test 4: Set system attributes
    print("\n5. Sistem varlıkları ayarlanıyor...")
    system_attrs = {
        "login_count": 42,
        "total_sessions": 156,
    }
    for attr_name, attr_value in system_attrs.items():
        asset_type = ASSET_TYPE_INTEGER if isinstance(attr_value, int) else ASSET_TYPE_STRING
        ok = asset_mgr.set_asset(user_id, attr_name, str(attr_value), 
                                 asset_type, ASSET_CATEGORY_SYSTEM)
        print(f"   {attr_name}: {'✓' if ok else '✗'}")
    
    # Test 5: Retrieve all assets
    print("\n6. Tüm varlıklar alınıyor...")
    all_assets = asset_mgr.get_all_assets(user_id)
    total_count = sum(len(assets) for assets in all_assets.values())
    print(f"   Toplam varlık sayısı: {total_count}")
    for category, assets in all_assets.items():
        if assets:
            print(f"   - {category}: {len(assets)} varlık")
    
    # Test 6: Get by category
    print("\n7. Kategoriye göre varlıklar alınıyor...")
    for category in [ASSET_CATEGORY_PROFILE, ASSET_CATEGORY_PREFERENCES]:
        assets = asset_mgr.get_assets_by_category(user_id, category)
        print(f"   {category}: {', '.join(assets.keys())}")
    
    # Test 7: Update an attribute
    print("\n8. Varlık güncelleniyor (theme: dark -> light)...")
    ok = asset_mgr.set_asset(user_id, "theme", "light", 
                             ASSET_TYPE_STRING, ASSET_CATEGORY_PREFERENCES)
    updated_theme = asset_mgr.get_asset(user_id, "theme")
    print(f"   Güncelleme: {'✓' if ok else '✗'}")
    if updated_theme:
        print(f"   Yeni değer: {updated_theme.asset_value}")
    
    # Test 8: Delete an attribute
    print("\n9. Varlık siliniyor (font_size)...")
    ok = asset_mgr.delete_asset(user_id, "font_size")
    print(f"   Silme: {'✓' if ok else '✗'}")
    
    # Final summary
    print("\n10. Final durum...")
    all_assets = asset_mgr.get_all_assets(user_id)
    total_count = sum(len(assets) for assets in all_assets.values())
    print(f"    Toplam varlık sayısı: {total_count}")
    print("\n=== Test Tamamlandı ===")
    
    # Display formatted JSON
    print("\n=== Tüm Varlıklar (JSON Formatı) ===")
    display_dict = {
        cat: {k: v.to_dict() for k, v in assets.items()}
        for cat, assets in all_assets.items()
    }
    print(json.dumps(display_dict, ensure_ascii=False, indent=2))
    
    def build_test_assets(all_assets):
    return {
        category: {
            key: value.to_dict()
            for key, value in assets.items()
        }
        for category, assets in all_assets.items()
    }
    
    print("\n11. Test kullanıcısı siliniyor...")
    test_assets = build_test_assets(all_assets)
    print("test_assets oluşturuldu.")


if __name__ == "__main__":
    test_asset_management()
