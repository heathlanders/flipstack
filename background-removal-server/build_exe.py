"""
Build Standalone .exe for Background Removal Tool
Run this to create a portable executable

Requirements:
1. Python installed
2. Run: pip install pyinstaller

Then run: python build_exe.py
"""

import subprocess
import sys
import os

print("=" * 60)
print("Background Removal Tool - Build Standalone .exe")
print("=" * 60)
print()

# Check if PyInstaller is installed
try:
    import PyInstaller
    print("✓ PyInstaller is installed")
except ImportError:
    print("✗ PyInstaller not found")
    print()
    print("Installing PyInstaller...")
    subprocess.check_call([sys.executable, "-m", "pip", "install", "pyinstaller"])
    print("✓ PyInstaller installed")

print()
print("Building executable...")
print("This will take 2-3 minutes...")
print()

# Build command
cmd = [
    'pyinstaller',
    '--onefile',                          # Single file
    '--windowed',                         # No console window
    '--name=BackgroundRemovalTool',       # Name of .exe
    '--clean',                            # Clean build
    '--noconfirm',                        # Don't ask to overwrite
    'simple_client.py'                    # Source file
]

try:
    subprocess.run(cmd, check=True)
    
    print()
    print("=" * 60)
    print("✓ BUILD SUCCESSFUL!")
    print("=" * 60)
    print()
    print("Your executable is here:")
    print("  dist\\BackgroundRemovalTool.exe")
    print()
    print("You can now:")
    print("  1. Copy BackgroundRemovalTool.exe to any Windows PC")
    print("  2. Run it - no Python needed!")
    print("  3. It's completely portable and standalone")
    print()
    print("File size: ~15-20 MB")
    print()
    
except subprocess.CalledProcessError as e:
    print()
    print("✗ Build failed!")
    print(f"Error: {e}")
    sys.exit(1)