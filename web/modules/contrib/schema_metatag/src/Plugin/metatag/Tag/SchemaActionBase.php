<?php

namespace Drupal\schema_metatag\Plugin\metatag\Tag;

use Drupal\schema_metatag\SchemaMetatagManager;

/**
 * Schema.org Action items should extend this class.
 */
class SchemaActionBase extends SchemaNameBase {

  use SchemaActionTrait;

  /**
   * Allowed action types.
   *
   * @var array
   */
  protected $actionTypes;

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
    $this->actionTypes = [];
    $this->actions = [];
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $element = []) {

    $value = SchemaMetatagManager::unserialize($this->value());

    $input_values = [
      'title' => $this->label(),
      'description' => $this->description(),
      'value' => $value,
      '#required' => isset($element['#required']) ? $element['#required'] : FALSE,
      'visibility_selector' => $this->visibilitySelector(),
      'actionTypes' => $this->actionTypes,
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
    $keys = self::actionFormKeys('TradeAction');
    foreach ($keys as $key) {
      switch ($key) {

        case '@type':
          $items[$key] = 'BuyAction';
          break;

        case 'location':
        case 'fromLocation':
        case 'toLocation':
          $items[$key] = SchemaPlaceBase::testValue();
          break;

        case 'expectsAcceptanceOf':
          $items[$key] = SchemaOfferBase::testValue();
          break;

        case 'event':
          $items[$key] = SchemaEventBase::testValue();
          break;

        case 'targetCollection':
        case 'result':
        case 'object':
        case 'error':
        case 'instrument':
          $items[$key] = SchemaThingBase::testValue();
          break;

        case 'target':
          $items[$key] = SchemaEntryPointBase::testValue();
          break;

        case 'agent':
        case 'buyer':
        case 'seller':
        case 'recipient':
        case 'participant':
          $items[$key] = SchemaPersonOrgBase::testValue();
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
        case 'location':
        case 'fromLocation':
        case 'toLocation':
          $items[$key] = SchemaPlaceBase::processedTestValue($items[$key]);
          break;

        case 'expectsAcceptanceOf':
          $items[$key] = SchemaOfferBase::processedTestValue($items[$key]);
          break;

        case 'event':
          $items[$key] = SchemaEventBase::processedTestValue($items[$key]);
          break;

        case 'targetCollection':
        case 'result':
        case 'object':
        case 'error':
        case 'instrument':
          $items[$key] = SchemaThingBase::processedTestValue($items[$key]);
          break;

        case 'target':
          $items[$key] = SchemaEntryPointBase::processedTestValue($items[$key]);
          break;

        case 'agent':
        case 'buyer':
        case 'seller':
        case 'recipient':
        case 'participant':
          $items[$key] = SchemaPersonOrgBase::processedTestValue($items[$key]);
          break;

      }
    }
    return $items;

  }

}
