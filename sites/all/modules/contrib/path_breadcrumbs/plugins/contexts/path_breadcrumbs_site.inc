<?php

/**
 * @file
 *
 * Plugin to provide a site context. A site context is a site wrapped in a
 * context object that can be utilized by anything that accepts contexts.
 */

$plugin = array(
  'title' => t('Site'),
  'description' => t('A site object.'),
  'keyword' => 'site',
  'context name' => 'site',
  'convert' => 'ctools_context_pb_site_convert',

  // This context is deprecated and should not be usable in the UI.
  'no ui' => TRUE,
  'no required context ui' => TRUE,
);

/**
 * Convert a context into a string.
 */
function ctools_context_pb_site_convert($context, $type) {
  $values = token_generate('site', array($type => $type));
  if (isset($values[$type])) {
    return $values[$type];
  }
  return '';
}
