#!/usr/bin/env bash
# Install italia/spid-laravel from the offline-agency fork in CI.
#
# Every tagged italia/spid-laravel pins onelogin/php-saml 4.1.0, which carries
# a critical advisory (CVE-2025-66475) that Composer refuses to install, and
# allows Laravel up to 12. The fork's feat/laravel-13 branch requires
# php-saml ^4.3.1, ships the SPID patch ported to it, and allows Laravel 13:
# https://github.com/italia/spid-laravel/pull/131
#
# Run from the package root before `composer update`. Idempotent. Delete this
# script, and its calls in the workflows, once upstream tags a fixed release.
set -euo pipefail

composer config repositories.spid-laravel vcs https://github.com/offline-agency/spid-laravel
composer config allow-plugins.cweagans/composer-patches true
# composer-patches only applies patches declared by dependencies when the
# root package opts in; without this php-saml would run unpatched.
composer config extra.enable-patching true --json
composer require "italia/spid-laravel:dev-feat/laravel-13 as 2.1.0-beta" --no-update --no-interaction
