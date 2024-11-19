<?php

namespace Drupal\ai\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\Enum\AiModelCapability;
use Drupal\ai\Exception\AiSetupFailureException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure AI module.
 */
class AiSettingsForm extends ConfigFormBase {

  /**
   * Config settings.
   */
  const CONFIG_NAME = 'ai.settings';

  /**
   * The AI Provider service.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected $providerManager;

  /**
   * The hard coded selections to add for filtering purposes.
   *
   * @var array
   */
  protected $hardcodedSelections = [
    [
      'id' => 'chat_with_image_vision',
      'actual_type' => 'chat',
      'label' => 'Chat with Image Vision',
      'filter' => [AiModelCapability::ChatWithImageVision],
    ],
    [
      'id' => 'chat_with_complex_json',
      'actual_type' => 'chat',
      'label' => 'Chat with Complex JSON',
      'filter' => [AiModelCapability::ChatJsonOutput],
    ],
  ];

  /**
   * Constructor.
   */
  final public function __construct(AiProviderPluginManager $provider_manager) {
    $this->providerManager = $provider_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ai.provider')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ai_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      static::CONFIG_NAME,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = [];

    $config = $this->config(static::CONFIG_NAME);

    $form['default_providers'] = [
      '#type' => 'details',
      '#title' => $this->t('Default Providers'),
      '#open' => TRUE,
      '#weight' => 10,
      '#description' => $this->t('These are default providers for each operation type that external modules can use or show on their configurations pages.'),
    ];

    $operation_types = $this->providerManager->getOperationTypes();
    $default_providers = $config->get('default_providers') ?? [];
    // Get all providers.
    /** @var \Drupal\ai\AiProviderInterface[] $providers */
    $providers = [];
    foreach ($this->providerManager->getDefinitions() as $id => $definition) {
      $providers[$id] = $this->providerManager->createInstance($id);
    }

    // Add the hardcoded selections of filtered types.
    $operation_types = array_merge($operation_types, $this->hardcodedSelections);

    foreach ($operation_types as $operation_type) {
      // Get all providers that allows for a specific operation type.
      $options = [
        '' => 'No default',
      ];
      $filters = $operation_type['filter'] ?? [];
      foreach ($providers as $provider) {
        if ($provider->isUsable($operation_type['actual_type'] ?? $operation_type['id'], $filters)) {
          $options[$provider->getPluginId()] = $provider->getPluginDefinition()['label'];
        }
      }
      $default_provider = $form_state->getValue('operation__' . $operation_type['id']);
      if (!$default_provider) {
        $default_provider = $default_providers[$operation_type['id']]['provider_id'] ?? '';
      }
      $form['default_providers'][$operation_type['id']] = [
        '#type' => 'fieldset',
        '#title' => $operation_type['label'],
      ];
      $form['default_providers'][$operation_type['id']]['operation__' . $operation_type['id']] = [
        '#type' => 'select',
        '#title' => $this->t('Default Provider'),
        '#options' => $options,
        '#default_value' => !empty($providers[$default_provider]) ? $default_provider : '',
        '#ajax' => [
          'callback' => '::loadModels',
          'wrapper' => 'model__' . $operation_type['id'],
        ],
      ];

      $form['default_providers'][$operation_type['id']]['model'] = [
        '#type' => 'container',
        '#attributes' => [
          'id' => 'model__' . $operation_type['id'],
        ],
      ];

      // Add the model id field if the provider is set.
      if ($default_provider && !empty($providers[$default_provider])) {
        $models = [];
        try {
          if ($providers[$default_provider]->isUsable($operation_type['actual_type'] ?? $operation_type['id'])) {
            $models = $providers[$default_provider]->getConfiguredModels($operation_type['actual_type'] ?? $operation_type['id'], $filters);
          }
          else {
            $this->messenger()->addWarning($this->t('The default %operation provider (%provider_id) is not currently usable. Please review your configuration.', [
              '%operation' => $operation_type['label'],
              '%provider_id' => $default_provider,
            ]));
          }
        }
        catch (AiSetupFailureException $e) {
          // Don't crash if the provider is not fully configured.
          $this->messenger()->addError($e->getMessage());
        }
        $form['default_providers'][$operation_type['id']]['model']['model__' . $operation_type['id']] = [
          '#type' => 'select',
          '#title' => $this->t('Default Model'),
          '#default_value' => $default_providers[$operation_type['id']]['model_id'] ?? '',
          '#options' => $models,
          '#empty_option' => $this->t('- Select -'),
        ];
      }
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $values = $form_state->getValues();
    foreach ($this->providerManager->getOperationTypes() as $operation_type) {

      // If a provider is selected, a model must also be selected.
      if (
        !empty($values['operation__' . $operation_type['id']])
        && empty($values['model__' . $operation_type['id']])
      ) {
        $message = $this->t('You have selected a provider for @operation but have not selected a model.', [
          '@operation' => $operation_type['label'],
        ]);
        $form_state->setErrorByName('model__' . $operation_type['id'], $message);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Set the default providers array.
    $default_providers = [];
    foreach ($form_state->getValues() as $key => $value) {
      if (strpos($key, 'operation__') === 0) {
        $operation_type = substr($key, 11);
        if (empty($value)) {
          continue;
        }
        $default_providers[$operation_type] = [
          'provider_id' => $value,
          'model_id' => $form_state->getValue('model__' . $operation_type),
        ];
      }
    }

    // Retrieve the configuration.
    $this->config(static::CONFIG_NAME)
      ->set('default_providers', $default_providers)
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Ajax callback to load models.
   */
  public function loadModels(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    $operation_type = substr($trigger['#name'], 11);
    return $form['default_providers'][$operation_type]['model']['model__' . $operation_type];
  }

}
