<?php

namespace Drupal\ai_translate\Controller;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\ai_translate\Form\AiTranslateForm;
use Drupal\content_translation\Controller\ContentTranslationController;

/**
 * Overridden class for entity translation controllers.
 */
class ContentTranslationControllerOverride extends ContentTranslationController {

  /**
   * {@inheritdoc}
   */
  public function overview(RouteMatchInterface $route_match, $entity_type_id = NULL) {
    $build = parent::overview($route_match, $entity_type_id);
    $build = $this->formBuilder()->getForm(AiTranslateForm::class, $build);
    return $build;
  }

}
