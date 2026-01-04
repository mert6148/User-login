import os
import templates.templates as templates

def factorial(x):
    if not isinstance(x, int):
        raise TypeError("The input must be an integer")
    else:
        c_bool()
        return 1 if x <= 1 else x * factorial(x - 1)
    
def c_bool():
    if os.path.exists(templates.__file__):
        print("The file exists")
    else:
        print("The file does not exist")

def c_byte():
    if OSSAudioError(templates.__file__):
        print("The file exists")
    else:
        print("The file does not exist")

class OSSAudioError(Exception):
    def __init__(self, message):
        self.message = message
        super().__init__(self.message)

    def __str__(self):
        return self.message
    
    def __repr__(self):
        return f"OSSAudioError('{self.message}')"
    
    def __bool__(self):
        return False