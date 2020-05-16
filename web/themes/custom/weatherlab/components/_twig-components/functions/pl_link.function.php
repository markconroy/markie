<?php

/**
 * @file
 * Add "link" function for Pattern Lab.
 */

use \Drupal\Core\Template\Attribute;

$function = new Twig_SimpleFunction(
    'link',
    function ($title, $url, $attributes = []) {
      if (!empty($attributes)) {
        if (is_array($attributes)) {
          $attributes = new Attribute($attributes);
        }
        return '<a href="' . $url . '"' . $attributes . '>' . $title . '</a>';
      }
      else {
        return '<a href="' . $url . '">' . $title . '</a>';
      }
    },
    ['is_safe' => ['html']]
);
