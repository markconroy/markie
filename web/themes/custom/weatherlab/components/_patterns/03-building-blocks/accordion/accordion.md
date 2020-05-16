---
title: Accordion
---

The Accordion pattern is made up of an accordion container and individual accordion items with h2 title and content.

### JS

Title is wrapped in a button with svg icon and aria-expanded attribute.
Content is hidden, except for first accordion item, and title button toggles visibility.

### Templates

- `paragraph--accordion.html.twig`
- `paragraph--accordion-item.html.twig`
- `field--paragraph--field-p-a-item.html.twig` (removes wrappers from _Accordion_ paragraph type _Accordion Item_ field)
