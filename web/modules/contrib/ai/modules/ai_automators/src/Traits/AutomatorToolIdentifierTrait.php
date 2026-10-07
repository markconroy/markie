<?php

declare(strict_types=1);

namespace Drupal\ai_automators\Traits;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\tool\ExecutableResult;

/**
 * Shared helpers for identifying ai_automator config entities.
 *
 * The ai_automator entity ID is always computed as
 * "<entity_type>.<bundle>.<field_name>.default" - it is never a free-form
 * slug like ai_guardrail IDs are. Every tool plugin that identifies an
 * automator uses these helpers so the computation never drifts between
 * tools.
 */
trait AutomatorToolIdentifierTrait {

  /**
   * Computes the canonical ai_automator entity ID.
   *
   * @param string $entityType
   *   The host entity type ID.
   * @param string $bundle
   *   The host bundle.
   * @param string $fieldName
   *   The field name.
   *
   * @return string
   *   The canonical ID, e.g. "node.article.field_summary.default".
   */
  protected function computeAutomatorId(string $entityType, string $bundle, string $fieldName): string {
    return sprintf('%s.%s.%s.default', $entityType, $bundle, $fieldName);
  }

  /**
   * Resolves the ai_automator entity ID from the tool's input values.
   *
   * Accepts either an explicit automator_id, or the entity_type/bundle/
   * field_name trio from which the ID is computed.
   *
   * @param array $values
   *   The tool's resolved input values.
   *
   * @return string|\Drupal\tool\ExecutableResult
   *   The resolved ID string, or a failure result if neither identification
   *   path was fully supplied.
   */
  protected function resolveAutomatorId(array $values): string|ExecutableResult {
    $automatorId = (string) ($values['automator_id'] ?? '');
    if ($automatorId !== '') {
      return $automatorId;
    }

    $entityType = (string) ($values['entity_type'] ?? '');
    $bundle = (string) ($values['bundle'] ?? '');
    $fieldName = (string) ($values['field_name'] ?? '');

    if ($entityType !== '' && $bundle !== '' && $fieldName !== '') {
      return $this->computeAutomatorId($entityType, $bundle, $fieldName);
    }

    return ExecutableResult::failure(
      new TranslatableMarkup('Provide either automator_id, or all of entity_type, bundle, and field_name to identify the automator.'),
    );
  }

}
