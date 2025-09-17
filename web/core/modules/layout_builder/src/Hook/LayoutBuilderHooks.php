<?php

namespace Drupal\layout_builder\Hook;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Link;
use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\layout_builder\InlineBlockEntityOperations;
use Drupal\layout_builder\Plugin\Block\ExtraFieldBlock;
use Drupal\Core\Render\Element;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\field\FieldConfigInterface;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\Core\Form\FormStateInterface;
use Drupal\layout_builder\Form\OverridesEntityForm;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\layout_builder\Form\LayoutBuilderEntityViewDisplayForm;
use Drupal\layout_builder\Form\DefaultsEntityForm;
use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplayStorage;
use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay;
use Drupal\Core\Url;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\Order;

/**
 * Hook implementations for layout_builder.
 */
class LayoutBuilderHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, RouteMatchInterface $route_match): ?string {
    // Add help text to the Layout Builder UI.
    if ($route_match->getRouteObject()->getOption('_layout_builder')) {
      $output = '<p>' . $this->t('This layout builder tool allows you to configure the layout of the main content area.') . '</p>';
      if (\Drupal::currentUser()->hasPermission('administer blocks')) {
        $output .= '<p>' . $this->t('To manage other areas of the page, use the <a href="@block-ui">block administration page</a>.', ['@block-ui' => Url::fromRoute('block.admin_display')->toString()]) . '</p>';
      }
      else {
        $output .= '<p>' . $this->t('To manage other areas of the page, use the block administration page.') . '</p>';
      }
      $output .= '<p>' . $this->t('Forms and links inside the content of the layout builder tool have been disabled.') . '</p>';
      return $output;
    }
    switch ($route_name) {
      case 'help.page.layout_builder':
        $output = '<h2>' . $this->t('About') . '</h2>';
        $output .= '<p>' . $this->t('Layout Builder allows you to use layouts to customize how content, content blocks, and other <a href=":field_help" title="Field module help, with background on content entities">content entities</a> are displayed.', [
          ':field_help' => Url::fromRoute('help.page', [
            'name' => 'field',
          ])->toString(),
        ]) . '</p>';
        $output .= '<p>' . $this->t('For more information, see the <a href=":layout-builder-documentation">online documentation for the Layout Builder module</a>.', [
          ':layout-builder-documentation' => 'https://www.drupal.org/docs/8/core/modules/layout-builder',
        ]) . '</p>';
        $output .= '<h2>' . $this->t('Uses') . '</h2>';
        $output .= '<dl>';
        $output .= '<dt>' . $this->t('Default layouts') . '</dt>';
        $output .= '<dd>' . $this->t('Layout Builder can be selectively enabled on the "Manage Display" page in the <a href=":field_ui">Field UI</a>. This allows you to control the output of each type of display individually. For example, a "Basic page" might have view modes such as Full and Teaser, with each view mode having different layouts selected.', [
          ':field_ui' => Url::fromRoute('help.page', [
            'name' => 'field_ui',
          ])->toString(),
        ]) . '</dd>';
        $output .= '<dt>' . $this->t('Overridden layouts') . '</dt>';
        $output .= '<dd>' . $this->t('If enabled, each individual content item can have a custom layout. Once the layout for an individual content item is overridden, changes to the Default layout will no longer affect it. Overridden layouts may be reverted to return to matching and being synchronized to their Default layout.') . '</dd>';
        $output .= '<dt>' . $this->t('User permissions') . '</dt>';
        $output .= '<dd>' . $this->t('The Layout Builder module makes a number of permissions available, which can be set by role on the <a href=":permissions">permissions page</a>. For more information, see the <a href=":layout-builder-permissions">Configuring Layout Builder permissions</a> online documentation.', [
          ':permissions' => Url::fromRoute('user.admin_permissions.module', [
            'modules' => 'layout_builder',
          ])->toString(),
          ':layout-builder-permissions' => 'https://www.drupal.org/docs/8/core/modules/layout-builder/configuring-layout-builder-permissions',
        ]) . '</dd>';
        $output .= '</dl>';
        return $output;
    }
    return NULL;
  }

  /**
   * Implements hook_entity_type_alter().
   */
  #[Hook('entity_type_alter')]
  public function entityTypeAlter(array &$entity_types) : void {
    /** @var \Drupal\Core\Entity\EntityTypeInterface[] $entity_types */
    $entity_types['entity_view_display']->setClass(LayoutBuilderEntityViewDisplay::class)->setStorageClass(LayoutBuilderEntityViewDisplayStorage::class)->setFormClass('layout_builder', DefaultsEntityForm::class)->setFormClass('edit', LayoutBuilderEntityViewDisplayForm::class);
    // Ensure every fieldable entity type has a layout form.
    foreach ($entity_types as $entity_type) {
      if ($entity_type->entityClassImplements(FieldableEntityInterface::class)) {
        $entity_type->setFormClass('layout_builder', OverridesEntityForm::class);
      }
    }
  }

  /**
   * Implements hook_form_FORM_ID_alter() for \Drupal\field_ui\Form\EntityFormDisplayEditForm.
   */
  #[Hook('form_entity_form_display_edit_form_alter')]
  public function formEntityFormDisplayEditFormAlter(&$form, FormStateInterface $form_state) : void {
    // Hides the Layout Builder field. It is rendered directly in
    // \Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay::buildMultiple().
    unset($form['fields'][OverridesSectionStorage::FIELD_NAME]);
    $key = array_search(OverridesSectionStorage::FIELD_NAME, $form['#fields']);
    if ($key !== FALSE) {
      unset($form['#fields'][$key]);
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_insert().
   */
  #[Hook('field_config_insert')]
  public function fieldConfigInsert(FieldConfigInterface $field_config): void {
    // Clear the sample entity for this entity type and bundle.
    $sample_entity_generator = \Drupal::service('layout_builder.sample_entity_generator');
    $sample_entity_generator->delete($field_config->getTargetEntityTypeId(), $field_config->getTargetBundle());
    \Drupal::service('plugin.manager.block')->clearCachedDefinitions();
  }

  /**
   * Implements hook_ENTITY_TYPE_delete().
   */
  #[Hook('field_config_delete')]
  public function fieldConfigDelete(FieldConfigInterface $field_config): void {
    // Clear the sample entity for this entity type and bundle.
    $sample_entity_generator = \Drupal::service('layout_builder.sample_entity_generator');
    $sample_entity_generator->delete($field_config->getTargetEntityTypeId(), $field_config->getTargetBundle());
    \Drupal::service('plugin.manager.block')->clearCachedDefinitions();
  }

  /**
   * Implements hook_entity_view_alter().
   *
   * ExtraFieldBlock block plugins add placeholders for each extra field which
   * is configured to be displayed. Those placeholders are replaced by this
   * hook. Modules that implement hook_entity_extra_field_info() use their
   * implementations of hook_entity_view_alter() to add the rendered output of
   * the extra fields they provide, so we cannot get the rendered output of
   * extra fields before this point in the view process.
   * layout_builder_module_implements_alter() moves this implementation of
   * hook_entity_view_alter() to the end of the list.
   *
   * @see \Drupal\layout_builder\Plugin\Block\ExtraFieldBlock::build()
   * @see layout_builder_module_implements_alter()
   */
  #[Hook('entity_view_alter', order: Order::Last)]
  public function entityViewAlter(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display): void {
    // Only replace extra fields when Layout Builder has been used to alter the
    // build. See \Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay::buildMultiple().
    if (isset($build['_layout_builder']) && !Element::isEmpty($build['_layout_builder'])) {
      /** @var \Drupal\Core\Entity\EntityFieldManagerInterface $field_manager */
      $field_manager = \Drupal::service('entity_field.manager');
      $extra_fields = $field_manager->getExtraFields($entity->getEntityTypeId(), $entity->bundle());
      if (!empty($extra_fields['display'])) {
        foreach ($extra_fields['display'] as $field_name => $extra_field) {
          // If the extra field is not set replace with an empty array to avoid
          // the placeholder text from being rendered.
          $replacement = $build[$field_name] ?? [];
          ExtraFieldBlock::replaceFieldPlaceholder($build, $replacement, $field_name);
          // After the rendered field in $build has been copied over to the
          // ExtraFieldBlock block we must remove it from its original location
          // or else it will be rendered twice.
          unset($build[$field_name]);
        }
      }
    }
    $route_name = \Drupal::routeMatch()->getRouteName();
    // If the entity is displayed within a Layout Builder block and the current
    // route is in the Layout Builder UI, then remove all contextual link
    // placeholders.
    if ($route_name && $display instanceof LayoutBuilderEntityViewDisplay && str_starts_with($route_name, 'layout_builder.')) {
      unset($build['#contextual_links']);
    }
  }

  /**
   * Implements hook_entity_build_defaults_alter().
   */
  #[Hook('entity_build_defaults_alter')]
  public function entityBuildDefaultsAlter(array &$build, EntityInterface $entity, $view_mode): void {
    // Contextual links are removed for entities viewed in Layout Builder's UI.
    // The route.name.is_layout_builder_ui cache context accounts for this
    // difference.
    // @see layout_builder_entity_view_alter()
    // @see \Drupal\layout_builder\Cache\LayoutBuilderUiCacheContext
    $build['#cache']['contexts'][] = 'route.name.is_layout_builder_ui';
  }

  /**
   * Implements hook_entity_presave().
   */
  #[Hook('entity_presave')]
  public function entityPresave(EntityInterface $entity): void {
    if (\Drupal::moduleHandler()->moduleExists('block_content')) {
      /** @var \Drupal\layout_builder\InlineBlockEntityOperations $entity_operations */
      $entity_operations = \Drupal::classResolver(InlineBlockEntityOperations::class);
      $entity_operations->handlePreSave($entity);
    }
  }

  /**
   * Implements hook_entity_delete().
   */
  #[Hook('entity_delete')]
  public function entityDelete(EntityInterface $entity): void {
    if (\Drupal::moduleHandler()->moduleExists('block_content')) {
      /** @var \Drupal\layout_builder\InlineBlockEntityOperations $entity_operations */
      $entity_operations = \Drupal::classResolver(InlineBlockEntityOperations::class);
      $entity_operations->handleEntityDelete($entity);
    }
  }

  /**
   * Implements hook_cron().
   */
  #[Hook('cron')]
  public function cron(): void {
    if (\Drupal::moduleHandler()->moduleExists('block_content')) {
      /** @var \Drupal\layout_builder\InlineBlockEntityOperations $entity_operations */
      $entity_operations = \Drupal::classResolver(InlineBlockEntityOperations::class);
      $entity_operations->removeUnused();
    }
  }

  /**
   * Implements hook_plugin_filter_TYPE__CONSUMER_alter().
   */
  #[Hook('plugin_filter_block__layout_builder_alter')]
  public function pluginFilterBlockLayoutBuilderAlter(array &$definitions, array $extra): void {
    // Remove blocks that are not useful within Layout Builder.
    unset($definitions['system_messages_block']);
    unset($definitions['help_block']);
    unset($definitions['local_tasks_block']);
    unset($definitions['local_actions_block']);
    // Remove blocks that are non-functional within Layout Builder.
    unset($definitions['system_main_block']);
    // @todo Restore the page title block in https://www.drupal.org/node/2938129.
    unset($definitions['page_title_block']);
  }

  /**
   * Implements hook_plugin_filter_TYPE_alter().
   */
  #[Hook('plugin_filter_block_alter')]
  public function pluginFilterBlockAlter(array &$definitions, array $extra, $consumer): void {
    // @todo Determine the 'inline_block' blocks should be allowed outside
    //   of layout_builder https://www.drupal.org/node/2979142.
    if ($consumer !== 'layout_builder' || !isset($extra['list']) || $extra['list'] !== 'inline_blocks') {
      foreach ($definitions as $id => $definition) {
        if ($definition['id'] === 'inline_block') {
          unset($definitions[$id]);
        }
      }
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_access().
   */
  #[Hook('block_content_access')]
  public function blockContentAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResultInterface {
    /** @var \Drupal\block_content\BlockContentInterface $entity */
    if ($operation === 'view' || $entity->isReusable() || empty(\Drupal::service('inline_block.usage')->getUsage($entity->id()))) {
      // If the operation is 'view' or this is reusable block or if this is
      // non-reusable that isn't used by this module then don't alter the
      // access.
      return AccessResult::neutral();
    }
    if ($account->hasPermission('create and edit custom blocks')) {
      return AccessResult::allowed();
    }
    return AccessResult::forbidden();
  }

  /**
   * Implements hook_plugin_filter_TYPE__CONSUMER_alter().
   */
  #[Hook('plugin_filter_block__block_ui_alter')]
  public function pluginFilterBlockBlockUiAlter(array &$definitions, array $extra): void {
    foreach ($definitions as $id => $definition) {
      // Filter out any layout_builder-provided block that has required context
      // definitions.
      if ($definition['provider'] === 'layout_builder' && !empty($definition['context_definitions'])) {
        /** @var \Drupal\Core\Plugin\Context\ContextDefinitionInterface $context_definition */
        foreach ($definition['context_definitions'] as $context_definition) {
          if ($context_definition->isRequired()) {
            unset($definitions[$id]);
            break;
          }
        }
      }
    }
  }

  /**
   * Implements hook_plugin_filter_TYPE__CONSUMER_alter().
   */
  #[Hook('plugin_filter_layout__layout_builder_alter')]
  public function pluginFilterLayoutLayoutBuilderAlter(array &$definitions, array $extra): void {
    // Remove layouts provide by layout discovery that are not needed because of
    // layouts provided by this module.
    $duplicate_layouts = [
      'layout_twocol',
      'layout_twocol_bricks',
      'layout_threecol_25_50_25',
      'layout_threecol_33_34_33',
    ];
    foreach ($duplicate_layouts as $duplicate_layout) {
      /** @var \Drupal\Core\Layout\LayoutDefinition[] $definitions */
      if (isset($definitions[$duplicate_layout])) {
        if ($definitions[$duplicate_layout]->getProvider() === 'layout_discovery') {
          unset($definitions[$duplicate_layout]);
        }
      }
    }
    // Move the one column layout to the top.
    if (isset($definitions['layout_onecol']) && $definitions['layout_onecol']->getProvider() === 'layout_discovery') {
      $one_col = $definitions['layout_onecol'];
      unset($definitions['layout_onecol']);
      $definitions = ['layout_onecol' => $one_col] + $definitions;
    }
  }

  /**
   * Implements hook_plugin_filter_TYPE_alter().
   */
  #[Hook('plugin_filter_layout_alter')]
  public function pluginFilterLayoutAlter(array &$definitions, array $extra, $consumer): void {
    // Hide the blank layout plugin from listings.
    unset($definitions['layout_builder_blank']);
  }

  /**
   * Implements hook_system_breadcrumb_alter().
   */
  #[Hook('system_breadcrumb_alter')]
  public function systemBreadcrumbAlter(Breadcrumb &$breadcrumb, RouteMatchInterface $route_match, array $context): void {
    // Remove the extra 'Manage display' breadcrumb for Layout Builder defaults.
    if ($route_match->getRouteObject() && $route_match->getRouteObject()->hasOption('_layout_builder') && $route_match->getParameter('section_storage_type') === 'defaults') {
      $links = array_filter($breadcrumb->getLinks(), function (Link $link) use ($route_match) {
          $entity_type_id = $route_match->getParameter('entity_type_id');
        if (!$link->getUrl()->isRouted()) {
                return TRUE;
        }
          return $link->getUrl()->getRouteName() !== "entity.entity_view_display.{$entity_type_id}.default";
      });
      // Links cannot be removed from an existing breadcrumb object. Create a
      // new object but carry over the cacheable metadata.
      $cacheability = CacheableMetadata::createFromObject($breadcrumb);
      $breadcrumb = new Breadcrumb();
      $breadcrumb->setLinks($links);
      $breadcrumb->addCacheableDependency($cacheability);
    }
  }

  /**
   * Implements hook_entity_translation_create().
   */
  #[Hook('entity_translation_create')]
  public function entityTranslationCreate(EntityInterface $translation): void {
    /** @var \Drupal\Core\Entity\FieldableEntityInterface $translation */
    if ($translation->hasField(OverridesSectionStorage::FIELD_NAME) && $translation->getFieldDefinition(OverridesSectionStorage::FIELD_NAME)->isTranslatable()) {
      // When creating a new translation do not copy untranslated sections
      // because per-language layouts are not supported.
      $translation->set(OverridesSectionStorage::FIELD_NAME, []);
    }
  }

  /**
   * Implements hook_theme_registry_alter().
   */
  #[Hook('theme_registry_alter')]
  public function themeRegistryAlter(&$theme_registry): void {
    // Move our preprocess to run after
    // content_translation_preprocess_language_content_settings_table().
    if (!empty($theme_registry['language_content_settings_table']['preprocess functions'])) {
      $preprocess_functions =& $theme_registry['language_content_settings_table']['preprocess functions'];
      $index = array_search('layout_builder_preprocess_language_content_settings_table', $preprocess_functions);
      if ($index !== FALSE) {
        unset($preprocess_functions[$index]);
        $preprocess_functions[] = 'layout_builder_preprocess_language_content_settings_table';
      }
    }
  }

  /**
   * Implements hook_theme_suggestions_HOOK_alter().
   */
  #[Hook('theme_suggestions_field_alter')]
  public function themeSuggestionsFieldAlter(&$suggestions, array $variables): void {
    $element = $variables['element'];
    if (isset($element['#third_party_settings']['layout_builder']['view_mode'])) {
      // See system_theme_suggestions_field().
      $suggestions[] = 'field__' . $element['#entity_type'] . '__' . $element['#field_name'] . '__' . $element['#bundle'] . '__' . $element['#third_party_settings']['layout_builder']['view_mode'];
    }
  }

}
