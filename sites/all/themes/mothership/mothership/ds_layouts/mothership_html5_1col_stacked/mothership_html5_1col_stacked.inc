<?php
//http://drupalcode.org/viewvc/drupal/contributions/modules/ds/ds.api.php?revision=1.1.2.4&view=markup&pathrev=DRUPAL-7--1
// from around line 122

function ds_mothership_html5_1col_stacked() {
  return array(
    'label' => t('HTML5 1 col stacked - mothership'),
    'regions' => array(
      // The key of this region name is also the variable used in
      // the template to print the content of that region.
      'header' => t('header'),
      'hgroup' => t('hgroup (only h1-h6)'),
      'top' => t('top'),
      'main' => t('main'), 
      'main_nowrapper' => t('main no div wrapper'), 
      'bottom' => t('bottom'),
      'footer' => t('footer'), 
    ),
    // Add this if there is a default css file.
    //'css' => TRUE,
  );
}

