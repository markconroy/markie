<?php

namespace Drupal\schema_movie\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaEventBase;

/**
 * Provides a plugin for the 'schema_movie_released_event' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_movie_released_event",
 *   label = @Translation("releasedEvent"),
 *   description = @Translation("RECOMMENDED BY GOOGLE. Details about the original release of the work. Google expects only the country of the location."),
 *   name = "releasedEvent",
 *   group = "schema_movie",
 *   weight = 5,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = TRUE
 * )
 */
class SchemaMovieReleasedEvent extends SchemaEventBase {

  /**
   * Generate a form element for this meta tag.
   */
  public function form(array $element = []) {
    $form = parent::form($element);

    // This should only be a PublicationEvent.
    unset($form['@type']['#options']['Event']);

    // Highlight the fields that Google recommends.
    $recommended = ['startDate', 'location'];
    foreach ($recommended as $key) {
      $description = $this->t('RECOMMENDED BY GOOGLE.') . ' ' . $form[$key]['#description'];
      $form[$key]['#description'] = $description;
    }

    return $form;
  }

}
