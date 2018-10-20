<?php

namespace Drupal\geshifield\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Plugin implementation of the 'geshifield' field type.
 *
 * @FieldType(
 *   id = "geshifield",
 *   label = @Translation("GeSHi field"),
 *   description = @Translation("Provides a field for source code with GeSHI."),
 *   default_widget = "geshifield_default",
 *   default_formatter = "geshifield_default"
 * )
 */
class GeshiFieldItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  protected static $propertyDefinitions;

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field) {
    return [
      'columns' => [
        'sourcecode' => [
          'type' => 'text',
          'size' => 'big',
          'not null' => FALSE,
        ],
        'language' => [
          'type' => 'varchar',
          'length' => 256,
          'not null' => FALSE,
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['sourcecode'] = DataDefinition::create('string')
      ->setLabel(t('Source code'));

    $properties['language'] = DataDefinition::create('string')
      ->setLabel(t('Syntax highlighting mode'));

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $value = $this->get('sourcecode')->getValue();
    return $value === NULL || $value === '';
  }

}
