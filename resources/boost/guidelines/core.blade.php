## offline-agency/filament-spid

SPID (the Italian public digital identity, SAML 2.0) login for Filament panels, built on `italia/spid-laravel`. A community package maintained by Offline Agency: it is not an official Filament or AgID product.

- Stable 1.0.0 was released on GitHub on 16 October 2026 and separately verified on Packagist on 18 October 2026. Installation still requires the pinned patched fork in the README quick start; issue #16 tracks replacing it. Do not infer upstream release availability or SPID accreditation from the plugin release.
- Check the installed version first: `composer show offline-agency/filament-spid`. The commands below exist from 1.0.0.
- Install and configure with `php artisan filament-spid:install --panel=<id> --input=setup.json` (try `--dry-run` first). Diagnose with `php artisan filament-spid:doctor` (`--json`, `--strict`). Validate the SP metadata with `php artisan filament-spid:metadata:validate --url=https://.../spid/metadata`.
- `italia/spid-laravel` must be installed with its SPID patch for `onelogin/php-saml` applied: the root `composer.json` needs `extra.enable-patching: true` and `allow-plugins.cweagans/composer-patches: true` before `composer require`. Never ignore the `onelogin/php-saml` or `robrichards/xmlseclibs` advisories to make an install pass.
- Register the plugin after `->login()` in the panel provider: `->login()->plugin(\OfflineAgency\FilamentSpid\SpidPlugin::make())`. A `->login()` chained after the plugin puts the default login page back.
- The panel SPID users sign in to is `filament-spid.panel` (`FILAMENT_SPID_PANEL`); its guard is the panel's auth guard. It is configuration, not routing per request.
- Exempt only the ACS route (`<spid-auth.routes_prefix>/acs`, default `spid/acs`) from CSRF verification. Never exempt `spid/*`, never disable CSRF globally, never disable SAML signature or response validation.
- Authorisation is the application's: implement `Filament\Models\Contracts\FilamentUser::canAccessPanel()` with an explicit rule (a role or flag the app defines). Never return `true` for everyone. `auto_create_users` is off by default; turning it on lets every SPID citizen get an account, still subject to `canAccessPanel()`.
- Accounts are matched by `fiscal_code`, never by email.
- Logging out of Filament ends the local session only. The IdP single logout (SLO) goes through the plugin's `spid.logout` route (with `registerRoutes(true)`).
- Roles for new accounts go in a `SpidUserProvisioning` listener (inside the provisioning transaction, before the panel gate), not in `SpidUserCreated` (after commit).
- This package does SPID over SAML only. Do not claim or add CIE, CIE OIDC or SPID OIDC support.
