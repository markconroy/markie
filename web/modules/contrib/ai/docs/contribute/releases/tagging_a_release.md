# Tagging a Release
This document outlines the steps the Preparation Manager needs to take to tag a new release of the AI module. This process is applicable for all types of releases, including patch, minor, major, and security releases. This document also explains how to revert a release.

All releases should be ready by 11:00 UTC on the release day to allow time for verification and publishing. For critical patch releases that happen outside of the normal schedule, the tagging should be done as soon as possible and communicated to the Publishing Manager.

## Preparation
1. The first step is to verify that all issues and merge requests intended for the release have been merged into the main branch before the cutoff date. If there are not merged issues that need to be included in the release or if the updates are pure documentation updates, no new release is needed.
2. Next, check the last release version on the [AI module project page](https://www.drupal.org/project/ai) to determine the new version number based on the type of release (patch, minor, major). Security releases should follow the same versioning as patch releases.
3. Make sure to pull the right main branch locally - if you need to update 1.1.x, make sure you are on that branch.

## Update the CHANGELOG
1. [Create release notes](release_notes.md) in HTML format
2. Copy and paste the new release notes into [CHANGELOG.md](../../changelog.md) with the new version entry
3. Push the new [CHANGELOG.md](../../changelog.md) changes up to the target branch

## Steps to Tag a Release
1. Create a new tag for the release using Git. The tag should follow the format `x.y.z`, where `x` is the major version, `y` is the minor version, and `z` is the patch version. For example, for a patch release of version 1.1.7, the new tag would be `1.1.8`. Use the following command to create the tag `git tag -a 1.1.8`.
2. A text editor will open in the terminal. Paste the release notes into the editor, save and close it.
3. Push the tag to the remote repository using the command `git push --tags`.
4. Verify under https://git.drupalcode.org/project/ai/-/tags that the tag has been created successfully.
5. Visit https://git.drupalcode.org/project/ai/-/compare?from=2.0.x&to=2.0.x to verify that the same diff is shown between the last release and the new tag as it is between the main branch and the last release.
6. Notify the Publishing Manager that the tag has been created and is ready for verification and publishing.

## Security Releases
For security releases, it is crucial to ensure that the release is not made public until the Drupal Security Team has reviewed and approved the release. Coordinate with the Drupal Security Team to determine the appropriate timing for pushing the security release, which is usually Wednesdays before 16:00 UTC.

The issues included in the security release are never public issues, but patches that need to be applied on the day of the release. The commit messages for these patches should be ambiguous and not reveal any security vulnerabilities. Up until the release day, they should only ever just be stored as patch files in the security issue on Drupal.org.

## Reverting a Release
While it's generally recommended to fix-forward with a new release, if you need to revert a release that has been tagged but not yet published, follow these steps:
1. Identify the tag that needs to be reverted
2. Use the command `git push --delete origin <tag_name>` to delete the tag from the remote repository
3. Use the command `git tag -d <tag_name>` to delete the tag from the local repository
4. Notify the Publishing Manager that the release has been reverted
