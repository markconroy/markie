<?php

declare(strict_types=1);

namespace Drupal\field_widget_actions;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Interface for field widget actions that support interactive refinement.
 *
 * Refinement-aware actions present their generated content in the standard
 * field widget action modal form, together with a refinement prompt. The user
 * can iteratively refine the content before inserting it into the field.
 *
 * @see \Drupal\field_widget_actions\FieldWidgetRefinableFormActionBase
 */
interface RefinementAwareInterface {

  /**
   * Checks if refinement is enabled for this plugin instance.
   *
   * @return bool
   *   TRUE if refinement is enabled in configuration, FALSE otherwise.
   */
  public function isRefinementEnabled(): bool;

  /**
   * Generates the initial content shown in the refinement modal.
   *
   * This is called when the modal form is first built, before the user has
   * entered any refinement instructions.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface|null $entity
   *   The entity in its current state with the form values copied into it,
   *   or NULL if it could not be determined.
   * @param array $context_data
   *   The field widget action context data. Contains at least the
   *   'target_element', 'target_element_field_name',
   *   'target_element_field_settings' and 'target_element_widget_settings'
   *   keys when the modal was opened from an entity form.
   *
   * @return string
   *   The generated content.
   */
  public function generateContent(?ContentEntityInterface $entity, array $context_data): string;

  /**
   * Refines previously generated content based on user instructions.
   *
   * This is called when the user submits the modal form with the Refine
   * button. The returned content replaces the current content in the
   * rebuilt modal form.
   *
   * @param string $content
   *   The current content to refine.
   * @param string $refinement_prompt
   *   The user-provided refinement instructions.
   * @param \Drupal\Core\Entity\ContentEntityInterface|null $entity
   *   The entity in its current state with the form values copied into it,
   *   or NULL if it could not be determined.
   * @param array $context_data
   *   The field widget action context data.
   *
   * @return string
   *   The refined content.
   */
  public function refineContent(string $content, string $refinement_prompt, ?ContentEntityInterface $entity, array $context_data): string;

}
