---
title: Page Title
---

This is the pattern for the page title region.

This region - in Drupal - is used to place the page title block. In effect, it's only used for views pages or other pages not created via a node full view mode (the page title for node pages is usually within the node template).

In the `.yml` file, you need to re-specify the block type as `page-title-block` to make sure we then have that class available here so the CSS carries through.