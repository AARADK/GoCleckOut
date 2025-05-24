#!/usr/bin/env python3
import serial
import time
import re

SERIAL_PORT = 'COM7' 
BAUDRATE = 9600
URL_TEMPLATE = 'http://localhost/GCO/frontend/user/product_detail.php?rfid={}'
URL_FILE = 'C:\\xampp\\htdocs\\GCO\\rfid_url.txt'

UID_PATTERN = re.compile(r'^[A-F0-9]{8}$')

def read_rfid():
    try:
        ser = serial.Serial(
            port=SERIAL_PORT,
            baudrate=BAUDRATE,
            timeout=1,
            write_timeout=1,
            inter_byte_timeout=1
        )

        while True:
            try:
                if ser.in_waiting:
                    data = ser.readline().decode('utf-8').strip()

                    if UID_PATTERN.match(data):
                        url = URL_TEMPLATE.format(data)
                        with open(URL_FILE, 'w') as f:
                            f.write(url)
                        time.sleep(3)

            except serial.SerialException:
                print('SerialException: Device not found')
                break
            except UnicodeDecodeError:
                print('UnicodeDecodeError: Invalid data received')
                continue

            time.sleep(0.1)

    except serial.SerialException:
        print('SerialException: Unable to open serial port')
    except OSError:
        pass
    except KeyboardInterrupt:
        pass
    finally:
        if 'ser' in locals():
            ser.close()

if __name__ == '__main__':
    read_rfid()
