"""Fail-closed report parser regressions, independent of Docker or Composer."""
import copy
import hashlib
import json
from pathlib import Path
import subprocess
import tempfile
import unittest

LAB = Path(__file__).resolve().parents[1]
IDS = json.loads((LAB / "scenario-ids.json").read_text())


def report(category, count):
    return {"test": {"sp": {category: {"Checks": [{"test": f"check {i}", "result": "success"} for i in range(count)]}}}}


class SummaryTest(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.root = Path(self.tmp.name)
        self.directory = self.root / "evidence"
        self.directory.mkdir()
        self.metadata = {"evidence": ["LOCAL_METADATA_CHECK_PASSED"], "summary": {"executed": 104, "passed": 104, "failed": 0, "warnings": 0}}
        self.authn = report("authnrequest_extra", 151)
        self.responses = report("prerequisites", 135)
        self.responses["test"]["sp"]["response"] = {"Checks": [{"test": f"Test [{i}] fixture", "test_id": i, "result": "success"} for i in IDS]}
        self.sentinel = report("prerequisites", 135)
        self.sentinel["test"]["sp"]["response"] = {"Checks": [{"test": "Test [1] fixture", "result": "failure"}]}
        self.diagnostics = [{"scenario": i, "authenticated": i in ['1','31','95','96','109','110','103'], "panel_login_redirect": i not in ['1','31','95','96','109','110','103'], "panel_status": 200 if i in ['1','31','95','96','109','110','103'] else 302, "error": None if i in ['1','31','95','96','109','110','103'] else ("SPID user-generated anomaly" if i in ["104","105","106","107","108","111"] else "SAML response validation error: invalid fixture"), "isolated": True} for i in IDS]
        self.denial = [{"scenario": "1", "authenticated": False, "panel_status": 302, "panel_login_redirect": True, "spid_session": False, "error": "the user may not access the panel", "isolated": True}]

    def tearDown(self):
        self.tmp.cleanup()

    def run_summary(self, exits=(0,0,1), damage=None):
        files = {"tool-provenance.json": {"upstream_version": "1.2.18", "upstream_sha": "32ac4e7ee819d140bca69e91d8626d6f7d98d364", "patch": "keyinfo-certificate-v1", "patch_sha256": hashlib.sha256((LAB/'patches/apply.py').read_bytes()).hexdigest(), "original_source_sha256": "612a03221884386d4a67667de070b8c64eab4e7db7df6e0adf4cb0dd9c1f56eb", "patched_source_sha256": "8e94ffac6c2794bc1c680c474924978c7f8c514cd81295ceea6c97c82252c3b5"},
                 "environment.json": {"sha": "a"*40, "dirty_files": 0, "php": "8.5.10", "packages": {name: {"version": "1.0", "reference": "b"*40} for name in ["italia/spid-laravel", "onelogin/php-saml", "robrichards/xmlseclibs", "laravel/framework", "filament/filament"]}},
                 "../metadata-report.json": self.metadata, "authn-request.json": self.authn,
                 "responses.json": self.responses, "sentinel-gate.json": self.sentinel,
                 "response-diagnostics.json": self.diagnostics, "sentinel-gate-diagnostics.json": self.denial}
        for name, value in files.items():
            (self.directory / name).write_text(json.dumps(value))
        if damage:
            damage(self.directory)
        result = subprocess.run(["php", str(LAB / "lab-summary.php"), str(self.directory), *map(str,exits), "1.2.18"], capture_output=True, text=True)
        return json.loads(result.stdout)["gate"]

    def test_complete_reports_pass(self):
        self.assertEqual(self.run_summary(), "pass")

    def test_invalid_reports_fail(self):
        for name in ['authn-request.json', 'responses.json', 'sentinel-gate.json', '../metadata-report.json', 'tool-provenance.json', 'environment.json']:
            for content in [None, '', '{', '{}', '{"test":{"sp":[]}}']:
                with self.subTest(name=name, content=content):
                    def damage(directory):
                        path = directory / name
                        path.unlink() if content is None else path.write_text(content)
                    self.assertEqual(self.run_summary(damage=damage), "fail")

    def test_upstream_tuple_description_is_counted(self):
        self.authn['test']['sp']['authnrequest_extra']['Checks'][0]['test'] = ['upstream tuple']
        self.assertEqual(self.run_summary(), 'pass')

    def test_mismatched_patch_provenance_fails(self):
        def damage(directory):
            path = directory / 'tool-provenance.json'
            value = json.loads(path.read_text())
            value['patch_sha256'] = '0' * 64
            path.write_text(json.dumps(value))
        self.assertEqual(self.run_summary(damage=damage), 'fail')

    def test_unknown_result_fails(self):
        self.responses['test']['sp']['response']['Checks'][0]['result'] = 'unknown'
        self.assertEqual(self.run_summary(), 'fail')

    def test_missing_or_duplicate_scenario_one_fails(self):
        entries = self.responses['test']['sp']['response']['Checks']
        entries[0] = copy.deepcopy(entries[1])
        self.assertEqual(self.run_summary(), "fail")

    def test_interrupted_or_nonzero_run_fails(self):
        for exits in [(1,0,1),(0,1,1),(0,0,0),(0,0,137)]:
            self.assertEqual(self.run_summary(exits), "fail")
        self.responses['test']['sp']['response']['Checks'].pop()
        self.assertEqual(self.run_summary(), "fail")

    def test_unrelated_sentinel_failure_fails(self):
        self.denial[0]['error'] = 'Unable to extract public key'
        self.assertEqual(self.run_summary(), 'fail')

    def test_authenticated_denied_citizen_fails(self):
        self.denial[0]['authenticated'] = True
        self.assertEqual(self.run_summary(), 'fail')

    def test_infrastructure_refusal_fails(self):
        self.diagnostics[1]['error'] = None
        self.assertEqual(self.run_summary(), 'fail')

    def test_unrelated_application_failure_fails(self):
        self.diagnostics[1]['error'] = 'Illuminate\\Database\\QueryException'
        self.assertEqual(self.run_summary(), 'fail')

    def test_missing_diagnostics_fail(self):
        self.assertEqual(self.run_summary(damage=lambda d: (d/'response-diagnostics.json').unlink()), 'fail')


if __name__ == '__main__':
    unittest.main()
