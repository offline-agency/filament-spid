#!/usr/bin/env bash
# Asserts use-spid-laravel-fork.sh points a copy of composer.json at the fork.
set -euo pipefail

SCRIPT="$(cd "$(dirname "$0")/.." && pwd)/use-spid-laravel-fork.sh"
ROOT="$(cd "$(dirname "$0")/../../.." && pwd)"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
cp "$ROOT/composer.json" "$TMP/composer.json"

# Run twice: the script must be idempotent.
(cd "$TMP" && bash "$SCRIPT" && bash "$SCRIPT")

fail() { echo "FAIL: $1" >&2; exit 1; }
json() {
    php -r '$d = json_decode(file_get_contents($argv[1]), true);
        foreach (explode(".", $argv[2]) as $k) { $d = $d[$k] ?? null; }
        echo json_encode($d, JSON_UNESCAPED_SLASHES);' "$TMP/composer.json" "$1"
}

# Composer writes named repositories either as an object key or as a list
# entry carrying "name", depending on the existing shape and its version.
fork_repos=$(php -r '$d = json_decode(file_get_contents($argv[1]), true);
    echo count(array_filter($d["repositories"] ?? [], fn ($r) => ($r["type"] ?? null) === "vcs"
        && ($r["url"] ?? null) === "https://github.com/offline-agency/spid-laravel"));' "$TMP/composer.json")
[ "$fork_repos" = '1' ] || fail "expected one fork vcs repository, found $fork_repos"
[ "$(json require.italia/spid-laravel)" = '"dev-feat/laravel-13 as 2.1.0-beta"' ] || fail "require: $(json require.italia/spid-laravel)"
[ "$(json extra.enable-patching)" = 'true' ] || fail "enable-patching: $(json extra.enable-patching)"
[ "$(json config.allow-plugins.cweagans/composer-patches)" = 'true' ] || fail "allow-plugins: $(json config.allow-plugins.cweagans/composer-patches)"

echo "PASS use-spid-laravel-fork"
