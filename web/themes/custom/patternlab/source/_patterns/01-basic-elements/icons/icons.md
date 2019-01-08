---
title: Icons
---
### How to use icons

The icon name is next to each icon and is represented by `ICON-NAME` below.

**In HTML**

```html
<i class="icon--ICON-NAME"></i>
```

**In Sass**

Use the mixin `icon` with the argument of the icon name like this: `@include icon(facebook);`. So

```scss
.class {
  @include icon(ICON-NAME);
}
```

**Direct Embed via SVG**
Add the icon as an SVG to the ./svg directory here (we already have _every_ font-awesome icon including in /svg/fa), then use this code in your template:
```
{% include '@basic-elements/icons/_svg.twig'
  with {
    svgpath : '@basic-elements/icons/svg/fa/drupal.svg'
  }
%}
```
**Adding and generating icons**

Add SVG files `images/icons/src/` to automatically add to this list. Use the Illustrator template at `images/icons/templates/` if you have any problems.
