#!/usr/bin/env bash
# Install italia/spid-laravel from the offline-agency fork in CI.
#
# Every tagged italia/spid-laravel pins onelogin/php-saml 4.1.0, which carries
# a critical advisory (CVE-2025-66475) that Composer refuses to install, and
# allows Laravel up to 12. The fork's feat/laravel-13 branch requires
# php-saml ^4.3.1, ships the SPID patch ported to it, and allows Laravel 13.
# Upstream merged it in https://github.com/italia/spid-laravel/pull/132 but has
# not tagged it, and its master points the patch URL at a deleted branch.
#
# The fork setup goes into a copy of composer.json named by $COMPOSER (the
# workflows set COMPOSER=composer.ci.json), so the published manifest, which
# the test suite checks, stays as consumers get it. Run from the package root
# before `composer update`. Idempotent. Delete this script, and its calls in
# the workflows, once upstream tags a fixed release.
set -euo pipefail

if [ -z "${COMPOSER:-}" ] || [ "$COMPOSER" = "composer.json" ]; then
    echo "Set COMPOSER to a copy of composer.json, e.g. COMPOSER=composer.ci.json" >&2
    exit 1
fi

cp composer.json "$COMPOSER"
composer config repositories.spid-laravel vcs https://github.com/offline-agency/spid-laravel
composer config allow-plugins.cweagans/composer-patches true
# composer-patches only applies patches declared by dependencies when the
# root package opts in; without this php-saml would run unpatched.
composer config extra.enable-patching true --json
composer require "italia/spid-laravel:dev-feat/laravel-13 as 2.1.0-beta" --no-update --no-interaction
