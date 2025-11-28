#!/usr/bin/env python3
"""
Basit "assets" derleyicisi.

Kullanım:
    python3 compiler.py /path/to/src_dir /path/to/output_dir

Ne yapar:
 - src_dir içindeki .py dosyalarını tarar
 - her dosyada bulunan sınıf tanımlarını AST ile analiz eder
 - eğer sınıf dekoratörü olarak @set_module("assets.print") kullanılmışsa veya
   dekoratör yoksa sınıfa ilişkin meta veriyi (name, module, uuid, file, line, timestamp)
   oluşturur
 - tüm meta verileri output_dir/manifest.json içine yazar
 - aynı zamanda output_dir/assets.db adlı SQLite veritabanına kaydeder
"""

import os
import json
import uuid
from pathlib import Path
from datetime import datetime
from typing import Dict, List, Optional, Any
import sqlite3
import ast
import sys

# -- Decorator helper (kullanıcı kodlarında kullanılmak üzere) --
def set_module(module_name: str):
    """Sınıfa atandığında, sınıfın __module__ bilgisini belirlemek için kullanılabilecek dekoratör fabrikası.

    Örnek kullanıcı kodu:
        @set_module("assets.print")
        class MyAsset:
            ...
    """
    def decorator(cls):
        # gerçek zamanda Python objesi olarak kullanımı durumunda etkisini gösterir:
        cls.__module__ = module_name
        return cls
    return decorator

# -- AST araçları: dosyadan sınıf tanımlarını çıkar --
def find_classes_in_source(source: str) -> List[Dict[str, Any]]:
    """
    Bir Python kaynağını AST ile parse edip bulunan sınıfların bilgilerini döndürür.
    Her öğe: {name, lineno, decorators: [ (decorator_name, args) , ... ] }
    """
    tree = ast.parse(source)
    classes = []

    for node in ast.walk(tree):
        if isinstance(node, ast.ClassDef):
            decs = []
            for d in node.decorator_list:
                # dekoratörün adını ve (varsa) sabit argümanlarını almaya çalış
                if isinstance(d, ast.Call):
                    # örn: @set_module("assets.print")
                    func = d.func
                    if isinstance(func, ast.Name):
                        fname = func.id
                    elif isinstance(func, ast.Attribute):
                        fname = func.attr
                    else:
                        fname = ast.unparse(func) if hasattr(ast, "unparse") else "<call>"
                    args = []
                    for a in d.args:
                        if isinstance(a, ast.Constant):
                            args.append(a.value)
                        else:
                            try:
                                args.append(ast.unparse(a))
                            except Exception:
                                args.append("<expr>")
                    decs.append((fname, args))
                else:
                    # basit dekoratör: @some_decorator
                    if isinstance(d, ast.Name):
                        decs.append((d.id, []))
                    elif isinstance(d, ast.Attribute):
                        decs.append((d.attr, []))
                    else:
                        decs.append((ast.unparse(d) if hasattr(ast, "unparse") else "<decorator>", []))
            classes.append({
                "name": node.name,
                "lineno": getattr(node, "lineno", None),
                "decorators": decs,
            })
    return classes

# -- Derleyici ana işi --
def compile_assets(src_dir: Path, out_dir: Path, module_default: str = "assets.print") -> Dict[str, Any]:
    """
    src_dir içindeki .py dosyalarını tarar, sınıfları bulur, meta üretir,
    JSON manifest ve SQLite DB oluşturur. Dönen dict manifest'in Python hali.
    """
    src_dir = Path(src_dir)
    out_dir = Path(out_dir)
    out_dir.mkdir(parents=True, exist_ok=True)

    manifest = {
        "generated_at": datetime.utcnow().isoformat() + "Z",
        "module_default": module_default,
        "assets": []
    }

    # sqlite setup
    db_path = out_dir / "assets.db"
    conn = sqlite3.connect(str(db_path))
    cur = conn.cursor()
    cur.execute("""
        CREATE TABLE IF NOT EXISTS assets (
            id TEXT PRIMARY KEY,
            name TEXT,
            module TEXT,
            file TEXT,
            lineno INTEGER,
            created_at TEXT,
            extra JSON
        )
    """)
    conn.commit()

    # iterate .py files
    for p in src_dir.rglob("*.py"):
        try:
            src_text = p.read_text(encoding="utf-8")
        except Exception as e:
            print(f"[warn] dosya okunamadı {p}: {e}", file=sys.stderr)
            continue

        classes = find_classes_in_source(src_text)
        for cls in classes:
            # default module, ancak dekoratörde set_module("assets.print") gibi birşey varsa onu al
            chosen_module = module_default
            for dec_name, dec_args in cls["decorators"]:
                if dec_name == "set_module" and dec_args:
                    # args ilkini al (genelde string)
                    chosen_module = str(dec_args[0])
                # kullanıcı farklı bir dekoratör ile direkt module set etmiş olabilir; burayı
                # ihtiyaç halinde genişletebiliriz.

            asset_id = str(uuid.uuid4())
            created_at = datetime.utcnow().isoformat() + "Z"
            entry = {
                "id": asset_id,
                "name": cls["name"],
                "module": chosen_module,
                "file": str(p),
                "lineno": cls.get("lineno"),
                "created_at": created_at,
                "decorators": cls.get("decorators"),
            }
            manifest["assets"].append(entry)

            # write to sqlite
            cur.execute("""
                INSERT OR REPLACE INTO assets (id, name, module, file, lineno, created_at, extra)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            """, (asset_id, cls["name"], chosen_module, str(p), cls.get("lineno"), created_at, json.dumps({"decorators": cls.get("decorators")})))
    conn.commit()
    conn.close()

    # write manifest.json
    manifest_path = out_dir / "manifest.json"
    manifest_path.write_text(json.dumps(manifest, indent=2, ensure_ascii=False), encoding="utf-8")

    return {"manifest_path": str(manifest_path), "db_path": str(db_path), "manifest": manifest}

# -- Basit CLI --
def main(argv: Optional[List[str]] = None):
    argv = argv if argv is not None else sys.argv[1:]
    if len(argv) < 2:
        print("Kullanım: python3 compiler.py /path/to/src_dir /path/to/out_dir")
        sys.exit(2)
    src_dir = Path(argv[0])
    out_dir = Path(argv[1])
    if not src_dir.exists():
        print(f"Kaynak dizini bulunamadı: {src_dir}", file=sys.stderr)
        sys.exit(3)

    result = compile_assets(src_dir, out_dir)
    print("Derleme tamamlandı.")
    print("Manifest:", result["manifest_path"])
    print("SQLite DB:", result["db_path"])
    print(f"{len(result['manifest']['assets'])} adet asset bulundu ve kaydedildi.")

if __name__ == "__main__":
    main()
