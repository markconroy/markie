<?php

namespace Drupal\schema_metatag\Plugin\metatag\Tag;

/**
 * Schema.org MainEntityOfPage items should extend this class.
 */
abstract class SchemaMainEntityOfPageBase extends SchemaNameBase {

  /**
   * Generate a form element for this meta tag.
   */
  public function form(array $element = []) {
    $form = parent::form($element);
    $form['#attributes']['placeholder'] = '[current-page:url]';
    $form['#description'] = $this->t('If this is the main content of the page, provide url of the page. Only one object on each page should be marked as the main entity of the page.');
    return $form;
  }

}
