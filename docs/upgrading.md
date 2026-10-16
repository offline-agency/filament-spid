# Upgrading from 0.1.x to 1.0

0.1.x is no longer maintained and receives no fixes ([SECURITY.md](../SECURITY.md)).
Its releases resolve `italia/spid-laravel` versions that pin `onelogin/php-saml`
4.1.0 (CVE-2025-66475): staying on it is not a way around the dependency
setup below.

- **Platform**: PHP 8.3+ and Laravel 12 or 13.
- **Composer**: follow [Composer](installation.md#composer) (fork repository
  pinned to a commit, `enable-patching`, the `cweagans/composer-patches`
  plugin), then `composer require offline-agency/filament-spid:^1.0`. The
  global `minimum-stability: beta` 0.1.x needed is no longer required.
- **Installer**: `php artisan filament-spid:install --panel=<id> --dry-run`
  shows what it would change; it keeps your existing files.
- **SPID level**: `spid-auth.sp_spid_level` SpidL2 or higher; logins are
  refused below `filament-spid.minimum_level` (SpidL2).
- **Provisioning**: `auto_create_users` defaults to `false`; set
  `SPID_AUTO_CREATE_USERS=true` to keep creating accounts.
- **Config**: delete `redirect_after_login`, `spid_level` and `providers` from
  your published `config/filament-spid.php` (and `SPID_REDIRECT_AFTER_LOGIN`,
  `SPID_LEVEL` from `.env`); they were never read. Use
  `spid-auth.after_login_url`, `spid-auth.sp_spid_level` and
  `SpidPlugin::providers()`. New keys: `sp_profile`, `deployment`,
  `validation`, `store_spid_data`, `spid_data_attributes`.
- **Field mapping**: replace closures with `FullName::class`,
  `EmailOrFallback::class`, `'fiscalNumber'` and `RandomPassword::class`
  (`OfflineAgency\FilamentSpid\Mapping`) so `config:cache` works. Plain
  function names are no longer called.
- **Fallback email**: `<fiscal code>@spid.invalid` instead of `@spid.local`.
- **Panel**: an unknown `FILAMENT_SPID_PANEL` throws instead of falling back to
  the default guard.
- **Events**: `SpidUserCreated`/`SpidUserUpdated` fire after the provisioning
  transaction commits; move code that prepares an account for the panel gate
  (roles) to `SpidUserProvisioning`.
- **Observers**: an exception or a redirect thrown by a
  `SpidAuthenticationSucceeded` listener now fails the login (closed) instead
  of leaving the citizen signed in.
- **Routes**: the plugin's helper routes are opt-in (`registerRoutes(true)`);
  exempt the library's `spid/acs` from CSRF.
- **Login page**: the self-linking "Login with credentials" link is gone; use
  `SpidPlugin::credentialsLoginUrl()`. Custom copies of the views must use the
  `fi-spid-*` classes (`php artisan vendor:publish --tag=filament-spid-views
  --force` after reviewing your changes).
- **Assets**: `php artisan filament:assets` (the button script and jQuery are
  served by your application) and `vendor:publish --tag=spid-assets`.
