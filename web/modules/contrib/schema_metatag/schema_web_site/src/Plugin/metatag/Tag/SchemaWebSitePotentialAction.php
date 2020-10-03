<?php

namespace Drupal\schema_web_site\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaActionBase;
use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaEntryPointBase;

/**
 * Provides a plugin for the 'schema_web_site_potential_action' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_web_site_potential_action",
 *   label = @Translation("potentialAction"),
 *   description = @Translation("Potential action that can be accomplished on this site, like SearchAction."),
 *   name = "potentialAction",
 *   group = "schema_web_site",
 *   weight = 5,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = TRUE
 * )
 */
class SchemaWebSitePotentialAction extends SchemaActionBase {

  /**
   * Generate a form element for this meta tag.
   */
  public function form(array $element = []) {

    $this->actions = ['Action', 'SearchAction'];

    $form = parent::form($element);
    return $form;
  }

}
