<?php

/**
 * @file
 * Implementation of hook_panels_layouts
 */
function adaptivetheme_two_75_25_panels_layouts() {
  $items['two_75_25'] = array(
    'title' => t('AT Two column 75/25'),
    'category' => t('AT Responsive Panels - 2 column'),
    'icon' => 'two_75_25.png',
    'theme' => 'two_75_25',
    'admin css' => 'two_75_25.admin.css',
    'theme arguments' => array('id', 'content'),
    'regions' => array(
      'two_75_25_top'    => t('Top (conditional)'),
      'two_75_25_first'  => t('Left'),
      'two_75_25_second' => t('Right'),
      'two_75_25_bottom' => t('Bottom (conditional)'),
    ),
    // AT Core
    'type' => 'two',
    'options' => array(
      'two-75-25' => 'default',
      'two-75-25-stack' => 'stack',
    ),
    'styles' => array(
      'two-75-25' => array(
        'css' => array(
          '75' => array('.two-75-25 > .region-two-75-25-first' => 'width:75%'),
          '25' => array('.two-75-25 > .region-two-75-25-second' => 'width:25%'),
        ),
      ),
      'two-75-25-stack' => array(
        'css' => array(
          'fdw' => array('.two-75-25 > .region' => 'float:none;display:block;width:100%;clear:both'),
        ),
      ),
    ),
  );

  return $items;
}

/**
 * Preprocess variables for two-75-25.php
 */
function template_preprocess_two_75_25(&$vars, $hook) {
  $vars['panel_prefix'] = '';
  $vars['panel_suffix'] = '';
}
