# Sample Images

We use this directory to create different (sample) versions of the images that will be used on our site.

In general, we need to create derivatives for:
1x1
4x3
16x9
35x10

These are the default image styles we have within Drupal, based on various standard photo/screen sizes.

To do this, create a directory with the image name, followed by an image of each size using the same name as the directory (it's easier for copy/pasting later on), followed by the image dimensions. So, if you have an image called "Annertech Team", then the directory structure here would look like this:

```
- responsive-images
- - responsive-images/annertech-team
- - responsive-images/annertech-team/annertech-team-original-image.jpg
- - responsive-images/annertech-team/annertech-team-1x1.jpg
- - responsive-images/annertech-team/annertech-team-4x3.jpg
- - responsive-images/annertech-team/annertech-team-16x9.jpg
- - responsive-images/annertech-team/annertech-team-35x10.jpg
```

We can then use these wherever we use responsive images, but also we can just select the specific image size we want to use anywhere we are not using a responsive image, such as in a teaser view mode.