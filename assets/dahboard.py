#!/usr/bin/env python3
"""
Basit SQLite veritabanı gösterge paneli (Flask).
 - Proje kökünde bulunan .db dosyalarını listeler
 - Seçilen DB içindeki tabloları listeler
 - Her tablonun satırlarını gösterir
 - İsteğe bağlı: basit SELECT sorgusu çalıştırma

Güvenlik notu: Bu araç yerel geliştirme amaçlıdır. Uzak erişime açmayın.
"""

import sqlite3
from pathlib import Path
from flask import Flask, render_template_string, request, redirect, url_for, abort

app = Flask(__name__)
ROOT = Path(".").resolve()

# ----------------- HTML şablonları (tek dosya, minimal) -----------------
BASE = """
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>DB Dashboard</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>body{padding:20px}</style>
</head>
<body class="container">
  <h1 class="mb-3">SQLite DB Dashboard</h1>
  <div class="mb-3">
    <a class="btn btn-primary" href="{{ url_for('index') }}">Anasayfa</a>
    <span class="ms-2 text-muted">Proje dizini: {{ root }}</span>
  </div>
  {% block body %}{% endblock %}
  <hr>
  <footer class="text-muted"><small>Local dashboard — yalnızca geliştirme amaçlı</small></footer>
</body>
</html>
"""

INDEX = """
{% extends "base" %}
{% block body %}
  <h4>Bulunan .db dosyaları</h4>
  {% if dbs %}
    <ul class="list-group">
    {% for db in dbs %}
      <li class="list-group-item d-flex justify-content-between align-items-center">
        <div>
          <strong>{{ db.name }}</strong><br><small class="text-muted">{{ db.path }}</small>
        </div>
        <div>
          <a class="btn btn-sm btn-outline-primary" href="{{ url_for('view_db', dbname=db.name) }}">Aç</a>
        </div>
      </li>
    {% endfor %}
    </ul>
  {% else %}
    <div class="alert alert-warning">Proje kökünde hiç .db dosyası bulunamadı.</div>
  {% endif %}
{% endblock %}
"""

DB_VIEW = """
{% extends "base" %}
{% block body %}
  <h4>Veritabanı: {{ dbname }}</h4>
  <p>
    <a class="btn btn-sm btn-secondary" href="{{ url_for('index') }}">&larr; Geri</a>
  </p>

  <h5>Tablolar</h5>
  {% if tables %}
    <ul class="list-group mb-3">
      {% for t in tables %}
        <li class="list-group-item d-flex justify-content-between align-items-center">
          {{ t }}
          <a class="btn btn-sm btn-outline-success" href="{{ url_for('view_table', dbname=dbname, table=t) }}">Görüntüle</a>
        </li>
      {% endfor %}
    </ul>
  {% else %}
    <div class="alert alert-info">Bu veritabanında tablo bulunamadı.</div>
  {% endif %}

  <h5>Basit SQL çalıştır (sadece SELECT izinli)</h5>
  <form method="post" action="{{ url_for('run_query', dbname=dbname) }}">
    <div class="mb-2">
      <textarea name="sql" class="form-control" rows="3">{{ default_sql }}</textarea>
    </div>
    <button class="btn btn-primary btn-sm">Çalıştır</button>
  </form>

  {% if query_result is defined %}
    <hr>
    <h6>Sonuç ({{ rowcount }} satır)</h6>
    {% if rowcount == 0 %}
      <div class="alert alert-secondary">Sonuç yok.</div>
    {% else %}
      <div class="table-responsive">
        <table class="table table-sm table-bordered">
          <thead>
            <tr>
              {% for h in headers %}
                <th>{{ h }}</th>
              {% endfor %}
            </tr>
          </thead>
          <tbody>
            {% for row in query_result %}
              <tr>
                {% for cell in row %}
                  <td>{{ cell }}</td>
                {% endfor %}
              </tr>
            {% endfor %}
          </tbody>
        </table>
      </div>
    {% endif %}
  {% endif %}
{% endblock %}
"""

TABLE_VIEW = """
{% extends "base" %}
{% block body %}
  <h4>Veritabanı: {{ dbname }} — Tablo: {{ table }}</h4>
  <p>
    <a class="btn btn-sm btn-secondary" href="{{ url_for('view_db', dbname=dbname) }}">&larr; Geri</a>
  </p>
  {% if headers %}
    <div class="table-responsive">
      <table class="table table-sm table-striped table-bordered">
        <thead>
          <tr>{% for h in headers %}<th>{{ h }}</th>{% endfor %}</tr>
        </thead>
        <tbody>
          {% for row in rows %}
            <tr>{% for c in row %}<td>{{ c }}</td>{% endfor %}</tr>
          {% endfor %}
        </tbody>
      </table>
    </div>
  {% else %}
    <div class="alert alert-info">Tablo boş veya okunamadı.</div>
  {% endif %}
{% endblock %}
"""

# --------------- Yardımcı fonksiyonlar ----------------
def find_db_files():
    return sorted([p for p in ROOT.glob("**/*.db")])

def open_conn(path: Path):
    conn = sqlite3.connect(str(path))
    conn.row_factory = sqlite3.Row
    return conn

# --------------- Route'lar ----------------
@app.route("/")
def index():
    dbs = [{"name": p.name, "path": str(p)} for p in find_db_files()]
    return render_template_string(INDEX, dbs=dbs, root=str(ROOT))

@app.route("/db/<dbname>/")
def view_db(dbname):
    dbpath = next((p for p in find_db_files() if p.name == dbname), None)
    if not dbpath:
        abort(404)
    conn = open_conn(dbpath)
    try:
        cur = conn.execute("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name;")
        tables = [r["name"] for r in cur.fetchall()]
    finally:
        conn.close()
    return render_template_string(DB_VIEW, dbname=dbname, tables=tables, default_sql=f"SELECT * FROM {tables[0]} LIMIT 100;" if tables else "SELECT name FROM sqlite_master;")

@app.route("/db/<dbname>/table/<table>/")
def view_table(dbname, table):
    dbpath = next((p for p in find_db_files() if p.name == dbname), None)
    if not dbpath:
        abort(404)
    conn = open_conn(dbpath)
    try:
        cur = conn.execute(f"SELECT * FROM \"{table}\" LIMIT 500;")
        rows = [tuple(r) for r in cur.fetchall()]
        headers = [d[0] for d in cur.description] if cur.description else []
    except Exception as e:
        rows, headers = [], []
    finally:
        conn.close()
    return render_template_string(TABLE_VIEW, dbname=dbname, table=table, rows=rows, headers=headers)

@app.route("/db/<dbname>/query", methods=["POST"])
def run_query(dbname):
    dbpath = next((p for p in find_db_files() if p.name == dbname), None)
    if not dbpath:
        abort(404)
    sql = request.form.get("sql", "").strip()
    if not sql:
        return redirect(url_for("view_db", dbname=dbname))
    # Basit güvenlik: yalnızca SELECT cümlesi izinli
    if not sql.lower().lstrip().startswith("select"):
        return render_template_string(DB_VIEW, dbname=dbname, tables=[], default_sql=sql, query_result=[], rowcount=0, headers=[], root=str(ROOT), error="Sadece SELECT sorgularına izin verilir.")
    conn = open_conn(dbpath)
    try:
        cur = conn.execute(sql)
        rows = [tuple(r) for r in cur.fetchall()]
        headers = [d[0] for d in cur.description] if cur.description else []
    except Exception as e:
        rows, headers = [], []
    finally:
        conn.close()
    return render_template_string(DB_VIEW, dbname=dbname, tables=[], default_sql=sql, query_result=rows, rowcount=len(rows), headers=headers, root=str(ROOT))

# --------------- Template loader (inline) -------------
@app.context_processor
def inject_base():
    return dict(root=str(ROOT))

@app.before_first_request
def register_templates():
    # register inline base template
    app.jinja_loader = None
    app.jinja_env.globals['base'] = BASE
    # we will use render_template_string directly with the templates above

# --------------- Main ----------------
if __name__ == "__main__":
    import argparse
    parser = argparse.ArgumentParser(description="SQLite DB Dashboard (local)")
    parser.add_argument("--host", default="127.0.0.1")
    parser.add_argument("--port", default=5001, type=int)
    parser.add_argument("--open", action="store_true", help="Tarayıcıda otomatik aç (Windows)")
    args = parser.parse_args()

    url = f"http://{args.host}:{args.port}/"
    print(f"Starting DB dashboard at {url}")
    if args.open:
        import webbrowser
        webbrowser.open(url)
    app.run(host=args.host, port=args.port, debug=False)
