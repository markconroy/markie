<?php

declare(strict_types=1);

namespace Drupal\field_widget_actions_test\Plugin\FieldWidgetAction;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\field_widget_actions\Ajax\FillEditorCommand;
use Drupal\field_widget_actions\Ajax\FillSimpleFieldCommand;
use Drupal\field_widget_actions\Attribute\FieldWidgetAction;
use Drupal\field_widget_actions\FieldWidgetRefinableFormActionBase;

/**
 * Test action demonstrating interactive refinement.
 *
 * This plugin shows how to implement a refinable field widget action. When
 * refinement is enabled via configuration, clicking the action button opens
 * the modal form with the generated content, a refinement prompt and a
 * Refine button. When refinement is disabled, the plugin's own AJAX callback
 * fills the field directly without a modal.
 */
#[FieldWidgetAction(
  id: 'refinable_texts_for_textfield',
  label: new TranslatableMarkup('Refinable texts for field'),
  widget_types: [
    'string_textfield',
    'string_textarea',
    'text_textfield',
    'text_textarea',
    'text_textarea_with_summary',
  ],
  field_types: [
    'string',
    'string_long',
    'text',
    'text_long',
    'text_with_summary',
  ],
  category: new TranslatableMarkup('Test Actions'),
)]
class RefinableTextsTestAction extends FieldWidgetRefinableFormActionBase {

  /**
   * {@inheritdoc}
   */
  public function getAjaxCallback(): ?string {
    return 'fillDirectly';
  }

  /**
   * {@inheritdoc}
   */
  public function getLibraries(): array {
    return [
      'field_widget_actions/commands',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function generateContent(?ContentEntityInterface $entity, array $context_data): string {
    // For formatted-text targets, return HTML so the modal exercises the
    // rich-text (CKEditor) path; for plain targets return plain text.
    if ($this->targetIsFormattedText($context_data)) {
      return '<p>Generated content that can be refined iteratively.</p><ul><li>One</li><li>Two</li></ul>';
    }
    return 'This is generated content that can be refined iteratively.';
  }

  /**
   * {@inheritdoc}
   */
  public function refineContent(string $content, string $refinement_prompt, ?ContentEntityInterface $entity, array $context_data): string {
    // For formatted-text targets, keep the HTML structure intact while applying
    // the refinement: add the instruction as a new list item so the result is
    // still a valid list.
    if ($this->targetIsFormattedText($context_data)) {
      $item = '<li>' . $refinement_prompt . '</li>';
      if (str_contains($content, '</ul>')) {
        return str_replace('</ul>', $item . '</ul>', $content);
      }
      return $content . '<ul>' . $item . '</ul>';
    }
    return $content . ' Refined with: ' . $refinement_prompt . '.';
  }

  /**
   * AJAX callback filling the field directly when refinement is disabled.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response.
   */
  public function fillDirectly(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();
    $target_element = $this->getTargetElement($form, $form_state);
    if (!empty($target_element['#name'])) {
      $entity = $this->buildEntity($form, $form_state);
      // Pass the captured target element as context so generated content and
      // the fill command match the target's type (rich text vs plain text),
      // exactly as the modal path does.
      $context_data = ['target_element' => $target_element];
      $content = $this->generateContent($entity, $context_data);
      $selector = '[name="' . $target_element['#name'] . '"]';
      if ($this->targetIsFormattedText($context_data)) {
        $response->addCommand(new FillEditorCommand($selector, $content));
      }
      else {
        $response->addCommand(new FillSimpleFieldCommand($selector, $content));
      }
    }
    return $response;
  }

}
