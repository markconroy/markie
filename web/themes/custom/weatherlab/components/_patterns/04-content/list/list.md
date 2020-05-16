---
title: List
---

This pattern is used to create views lists.

We have a custom `css_classes` variable created to allow us to add classes to the list. If we do so, we then need to recreate these classes in the Views UI in Drupal.

An example of what this might look like in PL is: 

`css_classes: 'list--grid layout-contained`

The `rows` variable is the Drupal row formatter item in the Views UI. We default to `Unformatted Rows`