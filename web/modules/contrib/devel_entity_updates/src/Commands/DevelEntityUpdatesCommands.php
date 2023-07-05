<?php
namespace Drupal\devel_entity_updates\Commands;

use Consolidation\SiteAlias\SiteAliasManagerAwareInterface;
use Consolidation\SiteAlias\SiteAliasManagerAwareTrait;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface;
use Drupal\devel_entity_updates\DevelEntityDefinitionUpdateManager;
use Drush\Commands\DrushCommands;
use Drush\Drush;
use Drush\Exceptions\UserAbortException;

/**
 * Drush9 commands definitions.
 */
class DevelEntityUpdatesCommands extends DrushCommands implements SiteAliasManagerAwareInterface {

  use SiteAliasManagerAwareTrait;

  /**
   * The class resolver.
   *
   * @var \Drupal\Core\DependencyInjection\ClassResolverInterface
   */
  protected $classResolver;

  /**
   * The entity definition update manager.
   *
   * @var \Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface
   */
  protected $entityDefinitionUpdateManager;

  /**
   * DevelEntityUpdatesCommands constructor.
   *
   * @param \Drupal\Core\DependencyInjection\ClassResolverInterface $class_resolver
   *   The class resolver.
   * @param \Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface $entity_definition_update_manager
   *   The entity definition update manager.
   */
  public function __construct(ClassResolverInterface $class_resolver, EntityDefinitionUpdateManagerInterface $entity_definition_update_manager) {
    parent::__construct();

    $this->classResolver = $class_resolver;
    $this->entityDefinitionUpdateManager = $entity_definition_update_manager;
  }

  /**
   * Apply pending entity schema updates.
   *
   * @command devel-entity-updates
   * @aliases dentup, entup, entity-updates
   * @option cache-clear Set to 0 to suppress normal cache clearing; the caller should then clear if needed.
   * @bootstrap full
   *
   * @param array $options
   *   Array of options.
   */
  public function entityUpdates($options = ['cache-clear' => TRUE]) {
    if (Drush::simulate()) {
      throw new \Exception(dt('entity-updates command does not support --simulate option.'));
    }

    if ($this->doEntityUpdates() === FALSE) {
      return;
    }

    if (!empty($options['cache-clear'])) {
      $process = Drush::drush($this->siteAliasManager()->getSelf(), 'cache-rebuild');
      $process->mustrun();
    }

    $this->logger()->success(dt('Finished performing updates.'));
  }

  /**
   * Actually performs entity schema updates.
   *
   * @return bool
   *   TRUE if updates were applied, FALSE otherwise.
   */
  protected function doEntityUpdates() {
    $result = TRUE;
    $change_summary = $this->entityDefinitionUpdateManager->getChangeSummary();

    if (!empty($change_summary)) {
      $this->output()->writeln(dt('The following updates are pending:'));
      $this->io()->newLine();

      foreach ($change_summary as $entity_type_id => $changes) {
        $this->output()->writeln($entity_type_id . ' entity type : ');
        foreach ($changes as $change) {
          $this->output()->writeln(strip_tags($change), 2);
        }
      }

      if (!$this->io()->confirm(dt('Do you wish to run all pending updates?'))) {
        throw new UserAbortException();
      }

      $this->classResolver
        ->getInstanceFromDefinition(DevelEntityDefinitionUpdateManager::class)
        ->applyUpdates();
    }
    else {
      $this->logger()->success(dt("No entity schema updates required"));
      $result = FALSE;
    }

    return $result;
  }

  /**
   * Replaces the "entity-updates" command.
   *
   * @hook replace-command entity:updates
   */
  public function doLegacyEntityUpdates($options = ['cache-clear' => TRUE]) {
    $this->entityUpdates($options);
  }
}
