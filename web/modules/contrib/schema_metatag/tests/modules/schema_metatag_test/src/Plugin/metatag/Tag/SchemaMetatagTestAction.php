<?php

namespace Drupal\schema_metatag_test\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaActionBase;
use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaEntryPointBase;
use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaThingBase;

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

    $this->actions = ['Action', 'OrganizeAction'];

    $form = parent::form($element);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public static function testValue() {
    $items = [];
    $keys = [
      '@type',
      'result',
      'target',
    ];
    foreach ($keys as $key) {
      switch ($key) {

        case '@type':
          $items[$key] = 'OrganizeAction';
          break;

        case 'target':
          $items[$key] = SchemaEntryPointBase::testValue();
          break;

        case 'result':
          $items[$key] = SchemaThingBase::testValue();
          break;

        default:
          $items[$key] = parent::testDefaultValue(1, '');
          break;

      }
    }
    return $items;

  }

}
