<?php

namespace Drupal\field_widget_actions_test\Plugin\FieldWidgetAction;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\field_widget_actions\Attribute\FieldWidgetAction;
use Drupal\field_widget_actions\FieldWidgetActionBase;

/**
 * A test action that is never available.
 *
 * Used to exercise the availability filtering and the "unavailable" messages in
 * the field widget settings form without depending on the AI Automators module.
 * It declares both a string and a boolean widget/field type so it can either
 * coexist with the always-available text actions (the "mixed" case) or be the
 * only matching action for a boolean field (the "all unavailable" case).
 */
#[FieldWidgetAction(
  id: 'unavailable_test_action',
  label: new TranslatableMarkup('Unavailable test action'),
  widget_types: [
    'string_textfield',
    'boolean_checkbox',
  ],
  field_types: [
    'string',
    'boolean',
  ],
  category: new TranslatableMarkup('Test Actions'),
)]
class UnavailableTestAction extends FieldWidgetActionBase {

  /**
   * {@inheritdoc}
   */
  public function isAvailable(): bool {
    return FALSE;
  }

}
