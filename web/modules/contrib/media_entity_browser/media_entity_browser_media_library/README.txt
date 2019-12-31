# Media Entity Browser (Media Library)
===

Provides an additional Entity Browser that leverages the
work done in the new Core Media Library. It is recommended to update existing
Entity Browsers to use the Media Entity Browser Media Library view.

As Media Library is still experimental this is an optional setting for users.

The UX for this view matches the Media Library UX as closely as possible
and is designed to fill the gap in WYSIWYG support until core catches up. Follow
https://www.drupal.org/project/drupal/issues/2801307 for core progress.

Once core provides support for WYSIWYG embedding this module will be deprecated.

## Requirements

- Inline Entity Form
- Entity Browser
- Entity Embed
- Media
- Media Library

## Installation

- Download Inline Entity Form from Drupal.org
- Download Entity Embed from Drupal.org
- Download Entity Browser from Drupal.org
- Download Media Entity Browser from Drupal.org
- Enable core Media
- Enable core Media Library (optional)
- Enable Media Entity Browser Media Library

## Usage

This module is largely a set of configuration files to provide a basic WYSIWYG
Entity Browser for Media. As such the documentation for Entity Browser and Media
in general will explain how to customise the installed Media Entity Browsers.

https://drupal-media.gitbooks.io/drupal8-guide/content/modules/entity_browser/intro.html

Once installed, changes to the configuration are managed outside of this module.

### Configuration

On installation the Browser isn't visible. You need to add it to an Entity
Embed button for use though the WYSIWYG.

- For WYSIWYG Entity Embedding use the iFrame Browser
(/admin/config/content/entity_browser/media_entity_browser).

Media Entity Reference Fields should use core Media Library directly. From the
Manage Form Display tab of your fielded entity select the "Media Library"
widget instead of the "Entity Browser" widget. The Media Library widget provides
a much nicer UX for fields.
