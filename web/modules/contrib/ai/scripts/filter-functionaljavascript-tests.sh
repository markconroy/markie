#!/bin/bash
#
# Filter FunctionalJavascript test files based on branch context.
#
# - Tags: keep all FJS tests (full test run)
# - Branches with an issue number (4 or more digits) in the name: keep only
#   FJS tests tagged with @group {issue_number} or #[Group('{issue_number}')]
# - All other branches, including main branches (e.g. 1.x, 2.x, 2.0.x):
#   remove ALL FJS tests
# - No CI tag or branch variable, for example when run locally: change nothing
#
# This works around a run-tests.sh limitation where --directory (used by the
# Drupal CI template for scoped discovery) ignores @group filtering via
# positional arguments. By removing test files before discovery, we achieve
# effective group filtering.
#
# Outputs "HAS_FJS=1" or "HAS_FJS=0" to indicate whether any FJS tests remain,
# which can be used to conditionally install ffmpeg.
#
# Expected environment variables (set by GitLab CI / Drupal CI template):
#   CI_COMMIT_TAG
#   CI_MERGE_REQUEST_SOURCE_BRANCH_NAME
#   CI_COMMIT_BRANCH
#   CI_PROJECT_DIR            - build directory (repo checkout)
#   CI_PROJECT_NAME           - project name (e.g. "ai")
#   _WEB_ROOT                 - Drupal web root (default: "web")
#   DRUPAL_PROJECT_FOLDER     - absolute path to the installed module (may not
#                               be set during before_script)

set -eo pipefail

# The test runner scans the *installed* module path (not the source checkout).
# Construct the path from CI variables if DRUPAL_PROJECT_FOLDER is not set.
if [ -n "${DRUPAL_PROJECT_FOLDER:-}" ] && [ -d "$DRUPAL_PROJECT_FOLDER" ]; then
  SEARCH_DIR="$DRUPAL_PROJECT_FOLDER"
else
  SEARCH_DIR="${CI_PROJECT_DIR:-.}/${_WEB_ROOT:-web}/modules/custom/${CI_PROJECT_NAME:-ai}"
fi

if [ ! -d "$SEARCH_DIR" ]; then
  echo "Warning: module directory not found at $SEARCH_DIR, falling back to CI_PROJECT_DIR."
  SEARCH_DIR="${CI_PROJECT_DIR:-.}"
fi

echo "Searching for FunctionalJavascript tests in: $SEARCH_DIR"

# Lists the FJS test files; extra find arguments are appended. Errors from
# find, such as unreadable paths, do not stop the script.
find_fjs_tests() {
  find -L "$SEARCH_DIR" -type f \( -path '*/FunctionalJavascript/*' -o -path '*/FunctionalJavascriptTests/*' \) -name '*Test.php' -not -path '*/node_modules/*' -not -path '*/vendor/*' -not -path '*/bower_components/*' "$@" 2>/dev/null || true
}

# Whether a test file is tagged with the issue number, as an annotation or as
# a PHPUnit Group attribute. The attribute may be quoted or not, use a named
# argument, or share #[...] with other attributes.
has_issue_group() {
  grep -Eq "@group[[:space:]]+${ISSUE_NUMBER}([^0-9]|\$)|(^|[^A-Za-z0-9_])Group\([[:space:]]*(name:[[:space:]]*)?['\"]?${ISSUE_NUMBER}['\"]?[[:space:]]*\)" "$1"
}

ISSUE_NUMBER=""
FJS_BRANCH="${CI_MERGE_REQUEST_SOURCE_BRANCH_NAME:-${CI_COMMIT_BRANCH:-}}"

if [ -n "${CI_COMMIT_TAG:-}" ]; then
  echo "Tag commit: keeping all FunctionalJavascript tests."
  REMOVE_FJS="none"
elif [ -z "$FJS_BRANCH" ]; then
  # GitLab CI always sets one of the variables, so this is not a CI run.
  echo "No CI tag or branch variable set: leaving FunctionalJavascript tests unchanged."
  REMOVE_FJS="none"
else
  echo "Checking branch: $FJS_BRANCH"
  ISSUE_NUMBER=$(echo "$FJS_BRANCH" | grep -oE '[0-9]{4,}' | head -1 || true)
  if [ -n "$ISSUE_NUMBER" ]; then
    REMOVE_FJS="untagged"
  else
    REMOVE_FJS="all"
  fi
fi

echo "REMOVE_FJS=$REMOVE_FJS, ISSUE_NUMBER=$ISSUE_NUMBER"
FJS_FILES=$(find_fjs_tests)

if [ "$REMOVE_FJS" = "all" ]; then
  echo "No issue number in the branch name: removing all FunctionalJavascript tests."
  if [ -n "$FJS_FILES" ]; then
    echo "$FJS_FILES" | xargs -r rm -f
  fi
elif [ "$REMOVE_FJS" = "untagged" ]; then
  echo "Issue branch: keeping only FunctionalJavascript tests with group $ISSUE_NUMBER."
  if [ -n "$FJS_FILES" ]; then
    echo "$FJS_FILES" | while IFS= read -r file; do
      [ -z "$file" ] && continue
      if ! has_issue_group "$file"; then
        echo "  Removing: $file"
        rm -f "$file"
      fi
    done
  fi
fi

# Check if any FJS tests remain and output result. Only find's output is used,
# not the exit status of a pipe, as this script runs with pipefail.
if [ -n "$(find_fjs_tests -print -quit)" ]; then
  echo "HAS_FJS=1"
  export HAS_FJS=1
else
  echo "HAS_FJS=0"
  export HAS_FJS=0
fi
