"""Exercise the real scenario runner and xmlsec in the lab image."""
import base64
import contextlib
import copy
import hashlib
import io
import tempfile
import subprocess
import unittest
from pathlib import Path
from cryptography import x509
from cryptography.exceptions import InvalidSignature
from cryptography.hazmat.primitives import hashes, serialization
from cryptography.hazmat.primitives.asymmetric import padding
from lxml import etree
from spid_sp_test.response import SpidSpResponseCheck
from spid_sp_test.idp.settings import SAML2_IDP_CONFIG

DS = '{http://www.w3.org/2000/09/xmldsig#}'


class FixtureCheck(SpidSpResponseCheck):
    def do_authnrequest(self):
        # Synthetic input at the network boundary; rendering/signing is real.
        self.authnreq_attrs = {}
        self.response_attrs = {"ResponseID": "_response", "AssertionID": "_assertion"}

    def load_user_attributes(self):
        self.user_attrs = {}


class SigningTest(unittest.TestCase):
    def test_patch_refuses_reapplication(self):
        # The installed source is already modified: a second application must
        # fail explicitly and leave it unchanged.
        import spid_sp_test.response
        source = Path(spid_sp_test.response.__file__)
        before = source.read_bytes()
        result = subprocess.run(['python', '/opt/lab/patches/apply.py'], capture_output=True, text=True)
        self.assertEqual(result.returncode, 1)
        self.assertIn('unexpected source/version (or already patched)', result.stderr)
        self.assertEqual(source.read_bytes(), before)

    def test_scenario_runner_signatures_and_credentials(self):
        xml = '''<samlp:Response xmlns:ds="http://www.w3.org/2000/09/xmldsig#" xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol" ID="_response">
<!-- Response Signature here --><saml:Assertion xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion" ID="_assertion">
<!-- Assertion Signature here --><saml:Issuer>synthetic</saml:Issuer></saml:Assertion></samlp:Response>'''
        trusted = x509.load_pem_x509_certificate(Path(SAML2_IDP_CONFIG['cert_file']).read_bytes())
        for scenario in ("1", "4", "5", "100"):
            with tempfile.TemporaryDirectory() as directory:
                Path(directory, 'base.xml').write_text(xml)
                check = FixtureCheck(template_path=directory, test_names=[scenario], no_send_response=True)
                output = io.StringIO()
                with contextlib.redirect_stdout(output):
                    check.test_profile_spid_sp()
                root = etree.fromstring(("<?xml" + output.getvalue().split("<?xml", 1)[1]).encode())
            nodes = root.findall('.//' + DS + 'X509Certificate')
            self.assertEqual(len(nodes), 2)
            for node in nodes:
                self.assertTrue(node.text and node.text.strip(), f"scenario {scenario}: empty KeyInfo")
                embedded = x509.load_der_x509_certificate(base64.b64decode(node.text))
                signature = node.getparent().getparent().getparent()
                info = signature.find(DS + 'SignedInfo')
                canonical = etree.tostring(info, method='c14n', exclusive=True)
                value = base64.b64decode(signature.findtext(DS + 'SignatureValue'))
                embedded.public_key().verify(value, canonical, padding.PKCS1v15(), hashes.SHA256())
                reference = info.find(DS + 'Reference')
                target = copy.deepcopy(root.xpath('//*[@ID=$id]', id=reference.get('URI')[1:])[0])
                # The enveloped transform removes only this signature, keeping
                # the already signed Assertion inside the outer Response.
                removed = target.find(DS + 'Signature')
                tail = removed.tail or ''
                previous = removed.getprevious()
                if previous is None:
                    target.text = (target.text or '') + tail
                else:
                    previous.tail = (previous.tail or '') + tail
                target.remove(removed)
                digest = hashlib.sha256(etree.tostring(target, method='c14n', exclusive=True)).digest()
                self.assertEqual(digest, base64.b64decode(reference.findtext(DS + 'DigestValue')))
                if scenario == '1':
                    self.assertEqual(embedded, trusted)
                    key = serialization.load_pem_private_key(Path(SAML2_IDP_CONFIG['key_file']).read_bytes(), None)
                    self.assertEqual(embedded.public_key().public_numbers(), key.public_key().public_numbers())
                else:
                    self.assertNotEqual(embedded, trusted)
                    with self.assertRaises(InvalidSignature):
                        trusted.public_key().verify(value, canonical, padding.PKCS1v15(), hashes.SHA256())
            print(f"scenario {scenario}: both signatures and digests verified; configured trust {'accepts' if scenario == '1' else 'rejects'}")


if __name__ == "__main__":
    unittest.main()
