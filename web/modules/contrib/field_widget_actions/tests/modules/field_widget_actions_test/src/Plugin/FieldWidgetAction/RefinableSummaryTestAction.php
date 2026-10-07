<?php

declare(strict_types=1);

namespace Drupal\field_widget_actions_test\Plugin\FieldWidgetAction;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\field_widget_actions\Attribute\FieldWidgetAction;
use Drupal\field_widget_actions\FieldWidgetRefinableFormActionBase;

/**
 * Test action demonstrating refinement of the plain summary property.
 *
 * Targets the 'summary' property of a text_with_summary field. The summary is
 * plain text even though the field itself stores a text format, so the modal
 * must present a plain textarea rather than a rich-text editor.
 */
#[FieldWidgetAction(
  id: 'refinable_summary_for_textfield',
  label: new TranslatableMarkup('Refinable summary for field'),
  widget_types: [
    'text_textarea_with_summary',
  ],
  field_types: [
    'text_with_summary',
  ],
  category: new TranslatableMarkup('Test Actions'),
)]
class RefinableSummaryTestAction extends FieldWidgetRefinableFormActionBase {

  /**
   * The summary property, not the formatted main value.
   */
  const FORM_ELEMENT_PROPERTY = 'summary';

  /**
   * {@inheritdoc}
   */
  public function generateContent(?ContentEntityInterface $entity, array $context_data): string {
    return 'A plain-text summary that can be refined.';
  }

  /**
   * {@inheritdoc}
   */
  public function refineContent(string $content, string $refinement_prompt, ?ContentEntityInterface $entity, array $context_data): string {
    return $content . ' Refined with: ' . $refinement_prompt . '.';
  }

}
