# Filament SPID

[![Latest Version on Packagist](https://img.shields.io/packagist/v/offline-agency/filament-spid.svg?style=flat-square)](https://packagist.org/packages/offline-agency/filament-spid)
[![PHP Version](https://img.shields.io/packagist/dependency-v/offline-agency/filament-spid/php?style=flat-square)](https://packagist.org/packages/offline-agency/filament-spid)
[![Tests](https://img.shields.io/github/actions/workflow/status/offline-agency/filament-spid/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/offline-agency/filament-spid/actions/workflows/run-tests.yml?query=branch%3Amain)
[![PHPStan](https://img.shields.io/github/actions/workflow/status/offline-agency/filament-spid/phpstan.yml?branch=main&label=phpstan&style=flat-square)](https://github.com/offline-agency/filament-spid/actions/workflows/phpstan.yml?query=branch%3Amain)
[![Code Style](https://img.shields.io/github/actions/workflow/status/offline-agency/filament-spid/code-style.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/offline-agency/filament-spid/actions/workflows/code-style.yml?query=branch%3Amain)
[![Coverage](https://img.shields.io/codecov/c/github/offline-agency/filament-spid/main?style=flat-square)](https://app.codecov.io/gh/offline-agency/filament-spid)
[![Total Downloads](https://img.shields.io/packagist/dt/offline-agency/filament-spid.svg?style=flat-square)](https://packagist.org/packages/offline-agency/filament-spid)
[![License](https://img.shields.io/packagist/l/offline-agency/filament-spid.svg?style=flat-square)](LICENSE.md)

SPID (Sistema Pubblico di Identità Digitale) authentication for
[Filament](https://filamentphp.com) panels, built on
[italia/spid-laravel](https://github.com/italia/spid-laravel).

The plugin replaces the panel login page with the official AgID SPID button,
listens to the events `italia/spid-laravel` fires once a SAML response is
validated, provisions the user and logs them in on the panel guard.

![Filament SPID Banner](https://banners.beyondco.de/Filament%20Spid.png?theme=dark&packageManager=composer+require&packageName=offline-agency%2Ffilament-spid&pattern=eyes&style=style_1&description=Filament+plugin+for+SPID+authentication+in+Laravel.&md=1&showWatermark=0&fontSize=100px&images=https%3A%2F%2Flaravel.com%2Fimg%2Flogomark.min.svg)

## Requirements

Every combination below is tested in CI:

| | Supported |
|---|---|
| PHP | 8.3, 8.4, 8.5 |
| Laravel | 12.x, 13.x |
| Filament | 3.x, 4.x, 5.x |
| `italia/spid-laravel` | the patched fork, see [Installation](#installation) |

Laravel 11 is not supported: it is end of life and every 11.x release carries
unpatched advisories, so Composer refuses to install it. PHP 8.2 is not
supported either: it leaves security support on 31 December 2026. Stay on
0.1.x for those.

## Installation

### 1. Prepare `composer.json`

The SAML layer comes from `italia/spid-laravel`, which needs three things from
your application's `composer.json` that Composer does not inherit from a
dependency:

1. **The patched `italia/spid-laravel`.** Every published release pins
   `onelogin/php-saml` 4.1.0, which is affected by a critical advisory
   ([CVE-2025-66475](https://github.com/advisories/GHSA-5j8p-438x-rgg5)), so
   Composer 2.9+ refuses to install it, and no release allows Laravel 13. Until
   upstream merges [italia/spid-laravel#131](https://github.com/italia/spid-laravel/pull/131)
   and tags a release, install the fork branch: it requires php-saml ^4.3.1,
   ships the SPID patch ported to it and allows Laravel 13.
2. **Patching enabled.** `italia/spid-laravel` adapts php-saml to the SPID rules
   through `cweagans/composer-patches`, which only applies patches declared by
   dependencies when your application opts in. Without it, IdPs reject the
   requests.
3. **Beta stability.** `italia/spid-laravel` is only published as beta, and
   Composer does not accept a beta that comes in transitively.

Merge these keys into your `composer.json`:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/offline-agency/spid-laravel"
        }
    ],
    "require": {
        "italia/spid-laravel": "dev-feat/laravel-13 as 2.1.0-beta"
    },
    "minimum-stability": "beta",
    "prefer-stable": true,
    "extra": {
        "enable-patching": true
    },
    "config": {
        "allow-plugins": {
            "cweagans/composer-patches": true
        }
    }
}
```

Once upstream tags a fixed release, drop the `repositories` entry and require
that release instead.

### 2. Install

```bash
composer require offline-agency/filament-spid
```

`vendor/onelogin/php-saml/PATCHES.txt` exists when the SPID patch was applied.

### 3. Publish and migrate

```bash
# config/filament-spid.php, config/spid-auth.php and config/spid-idps.php
php artisan vendor:publish --tag="filament-spid-config"

# fiscal_code and spid_data columns on the users table
php artisan vendor:publish --tag="filament-spid-migrations"
php artisan migrate

# AgID SPID button CSS, JS and IdP logos (public/vendor/spid-auth)
php artisan vendor:publish --tag="spid-assets"

# SPID AgID logo used by the login page (public/vendor/filament-spid/images)
php artisan vendor:publish --tag="filament-spid-images"

# the plugin's stylesheet and jQuery (public/css, public/js)
php artisan filament:assets
```

Re-run `php artisan filament:assets` after every upgrade. jQuery is served from
your application (`/js/offline-agency/filament-spid/spid-jquery.js`), not a CDN,
and only when the page does not already load jQuery. Optionally publish the views
with `--tag="filament-spid-views"`.

### 4. Configure the Service Provider

Follow the [italia/spid-laravel documentation](https://github.com/italia/spid-laravel)
to generate the SP certificate and fill in `config/spid-auth.php` (entity id,
base URL, organisation, contact persons). Then set the SPID level, which the
library defaults to SpidL1 (password only):

```php
// config/spid-auth.php
'sp_spid_level' => 'https://www.spid.gov.it/SpidL2',
```

See [SPID levels](#spid-levels).

### 5. Exclude the ACS route from CSRF

The Identity Provider posts the SAML assertion straight to the ACS endpoint and
cannot carry a Laravel CSRF token, so that one route must be excluded. The path
is `<spid-auth.routes_prefix>/acs`, `spid/acs` by default.

Laravel 12 (`bootstrap/app.php`):

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->validateCsrfTokens(except: ['spid/acs']);
})
```

Laravel 13 (`bootstrap/app.php`), where `validateCsrfTokens()` is deprecated:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->preventRequestForgery(except: ['spid/acs']);
})
```

Applications that still use the Laravel 10 structure exclude it in
`app/Http/Middleware/VerifyCsrfToken.php`:

```php
protected $except = [
    'spid/acs',
];
```

Keep the exclusion to the ACS path. The logout route is reached through
redirects (the SP uses the HTTP-Redirect binding) and your own forms, which carry
a token.

### 6. Register the plugin

No panel yet? Create one with `php artisan filament:install --panels`.

```php
use OfflineAgency\FilamentSpid\SpidPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->default()
        ->id('admin')
        ->path('admin')
        ->login()
        // After ->login(): the plugin swaps the login page when it is added,
        // so a ->login() chained later would put the default page back.
        ->plugin(SpidPlugin::make());
}
```

The panel provider `filament:install` generates calls `->login()`; register the
plugin after it, or remove it. If the order is wrong, the panel keeps the
standard login form and the log says
`panel [admin] does not use the SPID login page`.

### 7. Prepare the user model

```php
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;

class User extends Authenticatable implements FilamentUser
{
    protected $fillable = ['name', 'email', 'fiscal_code', 'spid_data'];

    protected $casts = ['spid_data' => 'array'];

    public function canAccessPanel(Panel $panel): bool
    {
        // The authorisation gate for SPID logins: decide who may enter.
        return $this->is_admin;
    }
}
```

In production Filament only lets in users whose model implements
`FilamentUser` and returns true from `canAccessPanel()`. The `spid_data` cast is
optional: the payload is stored as an array when the column is cast and as JSON
otherwise, never double-encoded.

## How it works

1. The panel login page shows the AgID SPID button. Picking an IdP posts to the
   library's `spid-auth_do-login` route, which redirects to the IdP.
2. The IdP posts the assertion to the library's ACS route. `italia/spid-laravel`
   validates it (signature, level, timing), stores the SPID session and fires
   `LoginEvent`.
3. `HandleSpidLogin` checks the [SPID level](#spid-levels), finds the user by
   `fiscal_code` (creating or updating it as configured), logs them in on the
   panel guard without a remember-me cookie and regenerates the session.
4. If anything fails, the SPID session is cleared, so the citizen can retry,
   and they land on the panel login page with a translated `spid_error` message.
   The log carries the exception class only, never SPID attributes.
5. Logging out:
   - Filament's own user-menu logout ends the panel session (and, by
     invalidating it, the library's SPID session keys) but not the citizen's
     session at the IdP.
   - To end that too, send users to the plugin's logout route (`spid.logout`,
     a POST under the panel path, available with `registerRoutes(true)`). It
     logs the panel guard out first, then starts the IdP single logout; when
     the IdP redirects back, the library fires `LogoutEvent` and
     `HandleSpidLogout` invalidates the session. Wire it to the panel's user
     menu or your own logout button.

## Configuration

`config/filament-spid.php`:

| Key | Env | Default | Purpose |
|---|---|---|---|
| `enabled` | `FILAMENT_SPID_ENABLED` | `true` | Replace the panel login page with the SPID one |
| `register_listeners` | `FILAMENT_SPID_REGISTER_LISTENERS` | `true` | Listen to the library's `LoginEvent`/`LogoutEvent`; disable to take over the flow |
| `panel` | `FILAMENT_SPID_PANEL` | `null` | Panel id SPID users authenticate against; `null` uses the current or default panel. An unknown id throws `SpidPanelNotFoundException` |
| `minimum_level` | `FILAMENT_SPID_MINIMUM_LEVEL` | SpidL2 URI | Lowest `spid-auth.sp_spid_level` the plugin accepts |
| `user_model` | `SPID_USER_MODEL` | `App\Models\User` | Model to provision; falls back to `spid-auth.user_model` |
| `auto_create_users` | `SPID_AUTO_CREATE_USERS` | `false` | Create a user when no `fiscal_code` matches |
| `update_user_data` | `SPID_UPDATE_USER_DATA` | `true` | Refresh mapped columns and `spid_data` on every login |
| `field_mapping` | | name, email, fiscal_code | Column => SPID attribute name (`'fiscalNumber'`) or invokable mapper class (`FullName::class`) |
| `create_user_callback` | | `null` | `fn (SpidUserData $data): Authenticatable` replacing user creation |
| `update_user_callback` | | `null` | `fn (Authenticatable $user, SpidUserData $data): void` replacing the update |

Keep the config cacheable: `php artisan config:cache` (and `optimize`) cannot
store closures. `field_mapping` accepts attribute names and invokable classes
(`__invoke(array $spidUser)`) for that reason; closures still work there and in
the two callbacks, but only if you do not cache the config.

The SAML side (entity id, certificates, level, IdPs, routes prefix, redirects
after login and logout) lives in `config/spid-auth.php` and `config/spid-idps.php`,
owned by `italia/spid-laravel`.

### Email without SPID email

SPID only sends an email when the IdP holds one. When it does not, the default
`field_mapping.email` stores `<fiscal code, lowercased>@spid.invalid`: unique
per citizen, stable across logins, and undeliverable (`.invalid` is reserved by
RFC 2606), so it can never collide with or reach a real mailbox. If your `email`
column is nullable, map it to `null` instead:

```php
'field_mapping' => [
    // ...
    'email' => 'email',
],
```

## SPID levels

| Level | URI | Credentials |
|---|---|---|
| SpidL1 | `https://www.spid.gov.it/SpidL1` | username and password |
| SpidL2 | `https://www.spid.gov.it/SpidL2` | plus a one-time password |
| SpidL3 | `https://www.spid.gov.it/SpidL3` | plus a smart card or hardware token |

`italia/spid-laravel` requests `spid-auth.sp_spid_level` from the IdP and rejects
any assertion below it. The plugin refuses every SPID login, and logs a warning
at boot, while that level is below `filament-spid.minimum_level` (SpidL2 by
default). Raise the minimum to SpidL3 for panels that need it; lowering it to
SpidL1 is possible but not recommended for admin panels.

## Multiple panels

The ACS request runs on a library route, outside any panel, so with more than
one panel name the one SPID users belong to:

```dotenv
FILAMENT_SPID_PANEL=admin
```

Register `SpidPlugin` on that panel. A value that matches no registered panel
throws `SpidPanelNotFoundException` instead of logging the citizen in on another
guard.

## Events

From `italia/spid-laravel`: `Italia\SPIDAuth\Events\LoginEvent` and `LogoutEvent`.

From this package (`OfflineAgency\FilamentSpid\Events`):

| Event | Payload | When |
|---|---|---|
| `SpidUserCreated` | `$user`, `$spidData` | a user was provisioned |
| `SpidUserUpdated` | `$user`, `$spidData` | a user's data was refreshed |
| `SpidAuthenticationSucceeded` | `$user`, `$spidData` | the user is logged in on the panel |
| `SpidAuthenticationFailed` | `$reason` | the login was refused; the reason holds no personal data |

`$spidData` is a `SpidUserData` (fiscal number, name, family name, email, SPID
code, place and date of birth, gender).

## Customisation

```php
SpidPlugin::make()
    // Button text and a blade-icons icon instead of the SPID mark
    ->spidButtonLabel('Entra con SPID')
    ->spidButtonIcon('heroicon-o-shield-check')
    // Allowlist over the keys of config/spid-idps.php; empty shows every active IdP
    ->providers(['poste', 'infocert', 'aruba', 'namirial', 'tim'])
    // Your own login view
    ->loginView('auth.spid-login')
    // Keep the panel's own login page instead of the SPID one
    ->showSpidButton(false);
```

Valid provider keys are those of `config/spid-idps.php`: `aruba`, `eht`,
`infocamere`, `infocert`, `intesigroup`, `lepida`, `namirial`, `poste`,
`sielte`, `spiditalia`, `teamsystem`, `tim`. Inactive IdPs are never shown.

The plugin can also register convenience routes under the panel path (a login
redirect, a providers JSON endpoint, the SP metadata and a logout that goes
through the IdP). They are off by default:

```php
SpidPlugin::make()
    ->registerRoutes(true)
    ->loginRoute('custom.spid.login')
    ->logoutRoute('custom.spid.logout')
    ->metadataRoute('custom.spid.metadata')
    ->providersRoute('custom.spid.providers');
```

To take over provisioning entirely, set `register_listeners` to `false` and
listen to `LoginEvent`/`LogoutEvent` yourself, or keep the listeners and use
`create_user_callback`/`update_user_callback`.

## Upgrading from 0.1.x to 1.0

- **Platform**: PHP 8.3+ and Laravel 12 or 13. PHP 8.2 and Laravel 11 are no
  longer supported.
- **Composer**: add the fork repository, `enable-patching`, the
  `cweagans/composer-patches` plugin and beta stability as shown in
  [Installation](#1-prepare-composerjson), then require
  `offline-agency/filament-spid:^1.0`.
- **SPID level**: set `spid-auth.sp_spid_level` to SpidL2 or higher; logins are
  refused below `filament-spid.minimum_level` (SpidL2).
- **Provisioning**: `auto_create_users` now defaults to `false`. Set
  `SPID_AUTO_CREATE_USERS=true` to keep creating accounts.
- **Config**: delete `redirect_after_login`, `spid_level` and `providers` from
  your published `config/filament-spid.php` (and `SPID_REDIRECT_AFTER_LOGIN`,
  `SPID_LEVEL` from `.env`); they were never read. Use
  `spid-auth.after_login_url`, `spid-auth.sp_spid_level` and
  `SpidPlugin::providers()`.
- **Field mapping**: the published config's closures stop `config:cache`.
  Republish it, or replace them with `FullName::class`,
  `EmailOrFallback::class` and `'fiscalNumber'`
  (`OfflineAgency\FilamentSpid\Mapping`).
- **Fallback email**: users without a SPID email get
  `<fiscal code>@spid.invalid` instead of `@spid.local` on their next login,
  once the mapping above is updated.
- **Panel**: an unknown `FILAMENT_SPID_PANEL` now throws instead of falling back
  to the default guard.
- **Routes**: the plugin's helper routes are opt-in (`registerRoutes(true)`) and
  the plugin no longer has an ACS route of its own: exclude the library's
  `spid/acs` from CSRF.
- **Assets**: run `php artisan filament:assets` (jQuery is now served by your
  application) and `vendor:publish --tag="spid-assets"` if you have not.

## Testing

```bash
composer test
composer analyse
vendor/bin/pint --test
```

See [CONTRIBUTING](CONTRIBUTING.md#local-setup) for the local setup that
mirrors CI.

Before going live, validate your SP with the AgID
[spid-saml-check](https://github.com/italia/spid-saml-check) validator. Enable
`spid-auth.validator_idp` to add it to the button, check the metadata at
`/<routes_prefix>/metadata`, then run its request and response tests. In
development, `spid-auth.test_idp` adds the
[SPID test environment](https://github.com/italia/spid-testenv2).

## Troubleshooting

**The panel shows the standard email and password form.** `->login()` is chained
after `->plugin(SpidPlugin::make())` and replaced the SPID page; move the plugin
after it ([step 6](#6-register-the-plugin)).

**419 Page Expired on the ACS.** The IdP's POST is hitting CSRF protection.
Exclude `<routes_prefix>/acs` as shown in
[step 5](#5-exclude-the-acs-route-from-csrf). Also check the session cookie:
the library sets its own cookies with `SameSite=None; Secure`, so the site must
be served over HTTPS.

**Clicking an IdP goes straight back to the login page.** The browser still holds
a SPID session from an earlier attempt (`spid_sessionId`), so the library's
`doLogin()` skips the IdP. 1.0.0 clears it after a failed login; for an older
session, log out or clear the session cookie.

**"Requires a higher SPID security level".** `spid-auth.sp_spid_level` is below
`filament-spid.minimum_level`; the boot log says which. Raise the requested
level.

**`spid_data` stored as an escaped JSON string.** Releases before 1.0.0
double-encoded it for models casting the column. The package now writes an array
when the column is cast and JSON otherwise; re-save affected rows (a login with
`update_user_data` on does it).

**Composer refuses `onelogin/php-saml` 4.1.0** ("affected by security
advisories"). The fork from [Installation](#1-prepare-composerjson) is missing;
do not silence the advisory.

**IdPs reject the AuthnRequest.** Check that `vendor/onelogin/php-saml/PATCHES.txt`
exists. If not, `extra.enable-patching` or the `cweagans/composer-patches`
plugin permission is missing; fix it and reinstall `onelogin/php-saml`.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Releasing

Merging a pull request into `main` tags and releases it automatically. Each pull
request carries exactly one of `release:major`, `release:minor`,
`release:patch` or `skip-release`, which decides the next version (tags have
no `v` prefix, e.g. `1.0.0`). The release notes start with the matching
`CHANGELOG.md` section. See [CONTRIBUTING](CONTRIBUTING.md#releasing) for the
full flow.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](SECURITY.md) on how to report security vulnerabilities. Participation in this project is governed by the [Code of Conduct](CODE_OF_CONDUCT.md).

## Credits

- [Offline Agency](https://offlineagency.it)
- [All Contributors](../../contributors)
- Based on [italia/spid-laravel](https://github.com/italia/spid-laravel)

## Support

For support and questions:
- Email: support@offlineagency.it
- GitHub Issues: [Create an issue](https://github.com/offline-agency/filament-spid/issues)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## About Offline Agency

Offline Agency is a web development agency based in Italy, specializing in Laravel and Filament applications.

- Website: [https://offlineagency.it](https://offlineagency.it)
- GitHub: [@offline-agency](https://github.com/offline-agency)
- Email: support@offlineagency.it
