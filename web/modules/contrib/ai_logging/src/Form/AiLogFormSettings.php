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
      '#title' => $this->t('Automatically log requests'),
      '#description' => $this->t('Log all or selective prompts and responses in the database automatically.'),
      '#default_value' => $config->get('prompt_logging'),
    ];

    $form['prompt_logging_output'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Automatically log responses'),
      '#description' => $this->t('Also log the output of the AI requests.'),
      '#default_value' => $config->get('prompt_logging_output'),
      '#states' => [
        'visible' => [
          ':input[name="prompt_logging"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['prompt_logging_tags'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Restrict automated logging by request tags'),
      '#description' => $this->t('All requests are tagged with the operation type (call, moderate, etc): to automatically log only specific operations, enter them here. Leave empty to automatically log all operation types.'),
      '#default_value' => $config->get('prompt_logging_tags'),
      '#states' => [
        'visible' => [
          ':input[name="prompt_logging"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['prompt_logging_excluded_tags'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Exclude automated logging by request tags'),
      '#description' => $this->t('Comma-separated list of tags to exclude from logging. If a request has any of these tags, it will not be logged, regardless of the included tags setting above.'),
      '#default_value' => $config->get('prompt_logging_excluded_tags'),
      '#states' => [
        'visible' => [
          ':input[name="prompt_logging"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['prompt_logging_max_messages'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum number messages to keep stored in the log'),
      '#description' => $this->t('The maximum number of messages to log in the database. Empty or 0 means unlimited. Beyond this number, older logs will be automatically deleted.'),
      '#default_value' => $config->get('prompt_logging_max_messages'),
    ];

    $form['prompt_logging_max_age'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum age of messages to keep stored in the log'),
      '#description' => $this->t('The maximum age of messages to log in the database in days. Empty or 0 means unlimited. Beyond this age, older logs will be automatically deleted.'),
      '#default_value' => $config->get('prompt_logging_max_age'),
    ];

    $form['threads'] = [
      '#type' => 'details',
      '#title' => $this->t('Conversation threads'),
      '#open' => TRUE,
    ];

    $form['threads']['thread_tag_prefixes'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Thread tag prefixes'),
      '#description' => $this->t('One request tag prefix per line. Logs whose tags start with one of these prefixes are grouped into conversation threads by the rest of the tag, the thread ID. Leave empty to turn the thread pages off.'),
      '#default_value' => implode("\n", $config->get('thread_tag_prefixes') ?? []),
      '#rows' => 3,
    ];

    $form['threads']['thread_primary_tag_pattern'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Primary turn tag pattern'),
      '#description' => $this->t('A thread is labeled with the first user message of its earliest chat call carrying a tag that matches this pattern, which skips helper calls (such as guardrails) that joined the thread before its first real turn. Use % as a wildcard. Leave empty to use the earliest chat call.'),
      '#default_value' => $config->get('thread_primary_tag_pattern'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $thread_tag_prefixes = array_values(array_unique(array_filter(
      array_map('trim', preg_split('/\R/', (string) $form_state->getValue('thread_tag_prefixes'))),
      static fn(string $prefix): bool => $prefix !== '',
    )));

    // Retrieve the configuration.
    $this->config(static::CONFIG_NAME)
      ->set('prompt_logging', $form_state->getValue('prompt_logging'))
      ->set('prompt_logging_tags', $form_state->getValue('prompt_logging_tags'))
      ->set('prompt_logging_excluded_tags', $form_state->getValue('prompt_logging_excluded_tags'))
      ->set('prompt_logging_output', (bool) $form_state->getValue('prompt_logging_output'))
      ->set('prompt_logging_max_messages', $form_state->getValue('prompt_logging_max_messages'))
      ->set('prompt_logging_max_age', $form_state->getValue('prompt_logging_max_age'))
      ->set('thread_tag_prefixes', $thread_tag_prefixes)
      ->set('thread_primary_tag_pattern', trim((string) $form_state->getValue('thread_primary_tag_pattern')))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
