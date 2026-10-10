#!/usr/bin/env bash
# Asserts use-spid-laravel-fork.sh writes the fork setup into $COMPOSER and
# leaves the published composer.json untouched.
set -euo pipefail

SCRIPT="$(cd "$(dirname "$0")/.." && pwd)/use-spid-laravel-fork.sh"
ROOT="$(cd "$(dirname "$0")/../../.." && pwd)"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
cp "$ROOT/composer.json" "$TMP/composer.json"

fail() { echo "FAIL: $1" >&2; exit 1; }

# Run twice: the script must be idempotent.
(cd "$TMP" && COMPOSER=composer.ci.json bash "$SCRIPT" && COMPOSER=composer.ci.json bash "$SCRIPT")

cmp -s "$ROOT/composer.json" "$TMP/composer.json" || fail "composer.json was modified"
[ -f "$TMP/composer.ci.json" ] || fail "composer.ci.json was not written"

json() {
    php -r '$d = json_decode(file_get_contents($argv[1]), true);
        foreach (explode(".", $argv[2]) as $k) { $d = $d[$k] ?? null; }
        echo json_encode($d, JSON_UNESCAPED_SLASHES);' "$TMP/composer.ci.json" "$1"
}

# Composer writes named repositories either as an object key or as a list
# entry carrying "name", depending on the existing shape and its version.
fork_repos=$(php -r '$d = json_decode(file_get_contents($argv[1]), true);
    echo count(array_filter($d["repositories"] ?? [], fn ($r) => ($r["type"] ?? null) === "vcs"
        && ($r["url"] ?? null) === "https://github.com/offline-agency/spid-laravel"));' "$TMP/composer.ci.json")
[ "$fork_repos" = '1' ] || fail "expected one fork vcs repository, found $fork_repos"
[ "$(json require.italia/spid-laravel)" = '"dev-feat/laravel-13 as 2.1.0-beta"' ] || fail "require: $(json require.italia/spid-laravel)"
[ "$(json extra.enable-patching)" = 'true' ] || fail "enable-patching: $(json extra.enable-patching)"
[ "$(json config.allow-plugins.cweagans/composer-patches)" = 'true' ] || fail "allow-plugins: $(json config.allow-plugins.cweagans/composer-patches)"

# Without COMPOSER the script refuses rather than rewrite composer.json.
if (cd "$TMP" && env -u COMPOSER bash "$SCRIPT" >/dev/null 2>&1); then
    fail "ran without COMPOSER set"
fi

echo "PASS use-spid-laravel-fork"
