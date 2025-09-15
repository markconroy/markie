<?php

namespace Drupal\ai_provider_openai\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai_provider_openai\OpenAiHelper;
use Drupal\key\KeyRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure OpenAI API access.
 */
class OpenAiConfigForm extends ConfigFormBase {

  /**
   * Config settings.
   */
  const CONFIG_NAME = 'ai_provider_openai.settings';

  /**
   * Default provider ID.
   */
  const PROVIDER_ID = 'openai';

  /**
   * Constructs a new OpenAIConfigForm object.
   */
  final public function __construct(
    private readonly AiProviderPluginManager $aiProviderManager,
    private readonly KeyRepositoryInterface $keyRepository,
    private readonly OpenAiHelper $openAiHelper,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  final public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ai.provider'),
      $container->get('key.repository'),
      $container->get('ai_provider_openai.helper'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'openai_settings';
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
    $config = $this->config(static::CONFIG_NAME);

    $form['api_key'] = [
      '#type' => 'key_select',
      '#title' => $this->t('OpenAI API Key'),
      '#description' => $this->t('The API Key. Can be found on <a href="https://platform.openai.com/">https://platform.openai.com/</a>.'),
      '#default_value' => $config->get('api_key'),
    ];

    $form['advanced'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced settings'),
      '#open' => FALSE,
    ];

    $form['advanced']['moderation'] = [
      '#markup' => '<p>' . $this->t('Moderation is always on by default for any text based call. You can disable it for each request either via code or by changing manually in ai_provider_openai.settings.yml.') . '</p>',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Validate the api key against model listing.
    $key = $form_state->getValue('api_key');
    if (empty($key)) {
      $form_state->setErrorByName('api_key', $this->t('The API Key is required.'));
      return;
    }
    $api_key = $this->keyRepository->getKey($key)->getKeyValue();
    if (!$api_key) {
      $form_state->setErrorByName('api_key', $this->t('The API Key is invalid.'));
      return;
    }
    $client = \OpenAI::client($api_key);
    try {
      $client->models()->list();
    }
    catch (\Exception $e) {
      $form_state->setErrorByName('api_key', $this->t('The API Key is not working.'));
    }

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $api_key = $this->keyRepository->getKey($form_state->getValue('api_key'))->getKeyValue();
    // If it all passed through, we do one last check of rate limits via chat.
    $this->openAiHelper->testRateLimit($api_key);
    // Retrieve the configuration.
    $this->config(static::CONFIG_NAME)
      ->set('api_key', $form_state->getValue('api_key'))
      ->save();

    // Set default models.
    $this->setDefaultModels();
    parent::submitForm($form, $form_state);
  }

  /**
   * Set default models for the AI provider.
   */
  private function setDefaultModels() {
    // Create provider instance.
    $provider = $this->aiProviderManager->createInstance(static::PROVIDER_ID);

    // Check if getSetupData() method exists and is callable.
    if (is_callable([$provider, 'getSetupData'])) {
      // Fetch setup data.
      $setup_data = $provider->getSetupData();

      // Ensure the setup data is valid.
      if (!empty($setup_data) && is_array($setup_data) && !empty($setup_data['default_models']) && is_array($setup_data['default_models'])) {
        // Loop through and set default models for each operation type.
        foreach ($setup_data['default_models'] as $op_type => $model_id) {
          $this->aiProviderManager->defaultIfNone($op_type, static::PROVIDER_ID, $model_id);
        }
      }
    }
  }

}
