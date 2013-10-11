<?php	
function mothership_date_display_single($vars) {
  $date = $vars['date'];
  $timezone = $vars['timezone'];

  $vars['attributes']['datetime'] = $vars['dates']['value']['formatted_iso']; 
  $attributes = $vars['attributes'];
 
// Wrap the result with the attributes.
// used to be return '<span class="date-display-single"' . drupal_attributes($attributes) . '>' . $date . $timezone . '</span>';
  return '<time ' . drupal_attributes($attributes) . '>' . $date . $timezone . '</time>';

}
