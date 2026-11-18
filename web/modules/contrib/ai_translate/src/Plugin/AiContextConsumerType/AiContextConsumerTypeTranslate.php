<?php

declare(strict_types=1);

namespace Drupal\ai_translate\Plugin\AiContextConsumerType;

use Drupal\ai_context\Attribute\AiContextConsumerType;
use Drupal\ai_context\Model\AiContextConsumerId;
use Drupal\ai_context\Model\AiContextConsumerInstance;
use Drupal\ai_context\Model\AiContextConsumerInstanceCollection;
use Drupal\ai_context\Model\AiContextProviderRequestContext;
use Drupal\ai_context\Plugin\AiContextConsumerType\AiContextConsumerTypeBase;
use Drupal\ai_context\Service\AiContextSelectionFactoryInterface;
use Drupal\ai_translate\AiTranslateRequestTags;
use Drupal\ai_translate\AiTranslateContext;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Translate context consumer type.
 *
 * Lists one Context Control Center consumer for chat-proxy
 * translations. Automatic context push stays off until an admin
 * turns it on. A request is matched only by the ai_translate
 * provider request tag. Native translate_text providers never emit
 * a chat request and are not this consumer. Agent-driven translation
 * stays on agent:{id}. Selection mode follows the site default
 * (inherit). Context consumer IDs use the short type
 * prefix translate: (same pattern as agent:, automator:, and
 * ckeditor:). Provider request tags use the module name
 * ai_translate.
 */
#[AiContextConsumerType(
  id: self::PLUGIN_ID,
  label: new TranslatableMarkup('Translate'),
  description: new TranslatableMarkup(
    'AI Translate chat-proxy translations that receive pushed context in their system prompt.',
  ),
)]
final class AiContextConsumerTypeTranslate extends AiContextConsumerTypeBase {

  /**
   * The consumer type plugin ID.
   */
  public const PLUGIN_ID = 'translate';

  /**
   * The single instance ID.
   */
  public const INSTANCE_ID = 'default';

  /**
   * Canonical ID of the single consumer instance.
   */
  public const CONSUMER_ID = AiTranslateContext::CONSUMER_ID;

  /**
   * Config name of the per-type settings in the Context Control Center.
   */
  public const TYPE_SETTINGS_CONFIG = AiTranslateContext::TYPE_SETTINGS_CONFIG;

  /**
   * Type setting: keep one cached translation per string while pushing.
   *
   * @see \Drupal\ai_translate\TextTranslator::entityContextSegment()
   */
  public const SHARE_ENTITY_CACHE = AiTranslateContext::SHARE_ENTITY_CACHE;

  /**
   * The AI Context selection factory, which reports the push gate.
   */
  private AiContextSelectionFactoryInterface $selectionFactory;

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ): static {
    $instance = parent::create(
      $container,
      $configuration,
      $plugin_id,
      $plugin_definition,
    );
    $instance->selectionFactory = $container
      ->get('ai_context.selection_factory');
    return $instance;
  }

  /**
   * {@inheritdoc}
   *
   * Adds the shared-cache option below the enabled flag. It is stored
   * only while checked, so exported config carries the key only when a
   * site builder opted in.
   */
  public function buildTypeSettingsForm(
    array $form,
    FormStateInterface $form_state,
    array $settings,
  ): array {
    $form = parent::buildTypeSettingsForm($form, $form_state, $settings);

    $description = $this->t('Reuse one cached translation of a string across all content, even while context is pushed into chat-proxy translations. Only enable this if none of the context items AI Translate can receive depend on the content being translated, for example through the Entity Types, Taxonomy or Specific Entities scopes. Otherwise a translation made with the context of one content item can be reused for, and saved to, another one. While this is off, each content item gets its own cached translation, which means more provider calls. This only applies while AI Translate caches translation results and context is pushed.');
    if (!$this->selectionFactory->isPushAllowed(self::CONSUMER_ID)) {
      $description = $this->t('@description Push is currently off for the AI Translate consumer, so this setting does nothing until push is turned on under Context consumers.', [
        '@description' => $description,
      ]);
    }

    $form[self::SHARE_ENTITY_CACHE] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Share cached translations across content while context is pushed'),
      '#description' => $description,
      '#default_value' => (bool) ($settings[self::SHARE_ENTITY_CACHE] ?? FALSE),
      '#states' => [
        'visible' => [
          ':input[name="enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitTypeSettingsForm(
    array &$form,
    FormStateInterface $form_state,
  ): array {
    $settings = parent::submitTypeSettingsForm($form, $form_state);
    // NULL clears the key, so off (the default) is not exported.
    $settings[self::SHARE_ENTITY_CACHE] = $form_state->getValue(self::SHARE_ENTITY_CACHE)
      ? TRUE
      : NULL;

    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function getLabelRoute(string $instanceId): ?array {
    if ($instanceId !== self::INSTANCE_ID) {
      return NULL;
    }
    return [
      'route_name' => 'ai_translate.settings_form',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getRoutingRequestTags(): array {
    return [AiTranslateRequestTags::ROUTING];
  }

  /**
   * {@inheritdoc}
   */
  public function getInstances(): AiContextConsumerInstanceCollection {
    $cache = $this->createInstanceCacheability();

    $instances = [
      new AiContextConsumerInstance(
        AiContextConsumerId::fromParts(
          self::PLUGIN_ID,
          self::INSTANCE_ID,
        ),
        (string) $this->t('AI Translate'),
        (string) $this->t(
          'UI, Drush, and interface chat-proxy translations share this consumer. Native translate_text providers never receive this push. Agent-driven translation uses agent consumers instead. Language pairs share one subscription set.',
        ),
      ),
    ];

    return new AiContextConsumerInstanceCollection($instances, $cache);
  }

  /**
   * {@inheritdoc}
   *
   * Requests tagged ai_agents or ai_assistant_api never reach this
   * method: AiContextConsumerRouter::getRoutingTypeIds() returns no
   * types for them, so agent-driven translation stays on agent:{id}.
   */
  public function resolveConsumerId(
    AiContextProviderRequestContext $providerRequestContext,
  ): ?AiContextConsumerId {
    if (!$providerRequestContext->hasRequestTag(
      AiTranslateRequestTags::ROUTING,
    )) {
      return NULL;
    }

    return AiContextConsumerId::fromParts(
      self::PLUGIN_ID,
      self::INSTANCE_ID,
    );
  }

}
