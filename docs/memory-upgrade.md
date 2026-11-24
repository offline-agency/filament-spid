# Upgrade memory

## PHP 8.6 upgrade (2026-11-24)

- **Target PHP:** 8.6. **Mode:** CURRENT. **Supported PHP:** 8.3, 8.4, 8.5, 8.6
  (`php` stays `^8.3`; CI matrix and docs list 8.6 as well).
- **Not a fork.** `offline-agency/filament-spid`, default branch `main`.
- **Gate:** VIA LIBERA, no blockers. The tree resolves on every PHP version
  (8.3 to 8.6) for all 6 Laravel (12, 13) x Filament (3, 4, 5) combinations,
  in a scratch copy with the CI fork setup (`.github/scripts/use-spid-laravel-fork.sh`,
  `COMPOSER=composer.ci.json`) and `config.platform.php` set per version.
  - Only BUMP: `pestphp/pest`, `pestphp/pest-plugin-laravel`,
    `pestphp/pest-plugin-arch` `^3.0|^4.0` -> `^3.0|^4.0|^5.0`. Pest 4 needs a
    `brianium/paratest` that does not allow PHP 8.6 and Pest 3's Laravel
    plugin does not support Laravel 13; Pest 5 needs PHP 8.4+, so PHP 8.3
    keeps Pest 4.
  - All `ext-*` are bundled with PHP; no abandoned packages.
  - `italia/spid-laravel` stays the pinned `offline-agency/spid-laravel` fork
    (no upstream tag, issue #16); its `php` constraint `^8.2|^8.3` allows 8.6.
- **Framework target:** Laravel 12 + 13, Filament 3 + 4 + 5 (unchanged).
- **Version:** 1.0.0 -> 1.1.0 (MINOR: support added, nothing dropped). The repo
  releases from PR labels (`release.yml`): the PR carries `release:minor`, the
  workflow tags and creates the GitHub Release on merge.
- **Docs layout:** flat `docs/` -> `docs/v1/` (guides, `it/`, `snippets/`,
  `images/`, `testing/`), `docs/README.md` version index, `docs/v1/index.md`.
  `docs/publication/` and this file stay in the root of `docs/`. No
  `.oa-docs.json` in the repo. Tests and CI scripts that read docs paths were
  updated.
- **QA:** Pint and PHPStan/Larastan (level max, no baseline) already existed;
  nothing added except the `format:check` script and a `composer validate
  --strict` CI job. PHPStan `phpVersion` is not pinned.
- **Badges:** all 10 README badges verified (HTTP 200, no error text); the
  Latest Release badge is active (release 1.0.0 exists), no release created.
- **Problem found:** Laravel 12 on PHP 8.6 fails the suite (`failOnDeprecation`)
  because every `laravel/framework` 12.x calls `spl_object_hash()` (deprecated
  in 8.6; 13.x uses `spl_object_id()`). The plugin's own code raises no
  deprecation. Decision: exclude `php 8.6 x laravel 12.*` from the CI matrix,
  documented in README/docs/CHANGELOG. Revisit when Laravel 12 stops calling it.
- **Local verification:** full suite on 8.3, 8.4, 8.5, 8.6 (default tree,
  Laravel 13 / Filament 5) plus Laravel 13 with Filament 3 and 4 on 8.6, all
  green with `-d error_reporting=E_ALL` and zero deprecations. PHPStan and
  Pint pass on every version.
- **Local tooling notes:** PHPStan needs `--memory-limit=1G` locally; the
  bundled `phpstan_turbo` extension prints a harmless load warning on this
  PHP 8.6 build.
- **Open follow-ups:** drop the CI exclusion when Laravel 12 supports PHP 8.6;
  confirm the new CI legs (8.6) on GitHub Actions after the PR runs.
