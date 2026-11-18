<?php

namespace Drupal\ai_translate\Controller;

use Drupal\ai\AiProviderPluginManager;
use Drupal\ai_translate\ChatModelResolver;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\content_translation\ContentTranslationManager;
use Drupal\content_translation\ContentTranslationManagerInterface;
use Drupal\Core\Controller\ControllerResolver;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\Element;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\content_translation\Controller\ContentTranslationController;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Controller\ArgumentResolverInterface;

/**
 * Overridden class for entity translation controllers.
 *
 * @phpstan-consistent-constructor
 */
class ContentTranslationControllerOverride extends ContentTranslationController {

  /**
   * How long, in seconds, the overview is cached when it shows model names.
   */
  protected const MODEL_LABEL_MAX_AGE = 3600;

  /**
   * Model labels already fetched in this request, keyed by provider/operation.
   *
   * @var array<string, array<string, string>>
   */
  protected array $modelLabels = [];

  /**
   * Initializes a content translation controller.
   *
   * @param \Drupal\content_translation\ContentTranslationManagerInterface $manager
   *   A content translation manager instance.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager service.
   * @param \Drupal\ai\AiProviderPluginManager $providerManager
   *   The AI provider manager.
   * @param \Drupal\Core\Controller\ControllerResolver $controllerResolver
   *   The controller resolver.
   * @param \Drupal\ai_translate\ChatModelResolver $chatModelResolver
   *   Resolves the chat model used for the target language.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Symfony\Component\HttpKernel\Controller\ArgumentResolverInterface|null $argumentResolver
   *   The controller argument resolver.
   * @param \Symfony\Component\HttpFoundation\RequestStack|null $requestStack
   *   The request stack.
   */
  public function __construct(
    ContentTranslationManagerInterface $manager,
    EntityFieldManagerInterface $entity_field_manager,
    protected readonly AiProviderPluginManager $providerManager,
    protected readonly ControllerResolver $controllerResolver,
    protected readonly ChatModelResolver $chatModelResolver,
    ?TimeInterface $time = NULL,
    protected ?ArgumentResolverInterface $argumentResolver = NULL,
    protected ?RequestStack $requestStack = NULL,
  ) {
    parent::__construct($manager, $entity_field_manager, $time);
    // A subclass whose constructor does not pass these services yet still
    // works: fall back to the container.
    // @phpstan-ignore-next-line
    $this->argumentResolver ??= \Drupal::service('http_kernel.controller.argument_resolver');
    // @phpstan-ignore-next-line
    $this->requestStack ??= \Drupal::service('request_stack');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('content_translation.manager'),
      $container->get('entity_field.manager'),
      $container->get('ai.provider'),
      $container->get('controller_resolver'),
      $container->get('ai_translate.chat_model_resolver'),
      $container->get('datetime.time'),
      $container->get('http_kernel.controller.argument_resolver'),
      $container->get('request_stack'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function overview(RouteMatchInterface $route_match, $entity_type_id = NULL): array|Response {
    $build = NULL;

    $parent_controller_id = $route_match->getRouteObject()->getDefault('_parent_controller');

    // Let the original controller build the form it wants to.
    if ($parent_controller = $this->controllerResolver->getControllerFromDefinition($parent_controller_id, $route_match->getRouteObject()->getPath())) {
      // Pass the original controller the arguments it asks for, as the
      // kernel would have: a module that replaces an overview controller may
      // take other arguments than core's, such as the entity itself.
      $arguments = $this->argumentResolver->getArguments($this->requestStack->getCurrentRequest(), $parent_controller);
      $build = call_user_func_array($parent_controller, $arguments);
    }

    if (!$build) {

      // If anything is unexpected, just use our parent controller to generate
      // the build.
      $build = parent::overview($route_match, $entity_type_id);
    }

    if ($build instanceof Response) {
      return $build;
    }

    $this->alterForm($build, $route_match, $entity_type_id);

    return $build;
  }

  /**
   * Helper to alter whatever page exists at our route.
   *
   * @param array $form
   *   The built form.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   * @param mixed $entity_type_id
   *   The entity type ID.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function alterForm(array &$form, RouteMatchInterface $route_match, mixed $entity_type_id = NULL): void {
    // Without a default provider, translating fails, also for languages with
    // their own model.
    if (!_ai_translate_check_default_provider_and_model()) {
      return;
    }
    $overview = NULL;

    foreach (Element::children($form) as $child) {
      if (!$overview) {
        if (isset($form[$child]['#theme']) && $form[$child]['#theme'] == 'table') {
          $overview = &$form[$child];
        }
        elseif (isset($form[$child]['#type']) && $form[$child]['#type'] == 'tableselect') {
          $overview = &$form[$child];
        }
      }
    }

    if (!$overview) {
      return;
    }

    $languages = $this->languageManager()->getLanguages();

    $entity = $route_match->getParameter($entity_type_id);
    $entity_id = $entity->id();
    /** @var Drupal\Core\Entity\RevisionableStorageInterface $storage */
    $storage = $this->entityTypeManager()->getStorage($entity_type_id);
    $default_revision = $storage->load($entity_id);
    $entity_type = $entity->getEntityType();

    $use_latest_revisions = $entity_type->isRevisionable() && ContentTranslationManager::isPendingRevisionSupportEnabled($entity_type_id, $entity->bundle());
    $lang_from = $entity->getUntranslated()->language()->getId();

    $show_model_name = (bool) $this->config('ai_translate.settings')->get('show_model_name');
    $cacheability = (new CacheableMetadata())
      ->addCacheableDependency($this->config('ai_translate.settings'))
      ->addCacheableDependency($this->config('ai.settings'));
    if ($show_model_name) {
      // A provider can rename or withdraw a model on its side, and no cache
      // tag reports that. The lifetime bounds how long an old name stays.
      $cacheability->setCacheMaxAge(self::MODEL_LABEL_MAX_AGE);
    }
    $key = 0;

    // Process translation in 2 passes.
    // In the first pass, build translation links with access check.
    // In the second pass, add translation links to the overview.
    // Do the second pass only if there is at least one link to translate.
    $hasTranslationLinks = FALSE;
    $aiTranslationLinks = [];

    // Pass 1.
    foreach ($languages as $langcode => $language) {
      if (isset($overview['#rows'][$key])) {
        $row = &$overview['#rows'][$key];
        $key++;
      }
      elseif (isset($overview['#options'][$langcode])) {
        $row = &$overview['#options'][$langcode];
      }
      else {
        $row = NULL;
      }

      // If we don't have a row, we cannot do anything.
      if ($row) {
        // Get the latest revision.
        // This logic comes from web/core/modules/content_translation/src/Controller/ContentTranslationController.php.
        if ($use_latest_revisions) {
          $entity = $default_revision;
          $latest_revision_id = $storage->getLatestTranslationAffectedRevisionId($entity->id(), $langcode);
          if ($latest_revision_id) {
            /** @var \Drupal\Core\Entity\ContentEntityInterface $latest_revision */
            $latest_revision = $storage->loadRevision($latest_revision_id);
            // Make sure we do not list removed translations, i.e.
            // translations that have been part of a default revision but no
            // longer are.
            if (!$latest_revision->wasDefaultRevision() || $default_revision->hasTranslation($langcode)) {
              $entity = $latest_revision;
            }
          }
        }

        $additional = '';
        if ($lang_from !== $langcode && !$entity->hasTranslation($langcode)) {
          $link = Link::createFromRoute($this->t('Translate with AI'),
            'ai_translate.translate_content', [
              'entity_type' => $entity_type_id,
              'entity_id' => $entity_id,
              'lang_from' => $lang_from,
              'lang_to' => $langcode,
            ]
          );
          $access = $link->getUrl()->access(NULL, TRUE);
          $cacheability->addCacheableDependency($access);
          if ($access->isAllowed()) {
            $content = ['link' => $link->toRenderable()];
            if ($show_model_name && ($label = $this->getModelLabel($language, $cacheability)) !== NULL) {
              $content['model'] = [
                '#type' => 'container',
                '#attributes' => ['class' => ['description']],
                'label' => ['#markup' => $this->t('Model: @model', ['@model' => $label])],
              ];
            }
            $additional = ['data' => $content];
            $hasTranslationLinks = TRUE;
          }
        }
        else {
          $additional = $this->t('n/a');
        }
        $aiTranslationLinks[$langcode] = $additional;
      }
    }
    // Pass 2. Add a column to the table header, and translation link(s).
    if ($hasTranslationLinks) {
      // Inject our additional column into the header.
      array_splice($overview['#header'], -1, 0,
        [$this->t('AI Translations')]);
      $key = 0;
      foreach ($languages as $langcode => $language) {
        if (isset($overview['#rows'][$key])) {
          $row = &$overview['#rows'][$key];
          $key++;
        }
        elseif (isset($overview['#options'][$langcode])) {
          $row = &$overview['#options'][$langcode];
        }
        else {
          continue;
        }
        array_splice($row, -1, 0, [$aiTranslationLinks[$langcode]]);
      }
    }
    $cacheability->merge(CacheableMetadata::createFromRenderArray($form))->applyTo($form);
  }

  /**
   * Returns the selected model's display label without making translation fail.
   *
   * Native translation providers use the translate_text default. The chat
   * proxy can override it per language, so it must use the execution resolver.
   *
   * @param \Drupal\Core\Language\LanguageInterface $language
   *   The target language.
   * @param \Drupal\Core\Cache\CacheableMetadata $cacheability
   *   Collects the selected provider's configuration dependencies.
   *
   * @return string|null
   *   The readable model label, or NULL when it cannot be retrieved.
   */
  protected function getModelLabel(LanguageInterface $language, CacheableMetadata $cacheability): ?string {
    try {
      $selection = $this->providerManager->getDefaultProviderForOperationType('translate_text');
      $operation = 'translate_text';
      if (($selection['provider_id'] ?? '') === 'chat_translation') {
        $selection = $this->chatModelResolver->resolve($selection['model_id'] ?? '', $language)?->toArray();
        $operation = 'chat';
      }
      if (empty($selection['provider_id']) || empty($selection['model_id'])) {
        return NULL;
      }

      $provider = $this->providerManager->createInstance($selection['provider_id']);
      $cacheability->addCacheableDependency($provider->getConfig());
      $key = $selection['provider_id'] . ':' . $operation;
      $this->modelLabels[$key] ??= $provider->getConfiguredModels($operation);
      $label = $this->modelLabels[$key][$selection['model_id']] ?? NULL;
      return is_string($label) && trim($label) !== '' ? $label : NULL;
    }
    catch (\Exception $e) {
      // Model names are optional. Retry on the next request if lookup failed.
      $cacheability->setCacheMaxAge(0);
      $this->getLogger('ai_translate')->warning('Could not load the translation model label: @message', [
        '@message' => $e->getMessage(),
      ]);
      return NULL;
    }
  }

}
