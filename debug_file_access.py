import os
from pathlib import Path

storage_path = "/var/www/kp/@archiv 2025"
filename = "VEC-2025-001_2025-12-05_19-25-14.json"
full_path = os.path.join(storage_path, filename)

print(f"Storage exists: {os.path.exists(storage_path)}")
print(f"Storage writable: {os.access(storage_path, os.W_OK)}")
print(f"Storage readable: {os.access(storage_path, os.R_OK)}")
print(f"File full path: {full_path}")
print(f"File exists: {os.path.exists(full_path)}")

if os.path.exists(storage_path):
    print("Listing first 5 files:")
    try:
        print(os.listdir(storage_path)[:5])
    except Exception as e:
        print(f"Error listing: {e}")

