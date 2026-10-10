#!/usr/bin/env bash
# Print the CHANGELOG section for <version>, falling back to "Unreleased".
#
# Matches "## <version>" followed by the end of the line or a space (so
# "## 0.2.0 - 2026-10-08" matches 0.2.0 but "## 0.2.0-beta" does not) and
# prints its body up to the next "## " heading, trimmed of blank edges.
# Prints nothing when the file or both sections are missing.
#
# Usage: release-notes.sh <changelog> <version>
set -euo pipefail

changelog="${1:-CHANGELOG.md}"
version="${2:?version required}"

[ -f "$changelog" ] || exit 0

section() {
    awk -v heading="$1" '
        BEGIN { found = 0 }
        /^## / {
            if (found) exit
            title = substr($0, 4)
            if (title == heading || index(title, heading " ") == 1) { found = 1; next }
        }
        found { print }
    ' "$changelog" | sed -e '/./,$!d' | sed -e ':a' -e '/^\n*$/{$d;N;ba' -e '}'
}

notes="$(section "$version")"
if [ -z "$notes" ]; then
    notes="$(section "Unreleased")"
fi

if [ -n "$notes" ]; then
    printf '%s\n' "$notes"
fi
