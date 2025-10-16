<?php

namespace Drupal\ai_observability\Form;

use Drupal\ai\Event\PreGenerateResponseEvent;
use Drupal\ai\Event\PostGenerateResponseEvent;
use Drupal\ai\Event\PostStreamingResponseEvent;
use Drupal\ai\Event\ProviderDisabledEvent;
use Drupal\ai_observability\EventSubscriber\AiEventsSubscriber;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure AI Observability settings.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * Config settings.
   */
  const CONFIG_NAME = AiEventsSubscriber::CONFIG_NAME;

  /**
   * A TypedConfigManager.
   *
   * @var \Drupal\Core\Config\TypedConfigManagerInterface
   */
  protected TypedConfigManagerInterface $configTyped;

  /**
   * The kernel service.
   *
   * @var \Drupal\Core\DrupalKernelInterface
   */
  protected $kernel;

  /**
   * The typed configuration for settings.
   *
   * @var \Drupal\Core\Config\Schema\TypedConfigInterface|null
   */
  protected $settingsTyped;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create(...func_get_args());
    $instance->kernel = $container->get('kernel');
    $instance->configTyped = $container->get('config.typed');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ai_observability_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      self::CONFIG_NAME,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(self::CONFIG_NAME);

    $form['description'] = [
      '#markup' => $this->t('Configures the observability settings for AI events. Allow to track individual requests, expenses, input and output texts, and other details as logs, metrics and traces.'),
    ];

    $form['logger'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Drupal Logger'),
      '#description' => $this->t('Submit AI usage info to the Drupal logger. To get more details in the logs, install a structured logger that is able to store metadata, for example, <a href="https://www.drupal.org/project/logger">Logger</a> or <a href="https://www.drupal.org/project/extended_logger">Extended Logger</a>.'),
      '#description_display' => 'before',
    ];

    $form['logger'][AiEventsSubscriber::CONFIG_KEY_LOG_EVENT_TYPES] = [
      '#type' => 'checkboxes',
      '#title' => $this->getSettingLabel(AiEventsSubscriber::CONFIG_KEY_LOG_EVENT_TYPES),
      '#description' => $this->t('Choose which event types should be logged.'),
      // @todo Read supported events from the constant.
      '#options' => [
        PreGenerateResponseEvent::class => $this->t('Pre-generate response event'),
        PostGenerateResponseEvent::class => $this->t('Post-generate response event'),
        PostStreamingResponseEvent::class => $this->t('Post-streaming response event'),
        ProviderDisabledEvent::class => $this->t('Provider disabled event'),
      ],
      '#default_value' => $config->get(AiEventsSubscriber::CONFIG_KEY_LOG_EVENT_TYPES),
      '#config_target' => static::CONFIG_NAME . ':' . AiEventsSubscriber::CONFIG_KEY_LOG_EVENT_TYPES,
    ];

    $eventDescriptions = [
      PreGenerateResponseEvent::class => [
        'title' => $this->t('Pre-generate response event'),
        'description' => $this->t('Before sending a request to the AI provider.'),
      ],
      PostGenerateResponseEvent::class => [
        'title' => $this->t('Post-generate response event'),
        'description' => $this->t('When the response from a provider is received.'),
      ],
      PostStreamingResponseEvent::class => [
        'title' => $this->t('Post-streaming response event'),
        'description' => $this->t('When the streaming response is finished.'),
      ],
      ProviderDisabledEvent::class => [
        'title' => $this->t('Provider disabled event'),
        'description' => $this->t('When the AI provider is disabled.'),
      ],
    ];

    foreach (AiEventsSubscriber::SUPPORTED_EVENTS as $event) {
      $form['logger'][AiEventsSubscriber::CONFIG_KEY_LOG_EVENT_TYPES]['#options'][$event] = $eventDescriptions[$event]['title'];
      $form['logger'][AiEventsSubscriber::CONFIG_KEY_LOG_EVENT_TYPES][$event]['#description'] = $eventDescriptions[$event]['description'];
    }
    $currentValues = $config->get(AiEventsSubscriber::CONFIG_KEY_LOG_EVENT_TYPES) ?? [];
    $form['logger'][AiEventsSubscriber::CONFIG_KEY_LOG_EVENT_TYPES]['#default_value'] = array_merge($currentValues, $currentValues);

    $form['logger'][AiEventsSubscriber::CONFIG_KEY_LOG_INPUT] = [
      '#type' => 'checkbox',
      '#title' => $this->getSettingLabel(AiEventsSubscriber::CONFIG_KEY_LOG_INPUT),
      '#description' => $this->t('Enables logging input data (input messages text).'),
      '#default_value' => $config->get(AiEventsSubscriber::CONFIG_KEY_LOG_INPUT),
      '#config_target' => static::CONFIG_NAME . ':' . AiEventsSubscriber::CONFIG_KEY_LOG_INPUT,
    ];

    $form['logger'][AiEventsSubscriber::CONFIG_KEY_LOG_OUTPUT] = [
      '#type' => 'checkbox',
      '#title' => $this->getSettingLabel(AiEventsSubscriber::CONFIG_KEY_LOG_OUTPUT),
      '#description' => $this->t('Enables logging output data (provider response).'),
      '#default_value' => $config->get(AiEventsSubscriber::CONFIG_KEY_LOG_OUTPUT),
      '#config_target' => static::CONFIG_NAME . ':' . AiEventsSubscriber::CONFIG_KEY_LOG_OUTPUT,
    ];

    $form['logger'][AiEventsSubscriber::CONFIG_KEY_LOG_TAGS] = [
      '#type' => 'textfield',
      '#title' => $this->getSettingLabel(AiEventsSubscriber::CONFIG_KEY_LOG_TAGS),
      '#description' => $this->t('You can limit logging to only specific tags by entering a comma-separated list of tags. Keep empty to log events with any tag.'),
      '#default_value' => $config->get(AiEventsSubscriber::CONFIG_KEY_LOG_TAGS),
      '#config_target' => static::CONFIG_NAME . ':' . AiEventsSubscriber::CONFIG_KEY_LOG_TAGS,
    ];

    $form['logger'][AiEventsSubscriber::CONFIG_KEY_FALLBACK_LOG_MESSAGE_MODE] = [
      '#type' => 'checkbox',
      '#title' => $this->getSettingLabel(AiEventsSubscriber::CONFIG_KEY_FALLBACK_LOG_MESSAGE_MODE),
      '#description' => $this->t('Enables generating the log message text using the Drupal style of placeholders, instead of the PSR3-style. This makes the log message compatible with the core Drupal Logger. Uncheck this to minimize the log entry size and increase the performance, if you use a structured logger like <a href="https://www.drupal.org/project/logger">Logger</a> or <a href="https://www.drupal.org/project/extended_logger">Extended Logger</a>.'),
      '#default_value' => $config->get(AiEventsSubscriber::CONFIG_KEY_FALLBACK_LOG_MESSAGE_MODE),
      '#config_target' => static::CONFIG_NAME . ':' . AiEventsSubscriber::CONFIG_KEY_FALLBACK_LOG_MESSAGE_MODE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Apply form state values transformation on the validation step, instead of
    // the submit, because ConfigFormBase::validateForm() requires the values to
    // be valid to store in the configuration.
    $form_state->setValue(AiEventsSubscriber::CONFIG_KEY_LOG_EVENT_TYPES, self::formOptionsToList($form_state->getValue(AiEventsSubscriber::CONFIG_KEY_LOG_EVENT_TYPES)));
    $form_state->setValue(AiEventsSubscriber::CONFIG_KEY_LOG_TAGS, array_filter(array_map('trim', explode(',', $form_state->getValue(AiEventsSubscriber::CONFIG_KEY_LOG_TAGS)))));
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    // Invalidate the container to ensure subscribers are rebuilt with updated
    // configuration.
    $this->kernel->invalidateContainer();
  }

  /**
   * Converts an array of form options into a list of selected values.
   *
   * @param array $options
   *   The submitted values from the form.
   *
   * @return array
   *   An array of values that were checked.
   */
  private static function formOptionsToList(array $options): array {
    return array_values(
      array_filter(
        $options, function ($value) {
          return $value != 0;
        }, ARRAY_FILTER_USE_BOTH
      )
    );
  }

  /**
   * Gets the label for a setting from typed settings object.
   */
  private function getSettingLabel(string $key, ?string $fallback = NULL): string {
    $this->settingsTyped ??= $this->configTyped->get(self::CONFIG_NAME);
    try {
      $label = $this->settingsTyped->get($key)->getDataDefinition()->getLabel();
    }
    catch (\InvalidArgumentException) {
      $label = $fallback ?: "[$key]";
    }
    return $label;
  }

}
