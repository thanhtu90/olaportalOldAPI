from cryptography.hazmat.primitives import serialization
from cryptography.hazmat.primitives.asymmetric import rsa
from cryptography.hazmat.primitives.hashes import SHA256
from cryptography import x509
from cryptography.x509.oid import NameOID
from cryptography.hazmat.primitives import serialization
import requests
import datetime

from cryptography.hazmat.backends import default_backend
from cryptography.hazmat.primitives import hashes
from cryptography.hazmat.primitives.serialization import Encoding, PrivateFormat, NoEncryption

from cryptography.hazmat.primitives.asymmetric import padding

import binascii
import struct

from Crypto.Hash import CMAC
from Crypto.Cipher import DES3

def generateKey(KBPK):
    KBEK_1 = generateCMAC(bytes.fromhex("0100000000000080"), KBPK)
    KBEK_2 = generateCMAC(bytes.fromhex("0200000000000080"), KBPK)
    KBEK = KBEK_1 + KBEK_2
    #print("KBEK_1: " + KBEK_1.hex()) # KBEK_1: f7eed7dec663351f
    #print("KBEK_2: " + KBEK_2.hex()) # KBEK_2: 735d7b2abd72dac8
    #print("KBEK  : " + KBEK.hex())   # KBEK  : f7eed7dec663351f735d7b2abd72dac8
    return KBEK

def generateCMAC(data, key):
    cmac = CMAC.new(key, ciphermod=DES3)
    cmac.update(data)
    return cmac.digest()

def decrypt_message(encrypted_message, private_key_path):
    with open(private_key_path, 'rb') as key_file:
        private_key = serialization.load_pem_private_key(
            key_file.read(),
            password=None,
            backend=default_backend()
        )

    decrypted_message = private_key.decrypt(
        encrypted_message,
        padding.OAEP(
            mgf=padding.MGF1(algorithm=hashes.SHA1()),
            algorithm=hashes.SHA1(),
            label=None
        )
    )

    return decrypted_message

# Section 1: get terminal cert

# URL to which you want to send the POST request
url = 'https://webtest.chargeanywhere.com/rki/rki.aspx'

# Get customer cert
customerCert = """-----BEGIN CERTIFICATE-----
MIIDkDCCAnigAwIBAgIEXxs+kTANBgkqhkiG9w0BAQsFADB6MQswCQYDVQQGEwJV
UzETMBEGA1UECBMKTmV3IEplcnNleTEZMBcGA1UEBxMQU291dGggUGxhaW5maWVs
ZDEcMBoGA1UEChMTQ2hhcmdlIEFueXdoZXJlIExMQzELMAkGA1UECxMCSVQxEDAO
BgNVBAMTB0Nhc3RsZXMwHhcNMjAwNzI0MjAwMzI5WhcNNDAwNzE5MjAwMzI5WjB7
MQswCQYDVQQGEwJVUzETMBEGA1UECBMKTmV3IEplcnNleTEZMBcGA1UEBxMQU291
dGggUGxhaW5maWVsZDEcMBoGA1UEChMTQ2hhcmdlIEFueXdoZXJlIExMQzELMAkG
A1UECxMCSVQxETAPBgNVBAMTCEN1c3RvbWVyMIIBIjANBgkqhkiG9w0BAQEFAAOC
AQ8AMIIBCgKCAQEAt0Pk77b9nIUWZ1chJNNHndC7BFSfm3HPS+C+Z6Icr4hibmXa
C8+4KSxhjoWlNziQ/uf+HPGDCrmN64Ie66ytViuyzoTYAQpIhQAS2Jh/jfYGh0TQ
Mr9kk9J5WHWGSI/tp1d6x49gRJzwBpu/22Sy5IRyCTCbJrSvGnbY+PtGJ2tNW8ox
gy3sCPaVcL2iuUKpQMxgyKbO0t3UGepGejsEHTYdFfMGPpirkPVHnFudnqKIUGui
qMpWc1Z4gEB4oLbB33VAaXdTNxWP3fCzSZwGWjS9QHnR+bG/KhvpDwCjjTwA42mM
bw9mwVxHT8eKryn2pj8wdPh6wmnlWZuLkLL0zQIDAQABox0wGzAMBgNVHRMEBTAD
AQH/MAsGA1UdDwQEAwICBDANBgkqhkiG9w0BAQsFAAOCAQEAdSN8O0EEyOrnN+Mi
RGPp+BO5FDJW2F94n2xcHKi3piGjxhZaoJ7qA2aq4BVd/nhu8HMKkYI/Jgr7IqWM
FOrw5De1gMqpn8QZbCe3qIMxIJ1hY1s7IskfscNBPQk0eEqP3Pk5ZrWJ5oZJK4rR
9l9W5uTkaBdXuGf0Bq5JTasImDBvD4QMCPJc3lPf3Jd1shLSV06Il0QygPxYRJJw
s3qyAc/9dCG+qF8+gxfVse3Gj4feXAA8KkMDmE5YxAETlvSXFB6G/VWOqVBrawaX
adT5pnxg/yEAln5jzebAY5Klr/QQNTUV9tgahQEAq9koqhekLGaFz/p3aIrIyZkJ
sVj5ww==
-----END CERTIFICATE-----"""

# Generate a new private key
private_key = rsa.generate_private_key(
    public_exponent=65537,
    key_size=2048,
)

# Serialize private key to PEM format
private_key_pem = private_key.private_bytes(
    encoding=serialization.Encoding.PEM,
    format=serialization.PrivateFormat.TraditionalOpenSSL,
    encryption_algorithm=serialization.NoEncryption()
)

# Save private key to a file
with open('private_key.pem', 'wb') as key_file:
    key_file.write(private_key_pem)

private_key_path = 'private_key.pem'

# Create a CSR (Certificate Signing Request)
csr = x509.CertificateSigningRequestBuilder().subject_name(x509.Name([
    x509.NameAttribute(NameOID.COUNTRY_NAME, "US"),
    x509.NameAttribute(NameOID.STATE_OR_PROVINCE_NAME, "California"),
    x509.NameAttribute(NameOID.LOCALITY_NAME, "San Francisco"),
    x509.NameAttribute(NameOID.ORGANIZATION_NAME, "Example Company"),
    x509.NameAttribute(NameOID.COMMON_NAME, "123456"),
])).add_extension(
    x509.SubjectAlternativeName([
        x509.DNSName("example.com"),
        x509.DNSName("www.example.com"),
    ]),
    critical=False,
).sign(private_key, SHA256())

# Serialize the CSR to a PEM format
csr_pem = csr.public_bytes(encoding=serialization.Encoding.PEM)
terminalCsr = csr_pem

# Form data to send in the request (including empty CustomerCert and TerminalCsr)
formData = {
    'Action': '2',
    'APIVesion': '1',
    'APIKey': 'testrki',
    'APIToken': 'testrki',
    'SerialNumber': '123456',
    'CustomerCert': customerCert,       # Empty CustomerCert field
    'TerminalCsr': terminalCsr,        # Empty TerminalCsr field
}

# Send the POST request with the modified form data
response = requests.post(url, data=formData)

# Check for any request errors
if response.status_code == 200:
    print('Request was successful')
    print('Response from the server:', response)
    terminalCert = response.text.split("TerminalCrt=")[1]
else:
    print('Request failed with status code:', response.status_code)
    print('Response content:', response.text)

# Section 2: RKI

#generate a self-signed certificate as a temp cert

# Create a self-signed certificate
subject = issuer = x509.Name([
    x509.NameAttribute(NameOID.COUNTRY_NAME, "US"),
    x509.NameAttribute(NameOID.STATE_OR_PROVINCE_NAME, "California"),
    x509.NameAttribute(NameOID.LOCALITY_NAME, "San Francisco"),
    x509.NameAttribute(NameOID.ORGANIZATION_NAME, "Example Company"),
    x509.NameAttribute(NameOID.COMMON_NAME, "123456"),
])

cert = x509.CertificateBuilder().subject_name(subject)
cert = cert.issuer_name(issuer)
cert = cert.public_key(private_key.public_key())
cert = cert.serial_number(x509.random_serial_number())
cert = cert.not_valid_before(datetime.datetime.utcnow())
cert = cert.not_valid_after(datetime.datetime.utcnow() + datetime.timedelta(days=365))
cert = cert.sign(private_key, hashes.SHA256(), default_backend())

# Serialize the certificate and private key
cert_pem = cert.public_bytes(encoding=serialization.Encoding.PEM).decode('utf-8')
tempCert = cert_pem
#private_key_pem = private_key.private_bytes(
#    encoding=Encoding.PEM,
#    format=PrivateFormat.PKCS8,
#    encryption_algorithm=NoEncryption()
#)

#print( terminalCert )

# Form data to send in the request (including empty CustomerCert and TerminalCsr)
formData = {
    'Action': '0',
    'APIVesion': '1',
    'APIKey': 'testrki',
    'APIToken': 'testrki',
    'SerialNumber': '123456',
    'CustomerCert': customerCert,       # Empty CustomerCert field
    'TerminalCert': terminalCert,        # Empty TerminalCsr field
    'TempCert': tempCert
}

# Send the POST request with the modified form data
response = requests.post(url, data=formData)

# Check for any request errors
if response.status_code == 200:
    print('Request was successful')
    print('Response from the server:', response.text)
    RKIData = bytes.fromhex( response.text.split("&RKIData=")[1] )
    currentOffset = 0
    #API Versoin 
    print( "API version: " + str(RKIData[currentOffset:currentOffset+1]) )
    #HSM Length
    currentOffset = currentOffset + 1 
    hsmLength = int.from_bytes(RKIData[currentOffset:currentOffset+4], "little")
    print( "HSM length: " + str(hsmLength) )
    #HSM Cert
    currentOffset = currentOffset + 4
    print( "HSM Cert: " +  str(RKIData[currentOffset:currentOffset+hsmLength]) )
    currentOffset = currentOffset + hsmLength
    #TMK under RSA Signature Length
    tmkUnderRsaSignatureLen = int.from_bytes(RKIData[currentOffset:currentOffset+4], "little")
    print( "TMK under RSA Signature Len: " + str(tmkUnderRsaSignatureLen) )
    currentOffset = currentOffset + 4

    print("TMK under RSA Signature: " + str(RKIData[currentOffset:currentOffset + tmkUnderRsaSignatureLen]))
    currentOffset = currentOffset + tmkUnderRsaSignatureLen 

    tmkUnderRsaLen = int.from_bytes(RKIData[currentOffset:currentOffset+4], "little")
    print( "TMK under RSA Len: " + str(tmkUnderRsaLen))
    currentOffset = currentOffset + 4

    print("TMK under RSA: " + str(RKIData[currentOffset:currentOffset + tmkUnderRsaLen]))
    encrypted_message = RKIData[currentOffset:currentOffset + tmkUnderRsaLen]
    decrypted_message = decrypt_message(encrypted_message, private_key_path)
    print( "TMK: " + str(decrypted_message) )
    KBPK = decrypted_message[4:20]

    currentOffset = currentOffset + tmkUnderRsaLen

    print( "TMK KVC: " + bytes(RKIData[currentOffset:currentOffset+3]).hex())
    currentOffset = currentOffset + 3

    keyCount = int.from_bytes(RKIData[currentOffset:currentOffset+1], "little")
    print( "KEY COUNT: " + str(keyCount) )
    currentOffset = currentOffset + 1

    print( "KEYS: " + str(RKIData[currentOffset:]) )

    while currentOffset < len(RKIData):
        print("Process Key")
        print("Key Set:" + str(RKIData[currentOffset:currentOffset+4]))
        currentOffset = currentOffset + 4
        print("Key Index:" + str(RKIData[currentOffset:currentOffset+4]))
        currentOffset = currentOffset + 4

        tr31Len  = int.from_bytes(RKIData[currentOffset:currentOffset+4], "little")
        print("TR-31 Len:" + str(tr31Len))
        currentOffset = currentOffset + 4

        print("TR-31 Block:" + str(RKIData[currentOffset:currentOffset+tr31Len]))

        #parse TR-31 Block
        print("TR_31 header:" + str(RKIData[currentOffset:currentOffset+16]))
        print("TR_31 optional:" + str(RKIData[currentOffset+16:currentOffset+40]))
        print("TR_31 KSN:" + str(RKIData[currentOffset+20:currentOffset+40]))
        print("TR_31 encKey:" + str(RKIData[currentOffset+40:currentOffset+88]))
        encKey = bytes.fromhex(RKIData[currentOffset+40:currentOffset+88].decode("UTF-8"))
        
        print("TR_31 MAC:" + str(RKIData[currentOffset+88:currentOffset+104]))
        iv = bytes.fromhex(RKIData[currentOffset+88:currentOffset+104].decode("UTF-8"))
        print( iv )
        #print( parse_tr_31_block_version_id_b(RKIData[currentOffset:currentOffset+tr31Len]) )
        currentOffset = currentOffset + tr31Len 

        print("IPEK KVC:" + bytes(RKIData[currentOffset:currentOffset+3]).hex())
        currentOffset = currentOffset + 3

    #print( len(RKIData[currentOffset:]) )

#Keys	Key Count * Key Structure Len

        #KBPK = bytes.fromhex('80EC1526EA0B6DF7C479DACB23B54685')
        #print("XXX")
        #print(KBPK)
        #iv = bytes.fromhex('1D5DE7A8828F2DA3') # iv = MAC
        #encKey = bytes.fromhex('166F99873C10DF71FD3A8C9A28C886D6B8BC1CC82E574ECE')

        KBEK = generateKey(KBPK)
        cipher = DES3.new(KBEK, DES3.MODE_CBC, iv)
        ptKB = cipher.decrypt(encKey) # plaintext key block

        lenKey = int.from_bytes(ptKB[:2], "big") // 8
        key = ptKB[2:2+lenKey]

        print("Plaintext key block: " + ptKB.hex()) # Plaintext key block: 00800056f2d5d6dd8391ea1a3d38416bf967216d5c9e51d2

        print("Plaintext key:       " + key.hex())  # Plaintext key:       0056f2d5d6dd8391ea1a3d38416bf967

else:
    print('Request failed with status code:', response.status_code)
    print('Response content:', response.text)

