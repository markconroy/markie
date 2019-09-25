<?php

namespace Drupal\schema_metatag\Plugin\metatag\Tag;

/**
 * Provides a plugin for the 'schema_date_base' meta tag.
 */
class SchemaDateBase extends SchemaNameBase {

  /**
   * Generate a form element for this meta tag.
   */
  public function form(array $element = []) {
    $form = parent::form($element);
    $form['#description'] .= ' ' . $this->t('Use a token like [node:created:html_datetime].');
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public static function testValue() {
    return parent::testDefaultValue(1, '');
  }

}
