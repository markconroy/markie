# Publishing a Patch Release
This document outlines the steps the Publishing Manager needs to take to publish a new patch release of the AI module. When publishing a patch release for the AI module, follow these steps to ensure a smooth and efficient process.

## Create the Tag
1. [Tag the patch release](tagging_a_release.md) and push it up to the remote repository
2. Visit https://git.drupalcode.org/project/ai/-/tags and verify that the tag corresponds to the correct version number and includes all intended changes.
3. Check the diff between the last release and the new tag to ensure it matches the intended changes.

## Confirm Fixed Issues
1. Review the list of issues and merge requests that were intended to be included in the patch release.
2. Try out and verify that the issues have been resolved in the codebase.
3. A full test of the module is not required for patch releases, but if you have time it is recommended to do a quick smoke test of the main functionality.

## Publish a Release
1. (If scheduled) The time to publish the release should be between 16:00 and 20:00 UTC on a Wednesday. Outside of this time frame, only critical patch releases should be published (see below).
2. (If critical) Publish the release as soon as possible after verifying the tag and fixed issues.
3. Go to the AI module project page on Drupal.org: https://www.drupal.org/project/ai
4. Click on the "Add new release" link on the bottom of the page.
5. Select the newly created tag from the "Version" dropdown menu and click next.
6. Copy the [CHANGELOG.md](../../changelog.md) release notes into the "Release notes" field.
7. We usually do not fill out the "Short description" field for patch releases.
8. Check the "Bug fixes" or "New features" boxes as appropriate.
9. Click Preview to verify the HTML formatting looks good.
10. Click Save to publish the release.

## Communicate the Release
1. Once the release is published, communicate the release to the relevant stakeholders and on the #ai-contrib channel on Drupal Slack.
2. Include the version number, a brief summary of the changes, and a link to the release notes on the Drupal.org project page.
