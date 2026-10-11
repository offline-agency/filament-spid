# FAQ

**How do I add SPID login to a Filament panel?**
Install `italia/spid-laravel` with its SPID patch and this plugin, then run
`php artisan filament-spid:install --panel=<id>` ([quick start](../README.md#quick-start)).
The plugin replaces the panel login page with the SPID button and signs the
citizen in on the panel's guard.

**Does it work with Filament 3, 4 and 5?**
Yes, on Laravel 12 and 13 and PHP 8.3–8.5; every combination runs in CI.

**Can existing users log in with SPID?**
Yes, once their account has their fiscal code in `fiscal_code`: accounts are
matched by fiscal code, never by email. Set it from an admin screen or an
import, then they sign in with SPID. `auto_create_users` is only needed to
create accounts for citizens who have none.

**Who decides who may enter?**
Your application, in `canAccessPanel()`. SPID proves identity, not
permissions.

**Why do I get 419 on the SPID callback?**
The IdP's POST to the ACS cannot carry a CSRF token. Exempt exactly
`spid/acs` (or `<prefix>/acs`) in `bootstrap/app.php`, never `spid/*`
([CSRF](installation.md#csrf-and-the-acs)).

**Does installing it make my service accredited to SPID, or GDPR compliant?**
No. Accreditation is AgID's procedure ([testing](testing/agid.md)); data
protection is the data controller's. The plugin gives you tools (attribute
minimisation, no personal data in logs, a doctor, a metadata validator) and
documents what stays yours.

**Is it an official Filament or AgID package?**
No. It is a community package maintained by Offline Agency, built on
`italia/spid-laravel`.

**Does it support CIE, CIE OIDC or SPID OIDC?**
No. SPID over SAML 2.0 only.

**Does a random password mean password login is disabled?**
No. It only fills a NOT NULL column. If your application offers password
login or reset, disable them where SPID must be the only way in.

**Is the panel chosen per request?**
No. `FILAMENT_SPID_PANEL` names the one panel SPID logins land on.

**If a login is refused, is everything undone?**
The account creation or update on the user model's connection is rolled
back. Emails, queued jobs, HTTP calls or writes to other databases are not:
keep side effects in `SpidUserCreated`/`SpidUserUpdated`, which fire after the
commit ([events](authentication.md#events-and-transactions)).

**Why is 1.0.0 not on Packagist yet?**
It is waiting for review and release; see the README's pre-release note.
