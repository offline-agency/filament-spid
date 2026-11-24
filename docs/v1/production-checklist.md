# Production checklist

> filament-spid 1.0.0. This list helps; it does not make a service compliant
> or accredited. The SPID technical rules and AgID's procedure are the
> authority, and they are your organisation's responsibility.

## Before the AgID acceptance test

- [ ] `composer audit` reports no advisories; `onelogin/php-saml` ≥ 4.3.1 and
      `robrichards/xmlseclibs` ≥ 3.1.5 (`filament-spid:doctor`, check `dependencies`).
- [ ] The SPID patch is applied (`spid_patch` passes; it inspects the metadata
      php-saml builds).
- [ ] `config/spid-auth.php` holds your SP's real identity and contacts:
      public SP with its IPA code, or private SP with its VAT number and the
      billing contact. No example value from `italia/spid-laravel` is left.
- [ ] Your own SP signing key pair, issued per the SPID rules for your profile;
      the key outside `public/`, out of Git, readable only by the application
      user; not the example pair (`sp_certificate`), expiry well ahead, and a
      renewal date in someone's calendar.
- [ ] `spid-auth.sp_spid_level` matches the service's risk (SpidL2 at least
      for admin panels; `filament-spid.minimum_level` not lowered).
- [ ] Requested attributes (`sp_requested_attributes`) limited to what the
      service needs; `spid_data_attributes` likewise.
- [ ] `canAccessPanel()` implements an explicit rule; `auto_create_users` is
      off unless every SPID citizen may hold an account; existing users have
      their `fiscal_code` set.
- [ ] Password login and password reset are disabled where SPID must be the
      only way in (a random password is not a disabled one).
- [ ] HTTPS everywhere, `SESSION_SECURE_COOKIE=true`; behind a proxy, trusted
      proxies and php-saml's proxy variables configured
      ([installation](installation.md#behind-a-proxy-or-load-balancer)).
- [ ] Only the ACS is exempt from CSRF; a POST elsewhere without a token gets
      419.
- [ ] Session lifetime and logout match the service's policy (local logout or
      SLO, see [authentication](authentication.md#logout)).
- [ ] `config/spid-idps.php` is current with `italia/spid-laravel`'s; the
      button lists every active real IdP; no test IdP.
- [ ] `php artisan filament-spid:doctor --strict` passes, except `csrf` and
      `csp`, which are `skip` and must be checked by hand.
- [ ] `php artisan filament-spid:metadata:validate --url=<your metadata URL>
      --profile=<spid-sp-public|spid-sp-private>` passes on the deployed
      metadata.
- [ ] The [local lab](testing/agid.md#the-local-lab) passes for your version,
      or its known failures are understood.

## The acceptance test

- [ ] `FILAMENT_SPID_DEPLOYMENT=acceptance`, only the official Validator
      configured as an extra IdP, local and demo IdPs removed.
- [ ] Metadata, Request and Response checks run on the
      [hosted Validator](https://validator.spid.gov.it) with the access AgID
      describes; results kept.
- [ ] The request sent to AgID as its procedure describes.

## After it

- [ ] `validator_idp` set back to `false`, `FILAMENT_SPID_DEPLOYMENT=production`,
      the doctor's `test_idps` passes.
- [ ] The production deployment tested end to end with a real identity by
      someone authorised to do so.
- [ ] Logs reviewed: SPID failures log the exception class and the IdP,
      never attributes; make sure your own logging does not add them.
- [ ] Data retention for `spid_data` and the users table decided and
      documented by the data controller.
- [ ] Dependency updates (Dependabot) and certificate renewals have an owner.
