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

    $drupal_client = \Drupal::service('schema_audit.drupal_client');
    $drupal = $drupal_client->parseDrupal();

    $google_client = \Drupal::service('schema_audit.google_client');
    $google = $google_client->parseGoogle();

    $schema_client = \Drupal::service('schema_audit.schema_client');
    $rows = $schema_client->getSchemaTable($drupal, $google);

    $header = [
      'Schema.org Object',
      'Google',
      'Drupal',
      'Schema.org Property',
      'Google',
      'Drupal',
    ];
    $build = [
      '#markup' => t('<h2>Schema.org Audit Page</h2>'),
      'table' => [
        '#theme' => 'table',
        '#header' => $header,
        '#rows' => $rows,
        '#attributes' => ['class' => 'schema-audit'],
        '#attached' => ['library' => ['schema_audit/audit-table']],
      ],
    ];

    return $build;
  }

}
