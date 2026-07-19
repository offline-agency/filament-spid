# Changelog

All notable changes to `filament-spid` will be documented in this file.

## Unreleased

### Changed

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
