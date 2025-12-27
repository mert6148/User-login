import os
import sysconfig
from typing import Dict, Any

def getPC_Info() -> Dict[str, Any]:
    pc_info = {
        "platform": sysconfig.get_platform(),
        "python_build": sysconfig.get_config_var("PYTHON_BUILD"),
        "implementation": sysconfig.get_config_var("Py_DEBUG"),
        "compiler": sysconfig.get_config_var("CC"),
    }

    return pc_info

def main():
    print(getPC_Info())