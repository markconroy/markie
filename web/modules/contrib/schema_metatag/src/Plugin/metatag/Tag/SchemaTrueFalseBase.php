<?php

namespace Drupal\schema_metatag\Plugin\metatag\Tag;

/**
 * Provides a plugin for the 'isAccessibleForFree' meta tag.
 */
class SchemaTrueFalseBase extends SchemaNameBase {

  /**
   * {@inheritdoc}
   */
  public function form(array $element = []) {
    $form = parent::form($element);
    $form['#type'] = 'select';
    $form['#empty_option'] = $this->t('- None -');
    $form['#empty_value'] = '';
    $form['#options'] = ['False' => $this->t('False'), 'True' => $this->t('True')];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public static function testValue() {
    return 'False';
  }

}
