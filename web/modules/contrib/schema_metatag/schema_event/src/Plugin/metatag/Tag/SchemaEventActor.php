<?php

namespace Drupal\schema_event\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaPersonOrgBase;

/**
 * Provides a plugin for the 'actor' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_event_actor",
 *   label = @Translation("actor"),
 *   description = @Translation("The actor on the event."),
 *   name = "actor",
 *   group = "schema_event",
 *   weight = 11,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = TRUE
 * )
 */
class SchemaEventActor extends SchemaPersonOrgBase {

  /**
   * {@inheritdoc}
   */
  public function form(array $element = []) {
    $form = parent::form($element);
    $form['name']['#description'] = $this->t("The name of the actor");
    $form['url']['#description'] = $this->t("The URL of the actor's website.");
    return $form;
  }

}
