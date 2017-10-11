<?php

use Drupal\Core\Url;
use Symfony\Cmf\Component\Routing\RouteObjectInterface;

$title = '';
if (!empty($variables['global_variables']['current_page_title'])) {
  // Social Sharing Global Variables
  // To use this, you need to wrap the variable in an anchor tag, such as:
  // <a href="{{ global_variables.social_sharing.twitter }}">Twitter</a>
  // Share the current page on Twitter.
  $title = $variables['global_variables']['current_page_title'];
  if (is_array($variables['global_variables']['current_page_title'])) {
    $title = urlencode($variables['global_variables']['current_page_title']['#markup']);
  }
  elseif (is_object($variables['global_variables']['current_page_title'])) {
    $title = render($variables['global_variables']['current_page_title']);
  }
}
// Share the current page on Twitter.
$variables['global_variables']['social_sharing']['twitter'] = Url::fromUri(
  'https://twitter.com/share',
  [
    'absolute' => TRUE,
    'https' => TRUE,
    'query' => [
      'url' => $variables['global_variables']['base_url'] . $variables['global_variables']['current_path'],
      'text' => $title,
    ],
  ])
  ->toUriString();
// Share the current page on Facebook.
$variables['global_variables']['social_sharing']['facebook'] = Url::fromUri(
  'https://www.facebook.com/sharer.php',
  [
    'absolute' => TRUE,
    'https' => TRUE,
    'query' => [
      'u' => $variables['global_variables']['base_url'] . $variables['global_variables']['current_path'],
      'text' => $title,
    ],
  ])
  ->toUriString();
  // Share the current page on LinkedIn.
  $variables['global_variables']['social_sharing']['linkedin'] = Url::fromUri(
    'https://www.linkedin.com/shareArticle',
    [
      'absolute' => TRUE,
      'https' => TRUE,
      'query' => [
        'mini' => 'true',
        'url' => $variables['global_variables']['base_url'] . $variables['global_variables']['current_path'],
        'title' => $title,
        'source' => $variables['global_variables']['site_name'],
      ],
    ])
    ->toUriString();
  // Share the current page by Email.
  $variables['global_variables']['social_sharing']['email'] = Url::fromUri(
    'mailto:',
    [
      'query' => [
        'subject' => $title,
        'body' => 'Check this out from ' . $variables['global_variables']['site_name'] . ': ' . $variables['global_variables']['base_url'] . $variables['global_variables']['current_path'],
      ],
    ])
    ->toUriString();
