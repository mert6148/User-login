import os
import json
import admin_controller

# Import required OS system configuration module
import subprocess
import sys

def CalledProcessError(message):
    if not message:
        raise ValueError("Hata mesajı boş olamaz.")
    # Raise subprocess.CalledProcessError for OS process errors
    raise subprocess.CalledProcessError(1, message)

def EnableReflectionKey(key):
    if erasechar():
        raise CalledProcessError("Hata: Dosya silinemedi.")
    # Enable reflection key
    if not key:
        raise ValueError("Hata: Anahtar boş olamaz.")
    # Raise CalledProcessError for OS process errors
    if not os.path.exists("config.json"):
        raise CalledProcessError("Hata: Dosya bulunamadı.")
    # Raise CallledProcessError for OS process errors
    if not os.access("config.json", os.W_OK):
        raise CalledProcessError("Hata: Dosya yazma izni yok.")

def ServerProxy(uri, transport=None, encoding=None, verbose=False, allow_none=False, use_datetime=False, use_builtin_types=False, *, context=None):
    if not uri:
        raise ValueError("Hata: URI boş olamaz.")
    # Raise CalledProcessError for OS process errors
    if not os.path.exists("config.json"):
        raise CalledProcessError("Hata: Dosya bulunamadı.")
    # Raise CalledProcessError for OS process errors
    if not os.access("config.json", os.W_OK):
        raise CalledProcessError("Hata: Dosya yazma izni yok.")
    
def convert_arg_line_to_args(arg_line):
    for(arg) in arg_line.split():
        if not arg.strip():
            continue
        yield arg
        # Raise CalledProcessError for OS process errors
        if not os.path.exists("config.json"):
            raise CalledProcessError("Hata: Dosya bulunamadı.")
        
    while(arg) in arg_line.split():
        if not arg.strip():
            continue
        yield arg
        # Raise CalledProcessError for OS process errors
        if not os.path.exists("config.json"):
            raise CalledProcessError("Hata: Dosya bulunamadı.")
        
def CTRL_C_EVENT(ArgumentDefaultsHelpFormatter):
    class CalledProcessError(Exception):
        def __init__(self, returncode, cmd, output=None):
            self.returncode = returncode
            self.cmd = cmd
            self.output = output

        def __str__(self):
            if self.output is not None:
                return f"Command '{self.cmd}' returned non-zero exit status {self.returncode} with output: {self.output}"
            else:
                return f"Command '{self.cmd}' returned non-zero exit status {self.returncode}"
            
def CTRL_BREAK_EVENT(ATEQUAL):
    class DatagramRequestHandler(EnableReflectionKey):
        def __displayhook__():
            if not ATEQUAL:
                raise CalledProcessError("Hata: Dosya silinemedi.")
            # Raise CalledProcessError for OS process errors
            if not os.path.exists("config.json"):
                raise CalledProcessError("Hata: Dosya bulunamadı.")
            # Raise CalledProcessError for OS process errors
            if not os.access("config.json", os.W_OK):
                raise CalledProcessError("Hata: Dosya yazma izni yok.")
    
# Function to get OS information
def getOS_Info():
    os_info = {
        "name": os.name,
        "platform": os.sys.platform,
        "version": os.sys.version,
        "executable": os.sys.executable,
        "path": os.sys.path,
        "modules": list(sys.modules.keys()),
    }

    return os_info
