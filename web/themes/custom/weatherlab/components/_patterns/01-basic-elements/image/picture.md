---
title: Picture (art-directed responsive image)
---

Embed a simple art-directed responsive image pattern.

It allows us to see that different image dimensions and aspect ratios can be used for different screen sizes and orientation, basically any media query.

To include this pattern in another one, simple add a variation of the following in the YML file:

```yaml
hero_image:
  join():
    - include():
        pattern: 'basic-elements-picture'
        with:
          picture_sources:
            - media: '(max-width: 599px)'
              srcset: '../../../../components/images/sample/responsive-images/annertech-team/annertech-team-1x1.jpg'
            - media: '(min-width: 600px) and (max-width: 899px)'
              srcset: '../../../../components/images/sample/responsive-images/annertech-team/annertech-team-4x3.jpg'
            - media: '(min-width: 900px) or (orientation: landspace)'
              srcset: '../../../../components/images/sample/responsive-images/annertech-team/annertech-team-16x9.jpg'
          picture_img_src: '../../../../components/images/sample/responsive-images/annertech-team/annertech-team-16x9.jpg'
          picture_img_alt: 'Example Picture alt tag'
```

We need the "picture_image_src" variable to use as the default image src attribute, for browsers that don't support responsive images such as IE11.
