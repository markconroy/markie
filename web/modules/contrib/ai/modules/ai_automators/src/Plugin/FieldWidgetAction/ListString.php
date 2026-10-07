<?php

namespace Drupal\ai_automators\Plugin\FieldWidgetAction;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\field_widget_actions\Ajax\FillSimpleFieldCommand;
use Drupal\field_widget_actions\Attribute\FieldWidgetAction;

/**
 * The List String action.
 */
#[FieldWidgetAction(
  id: 'automator_list_string',
  label: new TranslatableMarkup('Automator List String'),
  widget_types: ['options_select', 'options_buttons'],
  field_types: ['list_string'],
  multiple: FALSE,
)]
class ListString extends AutomatorRefinableBaseAction {

  /**
   * {@inheritdoc}
   *
   * The options_select / options_buttons widgets render the list as a single
   * form control, so user input is a flat scalar at $input[$form_key].
   */
  protected function setFormInput(FieldableEntityInterface $entity, FormStateInterface $form_state, $form_key): void {
    $first = $entity->get($form_key)->first();
    if (!$first) {
      return;
    }
    $input = $form_state->getUserInput();
    $input[$form_key] = (string) $first->value;
    $form_state->setUserInput($input);
  }

  /**
   * {@inheritdoc}
   *
   * Returns the current field value as a human-readable label rather than the
   * raw option key, so the modal textarea shows meaningful text to the user
   * and to the LLM during refinement.
   */
  public function generateContent(?ContentEntityInterface $entity, array $context_data): string {
    return $this->keyToLabel(parent::generateContent($entity, $context_data));
  }

  /**
   * {@inheritdoc}
   *
   * Validates the AI response against the field's allowed options. When the
   * model returns a string matching a label or key (exactly or
   * case-insensitively, after HTML entity decoding), the canonical label is
   * returned so the textarea remains human-readable. When no match is found
   * the original content is returned unchanged, preventing an invalid value
   * from reaching the field.
   */
  public function refineContent(string $content, string $refinement_prompt, ?ContentEntityInterface $entity, array $context_data): string {
    $refined = parent::refineContent($content, $refinement_prompt, $entity, $context_data);
    $key = $this->labelToKey($refined);
    if ($key !== NULL) {
      return $this->keyToLabel($key);
    }
    return $content;
  }

  /**
   * {@inheritdoc}
   *
   * Maps the human-readable label back to its option key before filling the
   * select or radio widget. Closes the modal without filling if the content
   * does not resolve to a valid option key, preventing invalid values from
   * being written to the field.
   */
  protected function submitModalFormFillFields(array $form, FormStateInterface $form_state, AjaxResponse $response): AjaxResponse {
    $context_data = $form_state->get('field_widget_action_context_data') ?? [];
    $target_element = $context_data['target_element'] ?? [];
    if (empty($target_element['#name'])) {
      return $response;
    }
    $key = $this->labelToKey($this->contentToString($form_state->getValue('content')));
    if ($key === NULL) {
      return $response;
    }
    $selector = '[name="' . $target_element['#name'] . '"]';
    $response->addCommand(new FillSimpleFieldCommand($selector, $key));
    return $response;
  }

  /**
   * Returns the field's allowed values keyed by option key.
   *
   * @return array<string, string>
   *   Allowed values map.
   */
  private function getAllowedValues(): array {
    $fieldDef = $this->getFieldDefinition();
    if (!$fieldDef) {
      return [];
    }
    return $fieldDef->getSetting('allowed_values') ?? [];
  }

  /**
   * Maps an option key to its human-readable label.
   *
   * Returns the key unchanged when no matching label is found.
   */
  private function keyToLabel(string $key): string {
    return $this->getAllowedValues()[$key] ?? $key;
  }

  /**
   * Maps a human-readable label or raw key to the canonical option key.
   *
   * Checks exact key, exact label, then case-insensitive label after HTML
   * entity decoding. Returns NULL when no match is found.
   */
  private function labelToKey(string $value): ?string {
    $allowed = $this->getAllowedValues();
    $decoded = html_entity_decode(trim($value), ENT_QUOTES | ENT_HTML5);

    if (isset($allowed[$decoded])) {
      return $decoded;
    }

    foreach ($allowed as $optKey => $optLabel) {
      $decodedLabel = html_entity_decode($optLabel, ENT_QUOTES | ENT_HTML5);
      if ($decodedLabel === $decoded || strcasecmp($decodedLabel, $decoded) === 0) {
        return $optKey;
      }
    }

    return NULL;
  }

}
