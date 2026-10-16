# Security Policy

`filament-spid` authenticates citizens on administrative panels, so we treat
security reports as a priority.

## Supported Versions

| Version | Supported by this project |
|---|---|
| 1.x | Yes, the latest 1.x release |
| 0.1.x | No: no fixes, and no reason to stay on it |

Versions before 1.0.0 resolve `italia/spid-laravel` releases that pin
`onelogin/php-saml` 4.1.0 (CVE-2025-66475): upgrade (see
[upgrading](docs/upgrading.md)); do not stay on 0.1.x to get around the
dependency setup.

This project's support is separate from the support cycles of what it runs
on: 1.x supports PHP 8.3–8.5 and Laravel 12–13 while those receive security
fixes from their own projects ([PHP](https://www.php.net/supported-versions.php),
[Laravel](https://laravel.com/docs/13.x/releases#support-policy)). When one of
them reaches its end of life, a later 1.x release may drop it.

## Reporting a Vulnerability

**Do not open a public issue, pull request or discussion for a vulnerability.**

Email [support@offlineagency.it](mailto:support@offlineagency.it) with:

- the affected version(s) of `filament-spid`, Laravel, Filament and
  `italia/spid-laravel`;
- a description of the issue and its impact;
- the steps or a minimal proof of concept to reproduce it.

We will acknowledge your report and keep you informed until the fix is
released. Please give us reasonable time to release a fix before
disclosing. We credit reporters in the release notes unless asked not to.

## Scope

In scope: the code in this repository (the plugin, its listeners, controller,
views, configuration defaults and published assets).

Issues in dependencies belong upstream:

- SAML processing and the SPID patch to `onelogin/php-saml`:
  [italia/spid-laravel](https://github.com/italia/spid-laravel)
- the SAML toolkit itself: [SAML-Toolkits/php-saml](https://github.com/SAML-Toolkits/php-saml)
- Filament and Laravel: their own security policies.

If you are unsure where an issue belongs, report it to us and we will route it.

## Hardening Checklist

The defaults are secure, but your configuration decides the outcome. The
[production checklist](docs/production-checklist.md) has the full list; in
short:

- Request at least SpidL2 (`spid-auth.sp_spid_level`); the plugin refuses logins
  below `filament-spid.minimum_level`.
- Keep `auto_create_users` off unless every SPID citizen may hold an account.
- Implement `FilamentUser::canAccessPanel()` with an explicit rule.
- Exclude only the library's ACS path from CSRF verification.
- Use your own SP key pair, never the example one; keep the key out of
  `public/` and Git.
- Run `composer audit` and `php artisan filament-spid:doctor --strict` in your
  pipeline.
