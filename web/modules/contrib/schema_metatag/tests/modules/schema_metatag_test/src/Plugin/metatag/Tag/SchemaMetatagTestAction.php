<?php

namespace Drupal\schema_metatag_test\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaActionBase;

/**
 * A metatag tag for testing.
 *
 * @MetatagTag(
 *   id = "schema_metatag_test_action",
 *   label = @Translation("Schema Metatag Test Action"),
 *   name = "action",
 *   description = @Translation("Test element"),
 *   group = "schema_metatag_test_group",
 *   weight = 0,
 *   type = "label",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaMetatagTestAction extends SchemaActionBase {

  /**
   * Generate a form element for this meta tag.
   */
  public function form(array $element = []) {

    $this->actionTypes = ['ConsumeAction'];
    $this->actions = ['WatchAction', 'ViewAction'];

    $form = parent::form($element);
    return $form;
  }

}
