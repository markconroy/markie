---
title: Icons
---
### How to use icons

**Direct Embed via SVG**
Add the icon as an SVG to the ./svg directory here (we have _every_ Font-Awesome [v4](https://fontawesome.com/v4.7.0/icons/) & [v5](https://fontawesome.com/icons?d=gallery) icon included in /svg/fa and Ionicons iOS & Material style icons in /svg/ionicons), then use this code in your template:
```
{% include '@basic-elements/icons/_svg.twig'
  with {
    svgpath : '@basic-elements/icons/svg/fa/drupal.svg'
  }
%}
```
