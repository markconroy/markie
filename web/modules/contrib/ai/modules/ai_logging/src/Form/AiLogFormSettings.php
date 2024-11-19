<?php

namespace Drupal\ai_logging\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure AI Logging settings.
 */
class AiLogFormSettings extends ConfigFormBase {

  /**
   * Config settings.
   */
  const CONFIG_NAME = 'ai_logging.settings';

  /**
   * The AI provider manager.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected $aiProviderManager;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ai_logging_settings';
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

    $form['prompt_logging'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Log requests'),
      '#description' => $this->t('Log all or selective prompts and responses in the database.'),
      '#default_value' => $config->get('prompt_logging'),
    ];

    $form['prompt_logging_output'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Log response'),
      '#description' => $this->t('Also log the output of the AI requests.'),
      '#default_value' => $config->get('prompt_logging_output'),
    ];

    $form['prompt_logging_tags'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Request Tags'),
      '#description' => $this->t('Log prompts and responses with these tags in the database. Separate tags with commas. Empty means all.'),
      '#default_value' => $config->get('prompt_logging_tags'),
      '#states' => [
        'visible' => [
          ':input[name="prompt_logging"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['prompt_logging_max_messages'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum number messages to log'),
      '#description' => $this->t('The maximum number of messages to log in the database. Empty or 0 means unlimited. Beyond this number, older logs will be automatically deleted.'),
      '#default_value' => $config->get('prompt_logging_max_messages'),
    ];

    $form['prompt_logging_max_age'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum age of messages to log'),
      '#description' => $this->t('The maximum age of messages to log in the database in days. Empty or 0 means unlimited. Beyond this age, older logs will be automatically deleted.'),
      '#default_value' => $config->get('prompt_logging_max_age'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Retrieve the configuration.
    $this->config(static::CONFIG_NAME)
      ->set('prompt_logging', $form_state->getValue('prompt_logging'))
      ->set('prompt_logging_tags', $form_state->getValue('prompt_logging_tags'))
      ->set('prompt_logging_output', $form_state->getValue('prompt_logging_output'))
      ->set('prompt_logging_bundles', $form_state->getValue('prompt_logging_bundles'))
      ->set('prompt_logging_max_messages', $form_state->getValue('prompt_logging_max_messages'))
      ->set('prompt_logging_max_age', $form_state->getValue('prompt_logging_max_age'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
