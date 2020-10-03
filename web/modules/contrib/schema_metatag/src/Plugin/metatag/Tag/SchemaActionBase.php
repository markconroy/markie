<?php

namespace Drupal\schema_metatag\Plugin\metatag\Tag;

/**
 * Schema.org Action items should extend this class.
 */
class SchemaActionBase extends SchemaNameBase {

  use SchemaActionTrait;

  /**
   * Allowed actions.
   *
   * @var array
   */
  protected $actions;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->actions = [];
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $element = []) {

    $value = $this->schemaMetatagManager()->unserialize($this->value());

    $input_values = [
      'title' => $this->label(),
      'description' => $this->description(),
      'value' => $value,
      '#required' => isset($element['#required']) ? $element['#required'] : FALSE,
      'visibility_selector' => $this->visibilitySelector(),
      'actions' => $this->actions,
    ];

    $form = $this->actionForm($input_values);

    if (empty($this->multiple())) {
      unset($form['pivot']);
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public static function testValue() {
    $items = [];
    $keys = [
      '@type',
      'target',
    ];
    foreach ($keys as $key) {
      switch ($key) {

        case '@type':
          $items[$key] = 'Action';
          break;

        case 'target':
          $items[$key] = SchemaEntryPointBase::testValue();
          break;

        default:
          $items[$key] = parent::testDefaultValue(1, '');
          break;

      }
    }
    return $items;

  }

  /**
   * {@inheritdoc}
   */
  public static function processedTestValue($items) {
    foreach ($items as $key => $value) {
      switch ($key) {
        case 'target':
          $items[$key] = SchemaEntryPointBase::processedTestValue($items[$key]);
          break;

      }
    }
    return $items;

  }

}
