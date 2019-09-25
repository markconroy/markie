<?php

namespace Drupal\schema_metatag\Plugin\metatag\Tag;

/**
 * Schema.org pivot trait.
 */
trait SchemaPivotTrait {

  /**
   * The form element.
   */
  public function pivotForm($value) {

    $form = [
      '#type' => 'select',
      '#title' => 'Pivot',
      '#default_value' => !empty($value['pivot']) ? $value['pivot'] : '',
      '#empty_option' => t('- None -'),
      '#empty_value' => '',
      '#options' => [
        1 => 'Pivot',
      ],
      '#weight' => -9,
      '#description' => 'Combine and pivot multiple values to display them as multiple objects.',
    ];

    return $form;
  }

}
