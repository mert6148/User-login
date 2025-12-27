import os
import platform
import sys
import s2clientprotocol
from typing import Dict, Any


# Function to get operating system information
def get_OS() -> Dict[str, Any]:
    os_info = {
        "os_name": platform.system(),
        "os_version": platform.version(),
        "architecture": platform.machine(),
        "python_version": platform.python_version(),
        "sc2_protocol_version": s2clientprotocol.common_pb2.SC2ProtocolVersion().version,
    }

# Get Node.js version if available
    try:
        import subprocess
        node_version = subprocess.check_output(['node', '--version']).decode().strip()
        os_info["node_version"] = node_version
    except Exception:
        os_info["node_version"] = "Node.js not installed"
    
# Get Java version if available
    try:
        java_version = subprocess.check_output(['java', '-version'], stderr=subprocess.STDOUT).decode().split('\n')[0]
        os_info["java_version"] = java_version
    except Exception:
        os_info["java_version"] = "Java not installed"
    
    return os_info