<?php

declare(strict_types=1);

namespace Drupal\ai_automators\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure AI Automators settings.
 */
class AiAutomatorsSettingsForm extends ConfigFormBase {

  /**
   * Config settings.
   */
  const CONFIG_NAME = 'ai_automators.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ai_automators_settings';
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

    $form['queue_cron_items'] = [
      '#type' => 'number',
      '#title' => $this->t('Queue items per cron run'),
      '#description' => $this->t('Maximum number of queued AI Automator items to process during each cron run. Set to 0 to process all pending items. Default: 10.'),
      '#default_value' => $config->get('queue_cron_items'),
      '#min' => 0,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config(static::CONFIG_NAME)
      ->set('queue_cron_items', (int) $form_state->getValue('queue_cron_items'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
