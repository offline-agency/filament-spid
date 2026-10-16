# Installation and configuration

> Applies to filament-spid **1.0.0** (not on Packagist yet, see the [README](../README.md)).
> Italian: [installazione](it/installazione.md).

1. [Before you start](#before-you-start)
2. [Composer](#composer)
3. [The installer](#the-installer)
4. [Manual installation](#manual-installation) (the same steps by hand)
5. [Public and private SPs](#public-and-private-sps)
6. [Certificates](#certificates)
7. [URLs: entityID, base URL, metadata URL](#urls-entityid-base-url-metadata-url)
8. [CSRF and the ACS](#csrf-and-the-acs)
9. [Behind a proxy or load balancer](#behind-a-proxy-or-load-balancer)
10. [After installing](#after-installing)

## Before you start

- A Laravel 12 or 13 application on PHP 8.3–8.5 with a Filament 3, 4 or 5
  panel. No panel yet: `php artisan filament:install --panels`.
- HTTPS. SPID requires it, and `italia/spid-laravel` keeps the state of a
  login in `Secure; SameSite=None` cookies, which browsers do not send over
  plain HTTP.
- The service provider's (SP) real data: the organisation running the
  service (not the developer, not Offline Agency), its IPA code (public
  administrations) or VAT number (private organisations), a contact email.
- The SP signing key pair (see [Certificates](#certificates)).
- A decision on who may enter each panel (`canAccessPanel()`), and on whether
  SPID may create accounts (`auto_create_users`, off by default).

## Composer

Run these in the application root **before** `composer require
offline-agency/filament-spid` (they are steps 1 and 2 of the
[quick start](../README.md#quick-start), which CI runs on a fresh application):

```bash
composer config repositories.spid-laravel vcs https://github.com/offline-agency/spid-laravel
composer config allow-plugins.cweagans/composer-patches true
composer config extra.enable-patching true --json
composer require "italia/spid-laravel:dev-feat/laravel-13#b8dd1a484d8e6551a1a84e90220c2e9dc0d0a0fe as 2.1.0-beta" --no-update
composer require offline-agency/filament-spid:^1.0
```

What each line is for, and when it can go:

| Line | Why | Remove when |
|---|---|---|
| `repositories.spid-laravel` | Every tagged `italia/spid-laravel` (latest v2.1.0-beta, 2025-12-18) pins `onelogin/php-saml` 4.1.0 ([CVE-2025-66475](https://github.com/advisories/GHSA-5j8p-438x-rgg5)) and allows Laravel up to 12. The fix is merged upstream in [#132](https://github.com/italia/spid-laravel/pull/132) but not tagged. | upstream tags a release with php-saml ^4.3.1 ([#16](https://github.com/offline-agency/filament-spid/issues/16)) |
| `allow-plugins` + `extra.enable-patching` | `italia/spid-laravel` adapts php-saml to the SPID rules with a patch applied by `cweagans/composer-patches`, which only applies patches declared by dependencies when the root package opts in. Without the patch IdPs reject the requests. | never, as long as the library ships its SPID patch |
| `dev-feat/laravel-13#b8dd1a48… as 2.1.0-beta` | The fork, pinned to commit `b8dd1a484d8e6551a1a84e90220c2e9dc0d0a0fe`, whose SPID patch URL is itself pinned to commit `1cf5c84b78`: a lockfile cannot freeze a patch downloaded from a branch URL, a commit URL can. Only this package gets a dev constraint; `minimum-stability` stays as it is. | with the repository line |

Composer must not be told to ignore the `onelogin/php-saml` or
`robrichards/xmlseclibs` advisories: the plugin also declares
`conflict: onelogin/php-saml <4.3.1`, so an unpatched stack cannot be resolved
on any Composer version. Check the patch reached php-saml with
`php artisan filament-spid:doctor` (it inspects the metadata php-saml builds,
not just `vendor/onelogin/php-saml/PATCHES.txt`).

## The installer

```bash
cp vendor/offline-agency/filament-spid/examples/setup.public.json setup.json   # or setup.private.json
# edit setup.json: your SP's data and the paths of its key pair
php artisan filament-spid:install --panel=admin --input=setup.json --dry-run
php artisan filament-spid:install --panel=admin --input=setup.json
php artisan migrate
```

Without `--input`, it asks the same questions, offering the values already in
`config/spid-auth.php` (the library's example values are never offered).

| Option | Effect |
|---|---|
| `--panel=<id>` | The panel SPID users sign in to. Required when several panels are registered; it is never guessed. |
| `--input=<file>` | Answers as JSON (schema below). |
| `--dry-run` | Print the plan; write nothing (no files, cache, database, keys). |
| `--migrate` | Also run `php artisan migrate --force`. Without it, migrations are only published. |
| `--no-interaction` | Never prompt; requires `--input`. Missing or invalid data stops the command before any write. |

What it does, in order, after validating **all** the input:

1. Publishes the missing files of `filament-spid-config` (`config/filament-spid.php`,
   `config/spid-idps.php`), the migration (only if none is published yet),
   `spid-assets` and `filament-spid-images`. Existing files are never
   overwritten, customised or not. Views are not published (`php artisan
   vendor:publish --tag=filament-spid-views` if you want them).
2. Writes `config/spid-auth.php`, the protocol configuration
   `italia/spid-laravel` reads: entityID, base URL, organisation, contacts,
   requested attributes, level, key pair **paths**. It rewrites the file only
   while it is the library's untouched copy or exactly what the installer
   wrote (a SHA-256 marker); a file edited by hand is left alone and the
   values to set are printed as a manual step.
3. Sets `FILAMENT_SPID_PANEL`, `SPID_AUTO_CREATE_USERS`,
   `FILAMENT_SPID_SP_PROFILE` and `FILAMENT_SPID_DEPLOYMENT` in `.env`
   (updated in place, added once). Without a `.env`, prints them.
4. Runs `php artisan filament:assets` (the button script and jQuery).
5. Registers `->plugin(SpidPlugin::make())` right after `->login()` in the
   panel provider, if it finds one it can edit safely (one `->id('<panel>')`,
   at most one `->login()`; edited through PHP's tokenizer, never with regular
   expressions). Otherwise it prints the line to add.
6. Exempts **only** the ACS from CSRF in `bootstrap/app.php`
   ([below](#csrf-and-the-acs)), unless an exemption list already exists, in
   which case it prints the entry to add.
7. Migrates, with `--migrate` only.
8. Prints the login, metadata and ACS URLs, the profile and deployment, the
   next commands, and every manual step left. With manual steps it exits with
   1: the installation is not complete until they are done.

If a step fails after others were applied, it says which were applied and
stops; fix the cause and run the same command again. Completed steps are
detected and skipped; migrations and other external effects are not rolled
back. A second run with the same input changes nothing: no new `.env` lines,
no second migration, no second plugin registration, no key rotation.

### Input schema (`schema_version` 1)

```json
{
    "schema_version": 1,
    "panel": "admin",
    "deployment": "local",
    "sp": {
        "profile": "public",
        "entity_id": "https://sp.example.test",
        "base_url": "https://sp.example.test",
        "service_name": "Example online service",
        "organization": { "name": "…", "display_name": "…", "url": "https://…" },
        "contact": { "email": "…", "phone": "optional", "ipa_code": "public SPs", "vat_number": "private SPs", "fiscal_code": "optional" },
        "attributes": ["fiscalNumber", "name", "familyName", "email"],
        "level": "https://www.spid.gov.it/SpidL2",
        "certificate_file": "/absolute/path/sp.crt",
        "private_key_file": "/absolute/path/sp.key"
    },
    "provisioning": { "auto_create_users": false }
}
```

| Field | Rules |
|---|---|
| `deployment` | `local` (lab, test IdPs allowed), `acceptance` (the AgID acceptance test: only the official Validator, see [testing](testing/agid.md)), `production` (no test IdP). |
| `sp.profile` | `public` (requires `contact.ipa_code`) or `private` (requires `contact.vat_number`). Aggregators and operators are refused: this installer does not cover them. |
| URLs | `https://` only. |
| `sp.attributes` | SPID attribute names, must include `fiscalNumber`. Request only what the service needs. |
| `sp.level` | Default SpidL2; never below `filament-spid.minimum_level`. |
| key pair | Paths to PEM files, never the key itself. Readable, outside `public/`, the key must belong to the certificate. A key readable by other users is reported as a manual step (`chmod 600`). |

`examples/setup.public.json` and `examples/setup.private.json` hold
obviously synthetic values: replace every one of them.

## Manual installation

The installer only automates these steps; use them when you prefer to, or
when it reports a manual step.

```bash
php artisan vendor:publish --tag=filament-spid-config      # config/filament-spid.php, spid-auth.php, spid-idps.php
php artisan vendor:publish --tag=filament-spid-migrations  # fiscal_code (unique) and spid_data on users
php artisan migrate
php artisan vendor:publish --tag=spid-assets               # the AgID button CSS, JS and IdP logos
php artisan vendor:publish --tag=filament-spid-images      # the SPID AgID logo
php artisan filament:assets                                # the button script and jQuery (re-run after upgrades)
```

Then:

1. Fill in `config/spid-auth.php` (entityID, base URL, organisation, contact
   persons, requested attributes, `sp_certificate_file`/`sp_private_key_file`,
   `sp_spid_level` at least `https://www.spid.gov.it/SpidL2`). Do not keep the
   example certificate and key the library ships: their private key is public.
2. Register the plugin **after** `->login()`:

   ```php
   use OfflineAgency\FilamentSpid\SpidPlugin;

   return $panel
       ->default()
       ->id('admin')
       ->path('admin')
       ->login()
       ->plugin(SpidPlugin::make());
   ```

   A `->login()` chained after the plugin puts the default login page back;
   the log then says `panel [admin] does not use the SPID login page`.
3. Exempt the ACS from CSRF ([below](#csrf-and-the-acs)).
4. Set `FILAMENT_SPID_PANEL` when the application has more than one panel.
5. Implement `canAccessPanel()` ([README](../README.md#quick-start)).

A users table that is not Laravel's stock one: the migration adds
`fiscal_code` (unique, nullable) and `spid_data` (JSON) only if they are
missing, and never changes existing columns. If your table uses other names,
keep the migration unpublished, add the columns yourself, and map them with
`field_mapping` ([configuration](configuration.md#field-mapping)). Matching is
always by fiscal code.

## Public and private SPs

| | Public SP (`spid-sp-public`) | Private SP (`spid-sp-private`) |
|---|---|---|
| Who | public administrations listed in IPA | companies and other private organisations |
| Required in `sp_contact_persons.other` | `Public: true`, `IPACode`, `EmailAddress` | `Private: true`, `VATNumber`, `EmailAddress` (`FiscalCode` when it applies) |
| Billing contact | not required | required by the SPID rules (`billing` with `CessionarioCommittente`): the installer leaves it as a manual step and never invents invoicing data |
| Signing certificate | issued per the SPID rules for public SPs | per the rules for private SPs |

`italia/spid-laravel` refuses a configuration that mixes `Public` and
`Private`, or lacks the code each requires.

## Certificates

Two different certificates:

- **The HTTPS certificate** of your domain, from your usual CA (Let's Encrypt,
  your organisation's CA). Browsers, IdPs and AgID connect to it.
- **The SP signing certificate**, used by `italia/spid-laravel` to sign the
  metadata and the AuthnRequests, and published in the metadata. It follows
  the SPID technical rules (AgID *Avviso SPID n. 29 v3*), which differ for
  public and private SPs; AgID's own guidance decides what is acceptable for
  production. A locally generated self-signed certificate is fine for the
  lab, not necessarily for production.

[italia/spid-compliant-certificates-python](https://github.com/italia/spid-compliant-certificates-python)
(v0.5.5, a Developers Italia tool, not an AgID certification) generates and
checks certificates against Avviso 29 v3:

```bash
pip install spid-compliant-certificates==0.5.5
spid-compliant-certificates generator --key-size 3072 --days 730 \
    --common-name "Comune di Esempio" --entity-id https://sp.example.test \
    --locality-name Roma --org-id "PA:IT-c_x000" --org-name "Comune di Esempio" --sector public
spid-compliant-certificates validator --sector public
```

Keep the private key outside `public/` and out of Git (for example
`storage/app/private/spid/`, with `chmod 600`), and point
`sp_private_key_file` at it. The installer and the doctor never print, copy or
generate keys. `php artisan filament-spid:doctor` checks that the key belongs
to the certificate, the expiry (warning within 30 days) and that it is not the
example pair `italia/spid-laravel` ships. Renewing the certificate changes the
metadata: AgID must receive the new one.

## URLs: entityID, base URL, metadata URL

| | Example | Where |
|---|---|---|
| entityID | `https://sp.example.test` | `spid-auth.sp_entity_id`: identifies the SP; an HTTPS URL you control. Not necessarily a page. |
| Base URL | `https://sp.example.test` | `spid-auth.sp_base_url`: where the application is served; the ACS and SLO URLs in the metadata are built from it. |
| Routes prefix | `spid` | `spid-auth.routes_prefix`: the library's routes live under it. |
| Metadata URL | `https://sp.example.test/spid/metadata` | `<base URL>/<prefix>/metadata`, what AgID and the IdPs download. |
| ACS | `https://sp.example.test/spid/acs` | `<base URL>/<prefix>/acs`, where IdPs post the Response. |

Change the prefix only before the metadata is registered with AgID: the ACS
and SLO URLs are part of it.

## CSRF and the ACS

The IdP posts the Response to the ACS from another site, so the ACS cannot
carry a CSRF token. Exempt **that path only** in `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware): void {
    // Laravel 13
    $middleware->preventRequestForgery(except: ['spid/acs']);
    // Laravel 12: $middleware->validateCsrfTokens(except: ['spid/acs']);
})
```

Use the real prefix (`auth/spid/acs` for `routes_prefix = 'auth/spid'`).
Never exempt `spid/*`: the login form posts to `spid/login` with a token, and
must keep it. The ACS is not left unprotected: the library accepts only a
signed Response correlated to a request it issued, with the request state in
its own cookies. The installer and CI check both sides: a POST to the ACS
without a token is not answered 419 and signs nobody in, a POST elsewhere
without a token is still answered 419.

## Behind a proxy or load balancer

If TLS ends at a proxy, the application sees plain HTTP. Two things must
agree on the public URL:

- Laravel: trust the proxy (`$middleware->trustProxies(at: [...])` with the
  proxy's addresses), so URLs and cookies are HTTPS.
- php-saml: it compares the Response `Destination` with the URL it was
  received at, built from `$_SERVER`. Call
  `\OneLogin\Saml2\Utils::setProxyVars(true)` (for example in a service
  provider's `boot()`) so it reads `X-Forwarded-Proto`, `-Host` and `-Port`.
  Only do this when nothing but the proxy can reach the application: these
  headers are otherwise forgeable. The proxy must send `X-Forwarded-Port`.

The [local lab](testing/agid.md) runs exactly this setup (Caddy in front of
`php artisan serve`).

## After installing

```bash
php artisan filament-spid:doctor --strict
php artisan filament-spid:metadata:validate --url=https://sp.example.test/spid/metadata --profile=spid-sp-public
```

See [diagnostics](diagnostics.md), then [testing with AgID's tools](testing/agid.md)
and the [production checklist](production-checklist.md).
