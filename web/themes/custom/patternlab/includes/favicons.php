<?php
/*
 * Add responsive favicons.
 *
 * Generate all the needed files through http://www.favicon-generator.org/
 * and place the generated files in to /images/favicons
 */
$html_head = &$attachments['#attached']['html_head'];
global $base_path;
$img_path = $base_path . 'themes/custom/patternlab/images/favicons/';

$html_head[] = [
  [
    '#tag' => 'meta',
    '#attributes' => [
      'name' => 'msapplication-TileColor',
      'content' => '#ffffff',
    ],
  ],
  'TileColor',
];

$html_head[] = [
  [
    '#tag' => 'meta',
    '#attributes' => [
      'name' => 'msapplication-TileImage',
      'content' => $img_path . 'ms-icon-144x144.png',
    ],
  ],
  'TileImage',
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
      'sizes' => '57x57',
      'href' => $img_path . 'apple-icon-57x57.png',
    ],
  ],
  'icon57',
];

$html_head[] = [
  [
    '#tag' => 'link',
    '#attributes' => [
      'rel' => 'apple-touch-icon',
      'sizes' => '60x60',
      'href' => $img_path . 'apple-icon-60x60.png',
    ],
  ],
  'icon60',
];

$html_head[] = [
  [
    '#tag' => 'link',
    '#attributes' => [
      'rel' => 'apple-touch-icon',
      'sizes' => '72x72',
      'href' => $img_path . 'apple-icon-72x72.png',
    ],
  ],
  'icon72',
];

$html_head[] = [
  [
    '#tag' => 'link',
    '#attributes' => [
      'rel' => 'apple-touch-icon',
      'sizes' => '76x76',
      'href' => $img_path . 'apple-icon-76x76.png',
    ],
  ],
  'icon76',
];

$html_head[] = [
  [
    '#tag' => 'link',
    '#attributes' => [
      'rel' => 'apple-touch-icon',
      'sizes' => '114x114',
      'href' => $img_path . 'apple-icon-114x114.png',
    ],
  ],
  'icon114',
];

$html_head[] = [
  [
    '#tag' => 'link',
    '#attributes' => [
      'rel' => 'apple-touch-icon',
      'sizes' => '120x120',
      'href' => $img_path . 'apple-icon-120x120.png',
    ],
  ],
  'icon120',
];

$html_head[] = [
  [
    '#tag' => 'link',
    '#attributes' => [
      'rel' => 'apple-touch-icon',
      'sizes' => '144x144',
      'href' => $img_path . 'apple-icon-144x144.png',
    ],
  ],
  'icon144',
];

$html_head[] = [
  [
    '#tag' => 'link',
    '#attributes' => [
      'rel' => 'apple-touch-icon',
      'sizes' => '152x152',
      'href' => $img_path . 'apple-icon-152x152.png',
    ],
  ],
  'icon152',
];

$html_head[] = [
  [
    '#tag' => 'link',
    '#attributes' => [
      'rel' => 'apple-touch-icon',
      'sizes' => '180x180',
      'href' => $img_path . 'apple-icon-180x180.png',
    ],
  ],
  'icon180',
];

$html_head[] = [
  [
    '#tag' => 'link',
    '#attributes' => [
      'rel' => 'icon',
      'sizes' => '192x192',
      'href' => $img_path . 'android-icon-192x192.png',
      'type' => 'image/png',
    ],
  ],
  'icon192',
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
      'sizes' => '96x96',
      'href' => $img_path . 'favicon-96x96.png',
      'type' => 'image/png',
    ],
  ],
  'favicon96',
];

$html_head[] = [
  [
    '#tag' => 'link',
    '#attributes' => [
      'rel' => 'icon',
      'sizes' => '16x16',
      'href' => $img_path . 'favicon-16x16.png',
      'type' => 'image/png'
    ],
  ],
  'favicon16',
];

$html_head[] = [
  [
    '#tag' => 'link',
    '#attributes' => [
      'rel' => 'manifest',
      'href' => $img_path . 'manifest.json',
    ],
  ],
  'manifest',
];
