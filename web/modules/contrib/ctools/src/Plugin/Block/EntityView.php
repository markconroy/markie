<?php

namespace Drupal\ctools\Plugin\Block;

use Drupal\Core\Access\AccessResultForbidden;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TypedData\TranslatableInterface as TranslatableDataInterface;
use Drupal\ctools\Plugin\Deriver\EntityViewDeriver;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a block to view a specific entity.
 *
 * @Block(
 *   id = "entity_view",
 *   deriver = "Drupal\ctools\Plugin\Deriver\EntityViewDeriver",
 * )
 */
#[Block(
  id: 'entity_view',
  deriver: EntityViewDeriver::class,
)]
class EntityView extends BlockBase implements ContextAwarePluginInterface, ContainerFactoryPluginInterface, TrustedCallbackInterface {

  /**
   * Stack for the recursion detection.
   *
   * @var array
   */
  protected static array $recursionStack = [];

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity display repository.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepository;

  /**
   * Constructs a new EntityView.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display_repository
   *   The entity display repository.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, EntityDisplayRepositoryInterface $entity_display_repository) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityTypeManager = $entity_type_manager;
    $this->entityDisplayRepository = $entity_display_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('entity_display.repository')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'view_mode' => 'default',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['view_mode'] = [
      '#type' => 'select',
      '#options' => $this->entityDisplayRepository->getViewModeOptions($this->getDerivativeId()),
      '#title' => $this->t('View mode'),
      '#default_value' => $this->configuration['view_mode'],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['view_mode'] = $form_state->getValue('view_mode');
  }

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account, $return_as_object = FALSE) {
    // Check the parent's access.
    $parent_access = parent::access($account, TRUE);
    if (!$parent_access->isAllowed()) {
      return $return_as_object ? $parent_access : $parent_access->isAllowed();
    }

    // A caller that sets the context directly, rather than through the
    // context handler, can hand over a required context with no value.
    /** @var \Drupal\Core\Entity\EntityInterface|null $entity */
    $entity = $this->getContextValue('entity');
    if (!$entity) {
      $forbidden = new AccessResultForbidden('No Entity Found.');
      return $return_as_object ? $forbidden : $forbidden->isAllowed();
    }
    return $entity->access('view', $account, $return_as_object);
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $entity = $this->getEntity();
    $view_builder = $this->entityTypeManager->getViewBuilder($entity->getEntityTypeId());
    $build = $view_builder->view($entity, $this->configuration['view_mode']);

    // @todo Core provides its own recursion protection since 11.4,
    //   see https://www.drupal.org/i/2940605.
    //   Remove the recursion protection entirely once core_version_requirement
    //   is bumped to >= 11.4.
    if (version_compare(\Drupal::VERSION, '11.3.99', '<=')) {
      $build['#pre_render'][] = [$this, 'trackRecursion'];
      $build['#post_render'][] = [$this, 'releaseRecursion'];
    }

    CacheableMetadata::createFromObject($entity)
      ->merge(CacheableMetadata::createFromRenderArray($build))
      ->applyTo($build);

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks(): array {
    return ['trackRecursion', 'releaseRecursion'];
  }

  /**
   * Pre-render callback for recursion protection.
   */
  public function trackRecursion(array $build): array {
    $recursion_key = $this->getRecursionKey($build);
    if (isset(self::$recursionStack[$recursion_key])) {
      trigger_error(sprintf('Recursive rendering attempt aborted for %s. In progress: %s', $recursion_key, print_r(self::$recursionStack, TRUE)), E_USER_WARNING);
      $build['#printed'] = TRUE;
    }
    else {
      self::$recursionStack[$recursion_key] = $recursion_key;
    }
    return $build;
  }

  /**
   * Post-render callback for recursion protection.
   */
  public function releaseRecursion(string $rendered_entity, array $build): string {
    $recursion_key = $this->getRecursionKey($build);
    unset(self::$recursionStack[$recursion_key]);
    return $rendered_entity;
  }

  /**
   * Generates a key for an entity render array for recursion protection.
   *
   * @param array $build
   *   The entity render array.
   *
   * @return string
   *   The key for the recursion stack.
   *
   * @see \Drupal\Core\Entity\EntityViewBuilder::getRenderRecursionKey
   */
  protected function getRecursionKey(array $build): string {
    $entity_type = $this->getEntity()->getEntityTypeId();
    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    $entity = $build["#$entity_type"];

    $recursion_keys = [
      $entity->getEntityTypeId(),
    ];

    // If entity is new and has no ID, generate unique ID from entity object.
    // This is to prevent false positives, for example when previewing a new
    // node that is referencing a new node without either node yet being saved.
    if ($entity->id()) {
      $recursion_keys[] = 'entity_id';
      $recursion_keys[] = $entity->id();
      if ($entity instanceof RevisionableInterface) {
        $recursion_keys[] = $entity->getRevisionId();
      }
    }
    else {
      $recursion_keys[] = 'object_id';
      $recursion_keys[] = spl_object_id($entity);
    }

    // Ensure that recursion keys are not leaking between Fibers.
    if (class_exists(\Fiber::class) && $fiber = \Fiber::getCurrent()) {
      $recursion_keys[] = 'fiber_id';
      $recursion_keys[] = spl_object_id($fiber);
    }

    if ($entity instanceof TranslatableDataInterface) {
      $recursion_keys[] = $entity->language()->getId();
    }

    $recursion_keys[] = $build['#view_mode'];

    // It seems very unlikely that the same entity displayed in the same view
    // mode would be recursively nested and meant to be displayed differently.
    return implode(':', $recursion_keys);
  }

  /**
   * Gets the entity from context.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The entity.
   */
  protected function getEntity(): EntityInterface {
    return $this->getContextValue('entity');
  }

}
