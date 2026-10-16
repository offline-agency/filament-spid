# Lab signing patch

`apply.py` modifies only the lab image's installed `spid_sp_test/response.py`.
Upstream: [italia/spid-sp-test v1.2.18](https://github.com/italia/spid-sp-test/tree/32ac4e7ee819d140bca69e91d8626d6f7d98d364),
commit `32ac4e7ee819d140bca69e91d8626d6f7d98d364`. Copyright remains with
upstream contributors; upstream's EUPL-1.2 license is preserved in
`upstream-LICENSE` and in the image. Local changes: Offline Agency, 2026-10-16.
The patch script and its replacements are available here with the image's
patch notice. This is modified lab tooling, not an official upstream release.

The original signer invokes xmlsec without the default certificate, leaving
empty KeyInfo certificates in both signatures. The patch supplies the
configured IdP certificate at signing time. Explicit Response certificates
retain priority, followed by per-scenario signing credentials, then the
default IdP certificate. It changes no scenarios, templates, expected statuses,
wrappers, keys, trust or application validation. Assertion signing still
precedes Response signing, preserving the outer signed digest.

The build fails on a version/source SHA mismatch, a non-unique replacement,
an already-patched file, or a changed scenario set. `--version` still prints
`1.2.18`. `/opt/lab/tool-provenance.json` separately records patch identity
`keyinfo-certificate-v1`, patch SHA-256, original/patched source hashes and the
upstream commit. The lab copies it into evidence. The build runs a regression
through the real scenario runner and xmlsec, checking both signatures and
digests, the default signing key/certificate pairing and lab trust, and
rejection under that trust of scenarios 4, 5 and 100.

Remove this patch when a released upstream version fixes certificate selection
and passes the complete lab with all original scenarios and expectations.
Update the version/source pins deliberately and rerun the integration tests,
full lab and sentinels; do not simply remove the source guard.

The bundled IdP certificate expires on 2025-03-08. php-saml/xmlseclibs use its
configured public key for signature verification in this path, without PKIX
lifetime validation. Positive real logins demonstrate that this expiry is not
a failure here. No certificate renewal, clock change or validity override is
needed. Deliberately invalid scenario credentials remain intact.

The Python base image contains wheel 0.46.3, requiring packaging >=24, while
spid-compliant-certificates 0.5.5 requires packaging ==21.2 (and pyparsing <3).
The lab pins wheel 0.45.1, which has no runtime packaging requirement, before
installing the tool; all existing runtime constraints stay pinned. Mandatory
`python -m pip check` runs in the same interpreter that executes the tool,
both at image build and at lab execution. No resolver warning is suppressed.
