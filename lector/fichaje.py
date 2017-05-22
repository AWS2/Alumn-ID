import nfc
import time
import binascii
import hashlib
import urllib, json
from random import random
from datetime import datetime
from pprint import pprint

clf = nfc.ContactlessFrontend('usb')

#sound = bytearray.fromhex("FF0040FC0401010402")
#clf.device.chipset.ccid_xfr_block(sound, 4)

debug = False

print("Cargado!")

def on_release(tag):
    print("remote reader is gone")
    return True

def check_user(hash):
    rn = str(random() * 1000)
    url = "http://example.com/login.php?rand=" + rn + "&id=" + hash
    response = urllib.urlopen(url)
    data = json.loads(response.read())
    if not data or data["status"] == "error":
        return False
    # TODO
    print(datetime.now().strftime('%d-%m-%Y %H:%M:%S') + " Validado: " + str(data["name"]))
    return True

while True:
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
    print("ID: " + id)

    error = False

    if tag.type == 'Type2Tag':
        error = True

    elif tag.type == 'Type4Tag':
        # print(tag.dump())
        # Buscar DNI
        r = tag.transceive(bytearray.fromhex("00A40000 02 2F01"), 2)
        if r[-2:] == '\x90\x00': # Si es DNI
            error = True
            if debug:
                print("DNIe encontrado")

        # Buscar PPSE
        sel = bytearray("2PAY.SYS.DDF01")
        r = tag.transceive(bytearray.fromhex("00A404000E") + sel, 2)
        if r[-2:] == '\x90\x00': # Si es Tarjeta PPSE
            # pprint(r)
            # print("---------")
            # app = r[3:] # XX XX 84
            # length = int(binascii.hexlify(app[0:1]), 16)
            # name = (app[1:length+1])
            # # pprint((r[1+length:]))
            # pprint(str(name))
            # pprint(app)
            error = False
            app = None
            for x in range(len(r)):
                if r[x:x+1][0] == 79: # 4F
                    appln = int(r[x+1:x+2][0])
                    app = r[x+2:x+2+appln]
                    break

            if app is not None:
                r = bytearray.fromhex("00A40400")
                r += bytes(chr(appln))
                r += app
                r = tag.transceive(r, 2)
                # pprint(r)

                    # print("Encontrado")
        # sound = bytearray.fromhex("FF0040280401010101")
        # clf.device.chipset.ccid_xfr_block(sound, 2)

        # Buscar PSE
        else:
            sel = bytearray("1PAY.SYS.DDF01")
            r = tag.transceive(bytearray.fromhex("00A404000E") + sel, 2)
            if r[-2:] != '\x90\x00': # Si es Tarjeta PSE
                error = True
                if debug:
                    print("Tarjeta desconocida")

        if not error:
            error = True # Hay que encontrar el ID
            sels = [
                "00 B2 01 0C 00",
                "00 B2 01 14 00",
                "00 B2 05 1C 00",
                "80 CA 9F 36 00"
            ]
            trid = None
            for x in sels:
                x = bytearray.fromhex(x)
                # pprint(x)
                r = tag.transceive(x, 2)
                # print(''.join('{:02x}'.format(x) for x in r))
                # pprint(r)
                for i in range(len(r)):
                    tracks = [
                        bytearray.fromhex("5713"),
                        bytearray.fromhex("6B13"),
                        bytearray.fromhex("5A08")
                    ]
                    if r[i:i+2] in tracks: # 57 13 -> Track 2
                        trid = r[i+2:i+2+8]
                #    elif r[i:i+2] == bytearray.fromhex("5D08"): # Track ID
                #        trid = r[i+2:i+2+8]
                    if trid is not None:
                        trid = ''.join('{:02x}'.format(x) for x in trid)
                        # pprint(trid)
                        break
                if trid is not None:
                    break
            if trid is not None:
                error = False
                fid = trid[0:4] + trid[12:16]
                chash = hashlib.sha1("T:" + id + fid).hexdigest()

                if debug:
                    print("TR: " + fid)
                    print("Sale: '" + id + fid + "'")
                    print("Hash: " + chash)

                if check_user(chash):
                    sound = bytearray.fromhex("FF0040740401010101")
                else:
                    sound = bytearray.fromhex("FF0040F30402010201")
                    print(datetime.now().strftime('%d-%m-%Y %H:%M:%S') + " Tarjeta no registrada.")

                clf.device.chipset.ccid_xfr_block(sound, 2)
                while tag.is_present:
                    time.sleep(0.1)

                clf.device.chipset.ccid_xfr_block(bytearray.fromhex("FF00400C0400000000"), 2)
                continue


    if error:
        sound = "FF00405D0401010301" # 01
        print(datetime.now().strftime('%d-%m-%Y %H:%M:%S') + " Tarjeta desconocida.")
    else:
                    #28
        sound = "FF0040740401010101" # 01

    sound = bytearray.fromhex(sound)
    clf.device.chipset.ccid_xfr_block(sound, 2)

    while tag.is_present:
        time.sleep(0.1)
    # Una vez sacada, blink off
    clf.device.chipset.ccid_xfr_block(bytearray.fromhex("FF00400C0400000000"), 2)
