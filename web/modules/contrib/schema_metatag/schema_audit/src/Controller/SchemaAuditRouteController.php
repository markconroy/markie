<?php

namespace Drupal\schema_audit\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Page for Schema Audit report.
 */
class SchemaAuditRouteController extends ControllerBase {

  /**
   * Constructs a page for auditing Schema.org classes and properties.
   */
  public function page() {

    $build = [
      '#markup' => $this->t('This report is deprecated.'),
    ];

    return $build;
  }

}
