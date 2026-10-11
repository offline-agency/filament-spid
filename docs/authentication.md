# How login works

> filament-spid 1.0.0.

## The flow

1. The panel login page shows the SPID button. Picking an IdP posts the
   button's form (with its CSRF token) to the library's `spid-auth_do-login`
   route, which builds the AuthnRequest, keeps its id in `Secure;
   SameSite=None` cookies and redirects to the IdP.
2. The IdP posts the Response to the ACS. `italia/spid-laravel` validates it
   (signature, issuer, audience, destination, timing, correlation with the
   request, level, required attributes), stores the SPID session and fires
   `LoginEvent`. An invalid Response stops here with an error: the plugin is
   never called.
3. `HandleSpidLogin` (this plugin) resolves the panel and its guard, checks
   the [SPID level](configuration.md#spid-levels), then provisions in one
   transaction: finds the account by `fiscal_code`, creates or updates it as
   configured, fires `SpidUserProvisioning`, and asks the panel gate
   (`canAccessPanel()`). A refusal rolls the transaction back.
4. It logs the citizen in on the panel guard (no remember-me cookie),
   regenerates the session id and fires `SpidAuthenticationSucceeded`.
5. When every `SpidAuthenticationSucceeded` listener has returned, the login
   has succeeded. That is the success boundary.

## Fail closed

Anything that throws before the success boundary fails the login closed:

- the panel guard is logged out, even if `login()` itself threw half way
  (Laravel's `SessionGuard` stores the user before firing its `Login` event),
- the SPID session keys are cleared, so the citizen can retry (otherwise
  `doLogin()` would short-circuit every retry),
- the error is logged with the exception class only, never SPID attributes
  (a failed insert quotes the values it tried to write),
- `SpidAuthenticationFailed` fires **after** the cleanup; a listener that
  throws is logged and cannot hide the original failure or re-enter it,
- the citizen lands on the panel login page with a translated message.

A redirect thrown by an observer or callback (`HttpResponseException`) is a
failure too, not a way around it. The cleanup is idempotent. Other guards are
never touched.

| Situation | Outcome |
|---|---|
| Unknown `FILAMENT_SPID_PANEL` | `SpidPanelNotFoundException` (configuration error), SPID session cleared, nobody signed in |
| Level below the minimum | refused (`insufficient_level`) |
| No account and `auto_create_users` off | refused (`authentication_failed`) |
| `canAccessPanel()` false | refused (`access_denied`); no account created, an existing one keeps its data |
| A listener of `SpidUserProvisioning` throws | rolled back, refused (`acs_error`) |
| A listener of Laravel's `Login` or of `SpidAuthenticationSucceeded` throws | guard logged out, refused (`acs_error`) |
| A `SpidAuthenticationFailed` listener throws | logged; cleanup already done; original message shown |

## Guards and sessions

The guard is the panel's auth guard (`$panel->getAuthGuard()`, `web` unless
the panel sets one). A failure logs out that guard only. Panels that share a
guard share its session: signing in, failing or logging out on one affects
the others, and a local logout (below) invalidates the whole session, every
guard included. Give a panel its own guard when that matters.

The session id is regenerated on success and no remember-me token is set, so
the SPID login lasts as long as the session. How long that is depends on your
application (`SESSION_LIFETIME`, `expire_on_close`, the driver): the plugin
does not promise that a session ends when the browser closes. Set
`SESSION_SECURE_COOKIE=true`; the doctor warns otherwise.

## Provisioning

Off by default. With `auto_create_users` on, any SPID citizen without an
account gets one, but still has to pass `canAccessPanel()`; the account is
not created when the gate refuses. Identity (SPID) and authorisation (your
gate, roles, flags) stay separate: never make `canAccessPanel()` return `true`
for everyone, and never link a SPID identity to an existing account by email.
To admit citizens with existing accounts, store their fiscal code in
`fiscal_code` beforehand (an admin screen, an import).

## Events and transactions

| Event | Payload | When |
|---|---|---|
| `SpidUserProvisioning` | `$user`, `$spidData`, `$created` | inside the provisioning transaction, before `canAccessPanel()` |
| `SpidUserCreated` | `$user`, `$spidData` | after the provisioning transaction **really commits** |
| `SpidUserUpdated` | `$user`, `$spidData` | likewise |
| `SpidAuthenticationSucceeded` | `$user`, `$spidData` | on the panel guard, before the success boundary |
| `SpidAuthenticationFailed` | `$reason` (no personal data) | after a refused login, after the cleanup |

The provisioning transaction runs on **the user model's connection**, not the
default one. `SpidUserCreated` and `SpidUserUpdated` are dispatched through
that connection's `afterCommit()`: after an enclosing transaction commits too,
and never when the creation, the update or an enclosing transaction rolls
back. So:

- Give new accounts their roles in a `SpidUserProvisioning` listener (or in
  `create_user_callback`): it runs inside the transaction, before the gate, and
  its database writes on the same connection roll back with a refusal.
- Send notifications, emails and calls to other systems from
  `SpidUserCreated`/`SpidUserUpdated` (queued, if slow): they only fire for
  accounts that exist.
- A rollback undoes database writes on that connection only. It does not undo
  emails, queued jobs already dispatched, HTTP calls or writes to another
  database made inside `SpidUserProvisioning`: keep side effects out of it.
- `SpidUserCreated` fires after the transaction but before the success
  boundary: a listener that throws there fails the login (closed), yet the
  account stays created.

Callbacks: `create_user_callback` and `update_user_callback` run inside the
transaction, on whatever connection they write to; only writes on the user
model's connection are rolled back. `create_user_callback` must return an
`Authenticatable`.

## Logout

- **Local logout** (Filament's user menu) ends the application session (and,
  by invalidating it, the library's SPID session keys). The citizen stays
  signed in at the IdP.
- **Single logout (SLO)** also ends the IdP session: send users to the
  plugin's `spid.logout` route (a POST under the panel path, enabled with
  `registerRoutes(true)`). It logs the panel guard out, then starts the SLO;
  when the IdP redirects back, the library fires `LogoutEvent` and
  `HandleSpidLogout` invalidates the session. An unreachable IdP still ends the
  local session.

Which one your service offers is your decision; SPID's rules on logout apply
to your service, not to this package.
