<?php

namespace Drupal\metatag\Hook;

use Drupal\commerce_migrate_commerce\Plugin\migrate\source\commerce1\ProductDisplay;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Plugin\Factory\DefaultFactory;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\migrate\Plugin\MigrateDestinationPluginManager;
use Drupal\migrate\Plugin\migrate\destination\EntityContentBase;
use Drupal\migrate\Plugin\MigrateSourceInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;
use Drupal\node\Plugin\migrate\source\d6\Node as Node6;
use Drupal\node\Plugin\migrate\source\d7\Node as Node7;
use Drupal\taxonomy\Plugin\migrate\source\d6\Term as Term6;
use Drupal\taxonomy\Plugin\migrate\source\d7\Term as Term7;
use Drupal\user\Plugin\migrate\source\d6\User as User6;
use Drupal\user\Plugin\migrate\source\d7\User as User7;

/**
 * Migrate hook implementations for Metatag.
 */
class MigrateHooks {

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected readonly ModuleHandlerInterface $moduleHandler;

  /**
   * The migrate destination plugin manager.
   *
   * @var \Drupal\migrate\Plugin\MigrateDestinationPluginManager|null
   */
  protected readonly ?MigrateDestinationPluginManager $destinationPluginManager;

  /**
   * Constructs a new MigrateHooks object.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\migrate\Plugin\MigrateDestinationPluginManager|null $destination_plugin_manager
   *   The migrate destination plugin manager, when the migrate module is
   *   installed.
   */
  public function __construct(
    ModuleHandlerInterface $module_handler,
    ?MigrateDestinationPluginManager $destination_plugin_manager = NULL,
  ) {
    $this->moduleHandler = $module_handler;
    $this->destinationPluginManager = $destination_plugin_manager;
  }

  /**
   * Implements hook_migrate_prepare_row().
   */
  #[Hook('migrate_prepare_row')]
  public function migratePrepareRow(Row $row, MigrateSourceInterface $source, MigrationInterface $migration) {
    // Don't bother if there source doesn't allow the getDatabase() method.
    if (!method_exists($source, 'getDatabase')) {
      return;
    }
    // Work out what sort of migration to do. Cache the results of this logic so
    // that it isn't checked on every single row being processed. Store this as
    // separate keys to work with situations where the source changes during the
    // migration.
    $metatag_table_exists =& drupal_static('metatag_migrate_prepare_row_metatag_table_exists', []);
    $nodewords_table_exists =& drupal_static('metatag_migrate_prepare_row_nodewords_table_exists', []);
    $source_db_key = $source->getDatabase()->getKey();
    if (!isset($metatag_table_exists[$source_db_key])) {
      $metatag_table_exists[$source_db_key] = $source->getDatabase()->schema()->tableExists('metatag');
    }
    if (!isset($nodewords_table_exists[$source_db_key])) {
      $nodewords_table_exists[$source_db_key] = $source->getDatabase()->schema()->tableExists('nodewords');
    }
    // The source is Metatag-D7.
    if ($metatag_table_exists[$source_db_key]) {
      // @todo Write a more general version rather than hard-coded.
      // Support a know subset of D7 sources.
      if (is_a($source, Node7::class)) {
        // E.g. d7_node, d7_node_revision.
        $source_type = 'node';
      }
      elseif (is_a($source, Term7::class)) {
        // E.g. d7_taxonomy_term.
        $source_type = 'taxonomy';
      }
      elseif (is_a($source, User7::class)) {
        // E.g. d7_user.
        $source_type = 'user';
      }
      elseif ($this->moduleHandler->moduleExists('commerce_migrate_commerce') && is_a($source, ProductDisplay::class)) {
        // Products were nodes in Drupal 7 so map the $source_type to node.
        $source_type = 'node';
      }
      else {
        // Not supported now, nothing to do.
        return;
      }
      if ($migration->getDestinationPlugin() instanceof EntityContentBase) {
        $entity_type = NULL;
        $entity_id = NULL;
        $revision_id = NULL;
        // @todo Write a more general version rather than a switch statement.
        switch ($source_type) {
          case 'node':
            $entity_type = 'node';
            $entity_id = $row->getSourceProperty('nid');
            $revision_id = $row->getSourceProperty('vid');
            break;

          case 'taxonomy':
            $entity_type = 'taxonomy_term';
            $entity_id = $row->getSourceProperty('tid');
            break;

          case 'user':
            $entity_type = 'user';
            $entity_id = $row->getSourceProperty('uid');
            break;
        }
        /** @var \Drupal\migrate\Plugin\migrate\source\SqlBase $source */
        /** @var \Drupal\Core\Database\Query\SelectInterface $query */
        $query = $source->getDatabase()->select('metatag', 'm')->fields('m', [
          'data',
        ])->condition('entity_type', $entity_type)->condition('entity_id', $entity_id);
        if (!is_null($revision_id)) {
          if ($source->getDatabase()->schema()->fieldExists('metatag', 'revision_id')) {
            $query->condition('revision_id', $revision_id);
          }
        }
        $value = $query->execute()->fetchCol();
        if (!empty($value) && is_array($value)) {
          $value = array_pop($value);
        }
        $row->setSourceProperty('pseudo_metatag_entities', $value);
      }
    }
    elseif ($nodewords_table_exists[$source_db_key]) {
      // @todo Write a more general version rather than hard-coded.
      // Support a know subset of D6 sources.
      if (is_a($source, Node6::class)) {
        // E.g. d6_node, d6_node_revision.
        $source_type = 'node';
      }
      elseif (is_a($source, Term6::class)) {
        // E.g. d6_taxonomy_term.
        $source_type = 'taxonomy_term';
      }
      elseif (is_a($source, User6::class)) {
        // E.g. d6_user.
        $source_type = 'user';
      }
      else {
        // Not supported now, nothing to do.
        return;
      }
      if ($migration->getDestinationPlugin() instanceof EntityContentBase) {
        $nodeword_type = $entity_id = NULL;
        // @todo Write a more general version rather than a switch statement.
        switch ($source_type) {
          case 'node':
            // @code
            // define('NODEWORDS_TYPE_NODE', 5);
            // @endcode
            $nodeword_type = 5;
            $entity_id = $row->getSourceProperty('nid');
            break;

          case 'taxonomy_term':
            // @code
            // define('NODEWORDS_TYPE_TERM', 6);
            // @endcode
            $nodeword_type = 6;
            $entity_id = $row->getSourceProperty('tid');
            break;

          case 'user':
            // @code
            // define('NODEWORDS_TYPE_USER', 8);
            // @endcode
            $nodeword_type = 8;
            $entity_id = $row->getSourceProperty('uid');
            break;
        }
        // @todo Migrate these configuration items.
        // @code
        // define('NODEWORDS_TYPE_BLOG',       13);
        // define('NODEWORDS_TYPE_DEFAULT',    1);
        // define('NODEWORDS_TYPE_ERRORPAGE',  2);
        // define('NODEWORDS_TYPE_FORUM',      12);
        // define('NODEWORDS_TYPE_FRONTPAGE',  3);
        // define('NODEWORDS_TYPE_NONE',       0);
        // define('NODEWORDS_TYPE_OFFLINE',    11);
        // define('NODEWORDS_TYPE_PAGE',       10);
        // define('NODEWORDS_TYPE_PAGER',      4);
        // define('NODEWORDS_TYPE_TRACKER',    7);
        // define('NODEWORDS_TYPE_VOCABULARY', 9);
        // @endcode
        /** @var \Drupal\migrate\Plugin\migrate\source\SqlBase $source */
        /** @var \Drupal\Core\Database\Query\SelectInterface $query */
        $query = $source->getDatabase()->select('nodewords', 'nw')->fields('nw', [
          'name',
          'content',
        ])->condition('type', $nodeword_type)->condition('id', $entity_id)->orderBy('nw.name');
        $value = $query->execute()->fetchAllKeyed();
        $row->setSourceProperty('pseudo_metatag_entities', $value);
      }
    }
  }

  /**
   * Implements hook_migration_plugins_alter().
   */
  #[Hook('migration_plugins_alter')]
  public function migrationPluginsAlter(array &$definitions) {
    // This is used for guided migrations from Drupal 7 using either core's
    // Migrate Drupal UI or the Migrate Upgrade contributed module. It will
    // automatically create a field named "field_metatag" with the per-entity
    // meta tag overrides for each entity.
    //
    // @todo Consider loading the relevant variables to determine which entities
    //   should be given the Metatag field.
    // @todo Document how to change the field name.
    //
    // @see metatag_migrate_prepare_row()
    // @see Drupal\metatag\Plugin\migrate\process\d7\MetatagD7
    foreach ($definitions as &$definition) {
      // Only certain migrate plugins are supported.
      if ($this->isMigrationPluginSupported($definition)) {
        // There are different field and process plugins for D6 and D7 too.
        if (in_array('Drupal 6', $definition['migration_tags'], TRUE)) {
          $definition['process']['field_metatag'] = [
            'plugin' => 'd6_nodewords_entities',
            'source' => 'pseudo_metatag_entities',
          ];
          $definition['migration_dependencies']['optional'][] = 'd6_nodewords_field';
          $definition['migration_dependencies']['optional'][] = 'd6_nodewords_field_instance';
        }
        if (in_array('Drupal 7', $definition['migration_tags'], TRUE)) {
          $definition['process']['field_metatag'] = [
            'plugin' => 'd7_metatag_entities',
            'source' => 'pseudo_metatag_entities',
          ];
          $destination_plugin_parts = explode(':', $definition['destination']['plugin']);
          $entity_destination_plugins = [
            'entity',
            'entity_complete',
          ];
          $entity_type_id = in_array($destination_plugin_parts[0], $entity_destination_plugins, TRUE) ? $destination_plugin_parts[1] : NULL;
          $bundle_id = $definition['destination']['default_bundle'] ?? NULL;
          // When there are no bundle derivatives, make e.g. the
          // d7_node_complete migration depend on:
          // - d7_metatag_field:node
          // - d7_metatag_field_instance:node
          // - d7_field_instance_widget:node
          // but if there is a bundle derivative such as
          // d7_node_complete:article, then instead make it depend on:
          // - d7_metatag_field:node
          // - d7_metatag_field_instance:node:article
          // - d7_field_instance_widget:node:article
          // Either way, this matches the dependencies used by for example
          // d7_node_complete, which has dependencies on d7_field_instance and
          // d7_comment_field_instance to ensure correct migration order.
          if ($bundle_id && isset($definitions["d7_metatag_field_instance:{$entity_type_id}:{$bundle_id}"])) {
            $definition['migration_dependencies']['optional'][] = "d7_metatag_field:{$entity_type_id}";
            $definition['migration_dependencies']['optional'][] = "d7_metatag_field_instance:{$entity_type_id}:{$bundle_id}";
            $definition['migration_dependencies']['optional'][] = "d7_metatag_field_instance_widget_settings:{$entity_type_id}:{$bundle_id}";
          }
          elseif (isset($definitions["d7_metatag_field_instance:{$entity_type_id}"])) {
            $definition['migration_dependencies']['optional'][] = "d7_metatag_field:{$entity_type_id}";
            $definition['migration_dependencies']['optional'][] = "d7_metatag_field_instance:{$entity_type_id}";
            $definition['migration_dependencies']['optional'][] = "d7_metatag_field_instance_widget_settings:{$entity_type_id}";
          }
        }
      }
    }
  }

  /**
   * Check if a given migrate plugin should have Metatag's logic added.
   *
   * @param array $definition
   *   The migration plugin definition to examine.
   *
   * @return bool
   *   Indicates whether Metatag's custom migration logic should be added for
   *   this migrate plugin definition
   */
  protected function isMigrationPluginSupported(array $definition) {
    // Only run add the migration plugins when doing a "Drupal 7" migration.
    // This will catch standard core migrations but allow skipping this log for
    // custom migrations that do not have this tag.
    if (empty($definition['migration_tags'])) {
      return FALSE;
    }
    if (!is_array($definition['migration_tags'])) {
      return FALSE;
    }
    if (!array_intersect(['Drupal 6', 'Drupal 7'], $definition['migration_tags'])) {
      return FALSE;
    }

    // Support for migrate_upgrade module, to avoid adding dependencies on
    // already processed migration procedures.
    if (!empty($definition['migration_group'])) {
      return FALSE;
    }

    // This migration has destination plugins defined.
    if (!empty($definition['destination']['plugin'])) {
      // Follow logic on hook_entity_base_field_info() and exclude the metatag
      // entity itself, plus some others.
      $destinations_to_ignore = [
        'entity:metatag',
        'color',
        'component_entity_display',
        'component_entity_form_display',
        'config',
        'd7_theme_settings',
        'entity:base_field_override',
        'entity:block',
        'entity:block_content',
        'entity:block_content_type',
        'entity:comment_type',
        'entity:contact_form',
        'entity:date_format',
        'entity:entity_view_mode',
        'entity:field_config',
        'entity:field_storage_config',
        'entity:filter_format',
        'entity:image_style',
        'entity:menu',
        'entity:menu_link_content',
        'entity:node_type',
        'entity:rdf_mapping',
        'entity:shortcut',
        'entity:shortcut_set',
        'entity:taxonomy_vocabulary',
        'entity:user_role',
        'shortcut_set_users',
        'url_alias',
        'user_data',
        // Various contrib modules.
        'entity:commerce_order',
        'entity:commerce_payment',
        'entity:commerce_payment_method',
        'entity:commerce_promotion',
        'entity:commerce_promotion_coupon',
        'entity:commerce_shipment',
        'entity:commerce_shipping_method',
        'entity:commerce_stock_location',
        'entity:linkcheckerlink',
        'entity:path_alias',
        'entity:redirect',
        'entity:salesforce_mapped_object',
        'entity:webform_submission',
      ];
      if (in_array($definition['destination']['plugin'], $destinations_to_ignore)) {
        return FALSE;
      }
    }

    // Only support content entity destinations. Protect against situations
    // where the plugins haven't loaded yet, e.g. when using Commerce Migrate.
    if ($this->destinationPluginManager === NULL) {
      return FALSE;
    }
    try {
      $plugin_definition = $this->destinationPluginManager
        ->getDefinition($definition['destination']['plugin']);
      $destination_plugin = DefaultFactory::getPluginClass($definition['destination']['plugin'], $plugin_definition);
      if (!is_subclass_of($destination_plugin, EntityContentBase::class) && $destination_plugin !== EntityContentBase::class) {
        return FALSE;
      }
    }
    catch (PluginNotFoundException $e) {
      // If the entity type doesn't exist, neither with the migration plugin.
      return FALSE;
    }

    // If this stage is reached then this is a supported core migration and the
    // Metatag migration will be automatically handled.
    return TRUE;
  }

}
