#!/usr/bin/env bash
# Print the version after <latest-tag> for a <major|minor|patch> bump.
#
# Tags carry no "v" prefix (0.1.8); one is tolerated when parsing but never
# emitted. An empty latest tag starts from 0.0.0. Exits 2 on bad input.
#
# Usage: next-version.sh <latest-tag> <major|minor|patch>
set -euo pipefail

latest="${1:-}"
bump="${2:-}"
version="${latest#v}"
version="${version:-0.0.0}"

if ! [[ "$version" =~ ^([0-9]+)\.([0-9]+)\.([0-9]+)$ ]]; then
    echo "next-version: cannot parse tag [$latest]" >&2
    exit 2
fi

major="${BASH_REMATCH[1]}"
minor="${BASH_REMATCH[2]}"
patch="${BASH_REMATCH[3]}"

case "$bump" in
    major) echo "$((major + 1)).0.0" ;;
    minor) echo "${major}.$((minor + 1)).0" ;;
    patch) echo "${major}.${minor}.$((patch + 1))" ;;
    *)
        echo "next-version: unknown bump [$bump]" >&2
        exit 2
        ;;
esac
