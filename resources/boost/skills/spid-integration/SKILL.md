---
name: spid-integration
description: Install, configure, diagnose and troubleshoot SPID (SAML) login on a Filament panel with offline-agency/filament-spid and italia/spid-laravel.
---

# SPID integration with offline-agency/filament-spid

## When to use this skill

- Adding SPID login to a Filament panel, or moving an existing panel to it.
- Diagnosing a SPID login that fails: 419 on the ACS, the default login page showing, signature or certificate errors, users refused.
- Preparing the SP metadata for the AgID Validator.

This package covers SPID over SAML 2.0 only. CIE, CIE OIDC and SPID OIDC are out of scope.

## 1. Check what is installed

```bash
composer show offline-agency/filament-spid italia/spid-laravel onelogin/php-saml
php artisan list filament-spid
```

`filament-spid:install`, `filament-spid:doctor` and `filament-spid:metadata:validate` exist from 1.0.0. On an older version, follow that version's README instead.

## 2. Composer prerequisites (before `composer require`)

The SPID patch for `onelogin/php-saml` is applied by `cweagans/composer-patches` only when the root package opts in:

```bash
composer config allow-plugins.cweagans/composer-patches true
composer config extra.enable-patching true --json
```

Then follow the package README for the `italia/spid-laravel` constraint it currently requires. If Composer refuses `onelogin/php-saml` because of an advisory, the constraint is wrong: fix the constraint, never add the advisory to `audit.ignore`.

## 3. Install and configure

```bash
cp vendor/offline-agency/filament-spid/examples/setup.public.json setup.json   # or setup.private.json
# fill in the SP's real data and the paths of its signing key pair
php artisan filament-spid:install --panel=admin --input=setup.json --dry-run
php artisan filament-spid:install --panel=admin --input=setup.json
php artisan migrate
```

The installer lists any step it could not do safely ("Manual steps left") and then exits with 1: do them, then run it again (it is idempotent).

The SP data (organisation, IPA code or VAT number, contacts) belongs to the organisation that runs the service, not to the developer. Do not invent it.

## 4. Diagnose

```bash
php artisan filament-spid:doctor            # human-readable
php artisan filament-spid:doctor --json     # schema_version, overall_status, checks[id,status,message,remediation]
```

`skip` means "not observable from the CLI" (CSRF, CSP), never "passed". Exit 1 means a failure (or a warning with `--strict`), exit 2 an internal error.

## 5. Common problems

| Symptom | Check | Fix |
|---|---|---|
| 419 on `POST /spid/acs` | `bootstrap/app.php` | `$middleware->validateCsrfTokens(except: ['spid/acs']);` (use the real routes prefix). Do not exempt `spid/*`. |
| The panel shows the email/password form | the panel provider | `->login()` must come before `->plugin(SpidPlugin::make())`. |
| "access denied" after a successful SPID login | `canAccessPanel()` | Write the rule that admits this user; do not return `true` for everyone. |
| No account found | `fiscal_code` column, `auto_create_users` | Create the account with the citizen's fiscal code, or enable provisioning knowingly. |
| IdPs reject the request | `php artisan filament-spid:doctor` (`spid_patch`) | Enable patching (section 2) and reinstall `onelogin/php-saml`. |
| Metadata signature errors | `filament-spid:doctor` (`sp_certificate`) | The private key must belong to the published certificate; never use the example pair `italia/spid-laravel` ships. |

## 6. Validate the metadata

```bash
php artisan filament-spid:metadata:validate --url=https://sp.example.test/spid/metadata --profile=spid-sp-public
```

It needs `spid_sp_test` 1.2.18 (`uv tool install spid-sp-test==1.2.18`) and `xmlsec1`. A pass is `LOCAL_METADATA_CHECK_PASSED`. That is not the hosted AgID Validator and not AgID acceptance: the report says so.

## Never

- Disable SAML signature or response validation, global CSRF protection, or `composer audit` advisories.
- Make `canAccessPanel()` return `true` for every user.
- Link SPID identities to accounts by email.
- Keep test or validator IdPs enabled in production, except the official AgID Validator during the acceptance window.
- Print or commit private keys, SAML assertions, or citizens' fiscal codes.
