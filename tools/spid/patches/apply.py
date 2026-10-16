"""Build-only patch for italia/spid-sp-test v1.2.18 (EUPL-1.2).

Upstream: 32ac4e7ee819d140bca69e91d8626d6f7d98d364/src/spid_sp_test/response.py
Remove when a released tool supplies the matching signing certificate and
passes the complete lab. Never import this script from the application.
"""
import hashlib
import importlib.metadata
import importlib.util
import json
from pathlib import Path

source = Path(importlib.util.find_spec("spid_sp_test").origin).parent / "response.py"
expected = "612a03221884386d4a67667de070b8c64eab4e7db7df6e0adf4cb0dd9c1f56eb"
if importlib.metadata.version("spid-sp-test") != "1.2.18" or hashlib.sha256(source.read_bytes()).hexdigest() != expected:
    raise SystemExit("lab signing patch: unexpected source/version (or already patched)")
replacements = [
    ('        # not used here ... so, commented.\n        # self.public_cert_fpath = kwargs.get("cert_file") or SAML2_IDP_CONFIG["cert_file"]',
     '        self.public_cert_fpath = kwargs.get("cert_file") or SAML2_IDP_CONFIG["cert_file"]'),
    ('cert_file=response_obj.response_attrs.get("X509Certificate"),',
     'cert_file=(response_obj.response_attrs.get("X509Certificate")\n'
     '                               or response_obj.conf.get("sign_credentials", {}).get("certificate")\n'
     '                               or self.public_cert_fpath),'),
]
content = source.read_text()
for old, new in replacements:
    if content.count(old) != 1:
        raise SystemExit("lab signing patch: rejected replacement")
    content = content.replace(old, new)
source.write_text(content)
identity = {"upstream_version": "1.2.18", "upstream_sha": "32ac4e7ee819d140bca69e91d8626d6f7d98d364",
            "patch": "keyinfo-certificate-v1", "patch_sha256": hashlib.sha256(Path(__file__).read_bytes()).hexdigest(),
            "original_source_sha256": expected, "patched_source_sha256": hashlib.sha256(source.read_bytes()).hexdigest()}
Path("/opt/lab/tool-provenance.json").write_text(json.dumps(identity, indent=2) + "\n")

from spid_sp_test.responses.settings import RESPONSE_TESTS
expected_ids = json.loads(Path("/opt/lab/scenario-ids.json").read_text())
if list(RESPONSE_TESTS) != expected_ids:
    raise SystemExit("lab signing patch: unexpected scenario set")
