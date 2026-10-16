# Filament plugin catalog submission

**Status: PREPARED_NOT_SUBMITTED.** Nothing has been sent to filamentphp.com.

## Verified procedure (2026-10-11)

- The catalog at <https://filamentphp.com/plugins> links "Submit plugins" to the
  authenticated author portal <https://filamentphp.com/author>; submission is
  made there, not by a pull request to a repository.
- No public page lists the portal's fields, image sizes or review rules.
  Everything below the line "Proposed content" is a proposal to adapt to what
  the portal actually asks once signed in.

## External action needed

A maintainer of Offline Agency signs in to <https://filamentphp.com/author>,
creates the plugin entry with the content below, uploads the images and
submits it, **after** 1.0.0 is tagged and on Packagist (the entry must point
at a version users can install).

## Proposed content

| Field | Proposed value | Verified in the portal? |
|---|---|---|
| Name | SPID Authentication | no |
| Author | Offline Agency | no |
| Slug | `offline-agency-spid` | no (check availability) |
| Package | `offline-agency/filament-spid` | n/a (Packagist) |
| Category | Authentication, if offered | no |
| Filament versions | only those of the published, tested release: 3.x, 4.x, 5.x for 1.0.0 | after the release |
| Repository | https://github.com/offline-agency/filament-spid | |
| Documentation | https://github.com/offline-agency/filament-spid#readme | |
| License | MIT | |

**Short description**

> Add Italian SPID authentication to Filament panels, with configurable user
> provisioning and panel-guard login, powered by italia/spid-laravel.

**Long description**

> SPID Authentication puts the official SPID button on a Filament panel's login
> page and signs citizens in with SPID, the Italian public digital identity
> system, over SAML 2.0. The SAML side (requests, responses, signatures,
> metadata) is handled by italia/spid-laravel; the plugin turns a validated
> SPID identity into a login on the panel's own guard.
>
> - Accounts matched by fiscal code; optional, opt-in account creation with
>   configurable field mapping and data minimisation.
> - Your `canAccessPanel()` decides who gets in, inside the provisioning
>   transaction: a refused citizen gets no account.
> - Fails closed: an exception after login logs the panel guard out and clears
>   the SPID session.
> - Events for provisioning, creation (after commit), success and failure.
> - Local logout and IdP single logout.
> - `filament-spid:install` (guided, idempotent setup), `filament-spid:doctor`
>   (read-only diagnostics) and `filament-spid:metadata:validate` (an adapter for
>   AgID's spid_sp_test).
> - Filament 3, 4 and 5 on Laravel 12 and 13, PHP 8.3–8.5.
>
> Maintained independently by Offline Agency; not an official Filament or AgID
> product. Accreditation to SPID, the service provider's data and keys, and
> authorisation remain the integrator's responsibility.

## Images

Real screenshots of the login page with synthetic test data (no real person,
key or organisation), taken from an application built from the distributable
archive:

| File | Content |
|---|---|
| `docs/images/login-light.png` | 1280×900 @2x, light mode, IdP menu open |
| `docs/images/login-dark.png` | 1280×900 @2x, dark mode |
| `docs/images/login-mobile.png` | 390×844 @3x, mobile, IdP menu open |

Required dimensions or formats are not publicly documented; crop or resize to
what the portal asks.

## Checklist

- [ ] 1.0.0 tagged and visible on Packagist (`composer require offline-agency/filament-spid:^1.0` works)
- [ ] README aligned with the published release (pre-release note removed)
- [ ] Filament versions in the entry = those of the published, tested release
- [ ] Slug available
- [ ] Images in the sizes the portal asks
- [ ] No "official", certification or guarantee wording
- [ ] Submitted by a maintainer from the author portal
