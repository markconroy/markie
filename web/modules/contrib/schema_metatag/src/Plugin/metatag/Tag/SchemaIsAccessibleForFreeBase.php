<?php

namespace Drupal\schema_metatag\Plugin\metatag\Tag;

/**
 * Provides a plugin for the 'isAccessibleForFree' meta tag.
 */
abstract class SchemaIsAccessibleForFreeBase extends SchemaNameBase {

  /**
   * {@inheritdoc}
   */
  public function form(array $element = []) {
    $form = parent::form($element);
    $form['#type'] = 'select';
    $form['#empty_option'] = t('True');
    $form['#empty_value'] = '';
    $form['#options'] = ['False' => 'False'];
    $form['#description'] = $this->t('Whether this object is accessible for free. If used on a CreativeWork, like a WebPage or Article, be sure to fill out "hasPart" as well.');
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public static function testValue() {
    return 'False';
  }

}
