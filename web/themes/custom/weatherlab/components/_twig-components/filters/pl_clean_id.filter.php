<?php

/**
 * @file
 * Add "clean_id" filter for Pattern Lab.
 *
 * Replicate Drupal filter so can use it in Pattern Lab.
 */

$filter = new Twig_SimpleFilter('clean_id', function ($string) {
   // Lower case everything.
  $string = strtolower($string);
  // Make alphanumeric (removes all other characters).
  $string = preg_replace("/[^a-z0-9_\s-]/", "", $string);
  // Clean up multiple dashes or whitespaces.
  $string = preg_replace("/[\s-]+/", " ", $string);
  // Convert whitespaces and dashes to underscore.
  $string = preg_replace("/[\s-]/", "_", $string);
  return $string;
});
