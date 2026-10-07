#!/bin/bash
# Ensures FunctionalJavascript tests follow AI module conventions.
#
# Only the AI module's own tests are checked. The GitLab/Drupal CI template
# checks out a full Drupal (core + contrib) under the web root, so that tree is
# excluded to avoid flagging core's FunctionalJavascript tests.

SEARCH_DIR="${CI_PROJECT_DIR:-.}"
WEB_ROOT="${_WEB_ROOT:-web}"
ERROR=0

echo "Checking FunctionalJavascript test conventions in AI module ($SEARCH_DIR, excluding /$WEB_ROOT)..."

# Find all FJS test files belonging to the AI module. The scaffolded Drupal web
# root (core + contrib), vendor, node_modules and bower_components are excluded.
FJS_FILES=$(find -L "$SEARCH_DIR" -type f \( -path '*/FunctionalJavascript/*' -o -path '*/FunctionalJavascriptTests/*' \) -name '*Test.php' -not -path "$SEARCH_DIR/$WEB_ROOT/*" -not -path '*/node_modules/*' -not -path '*/vendor/*' -not -path '*/bower_components/*' 2>/dev/null)

if [ -z "$FJS_FILES" ]; then
  echo "No FunctionalJavascript tests found."
  exit 0
fi

for file in $FJS_FILES; do
  # Skip files that declare no test class. Emptied-out leftovers are tracked in
  # the repository from time to time and there is nothing to run in them.
  if ! grep -Eq '^\s*(abstract\s+|final\s+)?class\s+' "$file"; then
    continue
  fi
  # Check for @group {number}
  if ! grep -Eq '@group\s+[0-9]+' "$file"; then
    echo "ERROR: Missing issue number '@group [0-9]' annotation in $file"
    ERROR=1
  fi
  # The video recording, screenshot and helper methods only exist on the AI
  # module's base class, so extending WebDriverTestBase directly makes the
  # properties below no-ops.
  if grep -Eq 'extends\s+WebDriverTestBase' "$file"; then
    echo "ERROR: Test extends WebDriverTestBase instead of BaseClassFunctionalJavascriptTests in $file"
    ERROR=1
  fi
  # The property checks only apply to classes extending the base class
  # directly. A test extending another test class inherits its properties, and
  # that parent file is checked on its own (e.g. TagifySelectTaxonomyTest
  # inherits $videoRecording and $screenshotModuleName from TagifyTaxonomyTest).
  if ! grep -Eq 'extends\s+(BaseClassFunctionalJavascriptTests|WebDriverTestBase)\b' "$file"; then
    continue
  fi
  # Check for $videoRecording = TRUE
  if ! grep -Eq '\$videoRecording\s*=\s*(TRUE|true|1)' "$file"; then
    echo "ERROR: Missing 'protected bool \$videoRecording = TRUE;' in $file"
    ERROR=1
  fi
  # Sub-module tests must name their module so videos and screenshots are not
  # written into the core AI module's artifact directory. Tests belonging to
  # the AI module itself rely on the base class default of 'ai'.
  #
  # Match against the path relative to $SEARCH_DIR rather than $file directly:
  # when $SEARCH_DIR is the ai repo checkout root (as in CI, and as intended
  # when running this script locally from the module root), $relative omits
  # any outer path segments, so "modules/*" matches only genuine AI submodule
  # paths and no longer false-positives on core AI module tests just because
  # SEARCH_DIR's own path happens to contain "modules/contrib/ai".
  #
  # This assumes $SEARCH_DIR is the ai module's own checkout root. Pointing
  # $SEARCH_DIR at something else (e.g. a full Drupal install root, one level
  # above the module) is not supported: the -not -path "$SEARCH_DIR/$WEB_ROOT/*"
  # exclusion above already discards every FJS test path in that layout (since
  # the module itself lives under $WEB_ROOT), so the script reports "No
  # FunctionalJavascript tests found" rather than checking anything at all.
  # That is a pre-existing limitation of $SEARCH_DIR/$WEB_ROOT handling, not
  # introduced by this fix.
  relative="${file#${SEARCH_DIR}/}"
  case "$relative" in
    modules/*)
      if ! grep -Eq '\$screenshotModuleName\s*=' "$file"; then
        echo "ERROR: Missing 'protected string \$screenshotModuleName = ...;' in $file"
        ERROR=1
      fi
      ;;
  esac
done

if [ $ERROR -eq 1 ]; then
  echo "FunctionalJavascript test conventions validation failed."
  exit 1
fi

echo "FunctionalJavascript test conventions validation passed."
exit 0
