# Entity Usage

This module provides a tool to track entity relationships in Drupal.

Out of the box, you will be able to view reports regarding entity usage and
relationships between Nodes, Taxonomy Terms, Blocks, and many others, including
custom and contrib entities. No need to create a View showing reverse
relationships on nodes, etc. -- this module will do it for you.

Configure the module to show a Local Task ("Usage" tab), then set up tracked
Source and Target entities, and you're done!

The module can also be configured to show a warning message when editing or
deleting entities with active references, i.e. they're being used somewhere.

# Supported Relationships

Currently the following tracking methods are supported:

- Entities related through entity_reference fields
- Entities related through link fields
- Standard HTML links inside text fields (when pointing to an entity URL).
- Entities embedded into text fields using the [Entity Embed](https://www.drupal.org/project/entity_embed) or [LinkIt](https://www.drupal.org/project/linkit) modules
- Entities related through fields provided by the [Block Field](https://drupal.org/project/block_field),
[Entity Reference Revisions](https://www.drupal.org/project/entity_reference_revisions), and [Dynamic Entity Reference](https://www.drupal.org/project/dynamic_entity_reference) modules
- Entities related through Layout Builder. Supported methods: Core's inline
(non-reusable) content blocks, and entities selected through the contributed
[Entity Browser Block](https://www.drupal.org/project/entity_browser_block) module.

# How it works

A relationship between two entities is considered so when a *source* entity
points to a *target* entity through one of the methods described above.

You can configure the entity types to be tracked as a source, and what
entity types should be tracked as target. By default all content entities
(except files and users) are tracked as source.

You can also configure what plugins (from the tracking methods indicated above)
should be active. By default all plugins are active.

When a *source* entity is created / updated / deleted, all active plugins are
called to register potential relationships.

Content entities can have a **local task link (Tab)** on its canonical page linking
to a **"Usage"** information page, where users can see where that entity is being
used. You can configure which entity types should have a local task displaying
usage information. By default no local tasks are shown.

In order to configure these and other settings, navigate to "Configuration ->
Content Authoring -> Entity Usage Settings" (or go to the URL
/admin/config/entity-usage/settings).

You can also display usage information in Views, or retrieve them in custom
code. Please refer to the [documentation](https://www.drupal.org/docs/8/modules/entity-usage) to learn more.

# Batch update

The module provides a tool to erase and regenerate all tracked information about
usage of entities on your site.

Go to the URL /admin/config/entity-usage/batch-update in order to start the
batch operation.

# Project page and Online handbook

More information can be found on the project page:
  https://www.drupal.org/project/entity_usage
and on the online handbook:
  https://www.drupal.org/docs/8/modules/entity-usage
