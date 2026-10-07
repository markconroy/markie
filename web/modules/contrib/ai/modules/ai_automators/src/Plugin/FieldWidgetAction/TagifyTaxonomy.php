<?php

namespace Drupal\ai_automators\Plugin\FieldWidgetAction;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\field_widget_actions\Attribute\FieldWidgetAction;

/**
 * The Tagify action for entity reference fields.
 *
 * Targets the Tagify autocomplete widgets — a single input holding every
 * referenced entity as a JSON payload, not per-delta sub-elements.
 */
#[FieldWidgetAction(
  id: 'automator_tagify_taxonomy',
  label: new TranslatableMarkup('Automator Taxonomy'),
  widget_types: [
    'tagify_entity_reference_autocomplete_widget',
    'tagify_user_list_entity_reference_autocomplete_widget',
  ],
  field_types: ['entity_reference'],
  category: new TranslatableMarkup('AI Automators'),
)]
class TagifyTaxonomy extends AutomatorBaseAction {

  /**
   * Target the 'target_id' of the referenced entity.
   */
  protected string $formElementProperty = 'target_id';

  /**
   * {@inheritdoc}
   *
   * A per-delta button would be attached to the Tagify input itself, and a
   * themed input never renders its children — the button would silently
   * vanish. One button beside the whole widget is also what it means here:
   * Tagify holds every value in a single element.
   */
  public function getMultiple(): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   *
   * Ignore any stored value: this is a property of the widget, not a
   * preference, and honouring a stale TRUE would silently drop the button.
   */
  public function isMultiple(): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state, $action_id = NULL) {
    $element = parent::buildConfigurationForm($form, $form_state, $action_id);
    // Don't offer a choice we ignore.
    $element['multiple']['#access'] = FALSE;
    return $element;
  }

  /**
   * {@inheritdoc}
   *
   * Tagify reads its tags from a JSON array of tag objects, but we write the
   * [['target_id' => id], …] shape and let the element's own valueCallback()
   * build that JSON. Label access checks, translations, info labels, parent
   * names and user avatars then stay the tagify module's business.
   *
   * @see \Drupal\tagify\Element\EntityAutocompleteTagify::valueCallback()
   */
  protected function setFormInput(FieldableEntityInterface $entity, FormStateInterface $form_state, $form_key): void {
    $targets = [];
    foreach ($entity->get($form_key) as $item) {
      if ($item->target_id) {
        $targets[] = [$this->formElementProperty => $item->target_id];
      }
    }

    // An empty array makes valueCallback() bail out and hand the raw array
    // back as the element value, so leave the widget alone instead.
    if (!$targets) {
      return;
    }

    $input = $form_state->getUserInput();
    $input[$form_key] = $targets;
    $form_state->setUserInput($input);
  }

}
