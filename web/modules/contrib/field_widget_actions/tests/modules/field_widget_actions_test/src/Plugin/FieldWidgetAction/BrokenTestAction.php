<?php

namespace Drupal\field_widget_actions_test\Plugin\FieldWidgetAction;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\field_widget_actions\Attribute\FieldWidgetAction;
use Drupal\field_widget_actions\FieldWidgetActionBase;

/**
 * A test action with a broken constructor signature.
 *
 * Used to verify that settings form building does not fatally fail when a
 * discovered action plugin cannot be instantiated.
 */
#[FieldWidgetAction(
  id: 'broken_test_action',
  label: new TranslatableMarkup('Broken test action'),
  widget_types: [
    'string_textfield',
  ],
  field_types: [
    'string',
  ],
  category: new TranslatableMarkup('Test Actions'),
)]
class BrokenTestAction extends FieldWidgetActionBase {

  /**
   * Constructs a broken test action.
   *
   * The extra required argument intentionally breaks plugin instantiation.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MessengerInterface $messenger, string $required) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $messenger);
    if ($required === '__never__') {
      throw new \RuntimeException('Unreachable guard for static analysis.');
    }
  }

}
