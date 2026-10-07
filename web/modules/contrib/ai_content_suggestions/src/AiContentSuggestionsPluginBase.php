<?php

declare(strict_types=1);

namespace Drupal\ai_content_suggestions;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\PluginBase;
use Drupal\Component\Utility\Html;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for ai_content_suggestions plugins.
 */
abstract class AiContentSuggestionsPluginBase extends PluginBase implements AiContentSuggestionsInterface, ConfigurableInterface, PluginFormInterface, ContainerFactoryPluginInterface {

  use StringTranslationTrait;
  use DependencySerializationTrait;

  /**
   * Constructs the instance of plugin class.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\ai\AiProviderPluginManager $providerPluginManager
   *   The AI provider plugin manager.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected AiProviderPluginManager $providerPluginManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->setConfiguration($configuration);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ai.provider'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration(): array {
    return $this->configuration + $this->defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration): void {
    $this->configuration = $configuration + $this->defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function label(): TranslatableMarkup {
    return $this->pluginDefinition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function description(): TranslatableMarkup {
    return $this->pluginDefinition['description'];
  }

  /**
   * {@inheritdoc}
   */
  public function operationType(): string {
    return $this->pluginDefinition['operation_type'];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'enabled' => FALSE,
      'model' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildSettingsForm(array &$form): void {
    @trigger_error(__METHOD__ . '() is deprecated in ai_content_suggestions:1.4.0 and is removed from ai_content_suggestions:2.0.0. Instead use buildConfigurationForm(). See https://www.drupal.org/node/3591233', E_USER_DEPRECATED);
    $models = $this->getModels();
    $default_model = $this->getDefaultModel();

    $element = [
      '#type' => 'fieldset',
      '#title' => $this->label(),
      '#description' => $this->description(),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
      '#parents' => ['plugins', $this->getPluginId()],
    ];
    $element[$this->getPluginId() . '_enabled'] = [
      '#type' => 'checkbox',
      '#default_value' => $this->isEnabled(),
      '#title' => $this->t('Enable :label.', [
        ':label' => $this->label(),
      ]),
    ];
    $element[$this->getPluginId() . '_model'] = [
      '#type' => 'select',
      '#options' => $models,
      '#default_value' => $default_model,
      '#title' => $this->t(':label model.', [
        ':label' => $this->label(),
      ]),
      '#empty_option' => $this->t('-- Default from AI provider --'),
      '#states' => [
        'visible' => [
          ':input[name="plugins[' . $this->getPluginId() . '][' . $this->getPluginId() . '_enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $form[$this->getPluginId()] = $element;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $models = $this->getModels();
    $default_model = $this->getDefaultModel();

    $form = [
      '#type' => 'fieldset',
      '#title' => $this->label(),
      '#description' => $this->description(),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
      '#parents' => ['plugins', $this->getPluginId()],
    ];
    $form['enabled'] = [
      '#type' => 'checkbox',
      '#default_value' => $this->isEnabled(),
      '#title' => $this->t('Enable :label.', [
        ':label' => $this->label(),
      ]),
    ];
    $form['model'] = [
      '#type' => 'select',
      '#options' => $models,
      '#default_value' => $default_model,
      '#title' => $this->t(':label model.', [
        ':label' => $this->label(),
      ]),
      '#empty_option' => $this->t('-- Default from AI provider --'),
      '#states' => [
        'visible' => [
          ':input[name="plugins[' . $this->getPluginId() . '][enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * {@inheritdoc}
   *
   * Reads values from form_state using the subform's #parents and merges them
   * into $this->configuration. Subclasses that need custom normalization
   * should override this method.
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $values = $form_state->getValues();
    if (is_array($values)) {
      $this->configuration = $values + $this->defaultConfiguration();
    }
  }

  /**
   * Saves the plugin settings.
   *
   * @param array $form
   *   The plugin form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The plugin's form state.
   *
   * @deprecated in ai_content_suggestions:1.4.0 and is removed from
   *   ai_content_suggestions:2.0.0. Instead use submitConfigurationForm.
   *
   * @see https://www.drupal.org/node/3591233
   */
  public function saveSettingsForm(array &$form, FormStateInterface $form_state): void {
    @trigger_error(__METHOD__ . '() is deprecated in ai_content_suggestions:1.4.0 and is removed from ai_content_suggestions:2.0.0. Instead use submitConfigurationForm(). See https://www.drupal.org/node/3591233', E_USER_DEPRECATED);
    $this->submitConfigurationForm($form, $form_state);
  }

  /**
   * Helper to get the default form structure for an alter form.
   *
   * @param array $fields
   *   An array of available fields on the content.
   *
   * @return array
   *   the form array for updating.
   */
  public function getAlterFormTemplate(array $fields): array {
    return [
      '#type' => 'details',
      '#title' => $this->label(),
      '#group' => 'advanced',
      '#tree' => TRUE,
      'target_fields' => [
        '#type' => 'select',
        '#description' => $this->t('<a href="javascript://" class="toggle_content_suggestion_fields">Select the field(s) you wish to send to the LLM</a>'),
        '#options' => $fields,
        '#default_value' => array_keys($fields),
        '#multiple' => TRUE,
        '#weight' => 0,
        '#attributes' => ['class' => ['toggle_content_suggestion_select']],
        '#attached' => [
          'library' => ['ai_content_suggestions/ai_content_suggestions_js'],
        ],
      ],
      'response' => [
        '#type' => 'container',
        '#attributes' => [
          'id' => $this->getAjaxId(),
        ],
        'response' => [
          '#type' => 'inline_template',
          '#template' => '{{ response }}',
          '#weight' => 0,
          '#context' => [
            'response' => [
              'heading' => [
                '#type' => 'html_tag',
                '#tag' => 'i',
                '#value' => '',
              ],
            ],
          ],
        ],
        '#weight' => 50,
      ],
      $this->getPluginId() . '_submit' => [
        '#type' => 'button',
        '#value' => $this->t('Submit'),
        '#plugin' => $this->getPluginId(),
        '#limit_validation_errors' => [],
        '#ajax' => [
          'callback' => [$this, 'getPluginResponse'],
          'wrapper' => $this->getAjaxId(),
        ],
        '#weight' => 51,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getModels(bool $empty = TRUE): array {
    return $this->providerPluginManager->getSimpleProviderModelOptions($this->operationType(), $empty);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultModel(): ?string {
    $config = $this->getConfiguration();
    $value = $config['model'] ?? NULL;

    if (empty($value)) {
      if ($default = $this->providerPluginManager->getDefaultProviderForOperationType($this->operationType())) {
        $value = $default['provider_id'] . '__' . $default['model_id'];
      }
    }

    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function isAvailable(): bool {
    // As a base check that devs can override if needed, we will check we have
    // available models.
    return count($this->getModels(FALSE)) > 0;
  }

  /**
   * {@inheritdoc}
   */
  public function isEnabled(): bool {
    $config = $this->getConfiguration();
    return $config['enabled'] ?? FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getAjaxId(): string {
    return 'response-' . Html::cleanCssIdentifier($this->getPluginId());
  }

  /**
   * {@inheritdoc}
   */
  public function getFormFieldValue(string $form_field, FormStateInterface $form_state): mixed {
    $value = [];

    if ($values = $form_state->getValue($this->getPluginId())) {
      if (isset($values[$form_field])) {
        $value = $values[$form_field];
      }
    }

    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function getTargetFieldValue(FormStateInterface $form_state): mixed {
    $values = [];
    $target_fields = $this->getFormFieldValue('target_fields', $form_state);
    foreach ($target_fields as $target_field) {
      if (!$field = $form_state->getValue($target_field)) {
        $tree = explode(':', $target_field);
        $field = $form_state->getValue($tree);
      }

      if ($field) {
        if (!empty($field[0]['value'])) {
          $values[] = $field[0]['value'];
        }
      }
    }

    $value = implode(PHP_EOL . PHP_EOL, $values);
    return trim($value);
  }

  /**
   * {@inheritdoc}
   */
  public function getSetProvider(string $operation_type, string|null $preferred_model = NULL): array {
    return $this->providerPluginManager->getSetProvider($operation_type, $preferred_model);
  }

  /**
   * {@inheritdoc}
   */
  public function sendChat(string $prompt): string|TranslatableMarkup {
    $config = $this->getConfiguration();
    $provider_config = $this->getSetProvider($this->operationType(), $config['model']);

    /** @var \Drupal\ai\AiProviderInterface $ai_provider */
    $ai_provider = $provider_config['provider_id'];

    try {
      $messages = new ChatInput([
        new ChatMessage('user', $prompt),
      ]);

      $messages->setSystemPrompt('You are helpful assistant.');

      /** @var \Drupal\ai\OperationType\Chat\ChatMessage $response */
      $response = $ai_provider->chat($messages, $provider_config['model_id'], [
        'ai_content_suggestions',
      ])->getNormalized();
      $message = trim($response->getText()) ?? $this->t('No result could be generated.');
    }
    catch (\Exception $e) {
      $message = $this->t('There was an error obtaining a response from the LLM.');
    }

    return $message;
  }

  /**
   * Helper to identify the submitted plugin and allow it to update the form.
   *
   * @param array $form
   *   The Content Entity form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The appropriate section of the updated form.
   */
  public function getPluginResponse(array $form, FormStateInterface $form_state): array {
    $this->updateFormWithResponse($form, $form_state);
    return $form[$this->getPluginId()]['response'];
  }

}
