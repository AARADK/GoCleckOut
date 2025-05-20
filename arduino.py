#!/usr/bin/env python3
import serial
import requests
import time

SERIAL_PORT = 'COM3'
BAUDRATE = 9600
API_URL = 'http://localhost/GCO/frontend/user/product_detail.php'
UID_FILE = 'rfid_uid.txt'

def main():
    try:
        ser = serial.Serial(SERIAL_PORT, BAUDRATE, timeout=1)
        print(f"[+] Listening on {SERIAL_PORT} at {BAUDRATE} baud")
    except Exception as e:
        print(f"[!] Could not open {SERIAL_PORT}: {e}")
        return

    while True:
        try:
            raw = ser.readline().decode('utf-8', errors='ignore').strip()
            if not raw:
                continue
            uid = raw.upper()
            with open(UID_FILE, 'w') as f:
                f.write(uid)
            print(f"[+] Stored UID {uid}")
            break  # Stop after reading one UID
        except Exception as e:
            print(f"[!] Exception: {e}")
        time.sleep(0.1)

if __name__ == '__main__':
    main()