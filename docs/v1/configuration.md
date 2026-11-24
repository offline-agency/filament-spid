# Configuration reference

> filament-spid 1.0.0. The SAML side (entityID, certificates, level, IdPs,
> routes prefix, redirects) lives in `config/spid-auth.php` and
> `config/spid-idps.php`, owned by `italia/spid-laravel`; this page covers
> `config/filament-spid.php` and the plugin's options.

## `config/filament-spid.php`

| Key | Env | Default | Purpose |
|---|---|---|---|
| `enabled` | `FILAMENT_SPID_ENABLED` | `true` | Replace the panel login page with the SPID one |
| `register_listeners` | `FILAMENT_SPID_REGISTER_LISTENERS` | `true` | Listen to the library's `LoginEvent`/`LogoutEvent`; disable to take over the flow yourself |
| `panel` | `FILAMENT_SPID_PANEL` | `null` | Panel SPID users sign in to; `null` uses the current or default panel. An unknown id throws `SpidPanelNotFoundException` |
| `minimum_level` | `FILAMENT_SPID_MINIMUM_LEVEL` | SpidL2 URI | Lowest `spid-auth.sp_spid_level` the plugin accepts |
| `user_model` | `SPID_USER_MODEL` | `App\Models\User` | Eloquent model implementing `Authenticatable`; falls back to `spid-auth.user_model` |
| `auto_create_users` | `SPID_AUTO_CREATE_USERS` | `false` | Create an account when no `fiscal_code` matches |
| `update_user_data` | `SPID_UPDATE_USER_DATA` | `true` | Refresh mapped columns and `spid_data` on every login |
| `sp_profile` | `FILAMENT_SPID_SP_PROFILE` | `null` | `public` or `private`: the `spid_sp_test` profile `filament-spid:metadata:validate` uses |
| `deployment` | `FILAMENT_SPID_DEPLOYMENT` | `null` | `local`, `acceptance` or `production`: which test IdPs the doctor expects. `null`: judged by `APP_ENV` |
| `validation.spid_sp_test` | `SPID_SP_TEST_BIN` | `spid_sp_test` | Executable `filament-spid:metadata:validate` runs |
| `store_spid_data` | `FILAMENT_SPID_STORE_SPID_DATA` | `true` | Store SPID attributes in `spid_data`; `false` never writes the column |
| `spid_data_attributes` | | `null` | Attributes kept in `spid_data`; `null` keeps all eight |
| `field_mapping` | | name, email, fiscal_code, password | Column ⇒ SPID attribute, mapper class or callable ([below](#field-mapping)) |
| `create_user_callback` | | `null` | `callable(SpidUserData): Authenticatable` replacing account creation |
| `update_user_callback` | | `null` | `callable(Authenticatable, SpidUserData): void` replacing the update |

Values of the wrong type are treated as missing instead of failing deep inside
a login: a `user_model` that is not an authenticatable Eloquent model throws an
`InvalidArgumentException` naming the key, a `create_user_callback` returning
no user throws `UnexpectedValueException`, non-callable callbacks and
`field_mapping` entries without a column are ignored.

Keep the configuration cacheable: `php artisan config:cache` (and `optimize`)
cannot store closures, so prefer attribute names and invokable classes.

## SPID levels

| Level | URI | Credentials |
|---|---|---|
| SpidL1 | `https://www.spid.gov.it/SpidL1` | username and password |
| SpidL2 | `https://www.spid.gov.it/SpidL2` | plus a one-time code |
| SpidL3 | `https://www.spid.gov.it/SpidL3` | plus a smart card or hardware token |

`italia/spid-laravel` requests `spid-auth.sp_spid_level` and rejects any
assertion below it. The plugin refuses every SPID login, and logs a warning at
boot, while that level is below `filament-spid.minimum_level` (SpidL2 by
default). Raise the minimum to SpidL3 for panels that need it. The minimum
must be one of the three URIs: anything else refuses every login, so a typo
never lowers it.

## Field mapping

```php
'field_mapping' => [
    'name' => \OfflineAgency\FilamentSpid\Mapping\FullName::class,        // "Name FamilyName"
    'email' => \OfflineAgency\FilamentSpid\Mapping\EmailOrFallback::class, // SPID email or <fiscal code>@spid.invalid
    'fiscal_code' => 'fiscalNumber',                                         // a SPID attribute
    'password' => \OfflineAgency\FilamentSpid\Mapping\RandomPassword::class, // creation only
],
```

An entry is resolved, in this order, as: a SPID attribute name (`fiscalNumber`,
`name`, `familyName`, `email`, `spidCode`, `placeOfBirth`, `dateOfBirth`,
`gender`, or any key present in the payload); the class name of an invokable
mapper (`__invoke(array $spidUser)`, resolved from the container); a closure
or a static array callable (`[Mapper::class, 'staticMethod']`). Anything else,
including a plain function name such as `'strtoupper'`, maps to `null`: a
configuration value never runs an arbitrary function. `fiscal_code` is never
updated after creation.

- **Email.** SPID sends an email only when the IdP holds one; the fallback
  `<fiscal code, lowercased>@spid.invalid` is unique, stable and
  undeliverable (`.invalid`, RFC 2606). With a nullable column, map `'email'
  => 'email'`. Accounts are never matched by email.
- **Password.** `RandomPassword` stores the hash of a 64-character random
  password nobody knows, because Laravel's stock `users.password` is NOT NULL.
  It is a create-only mapper (`Mapping\Contracts\CreateOnly`): later logins
  keep the hash. It does **not** disable password login or password reset:
  if your application offers them on any guard, a citizen who resets the
  password can then use it. Disable them where SPID must be the only way in,
  or drop the entry when the column is nullable.

## Data minimisation

`spid_data` keeps by default every attribute the IdP sent. Keep only what the
panel needs; for an admin panel usually:

```php
'spid_data_attributes' => ['fiscalNumber', 'name', 'familyName', 'email', 'spidCode'],
```

`store_spid_data = false` stops writing the column (existing values stay).
Columns filled through `field_mapping` are not affected. Request fewer
attributes in the first place with `spid-auth.sp_requested_attributes`: SPID
asks for attributes "pertinent and not excessive" for the service. These
settings support your data protection obligations; they do not fulfil them
on their own.

## Multiple panels

The ACS runs on a library route, outside any panel, so the panel SPID users
belong to is configuration, not routing: `FILAMENT_SPID_PANEL=admin`. One
panel per application receives SPID logins; register `SpidPlugin` on it. An
unknown value throws instead of signing the citizen in on another guard.
Panels sharing a guard share its session too: a SPID login, failure or local
logout on one affects the others ([authentication](authentication.md#guards-and-sessions)).

## The SPID button and the login page

```php
SpidPlugin::make()
    ->spidButtonLabel('Entra con SPID')            // text of the button
    ->spidButtonIcon('heroicon-o-shield-check')     // a blade-icons icon instead of the SPID mark
    ->providers(['poste', 'aruba'])                // optional allowlist over config/spid-idps.php keys
    ->loginView('auth.spid-login')                  // your own view
    ->credentialsLoginUrl('/staff/login')           // a "Login with credentials" link to another page
    ->showSpidButton(false);                        // keep the panel's own login page
```

By default the button lists **every active real IdP** in
`config/spid-idps.php`, in random order (Fisher–Yates, as the AgID rules
require), followed by the SPID information links. Keep `config/spid-idps.php`
up to date with the library's. `providers()` is for special cases (an internal
service limited by agreement); a shortlist is not a recommended production
setup. Test and validator IdPs are shown only when enabled in
`config/spid-auth.php`.

The button's behaviour is a Filament asset (`filament-spid-button`) loaded
with `<script src>`: no inline script or `on*` attribute, so a
Content-Security-Policy needs only `script-src 'self'` (plus the jQuery
asset, also served by your application). Each button has its own ids and
form, so several on a page submit the right provider; it is initialised once,
also across Livewire navigation. The trigger has `role="button"`,
`aria-haspopup`, `aria-controls` and an `aria-expanded` that follows the menu;
Space and Enter open it, Escape closes it and returns focus. If its scripts
cannot load, it shows a message instead of failing silently.

The plugin can also register helper routes under the panel path (a login
redirect, a providers JSON endpoint, the SP metadata and a logout through the
IdP). They are off by default:

```php
SpidPlugin::make()->registerRoutes(true);
```
