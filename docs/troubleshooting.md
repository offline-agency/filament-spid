# Troubleshooting

> filament-spid 1.0.0. Start with `php artisan filament-spid:doctor`: most of
> the problems below have a check. None of the fixes disables a security
> control: no `curl -k`, no TLS verification off, no `spid/*` exempt from
> CSRF, no ignored advisories.

| Symptom | Check | Fix | Verify |
|---|---|---|---|
| Composer refuses `onelogin/php-saml` 4.1.0, or reports a conflict with `onelogin/php-saml <4.3.1` | the `italia/spid-laravel` constraint | Follow [Composer](installation.md#composer): the repository and the pinned fork come before `composer require`. Do not add the advisory to `audit.ignore`. | `composer audit` |
| IdPs reject the AuthnRequest | doctor `spid_patch` | Set `extra.enable-patching: true` and allow `cweagans/composer-patches` in the root `composer.json`, then `composer reinstall onelogin/php-saml`. | doctor `spid_patch` passes |
| The panel shows the email/password form | the panel provider | `->login()` must come before `->plugin(SpidPlugin::make())`; the log says `does not use the SPID login page`. | doctor `panel` passes |
| 419 Page Expired on `POST /spid/acs` | `bootstrap/app.php` | Exempt exactly `<routes_prefix>/acs` ([CSRF](installation.md#csrf-and-the-acs)). | `curl -X POST https://…/spid/acs` is not 419 |
| 500 on the ACS, log "idp value not present or wrong (this is likely due to a non-https request)" | the scheme of the site | The library keeps the request state in `Secure; SameSite=None` cookies: serve over HTTPS; behind a proxy, configure it ([proxy](installation.md#behind-a-proxy-or-load-balancer)). | a login reaches the IdP and back |
| 500 on the ACS, "The response was received at http://… instead of https://…" | proxy headers | `trustProxies()` and `OneLogin\Saml2\Utils::setProxyVars(true)`, proxy sending `X-Forwarded-Proto/Host/Port`. | a login completes |
| Signature or certificate errors on the IdP side | doctor `sp_certificate` | The key must belong to the published certificate; never the example pair; the metadata registered with AgID must be the current one. | `filament-spid:metadata:validate` |
| "access denied" after a successful SPID login | `canAccessPanel()` | Write the rule that admits this user. Do not return `true` for everyone. | log in again |
| "No account" / `authentication_failed` | `users.fiscal_code` | Store the citizen's fiscal code on their account, or enable `auto_create_users` knowingly. | log in again |
| Creating users fails with a NOT NULL constraint on `password` | `field_mapping` | Add `'password' => RandomPassword::class` and `password` to `$fillable` (or drop the mapper for a nullable column). | log in again |
| "Requires a higher SPID security level" | `spid-auth.sp_spid_level` | Request at least the minimum (SpidL2). | doctor `spid_config` |
| Clicking an IdP goes straight back to the login page | an old SPID session | 1.0.0 clears it after a failed login; otherwise log out or clear the session cookie. | |
| The SPID button does not open; console error "SPID button scripts could not be loaded" | published assets | `php artisan vendor:publish --tag=spid-assets` and `php artisan filament:assets`; a CSP must allow your own scripts. | doctor `assets` |
| Config changes have no effect | the config cache | `php artisan config:cache` again (or `config:clear`). | `php artisan config:show filament-spid` |
| Wrong indexes in the metadata (`AttributeConsumingService index="1"`) | the SPID patch | as "IdPs reject the AuthnRequest" | doctor `spid_patch` |
| The lab cannot reach the application (Docker) | the network | Inside a container `localhost` is the container; use the lab's `sp.spid.test` or `host.docker.internal`. | `tools/spid/validate.sh` |
