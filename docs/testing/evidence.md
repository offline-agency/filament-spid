# Evidence

What was actually run for 1.0.0, on what, and what it does and does not show.
Updated on 11 October 2026. Synthetic data only: no real organisation,
citizen, key or assertion.

## Status

| Evidence | Status | Date, source |
|---|---|---|
| Unit and integration tests (Pest, 18 PHP × Laravel × Filament legs), 100% line coverage, PHPStan max, Pint, Node tests of the button script | pass | CI on the PR head |
| Install of the distributable archive by the published quickstart (Laravel 12 + Filament 3, Laravel 13 + Filament 4, Laravel 13 + Filament 5) | pass | `.github/scripts/install-check.sh`, CI job `install` and local runs on 2026-10-11 |
| `LOCAL_METADATA_CHECK_PASSED` | 104 of 104 `spid_sp_test` 1.2.18 checks, `spid-sp-public`, signature included | metadata served by the installed archive, [report](evidence/metadata-report-2026-10-11.json) |
| AuthnRequest from the real Filament form | 151 of 151 checks (metadata and request) | [lab summary](evidence/lab-summary-2026-10-11.json) |
| SAML Responses | **240 of 246 checks; 6 positive tests fail** (1, 31, 95, 96, 109, 110) | [lab summary](evidence/lab-summary-2026-10-11.json), finding below |
| `LOCAL_SAML_TESTS_PASSED` | **not obtained** (`LOCAL_SAML_TESTS_FAILED`) | the lab gate is red |
| Sentinels | metadata altered after signing: fail; public SP without IPA code: fail; HTML page and empty file: error; valid copy: pass | [sentinels](evidence/sentinels-2026-10-11.txt) |
| Gate sentinel (positive Response for a refused citizen) | not meaningful while test 1 fails for an admitted citizen too | lab summary |
| Browser check of the login page and the SPID button | pass, after fixing what it found | [Browser](#browser) |
| Laravel Boost discovery | laravel/boost 2.10.3 (`ThirdPartyPackage::guidelineDirectories()` / `skillDirectories()`) finds the package's `resources/boost/guidelines` and `skills` in an application where the archive is a direct dependency; the interactive `boost:install` itself was not run | 2026-10-11 |
| Self-hosted Validator (`spid-saml-check`) | not run (interactive, no automation API) | |
| `HOSTED_VALIDATOR_NOT_RUN` | the hosted AgID Validator was not used | no public HTTPS deployment or portal access in this work |
| `FORMAL_AGID_ACCEPTANCE_NOT_OBTAINED` | no acceptance requested | out of scope; AgID's procedure |

## Finding: positive Responses of `spid_sp_test` 1.2.18 are rejected

- **What fails.** All 6 positive Response tests (expected HTTP 200): 1, 31,
  95, 96, 109, 110. The application answers 500; the log says
  `SAML response validation error: openssl_x509_read(): X.509 Certificate
  cannot be retrieved` (under Laravel's error handler), or `Unable to extract
  public key` without it.
- **Why.** `spid_sp_test` signs its default Responses with `xmlsec1 --sign
  --privkey-pem <key>` and no certificate, which leaves an empty
  `<ds:X509Certificate/>` in `ds:KeyInfo`. `onelogin/php-saml` 4.3.2 passes
  `KeyInfo` to `robrichards/xmlseclibs` 3.1.5 (`XMLSecEnc::staticLocateKeyInfo`),
  which tries to load that empty certificate and throws, before php-saml uses
  the IdP certificate the SP configured.
- **Reproduction.** `php tools/spid/repro/empty-keyinfo-certificate.php`
  signs a document with a throwaway key, validates it with the right
  certificate: `valid` with the certificate in `KeyInfo`, `Exception: Unable
  to extract public key` with an empty one (exit 0 = reproduced).
- **Where it belongs.** Upstream, not in this plugin: the ACS and the SAML
  validation are `italia/spid-laravel`'s and php-saml's. Either side can
  resolve it (php-saml/xmlseclibs ignoring an empty `X509Certificate`, or
  `spid_sp_test` signing with its certificate). Whether AgID's hosted Validator
  produces the same Responses was not verified.
- **Consequence for the evidence.** 38 of the forged Responses stop at this
  error (the 6 positive ones and 32 negative ones). The 32 negative ones
  "pass" (they are refused) but for the wrong reason, so they prove nothing
  about the check they target; the remaining negative ones are refused for
  the intended reasons (schema, status, subject confirmation, timing, issuer,
  signature, anomaly status codes). The plugin's own handling of a valid
  login is covered by Pest with the real framework, but not end to end
  through SAML.
- **Release.** `LOCAL_SAML_TESTS_PASSED` blocks on this; nothing was silenced
  or skipped to turn the gate green.

Also observed: the IdP certificate bundled in `spid_sp_test` 1.2.18
(`spid_sp_test/idp/public.cert`, shown by `--idp-metadata`) expired on
8 March 2025.

## Sources and versions

| | Version, digest, commit | Observed |
|---|---|---|
| italia/spid-laravel | latest tag v2.1.0-beta (2025-12-18, pins php-saml 4.1.0); master `591dcdda20` (2026-10-09), patch URL on `master` (mutable) | 2026-10-11 |
| offline-agency/spid-laravel `feat/laravel-13` | `b8dd1a484d8e6551a1a84e90220c2e9dc0d0a0fe`, patch pinned to `1cf5c84b78` | 2026-10-11 |
| onelogin/php-saml | 4.3.2 installed; [GHSA-5j8p-438x-rgg5](https://github.com/advisories/GHSA-5j8p-438x-rgg5) fixed in 4.3.1 | 2026-10-11 |
| robrichards/xmlseclibs | 3.1.5 installed; GHSA-4v26-v6cg-g6f9 (CVE-2026-32313) fixed in 3.1.5, GHSA-c4cc-x928-vjw9 (CVE-2025-66578) in 3.1.4 | 2026-10-11 |
| spid-sp-test | 1.2.18 (tag `32ac4e7ee8`, PyPI), in `python:3.11-slim-bookworm@sha256:0a310eee…` | 2026-10-11 |
| spid-saml-check | v.2.2.5, `italia/spid-saml-check:2.2.5@sha256:46db12d2…` (amd64) | 2026-10-11 |
| Caddy (lab TLS) | `caddy:2.10.2-alpine@sha256:4c6e91c6…` | 2026-10-11 |
| AgID procedure | [spid.gov.it, procedura tecnica](https://www.spid.gov.it/cos-e-spid/diventa-fornitore-di-servizi/procedura-tecnica/) | 2026-10-11 |

## Browser

Chrome (DevTools protocol) on 2026-10-11, against `/admin/login` of a Laravel
13 + Filament 5 application installed from the distributable archive, test
IdPs disabled, synthetic data. The Claude in Chrome extension was not
connected; the checks used Chrome DevTools.

| Check | Result |
|---|---|
| Script initialised once, no inline script in the component | yes |
| Trigger `role="button"`, `aria-haspopup`, `aria-controls`; `aria-expanded` follows the menu | yes |
| Click opens, focus moves to the first IdP; Escape closes, focus returns to the trigger; Space opens | yes |
| Choosing an IdP posts `provider` with the CSRF token from that button's own form | yes (submit intercepted: no IdP contacted) |
| Second button on the page + `livewire:navigated`: distinct ids, first button not re-shuffled, each button sets only its own input | yes |
| IdP order random, information links last | yes |
| Console errors or warnings | none |
| Mobile 390×844: no horizontal overflow, menu within the viewport | yes |
| Light and dark mode | **found**: on Filament 5 the information text and logo were left-aligned and the error box unstyled (Tailwind utilities not compiled), the AgID logo nearly invisible in dark mode, the stylesheet still targeted a removed form id. **Fixed** with the plugin's own `fi-spid-*` classes and re-checked. |

Screenshots (real, synthetic data): [light](../images/login-light.png),
[dark](../images/login-dark.png), [mobile](../images/login-mobile.png).

Not checked in a browser: Filament 3 and 4 rendering (covered by the CI
matrix and the install check, not visually), screen readers, a real
Content-Security-Policy header.
