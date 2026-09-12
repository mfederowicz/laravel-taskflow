#!/usr/bin/env bash

set -euo pipefail

DEFAULT_BRANCH="main"
REMOTE_DEFAULT="origin"
RELEASES_DIR="releases"

usage() {
    echo "Usage: ./bin/release.sh <YYYY-MM-DD>"
    echo "       ./bin/release.sh --notes <YYYY-MM-DD>"
    echo "       ./bin/release.sh --backfill"
    echo
    echo "Create a CalVer release tag (YYYY-MM-DD) pointing at the last commit of"
    echo "that day on $DEFAULT_BRANCH, generate release notes listing the merged"
    echo "PRs for that day (git-only), and print the git push + curl commands to"
    echo "publish it as a GitHub release. No gh CLI or other tools required."
    echo
    echo "  <YYYY-MM-DD>          tag a day, write notes, print push/create commands"
    echo "  --notes <YYYY-MM-DD>  regenerate notes + API payload for an existing day"
    echo "  --backfill            tag every day that has commits (tags only)"
    exit 1
}

die() {
    echo "Error: $*" >&2
    exit 1
}

is_date() {
    [[ "$1" =~ ^[0-9]{4}-[0-9]{2}-[0-9]{2}$ ]]
}

if git remote | grep -qx "$REMOTE_DEFAULT"; then
    REMOTE="$REMOTE_DEFAULT"
else
    REMOTE="$(git remote | head -1)"
    [[ -n "$REMOTE" ]] || die "no git remote configured"
fi

SLUG="$(
    git remote get-url "$REMOTE" |
        sed -E -e 's#^gh:##' \
               -e 's#^(https?://[^/]+/|git@[^:]+:)##' \
               -e 's#\.git$##'
)"
if [[ "$SLUG" != */* ]]; then
    SLUG="owner/repo"
    echo "Warning: could not parse remote '$REMOTE' as owner/repo; PR links will use owner/repo." >&2
fi

# sha_of_day <YYYY-MM-DD> -> prints the last commit of that day on DEFAULT_BRANCH
sha_of_day() {
    local date="$1"
    git rev-list -1 --before="$date 23:59:59" "$DEFAULT_BRANCH"
}

# prev_tag_of <sha> [<exclude>] -> prints the newest release tag reachable from
# <sha> (optionally excluding <exclude>), or nothing
prev_tag_of() {
    local sha="$1" exclude="${2:-}"
    if [[ -n "$exclude" ]]; then
        git describe --tags --abbrev=0 --exclude="$exclude" "$sha" 2>/dev/null || true
    else
        git describe --tags --abbrev=0 "$sha" 2>/dev/null || true
    fi
}

# pr_numbers <range...> -> deduped merge-PR numbers (merge-commit order) in the range
pr_numbers() {
    git log --first-parent --merges --format=%s "$@" |
        grep -oE 'Merge pull request #[0-9]+' |
        grep -oE '[0-9]+$' |
        awk '!seen[$0]++'
}

# write_notes <YYYY-MM-DD> <sha> [<tag_before>]
write_notes() {
    local date="$1" sha="$2" tag_before="${3:-}"
    local range notes payload body_json n pr

    if [[ -n "$tag_before" ]]; then
        range="$tag_before..$sha"
    else
        range="$sha"
    fi

    mkdir -p "$RELEASES_DIR"
    notes="$RELEASES_DIR/$date.md"
    payload="$RELEASES_DIR/$date.json"

    {
        echo "# Release $date"
        echo
        if [[ -n "$tag_before" ]]; then
            echo "Pull requests between \`$tag_before\` and \`$date\` (\`$range\`):"
        else
            echo "Initial release — pull requests up to \`$date\`:"
        fi
        echo
        echo "## Merged pull requests"
        echo
        n=0
        while IFS= read -r pr; do
            [[ -n "$pr" ]] || continue
            printf -- "- [PR #%s](https://github.com/%s/pull/%s)\n" "$pr" "$SLUG" "$pr"
            n=$((n + 1))
        done < <(pr_numbers "$range")
        if [[ "$n" -eq 0 ]]; then
            echo "_No pull requests in this range._"
        fi
    } > "$notes"

    body_json="$(
        sed -e 's/\\/\\\\/g' \
            -e 's/"/\\"/g' \
            -e ':a' -e 'N' -e '$!ba' -e 's/\n/\\n/g' "$notes"
    )"
    printf '{"tag_name":"%s","name":"Release %s","body":"%s"}\n' "$date" "$date" "$body_json" > "$payload"
}

# tag_day <YYYY-MM-DD> -> creates the tag if missing (backfill mode; no notes/commands)
tag_day() {
    local date="$1" sha
    if git rev-parse -q --verify "refs/tags/$date" >/dev/null; then
        echo "Tag $date already exists — skipping."
        return 0
    fi
    sha="$(sha_of_day "$date")"
    [[ -n "$sha" ]] || die "no commits on $date in $DEFAULT_BRANCH"
    git tag -a "$date" "$sha" -m "Release $date"
    echo "Tagged $date -> $sha"
}

# release_day <YYYY-MM-DD> -> tag + notes + push/create commands
release_day() {
    local date="$1" sha tag_before
    if git rev-parse -q --verify "refs/tags/$date" >/dev/null; then
        echo "Tag $date already exists — skipping. Use --notes $date to regenerate notes."
        return 0
    fi
    sha="$(sha_of_day "$date")"
    [[ -n "$sha" ]] || die "no commits on $date in $DEFAULT_BRANCH"
    tag_before="$(prev_tag_of "$sha" "$date")"
    git tag -a "$date" "$sha" -m "Release $date"

    write_notes "$date" "$sha" "$tag_before"

    echo "Tagged $date -> $sha"
    if [[ -n "$tag_before" ]]; then
        echo "Release covers PRs between $tag_before and $date."
    else
        echo "No previous tag — GitHub will treat this as the initial release."
    fi
    echo
    echo "Push the tag, then publish the release (no gh needed):"
    echo "  git push $REMOTE $date"
    echo "  curl -X POST https://api.github.com/repos/$SLUG/releases \\"
    echo "    -H \"Authorization: Bearer \$GH_TOKEN\" \\"
    echo "    -H \"Accept: application/vnd.github+json\" \\"
    echo "    --data-binary @$RELEASES_DIR/$date.json"
    echo
    echo "Notes: $RELEASES_DIR/$date.md   API payload: $RELEASES_DIR/$date.json"
    echo "Set GH_TOKEN to a token with 'repo' scope first."
    echo
}

[[ $# -ge 1 ]] || usage

case "$1" in
    --backfill)
        DAYS="$(git log --date=short --format=%cd "$DEFAULT_BRANCH" | sort -u)"
        [[ -n "$DAYS" ]] || die "no commits found in $DEFAULT_BRANCH"
        while IFS= read -r day; do
            [[ -n "$day" ]] && tag_day "$day"
        done <<< "$DAYS"
        ;;
    --notes)
        [[ $# -ge 2 ]] || usage
        is_date "$2" || die "expected a date after --notes (YYYY-MM-DD)"
        SHA="$(sha_of_day "$2")"
        [[ -n "$SHA" ]] || die "no commits on $2 in $DEFAULT_BRANCH"
        write_notes "$2" "$SHA" "$(prev_tag_of "$SHA" "$2")"
        echo "Notes: $RELEASES_DIR/$2.md   API payload: $RELEASES_DIR/$2.json"
        ;;
    *)
        is_date "$1" || die "expected a date (YYYY-MM-DD), --notes, or --backfill"
        release_day "$1"
        ;;
esac