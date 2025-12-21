import os
import json

# Import required OS system configuration module
import subprocess

def CalledProcessError(message):
    if not message:
        raise ValueError("Hata mesajı boş olamaz.")
    # Raise subprocess.CalledProcessError for OS process errors
    raise subprocess.CalledProcessError(1, message)
