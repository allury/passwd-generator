#!/usr/bin/env bash

set -euo pipefail

repo_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$repo_dir"

version="$(sed -nE 's/^[[:space:]]*\*[[:space:]]*@version[[:space:]]+([0-9]+\.[0-9]+\.[0-9]+)[[:space:]]*$/\1/p' passwd.php)"
if [[ ! "$version" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
    echo "Unable to resolve a single semantic version from passwd.php." >&2
    exit 1
fi

notes_file=".github/releases/v${version}.md"
if [[ ! -s "$notes_file" ]]; then
    echo "Release notes are missing or empty: $notes_file" >&2
    exit 1
fi

if ! grep -Fqx '## 概要' "$notes_file"; then
    echo "Release notes are missing the overview section: $notes_file" >&2
    exit 1
fi

if ! grep -Fqx '## 验证' "$notes_file"; then
    echo "Release notes are missing the verification section: $notes_file" >&2
    exit 1
fi

for historical_notes in .github/releases/v*.md; do
    first_content_line="$(sed -n '/[^[:space:]]/{p;q;}' "$historical_notes")"
    if [[ "$first_content_line" == '# '* ]]; then
        echo "Release notes must not duplicate the GitHub Release title: $historical_notes" >&2
        exit 1
    fi
done

echo "Release metadata tests passed for v${version}."
