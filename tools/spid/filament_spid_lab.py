"""
Authentication adapter for spid_sp_test (-ap filament_spid_lab.FilamentSpidLogin),
for the filament-spid lab only: it is copied into the lab container and is
not part of the plugin.

spid_sp_test fetches --authn-url with a GET; the SPID login of a Filament
panel is a POST of the real login form, with the session cookie and its CSRF
token. This adapter does exactly what a browser does: GET the panel login
page, POST its SPID form, and hand spid_sp_test the AuthnRequest that
italia/spid-laravel produced. No route bypasses CSRF.

After each SAML Response it asks for the protected panel page with the same
session: a Response only counts as accepted (HTTP 200) when the panel guard
is really signed in. A refusal that ends on a 200 login or error page is
reported as 403, so it can never pass for a successful login.

Settings: FILAMENT_SPID_LAB_IDP, the provider key the form posts (default
"test", italia/spid-laravel's test IdP, configured with spid_sp_test's IdP).
"""

import base64
import json
import os
import re
import tempfile
import urllib.parse

from spid_sp_test.authn_request import get_authn_request
from spid_sp_test.plugins.authn_request import Base


class FilamentSpidLogin(Base):
    def panel_url(self):
        # --authn-url is the panel login page, e.g. https://sp/admin/login.
        return re.sub(r"/login/?$", "", self.authn_request_url)

    @staticmethod
    def state(response):
        # Lab-only observer headers come from the actual configured panel guard.
        values = [response.headers.get(name) for name in
                  ("X-Spid-Lab-Authenticated", "X-Spid-Lab-Spid-Session")]
        if any(value not in ("0", "1") for value in values):
            raise RuntimeError("missing panel-guard diagnostics (or a transport failure)")
        return {"authenticated": values[0] == "1", "spid_session": values[1] == "1"}

    def request(self):
        # Each scenario starts as a new browser, including runs after success.
        self.requests_session.cookies.clear()
        page = self.requests_session.get(self.authn_request_url, verify=False)
        page.raise_for_status()
        state = self.state(page)
        if state["authenticated"] or state["spid_session"]:
            raise RuntimeError("the scenario did not start with an isolated guest session")

        form = re.search(r'<form[^>]*action="([^"]+)"[^>]*method="post"', page.text)
        token = re.search(r'name="_token"\s+value="([^"]+)"', page.text)
        if not form or not token:
            raise RuntimeError("the panel login page has no SPID form with a CSRF token")

        action = urllib.parse.urljoin(self.authn_request_url, form.group(1).replace("&amp;", "&"))
        provider = os.environ.get("FILAMENT_SPID_LAB_IDP", "test")
        started = self.requests_session.post(
            action,
            data={"_token": token.group(1), "provider": provider},
            allow_redirects=False,
            verify=False,
        )
        location = started.headers.get("Location", "")
        if started.status_code != 302 or "SAMLRequest=" not in location:
            raise RuntimeError(f"the SPID form did not start a login (HTTP {started.status_code})")

        # The HTTP-Redirect AuthnRequest, as spid_sp_test reads it from a file.
        with tempfile.NamedTemporaryFile("w", suffix=".txt", delete=False) as handle:
            handle.write(location)
        return get_authn_request(f"file://{handle.name}", requests_session=self.requests_session)

    def response(self, url, data: dict = {}, **kwargs):
        res = self.requests_session.post(url, data=data, allow_redirects=False, verify=False)

        panel = self.requests_session.get(self.panel_url(), allow_redirects=False, verify=False)
        state = self.state(panel)
        refusal = res.headers.get("X-Spid-Lab-Refusal")
        reason = base64.b64decode(refusal).decode() if refusal else None
        print("LAB_RESPONSE " + json.dumps({
            "acs_status": res.status_code, "panel_status": panel.status_code,
            "panel_login_redirect": panel.status_code == 302 and
                urllib.parse.urlparse(panel.headers.get("Location", "")).path ==
                urllib.parse.urlparse(self.authn_request_url).path,
            "authenticated": state["authenticated"], "spid_session": state["spid_session"],
            "error": reason, "isolated": True,
        }), flush=True)
        if panel.status_code == 200 and state["authenticated"]:
            return panel
        if res.status_code < 400:
            res.status_code = 403
        return res
