<?php

declare(strict_types=1);

namespace Drupal\ai_translate_overview_test\Controller;

use Drupal\entity_test\Entity\EntityTestMul;

/**
 * A translation overview controller that takes the entity, not core's args.
 */
class ReplacementOverviewController {

  /**
   * Builds the translation overview of a test entity.
   *
   * @param \Drupal\entity_test\Entity\EntityTestMul $entity_test_mul
   *   The entity, upcast from the route parameter.
   *
   * @return array
   *   A render array.
   */
  public function overview(EntityTestMul $entity_test_mul): array {
    return ['#plain_text' => 'Replacement overview: ' . $entity_test_mul->label()];
  }

}
