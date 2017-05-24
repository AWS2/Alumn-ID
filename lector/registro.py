import nfc
import ndef
import time
import sys
import binascii
import hashlib
import urllib, json
from random import random
from datetime import datetime
from pprint import pprint

if len(sys.argv) < 2:
    print("Escribe el DNI asociado.")
    exit()

clf = nfc.ContactlessFrontend('usb')

#sound = bytearray.fromhex("FF0040FC0401010402")
#clf.device.chipset.ccid_xfr_block(sound, 4)

debug = False

print("Cargado!")

def on_release(tag):
    print("remote reader is gone")
    return True

tag = clf.connect(rdwr={
    'on-connect': lambda tag: False,
    'on-release': on_release,
    'beep-on-connect': False,
    'interval': 0.1,
    'iterations': 1
})

if debug:
    print("Tarjeta encontrada de tipo " + tag.type)

id = ''.join(x.encode('hex') for x in tag.identifier)

if debug:
    print("ID: " + id)

error = False

if tag.type != 'Type2Tag':
    error = True

if error:
    sound = bytearray.fromhex("FF00405D0401010301")
    clf.device.chipset.ccid_xfr_block(sound, 2)
    print(datetime.now().strftime('%d-%m-%Y %H:%M:%S') + " Tarjeta desconocida: " + id)
else:

    record1 = nfc.ndef.Record("urn:nfc:wkt:U", "Web", "\x01esesteveterradas.cat")
    record2 = nfc.ndef.Record("urn:nfc:wkt:T", "DNI", "\x02es" + str(sys.argv[1]))
    message = nfc.ndef.Message(record1, record2)

    tag.ndef.records = [message]

    sound = bytearray.fromhex("FF0040740401010101")
    clf.device.chipset.ccid_xfr_block(sound, 2)

time.sleep(1)
clf.device.chipset.ccid_xfr_block(bytearray.fromhex("FF00400C0400000000"), 2)
exit()
