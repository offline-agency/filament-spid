# Diagnostics

> filament-spid 1.0.0. Both commands are read-only and never contact a
> network service on their own.

## `php artisan filament-spid:doctor`

```bash
php artisan filament-spid:doctor            # readable report
php artisan filament-spid:doctor --json     # JSON only on stdout
php artisan filament-spid:doctor --strict   # warnings fail too
```

It publishes, migrates, caches, generates, writes and logs in nothing, and
makes no network request. It reads the configuration, the installed packages,
the published files and the database schema (through the user model's
connection).

| Check | Looks at |
|---|---|
| `dependencies` | installed versions; `onelogin/php-saml` ≥ 4.3.1 and `robrichards/xmlseclibs` ≥ 3.1.5 (CVE-2025-66475, CVE-2026-32313) |
| `spid_patch` | builds metadata with php-saml and looks for what the SPID patch changes (`AttributeConsumingService index="0"`, `isDefault="true"`): a behaviour, not `PATCHES.txt` |
| `panel` | the panel and its guard, as the login resolves them; whether its login page is the SPID one |
| `user_model` | the model, `FilamentUser`, provisioning on or off, fillable mapped columns |
| `schema` | `fiscal_code` (unique) and `spid_data` on the model's table |
| `spid_config` | entityID and base URL set and HTTPS, the requested level against the minimum, the ACS URL |
| `csrf` | **skip**: the CLI cannot observe which middleware a request really goes through |
| `assets` | the published SPID button, logo and plugin scripts |
| `https_session` | `app.url` HTTPS and `session.secure` |
| `csp` | **skip**: a Content-Security-Policy is set outside the application's configuration |
| `sp_certificate` | the key pair readable and matching, expiry (warning within 30 days), not the example pair `italia/spid-laravel` ships (compared by content, never printed) |
| `test_idps` | test and validator IdPs against `filament-spid.deployment`: none in production; during `acceptance` only the official AgID Validator, reported as a warning (an acceptance window, never a production state) |

`skip` means "not observable here", never "passed".

```json
{
    "schema_version": 1,
    "overall_status": "pass | warn | fail | error",
    "checks": [
        { "id": "sp_certificate", "status": "pass | warn | fail | skip", "message": "...", "remediation": "... or null" }
    ]
}
```

Exit codes: `0` no failure; `1` a failure, or a warning with `--strict`; `2` an
internal error (a check crashed) or an invalid invocation. Messages carry
config keys, versions and dates only: no key material, no fiscal code, no
SPID payload, at any verbosity.

## `php artisan filament-spid:metadata:validate`

A thin adapter over [`spid_sp_test`](https://github.com/italia/spid-sp-test)
(the CLI AgID's Validator suite is built on). The SPID rules, XSDs and
signature checks are the tool's; the adapter obtains the exact metadata,
runs the pinned tool and reads both its exit code and its report.

```bash
php artisan filament-spid:metadata:validate --url=https://sp.example.test/spid/metadata --profile=spid-sp-public \
    --report=storage/app/private/spid-validation/metadata.json
php artisan filament-spid:metadata:validate --file=metadata.xml --profile=spid-sp-private --json
```

| Option | |
|---|---|
| `--url` / `--file` | one of them. URLs must be `https://`; TLS is verified (`--ca-file` for a lab CA); redirects are not followed (they usually mean a login page); 1 MiB and `--timeout` limits. The bytes are kept as received: the document is signed. |
| `--profile` | `spid-sp-public` or `spid-sp-private` (other profiles are not supported here). Default from `filament-spid.sp_profile`; never guessed. |
| `--report` | write the JSON report there (directory created `0700`). Reports about real organisations stay private. |
| `--json`, `--strict` | as for the doctor |
| `--tool` | the executable (default `filament-spid.validation.spid_sp_test`) |

It needs `spid_sp_test` **1.2.18** and `xmlsec1`; another version is refused.
Install it apart from the application (it is not needed to log in):

```bash
uv tool install spid-sp-test==1.2.18        # or: pipx install spid-sp-test==1.2.18
# and xmlsec1: apt install xmlsec1 / brew install libxmlsec1
```

or use the pinned container of the [lab](testing/agid.md):
`--tool=tools/spid/bin/spid_sp_test` from a checkout of this repository.

A missing tool, another version, a crash, a timeout, a missing `xmlsec1`, an
empty or unreadable report, zero checks run, an HTML page or malformed XML
instead of metadata: all errors (exit 2), never a pass. A failing check is a
failure (exit 1) even if the tool exits with 0.

The report records the document's SHA-256, the plugin and tool versions, the
profile, the time, a summary and every check. It is about **that** document:
a configuration changed later needs a new run. Its `evidence` is
`LOCAL_METADATA_CHECK_PASSED` (or `_FAILED`, `_ERROR`) plus, always,
`HOSTED_VALIDATOR_NOT_RUN` and `FORMAL_AGID_ACCEPTANCE_NOT_OBTAINED`: this
command never contacts AgID's hosted Validator, and passing it is not AgID
acceptance. Nothing is uploaded anywhere.
