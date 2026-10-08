# Changelog

All notable changes to `filament-spid` will be documented in this file.

## Unreleased

## 0.2.0 - 2026-10-08

### BREAKING

See "Upgrading from 0.1.x" in the README.

- Installing requires the patched `italia/spid-laravel` fork, patching enabled
  and beta stability in the application's `composer.json`: every published
  `italia/spid-laravel` pins `onelogin/php-saml` 4.1.0 (CVE-2025-66475), which
  Composer refuses to install.
- Laravel 11 is no longer supported. It is end of life and every 11.x release
  carries unpatched advisories, so Composer refuses to install it.
- SPID logins are refused while `spid-auth.sp_spid_level` is below the new
  `filament-spid.minimum_level` (default SpidL2). The library defaults to
  SpidL1: set `sp_spid_level` to SpidL2 or higher.
- `auto_create_users` defaults to `false` (`SPID_AUTO_CREATE_USERS`): a SPID
  identity no longer creates an account unless you opt in.
- The unused `redirect_after_login`, `spid_level` and `providers` config keys are
  gone (with `SPID_REDIRECT_AFTER_LOGIN` and `SPID_LEVEL`). Use
  `spid-auth.after_login_url`, `spid-auth.sp_spid_level` and
  `SpidPlugin::providers()`.
- Without an email from SPID, the default mapping stores
  `<fiscal code, lowercased>@spid.invalid` instead of `<fiscal code>@spid.local`
  (`.local` resolves on a LAN). Existing accounts change on their next login when
  `update_user_data` is on.
- A `filament-spid.panel` (`FILAMENT_SPID_PANEL`) that names no registered panel
  throws `SpidPanelNotFoundException` instead of logging the citizen in on the
  default guard.
- `SpidPlugin::registerRoutes()` defaults to `false`: the shipped login view
  posts straight to `italia/spid-laravel` and authentication runs off its
  events. Call `->registerRoutes(true)` to keep the helper routes.
- The plugin no longer exposes an ACS endpoint of its own and
  `SpidPlugin::acsRoute()` is gone: `italia/spid-laravel` owns `spid-auth_acs`.
  The plugin no longer overrides `spid-auth.after_login_url` either.

### Added

- Laravel 13, PHP 8.5 and Filament 5 support. CI proves every combination of
  PHP 8.2–8.5, Laravel 12–13 and Filament 3–5 (Laravel 13 from PHP 8.3).
- `HandleSpidLogin` and `HandleSpidLogout` listeners provision the user,
  authenticate them on the panel guard and tear the session down on logout, so
  applications need no listeners of their own. Opt out with
  `filament-spid.register_listeners`.
- `filament-spid.panel` pins the panel SPID users authenticate against.
- `filament-spid.minimum_level` and `SpidLevel::rank()` / `meets()`.
- The plugin's fluent options take effect: `spidButtonLabel()`,
  `spidButtonIcon()`, `providers()` (an allowlist over `spid-idps`) and
  `showSpidButton(false)`, which leaves the panel login page untouched.
- `SpidPlugin::resolve()` returns the plugin on the current panel, or null, so
  views render outside a SPID panel.
- A warning is logged when a `->login()` chained after the plugin replaced the
  SPID login page.
- `SpidUserData::fromSpidAuth()` accepts the `SPIDUser` object as well as an
  array.
- Release automation: merging a PR into `main` tags and releases it from its
  `release:*` label, with notes taken from this file; `release-check` requires
  the label.
- `SECURITY.md` and `CODE_OF_CONDUCT.md`.

### Changed

- `SpidController::logout()` hands a SPID session to the IdP single logout and
  lets `HandleSpidLogout` end it; other sessions are logged out of the panel
  guard, and an unreachable IdP still ends the local session.
- jQuery 3.7.1 ships with the package as an on-request Filament asset instead of
  coming from `code.jquery.com`; run `php artisan filament:assets` after
  upgrading. Pages that already load jQuery keep using theirs.
- The AGID logo is served from the package assets instead of a third-party CDN.
- `php` is required as `^8.2` and `italia/spid-laravel` as `^2.1.0-beta`;
  `spatie/laravel-package-tools` needs `^1.93`.
- CI runs on every pull request, checks style without committing, and runs
  `composer audit`. PHPStan runs at level 8 without a baseline.

- The default `field_mapping` uses attribute names and invokable mappers
  (`Mapping\FullName`, `Mapping\EmailOrFallback`) instead of closures, so the
  config can be cached with `php artisan config:cache` / `optimize`. Closures
  are still accepted.

### Removed

- The providers JSON endpoint no longer caches: the list comes from config.
  `filament-spid.cache` is gone with it.
- `SpidLoginRequest` and the unused `EvaluatesClosures` trait on the plugin.
- The `repositories` entry in `composer.json`.

### Fixed

- A failed SPID login forgets the library's SPID session, so the citizen can
  retry instead of being sent back by `doLogin()`.
- The metadata route serves `SPIDAuth::metadata()`; it called a method that does
  not exist and answered 500.
- `spid_data` is no longer double-encoded for models casting the column, and the
  user model is resolved from `filament-spid.user_model` with a fallback to
  `spid-auth.user_model`.
- The migration rollback guards each column and drops the unique index first.
- Package images are published to `public/vendor/filament-spid/images`, the path
  the views reference.
- CSRF: the package no longer ships a middleware overriding the deprecated
  VerifyCsrfToken. Exclude the library's ACS path in your application.
- Redirects resolve the panel login URL from Filament instead of assuming a panel
  named `admin`.
- The library config is only copied into `config_path()` under artisan, not on
  every boot, and `SPIDAuth` resolves to the library singleton.
- jQuery is loaded through the DOM instead of `document.write`, which a strict
  Content-Security-Policy blocks.

### Security

- `onelogin/php-saml` ^4.3.1 (CVE-2025-66475, critical) and
  `robrichards/xmlseclibs` ^3.1.5 through the patched `italia/spid-laravel`
  ([italia/spid-laravel#131](https://github.com/italia/spid-laravel/pull/131)),
  with the SPID patch actually applied.
- Minimum SPID level enforcement (SpidL2 by default) and no automatic account
  creation by default.
- SPID failure logs carry the exception class only, never personal data.
- No third-party script on the login page.
