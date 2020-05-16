---
title: Block
---

This component is intended for use in situations where a menu requires
collapsible submenu items. It's designed for accessibility, and based on
[Adrian Roselli's blog post and Codepen](https://adrianroselli.com/2019/06/link-disclosure-widget-navigation.html).

## CSS

### `_menu-block.scss`

This component is provided with an absolute minimum of styling to enable it to
be easily adapted into any list-based menu design.

The styles as they are _only_ enable the javascript-based opening and closing
of submenus:

    .js-navigation {
      // This is the main limit on html strcuture: .menu MUST come AFTER the
      // controlling button.
      [aria-expanded="false"] ~ .menu {
        display: none;
      }

      .sub-menu-item-toggle > * {
        pointer-events: none;
      }
    }

## Javascript

### `menu-items.js`

The accompanying javascript library, `menu-items.js` was created to:

- End reliance on jQuery,
- Improve accessiblity (specifically keyboard navigation and operation),
- Improve localisability for multilanguage projects,
- Make possible the use of multiple javascript-enabled menus on one page,
- Reduce the need to modify the component's _javascript_ on new projects,
- Improve file organisation and readability,
- Illustrate Drupal.behaviors best practices,

The library will not be included **or** invoked unless `_menu-block.twig`
includes a variable, `submenu`, set to `true`. This variable will include
the library, and set an attribute, `data-submenus`, on the parent menu.

Similarly, 'open' and 'close' labels on submenu buttons are provided by data
attributes in `_menu-items.twig`.

## Templates

### `_menu-block.twig`

The `_menu-block.twig` template is very little different from other menu twig
templates, and has no special requirements other than those mentioned under the
**Javascript** section, above.

It has three notable variables related to `menu-items.js`:

- `submenu`: when set to `true`, this variable includes the javascript library
  and set an attribute in the menu that will allow the javascript library to
  discover and process it.
- `content`: this should be populated by the `site-components-menu-items-submenus`
  pattern.
- `menu_open_label`: this variable populates a data attribute on the menu root
  called `data-menu-open-label`; defaults to `Open` if not set.
- `menu_close_label`: this variable populates a data attribute on the menu root
  called `data_close_label`; defaults to `Close` if not set.

### `_menu-items.twig`

As with `_menu-block.twig` this template is not unusual. It provides more
accessibility-related attributes than the previous version but nothing more.

Note that _there is one limitation to the default markup_: namely, the
css expects submenu items (`.menu`) to occur _after_ the button toggle
control (`.sub-menu-item-toggle`).

### Drupal use

#### `_menu-block.twig`

This template's use is identical to our `_menu-block-main-navigation.twig`
template's: create a template in Weatherlab that extends the Pattern-Lab
template, passing it variables as needed. See e.g. `block--system-menu-block--main.html.twig`.

#### `_menu-items.twig`

This template is already responsible for top-level menu items and submenus in
the Weatherlab theme.
