<?php

namespace Drupal\ai_automators\Traits;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * Keeps text automator output in a text format the current user may use.
 *
 * The output of text automators is derived from content the current user
 * controls, so it must not be stored in a text format the user may not use.
 * If the configured format is not available to the user, the output is stored
 * in the first format of the field the user may use instead.
 *
 * @package Drupal\ai_automators\Traits
 */
trait TextFormatAccessTrait {

  /**
   * Checks if the output can be stored for the current user.
   *
   * Logs a warning if not, so the automator can stop before the request.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity being worked on.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The field definition.
   * @param array $automatorConfig
   *   The automator config.
   *
   * @return bool
   *   TRUE if the configured text format, or a fallback, may be used.
   */
  protected function currentUserCanUseConfiguredTextFormat(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $automatorConfig): bool {
    return $this->currentUserCanUseTextFormat($entity, $fieldDefinition, $automatorConfig['use_text_format'] ?? NULL);
  }

  /**
   * Checks if output in the given text format can be stored for the user.
   *
   * Logs a warning if not, so the automator can stop before the request.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity being worked on.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The field definition.
   * @param string|null $formatId
   *   The text format the output should be stored in.
   *
   * @return bool
   *   TRUE if no text format is given, the user may use it, or the user may
   *   use another format of the field.
   */
  protected function currentUserCanUseTextFormat(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, ?string $formatId): bool {
    if (empty($formatId) || $this->currentUserHasTextFormat($formatId) || $this->getGeneralHelper()->calculateTextFormat($fieldDefinition)) {
      return TRUE;
    }
    $this->getTextFormatLogger()->warning('Skipped automator on field @field of @entity_type @id: the current user (@uid) may not use text format @format or any other format of the field.', [
      '@field' => $fieldDefinition->getName(),
      '@entity_type' => $entity->getEntityTypeId(),
      '@id' => $entity->id() ?? 'new',
      '@uid' => \Drupal::currentUser()->id(),
      '@format' => $formatId,
    ]);
    return FALSE;
  }

  /**
   * Gets the text format to store the output in for the current user.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity being worked on.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The field definition.
   * @param string|null $formatId
   *   The configured text format.
   *
   * @return string|null
   *   The configured format if empty or the user may use it, otherwise the
   *   first format of the field the user may use, or NULL if there is none.
   */
  protected function textFormatForCurrentUser(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, ?string $formatId): ?string {
    if (empty($formatId) || $this->currentUserHasTextFormat($formatId)) {
      return $formatId;
    }
    $fallback = $this->getGeneralHelper()->calculateTextFormat($fieldDefinition);
    $this->getTextFormatLogger()->notice('Stored automator output on field @field of @entity_type @id in text format @fallback: the current user (@uid) may not use text format @format.', [
      '@field' => $fieldDefinition->getName(),
      '@entity_type' => $entity->getEntityTypeId(),
      '@id' => $entity->id() ?? 'new',
      '@uid' => \Drupal::currentUser()->id(),
      '@format' => $formatId,
      '@fallback' => $fallback ?? 'none',
    ]);
    return $fallback;
  }

  /**
   * Checks if the current user may use a text format.
   *
   * @param string $formatId
   *   The text format.
   *
   * @return bool
   *   TRUE if the format exists and the user may use it.
   */
  protected function currentUserHasTextFormat(string $formatId): bool {
    /** @var \Drupal\filter\FilterFormatInterface|null $format */
    $format = \Drupal::entityTypeManager()->getStorage('filter_format')->load($formatId);
    return $format && $format->access('use');
  }

  /**
   * Gets the logger for text format messages.
   *
   * @return \Psr\Log\LoggerInterface
   *   The logger.
   */
  protected function getTextFormatLogger() {
    return $this->logger ?? \Drupal::logger('ai_automators');
  }

}
