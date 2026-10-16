# Testing with AgID's tools

> filament-spid 1.0.0. Sources and versions checked on 16 October 2026; check
> them again before relying on them.

Three levels of evidence, never interchangeable:

| Level | Tool | Where | Proves |
|---|---|---|---|
| Unit and integration tests | Pest, Node | CI, every push | the plugin's own behaviour, with Laravel and Filament for real and SAML simulated |
| Local SPID lab | [`spid_sp_test`](https://github.com/italia/spid-sp-test) 1.2.18, pinned | `tools/spid/validate.sh`, the `spid-validation` workflow | the metadata, the AgID checks on the AuthnRequest from the real Filament form, and the SP's handling of 111 forged Responses, on a fresh application built from the distributable archive |
| Hosted AgID Validator | [validator.spid.gov.it](https://validator.spid.gov.it) | your own HTTPS deployment, by hand | what AgID checks before accreditation |

Only AgID's procedure accredits a service. Passing the lab is
`LOCAL_METADATA_CHECK_PASSED` and `LOCAL_SAML_TESTS_PASSED`, not "validated"
or "certified"; the hosted Validator is `HOSTED_VALIDATOR_NOT_RUN` until you
run it.

## The local lab

Only against your own throwaway application: it sends forged and broken SAML
Responses to the ACS. Requires Docker, PHP 8.3+, Composer and network access
to Packagist, GitHub and Docker Hub.

```bash
bash tools/spid/validate.sh /tmp/spid-lab        # LARAVEL=13 FILAMENT=5 by default
cat /tmp/spid-lab/evidence/summary.json
```

What it does:

1. Builds the pinned `spid_sp_test` container (`tools/spid/Dockerfile.spid-sp-test`:
   Python 3.11 slim by digest, `xmlsec1`, `spid-sp-test==1.2.18` with every
   runtime dependency pinned in `tools/spid/constraints.txt`). It applies the
   guarded lab-only [signing patch](../../tools/spid/patches/README.md), pins
   compatible wheel 0.45.1 and requires `python -m pip check`. The official
   version and separate local patch hash are recorded in evidence.
2. Installs the **distributable archive** of the current commit into a fresh
   Laravel application by the published quickstart
   (`.github/scripts/install-check.sh`), and validates the served metadata.
3. Trusts `spid_sp_test`'s test IdP as `italia/spid-laravel`'s `test` IdP
   (entityID, endpoints and certificate read from `spid_sp_test
   --idp-metadata`), adds one synthetic citizen (`GDASDV00A01H501J`, the
   tool's own AgID test identity) admitted by an explicit lab gate.
4. Puts Caddy (`tls internal`: a development CA per run) in front of the
   application as `https://sp.spid.test`, on a private docker network the
   tool container joins. HTTPS is required: the library keeps the request
   state in `Secure; SameSite=None` cookies. The lab application calls
   `OneLogin\Saml2\Utils::setProxyVars(true)` because it sits behind that proxy.
5. Runs `spid_sp_test` with the lab's authentication adapter
   (`tools/spid/filament_spid_lab.py`): it GETs the panel login page and POSTs
   the real SPID form with the session cookie and CSRF token, exactly as a
   browser; no route bypasses CSRF. After each Response it asks for the
   protected panel page: a Response counts as accepted only if the panel guard
   is signed in (a 200 login or error page counts as refused).
6. Sentinels that must fail: metadata altered after signing, a public SP
   without its IPA code, an HTML page, an empty file (adapter), and a
   positive Response for a citizen the gate refuses. A valid copy must pass.
7. Writes `evidence/summary.json`, source/dependency and patch provenance,
   and per-scenario diagnostics. Only complete reports (104 metadata,
   151 AuthnRequest, 246 Response assertions and all 111 original IDs), valid
   tool exits, six explicit positive authentications and an authorization-only
   denial sentinel can pass. Missing/truncated reports or provenance fail;
   an interrupted run leaves `LAB_INCOMPLETE` / fail. Reports and sanitized
   diagnostics, including the proxy log, are retained on failure.

Topology: Linux and Docker Desktop alike. `localhost` inside a container is
the container, so the tool reaches the application through Caddy on the
`spid-lab` network, and Caddy reaches `php artisan serve` on the host through
`host.docker.internal` (mapped to the host gateway). On Apple Silicon the
images used are multi-arch; the `spid-saml-check` image below is amd64 only
and runs emulated.

The CI job `spid-validation` runs the same script. It never contacts the
hosted Validator, so its availability cannot make CI flaky; its synthetic
evidence (no real organisation, no real key) is uploaded as an artefact.

Current results and exact verification limits: see the dated
[repair evidence](evidence.md#ci-repair-on-16-october-2026). The original
11 October failed run is retained as history. The lab uses explicitly
identified modified tooling; passing it grants no hosted-validator or formal
AgID acceptance status.

### The Validator, self-hosted

[`italia/spid-saml-check`](https://github.com/italia/spid-saml-check) v.2.2.5
(`tools/spid/versions.env`, amd64 only) is AgID's Validator suite, interactive.
It is not automated here: it has no documented automation API.

```bash
docker compose -f tools/spid/compose.yaml --profile validator up
# https://localhost:8443  (self-hosted demo credentials: validator / validator;
# they are NOT the credentials of the public AgID service)
```

Register the SP metadata from its *Metadata → Download* page (a URL the
container can reach: `localhost` there is the container, use
`host.docker.internal` or a lab hostname), configure its IdP metadata
(`https://localhost:8443/metadata.xml`) in your application's `validator_idp`,
then log in from the panel choosing the Validator and run the Request and
Response tests from its interface. The URL used to download the metadata does
not authorise rewriting the endpoints signed inside it.

## The hosted AgID Validator and the acceptance test

From AgID's [technical procedure](https://www.spid.gov.it/cos-e-spid/diventa-fornitore-di-servizi/procedura-tecnica/)
(checked on 16 October 2026; it is the authority, this is a summary):

1. Publish the SP metadata at an HTTPS URL on your own domain, signed with the
   key that signs the requests.
2. Set `FILAMENT_SPID_DEPLOYMENT=acceptance` and configure **only** the
   official Validator as an extra IdP (`validator_idp`, from
   [validator.spid.gov.it/metadata.xml](https://validator.spid.gov.it/metadata.xml):
   entityID `https://validator.spid.gov.it`, its SSO/SLO endpoints and
   certificate). Remove local and demo IdPs and their certificates.
   `filament-spid:doctor` reports this as an acceptance window.
3. On the [Validator](https://validator.spid.gov.it), with the access AgID's
   procedure describes, register the SP metadata, configure the Validator as
   the IdP, log in from the panel, and run the Metadata, Request and Response
   checks. The [demo environment](https://demo.spid.gov.it) (metadata at
   `https://demo.spid.gov.it/metadata.xml`) helps rehearse the login and the
   user-facing errors; it does not replace the Validator.
4. Collect the results the portal shows, fix, repeat.
5. Ask AgID for the acceptance as the procedure describes (it names
   spid.tech@agid.gov.it and the data to send). AgID verifies with the SPID
   SAML Check platform; requests listing other test IdPs in the button are
   not processed.
6. After the acceptance test, set `validator_idp` back to `false` and
   `FILAMENT_SPID_DEPLOYMENT=production`, and check the doctor reports no
   test IdP: removing the trust, not only hiding the button.

This package automates none of the portal steps and sends nothing to AgID.
