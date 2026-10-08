# Changelog

All notable changes to `filament-spid` will be documented in this file.

## Unreleased

### Changed

- **BREAKING** SPID logins are refused while `spid-auth.sp_spid_level` is below
  the new `filament-spid.minimum_level` (default SpidL2), and boot logs a
  warning. The library defaults to SpidL1: set `sp_spid_level` to SpidL2 or
  higher.
- **BREAKING** `auto_create_users` defaults to `false` (`SPID_AUTO_CREATE_USERS`):
  a SPID identity no longer creates an account unless you opt in.
- **BREAKING** The unused `redirect_after_login`, `spid_level` and `providers`
  config keys are gone (with `SPID_REDIRECT_AFTER_LOGIN` and `SPID_LEVEL`). Use
  `spid-auth.after_login_url`, `spid-auth.sp_spid_level` and
  `SpidPlugin::providers()` instead.
- **BREAKING** Laravel 11 is no longer supported. It is end of life and every
  11.x release carries unpatched advisories, so Composer refuses to install it.
- **BREAKING** A `filament-spid.panel` (`FILAMENT_SPID_PANEL`) that names no
  registered panel throws `SpidPanelNotFoundException` instead of logging the
  citizen in on the default guard.
- **BREAKING** Installing requires the patched `italia/spid-laravel`, patching
  enabled and beta stability in the application's `composer.json`: see
  Installation in the README.
- `SpidController::logout()` hands a SPID session to the IdP single logout and
  lets `HandleSpidLogout` end it; other sessions are logged out of the panel
  guard.
- **BREAKING** `SpidPlugin::registerRoutes()` now defaults to `false`. The shipped
  login view posts straight to `italia/spid-laravel` and authentication runs off
  its events, so the plugin's own helper routes are opt-in. Call
  `->registerRoutes(true)` to keep them.
- **BREAKING** The plugin no longer exposes an ACS endpoint of its own, and
  `SpidPlugin::acsRoute()` is gone: `italia/spid-laravel` owns the
  `spid-auth_acs` route. The plugin no longer overrides
  `spid-auth.after_login_url` either.

### Added

- The plugin's fluent options are wired to the views: `spidButtonLabel()`,
  `spidButtonIcon()` and `providers()` (an allowlist over `spid-idps`) now take
  effect, and `showSpidButton(false)` leaves the panel login page untouched.
- `SpidPlugin::resolve()` returns the plugin registered on the current panel, or
  null, so views render outside a SPID panel without throwing.
- `filament-spid.panel` pins the panel SPID users are authenticated against.
- `HandleSpidLogin` and `HandleSpidLogout` listeners provision the user,
  authenticate them on the panel guard and tear the session down on logout.
  Consumer applications no longer need listeners of their own. Opt out with
  `filament-spid.register_listeners`.
- `SpidUserData::fromSpidAuth()` accepts the `SPIDUser` object as well as an
  array, reading its magic `__get` attributes explicitly.

### Removed

- The providers JSON endpoint no longer caches: the list comes from config.
  `filament-spid.cache` is gone with it.
- `SpidLoginRequest`, which validated a provider for an action that no longer
  takes one, and the unused `EvaluatesClosures` trait on the plugin.

### Fixed

- `spid_data` is no longer double-encoded for models casting the column, and the
  user model is resolved from `filament-spid.user_model` with a fallback to
  `spid-auth.user_model`.
- The migration rollback guards each column and drops the unique index first, so
  it neither removes pre-existing columns nor fails midway.
- Package images are published to `public/vendor/filament-spid/images`, the path
  the views reference; previously they never reached it.
- The AGID logo is served from the package assets instead of a third-party CDN.
- CSRF: the package no longer ships a middleware overriding the deprecated
  VerifyCsrfToken, which Laravel 11+ does not use. Exclude the ACS path in your
  application instead.
- Redirects resolve the panel login URL from Filament instead of assuming a
  panel named `admin`.
- The library config is only copied into `config_path()` under artisan, not on
  every boot, and `SPIDAuth` resolves to the library singleton rather than a
  second instance.
- jQuery is loaded through the DOM instead of `document.write`, which a strict
  Content-Security-Policy blocks.
- Documentation matches reality: supported versions (PHP 8.2–8.5, Laravel 12–13,
  Filament 3–5), the installation requirements, and the real
  `spid-idps` provider keys (`poste`, `infocert`, `tim`, …).
- A failed SPID login forgets the library's SPID session keys, so the citizen
  can retry instead of being short-circuited by `doLogin()`.
- The metadata route serves `SPIDAuth::metadata()`; it called a method that does
  not exist and answered 500.

### Security

- CI installs `italia/spid-laravel` with `onelogin/php-saml` ^4.3.1
  (CVE-2025-66475, critical) and the SPID patch applied, and runs
  `composer audit`. See [italia/spid-laravel#131](https://github.com/italia/spid-laravel/pull/131).
