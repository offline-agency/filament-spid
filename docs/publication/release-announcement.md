# 1.0.0 release announcement (draft; not published)

**filament-spid 1.0.0: SPID login for Filament 3, 4 and 5**

filament-spid 1.0.0 signs citizens in to Filament panels with SPID, over SAML
2.0, on top of italia/spid-laravel.

- `php artisan filament-spid:install`: guided, idempotent setup; plans before
  writing, keeps your files, exempts only the ACS from CSRF.
- `php artisan filament-spid:doctor`: read-only diagnostics with stable JSON.
- `php artisan filament-spid:metadata:validate`: runs AgID's spid_sp_test on
  your metadata and records what it checked.
- Fail-closed login, provisioning in a transaction on the user model's
  connection, user events after commit, an accessible, CSP-friendly SPID
  button.
- PHP 8.3–8.5, Laravel 12–13, Filament 3–5.

Upgrading from 0.1.x: see docs/upgrading.md. A community package maintained
by Offline Agency; it does not replace AgID's accreditation procedure.
