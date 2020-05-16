---
title: Responsive Image (resolution switching)
---

This is a very simple implementation of a responsive image pattern.

It allows us to see that different image dimensions can be used for different screen sizes.

To include this pattern in another one, simple add a variation of the following in the YML file:

```yaml
hero_image:
  join():
    - include():
        pattern: 'basic-elements-responsive-image'
        with:
          image_src_sets:
            join():
              - '../../../../components/images/sample/responsive-images/annertech-team/annertech-team-1x1.jpg 639w, '
              - '../../../../components/images/sample/responsive-images/annertech-team/annertech-team-4x3.jpg 1023w, '
              - '../../../../components/images/sample/responsive-images/annertech-team/annertech-team-16x9.jpg 1440w'
          image_src: '../../../../components/images/sample/responsive-images/annertech-team/annertech-team-4x3.jpg'
          image_alt: 'Image description'
```

We need the "image_src" variable to use as the default image src attribute, for browsers that don't support responsive images such as IE11.
