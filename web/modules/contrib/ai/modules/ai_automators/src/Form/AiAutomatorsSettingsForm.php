<?php

declare(strict_types=1);

namespace Drupal\ai_automators\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ai_automators\AutomatorFallbackUser;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure AI Automators settings.
 */
class AiAutomatorsSettingsForm extends ConfigFormBase {

  /**
   * Config settings.
   */
  const CONFIG_NAME = 'ai_automators.settings';

  /**
   * The user automators run as when there is no current user.
   */
  protected AutomatorFallbackUser $fallbackUser;

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->fallbackUser = $container->get('ai_automator.fallback_user');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

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

    // Choosing whose access automators run with is close to acting as that
    // user, so it needs a restricted permission.
    $fallback_uid = $this->fallbackUser->getUid();
    $form['fallback_user'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Run automators without a logged-in user as'),
      '#description' => $this->t('Content saved without a logged-in user, for example from cron, drush or a scheduled publish, is processed with the access of this user, including the text formats it may use. Leave empty to run as anonymous. Do not choose an administrator: anyone whose content is saved this way would get output in formats such as Full HTML. Stored per environment, not in configuration.'),
      '#target_type' => 'user',
      '#selection_settings' => ['include_anonymous' => FALSE],
      '#default_value' => $fallback_uid ? $this->entityTypeManager->getStorage('user')->load($fallback_uid) : NULL,
      '#access' => $this->currentUser()->hasPermission('administer users'),
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

    $uid = $form_state->getValue('fallback_user');
    $this->fallbackUser->setUid($uid ? (int) $uid : NULL);

    parent::submitForm($form, $form_state);
  }

}
