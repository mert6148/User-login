#!/usr/bin/env python3
"""
tester.py

Özellikler:
- Basit Tkinter GUI: API ile etkileşim (listele, aktif sorgula, profil değiştir)
- ML modülü: örnek veri (Iris) ile model eğitme/öğrenme ve tahmin
- Sistem bilgisi: CPU, RAM, disk, ağ bilgilerini gösterir (psutil varsa)
- API iletişimi: requests varsa kullanır, yoksa urllib ile fallback
- Loglama ve hata gösterimi

Kullanım:
    python3 tester.py

Notlar:
- İsteğe bağlı paketler: requests, psutil, scikit-learn, pandas
  Yüklü değilse script yine çalışır ama bazı özellikler kısıtlı olur.

"""

import sys
import threading
import json
import os
import time
import platform
from pathlib import Path
from datetime import datetime

try:
    import tkinter as tk
    from tkinter import ttk, scrolledtext, messagebox
except Exception as e:
    print("Tkinter gerekli: GUI çalışmayacak.", e)
    sys.exit(1)

# Optional dependencies
try:
    import requests
except Exception:
    requests = None

try:
    import psutil
except Exception:
    psutil = None

try:
    from sklearn.datasets import load_iris
    from sklearn.model_selection import train_test_split
    from sklearn.ensemble import RandomForestClassifier
    from sklearn.metrics import accuracy_score
except Exception:
    load_iris = None

APP_DIR = Path(__file__).resolve().parent
LOG_FILE = APP_DIR / 'tester.log'
API_KEY = '12345'  # default, GUI'den değiştirilebilir
API_BASE = 'http://localhost/api'  # default, GUI'den değiştirilebilir

# --------------------- Utilities ---------------------

def log(msg):
    ts = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
    line = f"[{ts}] {msg}\n"
    with open(LOG_FILE, 'a', encoding='utf-8') as f:
        f.write(line)
    try:
        app and app.log_text.insert(tk.END, line)
    except Exception:
        pass


def http_get(path, params=None, timeout=10):
    url = API_BASE.rstrip('/') + path
    if params is None:
        params = {}
    params['key'] = API_KEY
    if requests:
        r = requests.get(url, params=params, timeout=timeout)
        r.raise_for_status()
        return r.json()
    else:
        # fallback to urllib
        from urllib import request, parse
        q = parse.urlencode(params)
        with request.urlopen(url + '?' + q, timeout=timeout) as resp:
            return json.loads(resp.read().decode('utf-8'))


def http_post(path, data=None, timeout=10):
    url = API_BASE.rstrip('/') + path + '?key=' + API_KEY
    body = json.dumps(data or {}).encode('utf-8')
    headers = {'Content-Type': 'application/json'}
    if requests:
        r = requests.post(url, json=data or {}, timeout=timeout)
        r.raise_for_status()
        return r.json()
    else:
        from urllib import request
        req = request.Request(url, data=body, headers=headers, method='POST')
        with request.urlopen(req, timeout=timeout) as resp:
            return json.loads(resp.read().decode('utf-8'))

# --------------------- ML Helpers ---------------------

def train_example_model():
    if load_iris is None:
        raise RuntimeError('scikit-learn yüklü değil')
    iris = load_iris()
    X_train, X_test, y_train, y_test = train_test_split(iris.data, iris.target, test_size=0.2, random_state=42)
    clf = RandomForestClassifier(n_estimators=50, random_state=42)
    clf.fit(X_train, y_train)
    preds = clf.predict(X_test)
    acc = accuracy_score(y_test, preds)
    return {
        'model': clf,
        'accuracy': float(acc),
        'classes': iris.target_names.tolist()
    }

# --------------------- System Info ---------------------

def get_system_info():
    info = {
        'platform': platform.system(),
        'platform-release': platform.release(),
        'platform-version': platform.version(),
        'architecture': platform.machine(),
        'hostname': platform.node(),
        'processor': platform.processor(),
        'python': platform.python_version()
    }
    if psutil:
        info.update({
            'cpu_count': psutil.cpu_count(logical=True),
            'cpu_percent': psutil.cpu_percent(interval=0.5),
            'mem_total': psutil.virtual_memory().total,
            'mem_used': psutil.virtual_memory().used,
            'mem_percent': psutil.virtual_memory().percent,
            'disk_total': psutil.disk_usage('/').total,
            'disk_used': psutil.disk_usage('/').used,
            'disk_percent': psutil.disk_usage('/').percent,
            'net_io': psutil.net_io_counters()._asdict(),
        })
    return info

# --------------------- GUI ---------------------

class TesterApp(tk.Tk):
    def __init__(self):
        super().__init__()
        self.title('Tester - Network API & ML')
        self.geometry('900x600')

        # Top frame for API settings
        f_top = ttk.Frame(self)
        f_top.pack(fill='x', padx=8, pady=6)

        ttk.Label(f_top, text='API Base:').pack(side='left')
        self.api_base_var = tk.StringVar(value=API_BASE)
        ttk.Entry(f_top, textvariable=self.api_base_var, width=40).pack(side='left', padx=6)

        ttk.Label(f_top, text='API Key:').pack(side='left')
        self.api_key_var = tk.StringVar(value=API_KEY)
        ttk.Entry(f_top, textvariable=self.api_key_var, width=15).pack(side='left', padx=6)

        ttk.Button(f_top, text='Save', command=self.save_api_settings).pack(side='left')

        # Main panes
        pan = ttk.Panedwindow(self, orient='horizontal')
        pan.pack(fill='both', expand=True, padx=8, pady=6)

        # Left: Controls
        left = ttk.Frame(pan, width=300)
        pan.add(left, weight=1)

        ttk.Label(left, text='API Controls', font=('Segoe UI', 12, 'bold')).pack(anchor='w')
        ttk.Button(left, text='List Profiles', command=self.list_profiles).pack(fill='x', pady=4)
        ttk.Button(left, text='Get Active', command=self.get_active).pack(fill='x', pady=4)

        ttk.Label(left, text='Switch Profile:').pack(anchor='w', pady=(8,0))
        self.profile_var = tk.StringVar()
        ttk.Entry(left, textvariable=self.profile_var).pack(fill='x')
        ttk.Button(left, text='Switch', command=self.switch_profile).pack(fill='x', pady=4)

        ttk.Separator(left).pack(fill='x', pady=8)
        ttk.Label(left, text='ML (example)', font=('Segoe UI', 12, 'bold')).pack(anchor='w')
        ttk.Button(left, text='Train Example Model', command=self.train_model).pack(fill='x', pady=4)
        self.ml_status = ttk.Label(left, text='Model: not trained')
        self.ml_status.pack(anchor='w')

        ttk.Separator(left).pack(fill='x', pady=8)
        ttk.Label(left, text='System', font=('Segoe UI', 12, 'bold')).pack(anchor='w')
        ttk.Button(left, text='Show System Info', command=self.show_system).pack(fill='x', pady=4)

        # Right: Output / logs
        right = ttk.Frame(pan)
        pan.add(right, weight=3)

        ttk.Label(right, text='Output / Logs', font=('Segoe UI', 12, 'bold')).pack(anchor='w')
        self.log_text = scrolledtext.ScrolledText(right, wrap='word')
        self.log_text.pack(fill='both', expand=True)

        # Internal state
        self.model = None
        self.model_info = None

        # Populate initial log
        log('Tester started')

    def save_api_settings(self):
        global API_BASE, API_KEY
        API_BASE = self.api_base_var.get().strip()
        API_KEY = self.api_key_var.get().strip()
        log(f'API settings updated: base={API_BASE} key={API_KEY}')

    def list_profiles(self):
        def job():
            try:
                res = http_get('/network/list')
                log('LIST result: ' + json.dumps(res, ensure_ascii=False))
            except Exception as e:
                log('LIST error: ' + str(e))
                messagebox.showerror('Error', str(e))
        threading.Thread(target=job, daemon=True).start()

    def get_active(self):
        def job():
            try:
                res = http_get('/network/active')
                log('ACTIVE: ' + json.dumps(res, ensure_ascii=False))
            except Exception as e:
                log('ACTIVE error: ' + str(e))
                messagebox.showerror('Error', str(e))
        threading.Thread(target=job, daemon=True).start()

    def switch_profile(self):
        profile = self.profile_var.get().strip()
        if not profile:
            messagebox.showwarning('Warn', 'Profile is empty')
            return
        def job():
            try:
                res = http_post('/network/switch', {'profile': profile})
                log('SWITCH: ' + json.dumps(res, ensure_ascii=False))
                messagebox.showinfo('OK', 'Switched: ' + str(res.get('active')))
            except Exception as e:
                log('SWITCH error: ' + str(e))
                messagebox.showerror('Error', str(e))
        threading.Thread(target=job, daemon=True).start()

    def train_model(self):
        def job():
            try:
                self.ml_status.config(text='Training...')
                res = train_example_model()
                self.model = res['model']
                self.model_info = res
                self.ml_status.config(text=f"Model trained, accuracy={res['accuracy']:.3f}")
                log(f"ML trained, accuracy={res['accuracy']}")
            except Exception as e:
                log('ML error: ' + str(e))
                messagebox.showerror('ML Error', str(e))
                self.ml_status.config(text='Model: error')
        threading.Thread(target=job, daemon=True).start()

    def show_system(self):
        info = get_system_info()
        out = json.dumps(info, indent=2, default=str, ensure_ascii=False)
        log('SYSTEM INFO:\n' + out)
        # also show in popup
        top = tk.Toplevel(self)
        top.title('System Info')
        txt = scrolledtext.ScrolledText(top, wrap='word', width=80, height=20)
        txt.pack(fill='both', expand=True)
        txt.insert(tk.END, out)
        txt.config(state='disabled')


# --------------------- Entry ---------------------

if __name__ == '__main__':
    app = TesterApp()
    try:
        app.mainloop()
    except KeyboardInterrupt:
        log('Tester stopped by user')
        print('\nExiting')
