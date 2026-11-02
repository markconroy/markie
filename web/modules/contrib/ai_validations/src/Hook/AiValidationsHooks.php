<?php

declare(strict_types=1);

namespace Drupal\ai_validations\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for AI Validations.
 */
final class AiValidationsHooks {

  /**
   * Implements hook_form_FORM_ID_alter() for field_validation_rule_form.
   *
   * Registers the procedural _ai_validations_sync_rule_to_collection()
   * submit handler. It stays procedural, referenced by name, because the
   * rule form gets cached and unserialized across requests; see its
   * docblock in ai_validations.module for why an object callable would be
   * unsafe here.
   *
   * @see _ai_validations_sync_rule_to_collection()
   */
  #[Hook('form_field_validation_rule_form_alter')]
  public function formFieldValidationRuleFormAlter(array &$form, FormStateInterface $form_state): void {
    $form['#submit'][] = '_ai_validations_sync_rule_to_collection';
  }

}
