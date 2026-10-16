# Evidence

What was actually run for 1.0.0, on what, and what it does and does not show.
Updated on 16 October 2026. The 11 October run below remains historical. Synthetic data only: no real organisation,
citizen, key or assertion.

## CI repair on 16 October 2026

PR #15 was refreshed at `ed960e4d0a7472825b74127f353bf9866bf41f1c`.
The latest failing workflow remained [38098866015](https://github.com/offline-agency/filament-spid/actions/runs/38098866015),
SPID lab job `114350413142`; the other workflows were successful on that
head. The original local lab reproduced 104/104 metadata, 151/151
AuthnRequest and 240/246 Response assertions. Each positive scenario
(1, 31, 95, 96, 109, 110) was also run separately with the original tool:
exit 1, expected HTTP 200 but actual HTTP 500. The synthetic PHP reproduction
exited **0 = incompatibility reproduced**, not successful authentication:
php-saml 4.3.2 / xmlseclibs 3.1.5 validated a populated KeyInfo but threw
`Unable to extract public key` with an empty certificate.

The repaired local lab **passed**: 104/104 metadata, 151/151 AuthnRequest,
246/246 Response assertions across all 111 original IDs. All six positives
actually authenticated on the panel guard; the refused-citizen sentinel
produced `the user may not access the panel`, a login redirect and no guard
or SPID session. All 18 compatibility combinations and three real archive
install legs passed locally, alongside 100% line coverage, max-level PHPStan,
Pint, JavaScript, audit and non-publishing script checks.

The new dated [repair record](evidence/ci-repair-2026-10-16.json) records the
actual commands, environments and outcomes. Local runs use macOS arm64,
Herd PHP and the digest-pinned multi-architecture Docker images. They are
not a completed Ubuntu GitHub Actions run for the repair commit.

The build-only [patch and removal condition](../../tools/spid/patches/README.md)
fix certificate selection in `spid_sp_test` 1.2.18, preserving scenario keys,
explicit certificates, templates, wrappers and expectations. Both original
signatures had empty KeyInfo certificates; both corrected signatures contain
the matching certificate. Regression tests exercise the actual scenario
runner and xmlsec, verify both signatures and signed digests, and prove
alternate-key fixtures fail under configured lab trust. Upstream's latest
release is still [v1.2.18](https://github.com/italia/spid-sp-test/releases/tag/v1.2.18);
main `cf172c5d17cc7ef7d2364d5f1417d7e9dda1ebc8` changes supported Python
versions, not signing. `--version` remains `1.2.18`, with separate patch
identity/hash and original/patched source hashes in `tool-provenance.json`.
Upstream's EUPL-1.2 license and attribution accompany the patch.

The bundled certificate expired at **2025-03-08 10:02:08 UTC** and matches
the bundled signing key. php-saml/xmlseclibs use the configured certificate's
public key in this verification path without enforcing its PKIX lifetime;
corrected positive fixtures authenticate with it. No clock change,
certificate replacement or production validity bypass was needed.

Fixing signing exposed two dependency validation omissions, each reproduced
by a focused failing test before the production fix: scenario 64 authenticated
without `SubjectConfirmationData@NotOnOrAfter`, and scenario 28 (missing
Response Issuer) raised a null dereference instead of a validation exception.
The internal `Services\SpidAuth` integration adds those two required-field
checks while retaining the parent SPID validation and singleton/public API.
The full lab independently exercises these paths through the real ACS.

A fresh baseline log associates **37** Response scenarios with the empty-cert
error: six positives and **31** other scenarios (including upstream's flexible
scenario 103). The gate sentinel adds one further occurrence, making 38 in
the application log. This differs from the historical “six plus 32 negatives”
classification below; that earlier classification was not independently
confirmed. The dated record identifies every affected ID and its new refusal
reason or upstream-permitted acceptance. Scenario 103 permits either outcome;
its original expectation is retained. Wrapping attacks are refused at the
protocol/status checks; this does not claim every attack reaches signature
verification.

Python's separate consistency warning came from wheel 0.46.3 requiring
packaging >=24 while spid-compliant-certificates 0.5.5 requires packaging
==21.2. wheel 0.45.1 has no runtime packaging requirement and keeps the
existing constrained runtime consistent. `python -m pip check` is mandatory
in the executing environment at build and lab run. CI's Composer helper now
pins the same `b8dd1a484d8e6551a1a84e90220c2e9dc0d0a0fe` fork commit as
the quickstart; the baseline floating branch happened to resolve to that
commit too. The CI-only package repository copies that reviewed commit's
runtime/autoload/patch metadata and pins both source and dist; the root keeps
its compatible range. This avoids Composer strict-validation warnings for
`dev-branch#commit` or exact root requirements. Published `composer.json` and audit protection stay intact.

The report gate requires all 111 original scenario IDs and 246 Response
assertions, 151 AuthnRequest assertions, 104 metadata assertions, successful
tool exits, explicit positive results, isolated sessions and authenticated
panel access. A refused citizen must produce the precise panel-access reason,
a login redirect and no authenticated guard or SPID session. Missing,
malformed, truncated and incomplete reports, unexpected results, crashed
runs, unrelated exceptions and missing/mismatched provenance fail. An
interrupted lab starts and leaves `summary.json` as `LAB_INCOMPLETE` / fail;
reports, per-scenario sanitized diagnostics and proxy logs remain artifacts
on failure. Local Docker-to-host dial timeouts were observed as HTTP 502
and failed the gate; Caddy's lab dial timeout is now 30s, without retrying
SAML POSTs. Guard/session diagnostics ride on the existing login/panel
responses, avoiding extra HTTP requests. Reused work directories discard
previous scenario reports before starting. No keys, cookies or application directories are uploaded.

`HOSTED_VALIDATOR_NOT_RUN` and `FORMAL_AGID_ACCEPTANCE_NOT_OBTAINED`
remain unchanged. No merge, tag, release, external comment or repository
protection change was performed.

## Historical status (11 October 2026)

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
