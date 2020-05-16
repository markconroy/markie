<?php

/**
 * @file
 * Add responsive favicons.
 *
 * Generate all the needed files through https://realfavicongenerator.net
 * Under "Favicon Generator Options" set path to
 * "web/themes/custom/weatherlab/components/images/favicons
 * and place the generated files in to components/images/favicons.
 */

$html_head = &$attachments['#attached']['html_head'];
global $base_path;
$img_path = $base_path . 'themes/custom/weatherlab/components/images/favicons/';

$html_head[] = [
  [
    '#tag' => 'meta',
    '#attributes' => [
      'name' => 'msapplication-TileColor',
      'content' => '#db3c36',
    ],
  ],
  'TileColor',
];

$html_head[] = [
  [
    '#tag' => 'meta',
    '#attributes' => [
      'name' => 'msapplication-config',
      'content' => $img_path . 'browserconfig.xml',
    ],
  ],
  'BrowserConfig',
];

$html_head[] = [
  [
    '#tag' => 'meta',
    '#attributes' => [
      'name' => 'theme-color',
      'content' => '#ffffff',
    ],
  ],
  'themecolor',
];

$html_head[] = [
  [
    '#tag' => 'link',
    '#attributes' => [
      'rel' => 'apple-touch-icon',
      'sizes' => '180x180',
      'href' => $img_path . 'apple-touch-icon.png',
    ],
  ],
  'icon180',
];

$html_head[] = [
  [
    '#tag' => 'link',
    '#attributes' => [
      'rel' => 'icon',
      'sizes' => '32x32',
      'href' => $img_path . 'favicon-32x32.png',
      'type' => 'image/png',
    ],
  ],
  'favicon32',
];

$html_head[] = [
  [
    '#tag' => 'link',
    '#attributes' => [
      'rel' => 'icon',
      'sizes' => '16x16',
      'href' => $img_path . 'favicon-16x16.png',
      'type' => 'image/png',
    ],
  ],
  'favicon16',
];

$html_head[] = [
  [
    '#tag' => 'link',
    '#attributes' => [
      'rel' => 'shortcut icon',
      'href' => $img_path . 'favicon.ico',
    ],
  ],
  'favicon',
];

$html_head[] = [
  [
    '#tag' => 'link',
    '#attributes' => [
      'rel' => 'manifest',
      'href' => $img_path . 'site.webmanifest',
    ],
  ],
  'manifest',
];

$html_head[] = [
  [
    '#tag' => 'link',
    '#attributes' => [
      'rel' => 'mask-icon',
      'href' => $img_path . 'safari-pinned-tab.svg',
      'color' => '#064a89',
    ],
  ],
  'maskicon',
];
