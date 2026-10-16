#!/usr/bin/env bash
# Plan the release of <commit> for a <major|minor|patch> bump.
#
# Prints "latest=<tag>" and "next=<version>" (GITHUB_OUTPUT format). Only
# plain X.Y.Z tags count; pre-releases such as 0.2.0-rc1 are ignored. Exits 3
# when <commit> already carries a release tag, so re-running a release never
# tags the same commit twice; exits 2 on bad input.
#
# Usage: release-plan.sh <commit> <major|minor|patch>   (run inside the repo)
set -euo pipefail

commit="${1:?commit required}"
bump="${2:?bump required}"
semver='^[0-9]+\.[0-9]+\.[0-9]+$'

if ! git rev-parse -q --verify "${commit}^{commit}" >/dev/null; then
    echo "release-plan: unknown commit [$commit]" >&2
    exit 2
fi

existing=$(git tag --points-at "$commit" | grep -E "$semver" | head -n 1 || true)
if [ -n "$existing" ]; then
    echo "release-plan: $commit is already released as $existing" >&2
    exit 3
fi

latest=$(git tag --list | grep -E "$semver" | sort -t. -k1,1n -k2,2n -k3,3n | tail -n 1 || true)
next=$(bash "$(dirname "$0")/next-version.sh" "$latest" "$bump")

echo "latest=$latest"
echo "next=$next"
