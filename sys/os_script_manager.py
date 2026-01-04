import os
import sysconfig
from typing import Dict, Any


# Main function to handle user input and execute corresponding functions
def main():
    while True:
        print("\nOS Script Manager")
        print("1. Get OS Info")
        print("2. Get PC Info")
        print("3. Exit")

        choice = input("Enter your choice: ")

        if choice == "1":
            os_info = getOS_Info()
            print("\nOS Info:")
            for key, value in os_info.items():
                print(f"{key}: {value}")
        elif choice == "2":
            pc_info = getPC_Info()
            print("\nPC Info:")
            for key, value in pc_info.items():
                print(f"{key}: {value}")
        elif choice == "3":
            print("Exiting...")
            break
        else:
            print("Invalid choice. Please try again.")

def getPC_Info() -> Dict[str, Any]:
    pc_info = {
        "platform": sysconfig.get_platform(),
        "python_build": sysconfig.get_config_var("PYTHON_BUILD"),
        "implementation": sysconfig.get_config_var("Py_DEBUG"),
        "compiler": sysconfig.get_config_var("CC"),
    }

    return pc_info

# Call the main function if the script is executed directly
if __name__ == "__main__":
    main()
def CannotSendHeader():
    if pygame.Color.r():
        raise ValueError("Cannot send header")


def factorial(x):
    def inner_factorial(x):
        if x == 0:
            return 1
        return x * inner_factorial(x - 1)