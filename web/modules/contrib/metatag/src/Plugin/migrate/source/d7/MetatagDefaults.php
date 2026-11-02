<?php

namespace Drupal\metatag\Plugin\migrate\source\d7;

use Drupal\metatag\Plugin\migrate\MigrateMetatagD7Trait;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate_drupal\Plugin\migrate\source\DrupalSqlBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Drupal 7 Metatag configuration.
 *
 * @MigrateSource(
 *   id = "d7_metatag_defaults",
 *   source_module = "metatag"
 * )
 */
class MetatagDefaults extends DrupalSqlBase {

  use MigrateMetatagD7Trait;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, ?MigrationInterface $migration = NULL) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition, $migration);
    $instance->metatagModuleHandler = $container->get('module_handler');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    return $this->select('metatag_config', 'm')
      ->fields('m', ['instance', 'config']);
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = [
      'instance' => $this->t('Configuration instance'),
      'config' => $this->t('Meta tag configuration, stored as either a serialized array or a JSON-encoded string.'),
    ];
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    $ids['instance']['type'] = 'string';
    return $ids;
  }

}
