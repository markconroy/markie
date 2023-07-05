Devel Entity Updates
--------------------

The goal of this module is to have a quick way to **apply schema updates while
developing new entity types** and ending up incrementally
adding/removing/changing entity type/field definitions.

When these changes are part of an official release (and not in the scope of a
code development session), they should absolutely rely on DB update functions,
as explained in the CR above, because that's the only way the entity schema
update process can be predictable and reliable.

For this reason, this module depends on [Devel](https://www.drupal.org/project/devel)
and is not meant to be enabled in production environments or relied upon in
deployment workflows, see https://www.drupal.org/node/3082442 for more details.


**Usage**

This command is a drop-in replacement of the legacy `drush entup` or
`drush entity-updates` core commands, however `drush updb --entity-updates` is
no longer supported. Just run the `drush entup` command as before.

Do not use this to fix the `Mismatched entity and/or field definitions` error:
again this is not meant to fix production sites.

If you encounter that error you should identify which module defines the
problematic entity type or field type and open a bug report or support request
in its queue.

This version is compatible with Drupal 9+ and Drush 11+
