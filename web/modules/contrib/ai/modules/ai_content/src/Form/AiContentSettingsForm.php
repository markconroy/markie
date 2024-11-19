<?php

namespace Drupal\ai_content\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\ai\AiProviderPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure AI Content module.
 */
class AiContentSettingsForm extends ConfigFormBase {

  use StringTranslationTrait;

  /**
   * Config settings.
   */
  const CONFIG_NAME = 'ai_content.settings';

  /**
   * The provider manager.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected $providerManager;

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
    return 'ai_content_settings';
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
    // Load config.
    $config = $this->config(static::CONFIG_NAME);
    $moderation_models = $this->providerManager->getSimpleProviderModelOptions('moderation');
    $default_moderation_model = $this->providerManager->getDefaultProviderForOperationType('moderation');
    $chat_models = $this->providerManager->getSimpleProviderModelOptions('chat');
    $form['policy'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Policy'),
    // Added.
      '#collapsible' => TRUE,
    // Added.
      '#collapsed' => FALSE,
    ];
    $form['policy']['analyse_policies_enabled'] = [
      '#type' => 'checkbox',
      '#default_value' => $config->get('analyse_policies_enabled'),
      '#title' => $this->t('Enable content analysis.'),
    ];
    $form['policy']['analyse_policies_model'] = [
      '#type' => 'select',
      '#options' => $moderation_models,
      '#disabled' => count($moderation_models) == 0,
      '#default_value' => $config->get('analyse_policies_enabled') ?? $default_moderation_model,
      '#description' => $this->t('<em>AI can analyze content and tell you what content policies it may violate. This is beneficial if your audience are certain demographics and sensitive to certain categories. Note that this is only a useful guide.</em>'),
      '#title' => $this->t('Content analysis model'),
      "#empty_option" => $this->t('-- Default from AI module (chat) --'),
    ];
    $form['tone'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Tone of voice'),
    // Added.
      '#collapsible' => TRUE,
    // Added.
      '#collapsed' => FALSE,
    ];
    $form['tone']['tone_adjust_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Tone adjust feature'),
      '#default_value' => $config->get('tone_adjust_enabled'),
    ];
    $form['tone']['tone_adjust_model'] = [
      '#type' => 'select',
      '#options' => $chat_models,
      '#disabled' => count($chat_models) == 0,
      '#description' => $this->t('Have AI check your content and adjust the tone of it for different reader audiences for you.'),
      '#default_value' => $config->get('tone_adjust_model'),
      '#title' => $this->t('content tone model'),
      "#empty_option" => $this->t('-- Default from AI module (chat) --'),
    ];
    $form['summary'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Summarise'),
    // Added.
      '#collapsible' => TRUE,
    // Added.
      '#collapsed' => FALSE,
    ];
    $form['summary']['summarise_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable summary suggestion feature'),
      '#default_value' => $config->get('summarise_enabled'),
    ];
    $form['summary']['summarise_model'] = [
      '#type' => 'select',
      '#options' => $chat_models,
      '#disabled' => count($chat_models) == 0,
      '#description' => $this->t('This will use the selected field and OpenAI will attempt to summarize it for you. You can use the result to help generate a summary/teaser, social media share text, or similar.'),
      '#default_value' => $config->get('summarise_model'),
      '#title' => $this->t('Summarisation model'),
      "#empty_option" => $this->t('-- Default from AI module (chat) --'),
    ];
    $form['title'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Suggest title'),
    // Added.
      '#collapsible' => TRUE,
    // Added.
      '#collapsed' => FALSE,
    ];
    $form['title']['suggest_title_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable summary suggestion feature'),
      '#default_value' => $config->get('suggest_title_enabled'),
    ];
    $form['title']['suggest_title_model'] = [
      '#type' => 'select',
      '#options' => $chat_models,
      '#disabled' => count($chat_models) == 0,
      '#description' => $this->t('AI can try to suggest an SEO friendly title from the selected field.'),
      '#default_value' => $config->get('suggest_title_model'),
      '#title' => $this->t('Title suggestion model'),
      "#empty_option" => $this->t('-- Default from AI module (chat) --'),
    ];
    $form['taxonomy'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Suggest taxonomy'),
    // Added.
      '#collapsible' => TRUE,
    // Added.
      '#collapsed' => FALSE,
    ];
    $form['taxonomy']['suggest_tax_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable taxonomy suggestion feature'),
      '#default_value' => $config->get('suggest_tax_enabled'),
    ];
    $form['taxonomy']['suggest_tax_model'] = [
      '#type' => 'select',
      '#options' => $chat_models,
      '#disabled' => count($chat_models) == 0,
      '#description' => $this->t('AI can attempt to suggest possible classification terms to use as taxonomy.'),
      '#default_value' => $config->get('suggest_tax_model'),
      '#title' => $this->t('Taxonomy term suggestion model'),
      "#empty_option" => $this->t('-- Default from AI module (chat) --'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config(static::CONFIG_NAME)
      ->set('analyse_policies_enabled', $form_state->getValue('analyse_policies_enabled'))
      ->set('tone_adjust_enabled', $form_state->getValue('tone_adjust_enabled'))
      ->set('summarise_enabled', $form_state->getValue('summarise_enabled'))
      ->set('suggest_title_enabled', $form_state->getValue('suggest_title_enabled'))
      ->set('suggest_tax_enabled', $form_state->getValue('suggest_tax_enabled'))
      ->set('analyse_policies_model', $form_state->getValue('analyse_policies_model'))
      ->set('tone_adjust_model', $form_state->getValue('tone_adjust_model'))
      ->set('summarise_model', $form_state->getValue('summarise_model'))
      ->set('suggest_title_model', $form_state->getValue('suggest_title_model'))
      ->set('suggest_tax_model', $form_state->getValue('suggest_tax_model'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
