#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Şema Validatörü - If Döngüsü Test Script
Python3 İş Akışı Örneği
"""

import sys
import json
from pathlib import Path
import io

# UTF-8 encoding for output
if sys.stdout.encoding != 'utf-8':
    sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8')

# Assests modülünü ekle
sys.path.insert(0, str(Path(__file__).parent / "assests"))

from assest import (
    UserAssetManager,
    validate_schema_with_conditions,
    validate_user_assets_batch,
    process_validated_assets,
    ASSET_CATEGORY_PROFILE,
    ASSET_CATEGORY_PREFERENCES,
    ASSET_CATEGORY_SECURITY,
    ASSET_CATEGORY_SYSTEM,
    VALID_ASSET_CATEGORIES,
    ASSET_TYPE_STRING,
    ASSET_TYPE_BOOLEAN,
    ASSET_TYPE_INTEGER,
)


def test_single_validation():
    """Test 1: Tekil varlık doğrulaması"""
    print("\n" + "=" * 70)
    print("TEST 1: Tekil Varlık Doğrulaması (If Döngüsü)")
    print("=" * 70)
    
    manager = UserAssetManager()
    
    # Test veri seti
    test_cases = [
        {
            "category": ASSET_CATEGORY_PROFILE,
            "name": "email",
            "value": "user@example.com",
            "type": ASSET_TYPE_STRING,
            "expected": True
        },
        {
            "category": ASSET_CATEGORY_PROFILE,
            "name": "email",
            "value": "invalid-email",  # Invalid
            "type": ASSET_TYPE_STRING,
            "expected": False
        },
        {
            "category": ASSET_CATEGORY_PREFERENCES,
            "name": "theme",
            "value": "dark",
            "type": ASSET_TYPE_STRING,
            "expected": True
        },
        {
            "category": ASSET_CATEGORY_SECURITY,
            "name": "login_attempts",
            "value": "5",
            "type": ASSET_TYPE_INTEGER,
            "expected": True
        },
        {
            "category": ASSET_CATEGORY_SECURITY,
            "name": "login_attempts",
            "value": "15",  # Max 10
            "type": ASSET_TYPE_INTEGER,
            "expected": False
        },
    ]
    
    passed = 0
    failed = 0
    
    # If döngüsü ile test et
    for i, test_case in enumerate(test_cases, 1):
        is_valid, error_msg = validate_schema_with_conditions(
            manager,
            test_case["category"],
            test_case["name"],
            test_case["value"],
            test_case["type"]
        )
        
        # Sonuç kontrolü
        if is_valid == test_case["expected"]:
            status = "✓ PASS"
            passed += 1
        else:
            status = "✗ FAIL"
            failed += 1
        
        print(f"\n[Test {i}] {status}")
        print(f"  Kategori: {test_case['category']}")
        print(f"  Varlık: {test_case['name']} = {test_case['value']}")
        print(f"  Beklenen: {'Geçerli' if test_case['expected'] else 'Geçersiz'}")
        print(f"  Sonuç: {'Geçerli' if is_valid else 'Geçersiz'}")
        if error_msg:
            print(f"  Hata: {error_msg}")
    
    print(f"\n{'-' * 70}")
    print(f"Sonuç: {passed} Geçti, {failed} Başarısız")
    assert failed == 0, f"{failed} test failed in single validation"


def test_batch_validation():
    """Test 2: Toplu varlık doğrulaması"""
    print("\n" + "=" * 70)
    print("TEST 2: Toplu Varlık Doğrulaması (If Döngüsü)")
    print("=" * 70)
    
    manager = UserAssetManager()
    
    # Test veri seti - Tüm kategorilerde varlıklar
    test_assets = {
        ASSET_CATEGORY_PROFILE: {
            "first_name": "Ahmet",
            "last_name": "Yılmaz",
            "email": "ahmet@example.com",
            "department": "IT",
        },
        ASSET_CATEGORY_PREFERENCES: {
            "theme": "dark",
            "language": "tr_TR",
            "timezone": "Europe/Istanbul",
        },
        ASSET_CATEGORY_SECURITY: {
            "two_factor_enabled": "true",
            "login_attempts": "0",
        },
        ASSET_CATEGORY_SYSTEM: {
            "login_count": "5",
        }
    }
    
    print(f"\nDoğrulanacak varlık kategorileri: {len(test_assets)}")
    
    # If döngüsü ile kategorileri kontrol et
    total_assets = 0
    for category in VALID_ASSET_CATEGORIES:
        if category in test_assets:
            count = len(test_assets[category])
            print(f"  - {category}: {count} varlık")
            total_assets += count
    
    print(f"\nToplam varlık sayısı: {total_assets}")
    
    # Toplu doğrulama
    print(f"\nDoğrulama başlatılıyor...")
    all_valid, errors = validate_user_assets_batch(manager, test_assets)
    
    assert all_valid, f"Batch validation failed with errors: {errors}"
    print(f"✓ Tüm {total_assets} varlık başarıyla doğrulandı!")


def test_save_and_retrieve():
    """Test 3: Doğrulama, Kaydetme ve Geri Alma"""
    print("\n" + "=" * 70)
    print("TEST 3: Doğrulama → Kaydetme → Geri Alma (If Döngüsü)")
    print("=" * 70)
    
    manager = UserAssetManager()
    user_id = 999  # Test kullanıcı ID'si
    
    # Test varlıkları
    test_assets = {
        ASSET_CATEGORY_PROFILE: {
            "first_name": "Test",
            "email": "test@example.com",
        },
        ASSET_CATEGORY_PREFERENCES: {
            "theme": "light",
        },
    }
    
    print(f"\nKullanıcı ID: {user_id}")
    
    # Adım 1: Doğrulama
    print(f"\n[Adım 1] Doğrulama...")
    all_valid, errors = validate_user_assets_batch(manager, test_assets)
    
    if not all_valid:
        print(f"✗ Doğrulama başarısız:")
        for err in errors:
            print(f"  - {err}")
        return False
    
    print(f"✓ Doğrulama başarılı")
    
    # Adım 2: Kaydetme
    print(f"\n[Adım 2] Varlıkları kaydetme...")
    success, failed = process_validated_assets(manager, user_id, test_assets)
    
    if not success:
        print(f"✗ Kayıt başarısız:")
        for fail in failed:
            print(f"  - {fail}")
        return False
    
    print(f"✓ Varlıklar kaydedildi")
    
    # Adım 3: Geri Alma
    print(f"\n[Adım 3] Kaydedilmiş varlıkları geri alma...")
    all_assets = manager.get_all_assets(user_id)
    
    # If döngüsü ile kategorileri kontrol et
    retrieved_count = 0
    for category in VALID_ASSET_CATEGORIES:
        category_assets = all_assets.get(category, {})
        
        if category_assets:
            print(f"\n  {category}:")
            for asset_name, asset in category_assets.items():
                print(f"    - {asset_name}: {asset.asset_value}")
                retrieved_count += 1
    
    print(f"\n✓ Toplam {retrieved_count} varlık geri alındı")
    
    # Temizle
    manager.delete_all_assets(user_id)

    assert retrieved_count > 0, "No assets were retrieved after save and retrieve"


def test_error_handling():
    """Test 4: Hata Yönetimi"""
    print("\n" + "=" * 70)
    print("TEST 4: Hata Yönetimi (If Döngüsü)")
    print("=" * 70)
    
    manager = UserAssetManager()
    
    # Geçersiz test verileri
    invalid_tests = [
        {
            "name": "Boş değer",
            "data": {ASSET_CATEGORY_PROFILE: {"email": None}},
        },
        {
            "name": "Geçersiz kategori",
            "data": {"invalid_category": {"field": "value"}},
        },
        {
            "name": "Email formatı geçersiz",
            "data": {ASSET_CATEGORY_PROFILE: {"email": "invalid"}},
        },
        {
            "name": "Max length aşımı",
            "data": {ASSET_CATEGORY_PROFILE: {"first_name": "a" * 101}},
        },
    ]
    
    print(f"\n{len(invalid_tests)} hata testi yapılıyor...")
    any_error_detected = False
    
    # If döngüsü ile hataları test et
    for i, test in enumerate(invalid_tests, 1):
        print(f"\n[Test {i}] {test['name']}")
        
        # Kategorileri kontrol et
        has_errors = False
        for category in VALID_ASSET_CATEGORIES:
            if category in test["data"]:
                for asset_name, asset_value in test["data"][category].items():
                    is_valid, error = validate_schema_with_conditions(
                        manager, category, asset_name, asset_value
                    )
                    
                    if not is_valid:
                        print(f"  ✓ Hata tespit edildi: {error}")
                        has_errors = True
        
        if has_errors:
            any_error_detected = True
        else:
            # Toplu doğrulama ile hata kontrolü
            all_valid, errors = validate_user_assets_batch(manager, test["data"])
            if not all_valid:
                print(f"  ✓ {len(errors)} hata tespit edildi")
                any_error_detected = True
            else:
                print(f"  ✗ Hata tespit edilemedi")
    
    assert any_error_detected, "No expected errors were detected in error handling tests"


def test_if_loop_patterns():
    """Test 5: If Döngüsü Desenler"""
    print("\n" + "=" * 70)
    print("TEST 5: If Döngüsü Desenler")
    print("=" * 70)
    
    manager = UserAssetManager()
    
    test_assets = {
        ASSET_CATEGORY_PROFILE: {
            "first_name": "Ahmet",
            "email": "ahmet@example.com",
        },
        ASSET_CATEGORY_PREFERENCES: {
            "theme": "dark",
        },
    }
    
    # Desen 1: İç İçe Döngü
    print(f"\n[Desen 1] İç İçe Döngü - Tüm varlıkları kontrol et")
    total = 0
    for category in VALID_ASSET_CATEGORIES:
        if category in test_assets:
            category_assets = test_assets[category]
            if isinstance(category_assets, dict):
                for asset_name in category_assets.keys():
                    total += 1
    print(f"  ✓ {total} varlık bulundu")
    
    # Desen 2: Koşullu İşlem
    print(f"\n[Desen 2] Koşullu İşlem - Geçerli/Geçersiz Ayırma")
    valid_count = 0
    invalid_count = 0
    
    for category in VALID_ASSET_CATEGORIES:
        if category not in test_assets:
            continue
        
        for asset_name, asset_value in test_assets[category].items():
            is_valid, _ = validate_schema_with_conditions(
                manager, category, asset_name, asset_value
            )
            
            if is_valid:
                valid_count += 1
            else:
                invalid_count += 1
    
    print(f"  ✓ Geçerli: {valid_count}, Geçersiz: {invalid_count}")
    
    # Desen 3: Erken Çıkış (Continue/Break)
    print(f"\n[Desen 3] Erken Çıkış - Hata Bulunca Dur")
    error_found = False
    for category in VALID_ASSET_CATEGORIES:
        if category not in test_assets:
            continue
        
        for asset_name, asset_value in test_assets[category].items():
            is_valid, error = validate_schema_with_conditions(
                manager, category, asset_name, asset_value
            )
            
            if not is_valid:
                print(f"  ✗ Hata bulundu: {category}.{asset_name}")
                error_found = True
                break
        
        if error_found:
            break
    
    if not error_found:
        print(f"  ✓ Hata bulunmadı")

    assert total > 0, "No assets found in if loop patterns test"


def main():
    """Ana test fonksiyonu"""
    print("\n" + "=" * 70)
    print("ŞEMAVALİDATÖR - İF DÖNGÜSÜ TEST SETİ")
    print("Python3 İş Akışı")
    print("=" * 70)
    
    tests = [
        ("Tekil Doğrulama", test_single_validation),
        ("Toplu Doğrulama", test_batch_validation),
        ("Kaydetme ve Geri Alma", test_save_and_retrieve),
        ("Hata Yönetimi", test_error_handling),
        ("If Döngüsü Desenleri", test_if_loop_patterns),
    ]
    
    results = {}
    for test_name, test_func in tests:
        try:
            result = test_func()
            results[test_name] = "✓ PASS" if result else "✗ FAIL"
        except Exception as e:
            print(f"\n✗ Beklenmeyen hata: {e}")
            results[test_name] = "✗ ERROR"
    
    # Özet
    print("\n" + "=" * 70)
    print("ÖZET")
    print("=" * 70)
    for test_name, result in results.items():
        print(f"{result} {test_name}")
    
    # Genel sonuç
    passed = sum(1 for r in results.values() if "PASS" in r)
    total = len(results)
    print(f"\n{passed}/{total} test başarılı")
    
    return passed == total


if __name__ == "__main__":
    success = main()
    sys.exit(0 if success else 1)
