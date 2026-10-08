#!/usr/bin/env bash
# Tests next-version.sh and release-notes.sh.
set -uo pipefail

DIR="$(cd "$(dirname "$0")/.." && pwd)"
failures=0

check() { # check <description> <expected> <actual>
    if [ "$2" = "$3" ]; then
        echo "ok   $1"
    else
        echo "FAIL $1: expected [$2], got [$3]"
        failures=$((failures + 1))
    fi
}

check_exit() { # check_exit <description> <expected-code> <command...>
    local desc=$1 expected=$2
    shift 2
    "$@" >/dev/null 2>&1
    check "$desc" "$expected" "$?"
}

nv() { bash "$DIR/next-version.sh" "$@"; }

check "minor from 0.1.8" "0.2.0" "$(nv 0.1.8 minor)"
check "patch from 0.1.8" "0.1.9" "$(nv 0.1.8 patch)"
check "major from 0.1.8" "1.0.0" "$(nv 0.1.8 major)"
check "v prefix is parsed, never emitted" "1.2.4" "$(nv v1.2.3 patch)"
check "first patch" "0.0.1" "$(nv '' patch)"
check "first minor" "0.1.0" "$(nv '' minor)"
check "first major" "1.0.0" "$(nv '' major)"
check_exit "unknown bump" 2 bash "$DIR/next-version.sh" 0.1.8 huge
check_exit "malformed tag" 2 bash "$DIR/next-version.sh" 0.1 patch

TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

cat > "$TMP/released.md" <<'MD'
# Changelog

## 0.2.0 - 2026-10-08

### Added

- Thing one.

## 0.1.8 - 2026-03-01

- Old.
MD

cat > "$TMP/unreleased.md" <<'MD'
# Changelog

## Unreleased

### Fixed

- Bug.

## 0.1.8 - 2026-03-01

- Old.
MD

cat > "$TMP/prefix.md" <<'MD'
## 0.2.0-beta - 2026-09-01

- Beta notes.
MD

notes() { bash "$DIR/release-notes.sh" "$@"; }

check "section for the version" $'### Added\n\n- Thing one.' "$(notes "$TMP/released.md" 0.2.0)"
check "falls back to Unreleased" $'### Fixed\n\n- Bug.' "$(notes "$TMP/unreleased.md" 0.2.0)"
check "no partial version match" "" "$(notes "$TMP/prefix.md" 0.2.0)"
check "missing changelog" "" "$(notes "$TMP/missing.md" 0.2.0)"

if [ "$failures" -gt 0 ]; then
    echo "$failures failure(s)"
    exit 1
fi
echo "PASS release-scripts"
