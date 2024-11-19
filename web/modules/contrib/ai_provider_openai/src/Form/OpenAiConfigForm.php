<?php

namespace Drupal\ai_provider_openai\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ai\AiProviderPluginManager;
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
   * The AI Provider service.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected $aiProviderManager;

  /**
   * The key factory.
   *
   * @var \Drupal\key\KeyRepositoryInterface
   */
  protected $keyRepository;

  /**
   * Constructs a new OpenAIConfigForm object.
   */
  final public function __construct(AiProviderPluginManager $ai_provider_manager, KeyRepositoryInterface $key_repository) {
    $this->aiProviderManager = $ai_provider_manager;
    $this->keyRepository = $key_repository;
  }

  /**
   * {@inheritdoc}
   */
  final public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ai.provider'),
      $container->get('key.repository'),
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
    // Retrieve the configuration.
    $this->config(static::CONFIG_NAME)
      ->set('api_key', $form_state->getValue('api_key'))
      ->save();

    // Set some defaults.
    $this->aiProviderManager->defaultIfNone('chat', 'openai', 'gpt-4o');
    $this->aiProviderManager->defaultIfNone('chat_with_image_vision', 'openai', 'gpt-4o');
    $this->aiProviderManager->defaultIfNone('chat_with_complex_json', 'openai', 'gpt-4o');
    $this->aiProviderManager->defaultIfNone('text_to_image', 'openai', 'dall-e-3');
    $this->aiProviderManager->defaultIfNone('embeddings', 'openai', 'text-embedding-3-small');
    $this->aiProviderManager->defaultIfNone('text_to_speech', 'openai', 'tts-1-hd');
    $this->aiProviderManager->defaultIfNone('speech_to_text', 'openai', 'whisper-1');

    parent::submitForm($form, $form_state);
  }

}
