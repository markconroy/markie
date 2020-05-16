---
title: Block
---

This is a base pattern that can be used for creating other templates for custom blocks.

I expect it will be used in the same manner as we currently use the `list.twig` pattern:
  - create a corresponding `block-*` directory
  - create a `block-*.twig` file that extends this block
  - create a `block-*.yml` file that sets:
  - - block title if needed
  - - block type if needed
  - - block classes if needed (add these, then, through the block interface in Drupal, same as we do views classes)
  - - block content

The most popular use for this in PL will probably be creating blocks for views lists.

### Templates

This is the pattern that is used as the base for `block.html.twig`.