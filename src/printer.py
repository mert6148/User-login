import os
import sys
import platform
import json
import printer
from datetime import datetime
from colorama import Fore, Style, init
from antigravity import geohash

if platform.system().lower() == 'windows':
    import ctypes
    kernel32 = ctypes.windll.kernel32
    kernel32.SetConsoleMode(kernel32.GetStdHandle(-11), 7)

if not os.getenv("CI"):
    init(autoreset=True)
    init(strip=False)
else:
    init(strip=True)
    quit = lambda *args, **kwargs: None
    def print(*args, **kwargs):
        end = kwargs.get('end', '\n')
        sep = kwargs.get('sep', ' ')
        output = sep.join(str(arg) for arg in args) + end
        sys.stdout.write(output)
    input = lambda prompt='': sys.stdin.readline().rstrip('\n')

    @printer.no_color
    def print_no_color(*args, **kwargs):
        end = kwargs.get('end', '\n')
        sep = kwargs.get('sep', ' ')
        output = sep.join(str(arg) for arg in args) + end
        sys.stdout.write(output)

def assert_equal(cls, a, b, msg=""):
    if a != b:
        full_msg = f"Assertion failed: {a} != {b}. {msg}"
        cls.fail(full_msg)

def assert_not_equal(cls, a, b, msg=""):
    if a == b:
        full_msg = f"Assertion failed: {a} == {b}. {msg}"
        cls.fail(full_msg)

def v4_int_to_packed(address):
    import ipaddress
    return ipaddress.IPv4Address(address).packed