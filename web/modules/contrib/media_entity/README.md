# Upgrade path from Media Entity to Media (core)

This version of Media Entity is intended **only** to be used as a bridge to
move to the new "Media" module included in Drupal core (>= 8.6.0). While the
storage of media entities is the same, some aspects of the API have changed.
Because of that, if you have an existing site using Media Entity 1.x, you need
to follow the upgrade path indicated below in order to move to Media in core.

## Upgrade instructions
1. Backup your code and your database
2. Test that you can successfully roll-back from the backup!
3. Upgrade the codebase with:
  - Core: >= **8.6.x**
  - Media Entity: **8.x-2.x**
  - All media entity providers: **8.x-2.x** (or use patches from #2860796: Plan for
  contributed modules with Media Entity API in core). Note that the modules
  Media Entity Image and Media Entity Document, if present, don't need to be
  updated. Their configs will be updated by the main Media Entity updates.
  - All modules that depend on or interact with Media Entity: **8.x-2.x**
  - The new contrib module **Media Entity Actions**: **8.x-1.x**
  - Note: If your site uses media entities with the "Generic" provider, make
  sure you download to your codebase the **Media Entity Generic** module as
  well.
4. Clear your caches.
5. (Optional) Check that all requirements for the upgrade are met with
  `drush mecu`.
  **IMPORTANT**: Please note that if you are running DB updates with Drush 9
  (between 9.0.0-alpha1 and 9.0.0-beta7), you are **strongly** encouraged to
  use this command prior to running the updates. Drush 9 will not run the
  requirements validation and will try to run the updates even if your site has
  some of the requisites misconfigured. Executing the updates in that scenario
  will likely break your site. This was fixed in Drush 9.0.0-beta8. Drush 8
  users don't need to worry about this.
6. Run the DB Updates, either by visiting `/update.php`, or using `drush updb`.
7. Double-check **Media Entity** is uninstalled, and remove it from the
  codebase. Remove also **Media Entity Image** / **Document**, if present.
8. Run your automated tests, if any, or manually verify that all media-related
  functionality on your site works as expected.

**Known issues concerning the upgrade path:**
- If your existing site relies on the EXIF image metadata handling, please check
 https://drupal.org/node/2927481 before proceeding with the upgrade.
- Entity Browser has a 2.x branch that has new features for media in core, for
 example the widget that was formerly present in the "Media Entity Image"
 module. However, if you intend to upgrade Entity Browser to the 2.x branch, you
 should do that only after performing the main Media Entity upgrades. There is
 currently a bug preventing the Media Entity upgrade if the Entity Browser 2.x
 is present in the codebase.
