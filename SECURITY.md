# Security Policy

`filament-spid` authenticates citizens on administrative panels, so we treat
security reports as a priority.

## Supported Versions

| Version | Supported |
|---|---|
| 0.2.x | Yes |
| < 0.2 | No |

Only the latest minor release receives security fixes. Versions before 0.2.0
install `italia/spid-laravel` releases that pin `onelogin/php-saml` 4.1.0
(CVE-2025-66475); upgrade.

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

The defaults are secure, but your configuration decides the outcome:

- Request at least SpidL2 (`spid-auth.sp_spid_level`); the plugin refuses logins
  below `filament-spid.minimum_level`.
- Keep `auto_create_users` off unless every SPID citizen may hold an account.
- Implement `FilamentUser::canAccessPanel()` on your user model.
- Exclude only the library's ACS path from CSRF verification.
- Run `composer audit` in your pipeline.
