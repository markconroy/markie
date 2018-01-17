<?php

namespace Drupal\schema_metatag\Plugin\metatag\Tag;

trait SchemaPivotTrait {

  public function pivot_form($value) {

    $form = [
      '#type' => 'select',
      '#title' => 'Multiple values',
      '#default_value' => !empty($value['pivot']) ? $value['pivot'] : '',
      '#empty_option' => t('- None -'),
      '#empty_value' => '',
      '#options' => [
        0 => 'Normal',
        1 => 'Pivot',
      ],
      '#weight' => 50,
      '#description' => 'For multiple values, insert a comma-separated list of tokens or values into the properties in this section, e.g. "[node:field_related:0:entity:url],[node:field_related:1:entity:url],[node:field_related:2:entity:url]". If set to "Normal", the multiple values in this section will display normally as a series of properties, each with multiple values. If set to "Pivot", the values on each property in this section will be combined and pivoted to display multiple entities, each with one value per property.',
    ];

    return $form;
  }
}
