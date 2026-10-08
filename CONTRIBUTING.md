# Contributing

Contributions are **welcome** and will be fully **credited**.

We accept contributions via Pull Requests on [GitHub](https://github.com/offline-agency/filament-spid).

## Pull Requests

- **Document any change in behaviour** - Keep `README.md` and `CHANGELOG.md` (under `## Unreleased`) up to date.
- **Consider our release cycle** - We follow [SemVer v2.0.0](https://semver.org/). Breaking public APIs needs a `release:major` label.
- **Create feature branches** - Don't ask us to pull from your `main` branch.
- **One pull request per feature** - If you want to do more than one thing, send multiple pull requests.
- **Send coherent history** - Use [conventional commits](https://www.conventionalcommits.org/) and make each commit meaningful.
- **Pick a release label** - See [Releasing](#releasing).

## Local Setup

Every published `italia/spid-laravel` pins `onelogin/php-saml` 4.1.0, which
Composer refuses to install (CVE-2025-66475). Until
[italia/spid-laravel#131](https://github.com/italia/spid-laravel/pull/131) is
released, work on a copy of `composer.json` that uses the patched fork, exactly
as CI does, so the published manifest stays untouched:

```bash
export COMPOSER=composer.ci.json
bash .github/scripts/use-spid-laravel-fork.sh
composer update
```

`composer.ci.json` and `composer.ci.lock` are git-ignored. The suite checks the
published `composer.json`, so never commit the fork setup into it.

## Running Tests

```bash
composer test
```

To generate coverage you need a coverage driver (Xdebug or pcov), then:

```bash
composer test-coverage
```

The CI helper scripts have their own tests:

```bash
for t in .github/scripts/tests/*.test.sh; do bash "$t"; done
```

## Code Style

We use Laravel Pint. CI only checks (`vendor/bin/pint --test`); fix locally with:

```bash
composer format
```

## Static Analysis

```bash
composer analyse
```

## Releasing

Releases are cut automatically when a pull request is merged into `main`
(`.github/workflows/release.yml`):

| Label | Effect on merge |
|---|---|
| `release:major` | `0.2.0` → `1.0.0` |
| `release:minor` | `0.2.0` → `0.3.0` |
| `release:patch` | `0.2.0` → `0.2.1` |
| `skip-release` | no tag, no release |

- Every pull request into `main` needs exactly one of these labels;
  `release-check.yml` fails otherwise. Dependabot PRs are exempt and never
  release.
- The version is computed from the latest tag. Tags carry no `v` prefix
  (`0.2.0`). The job fails if the tag already exists.
- The workflow creates an annotated tag as `github-actions[bot]` and a GitHub
  release whose notes start with the matching `CHANGELOG.md` section
  (`## <version>`, or `## Unreleased` when there is none), followed by the notes
  GitHub generates since the previous tag.
- To release `main` by hand, run the `release` workflow from the Actions tab and
  pick the bump.

Before merging a release, move `## Unreleased` in `CHANGELOG.md` to
`## <version> - <date>`.

**Happy coding**!
