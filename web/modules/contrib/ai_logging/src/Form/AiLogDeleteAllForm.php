<?php

declare(strict_types=1);

namespace Drupal\ai_logging\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\ai_logging\Batch\AiLogDeleteBatch;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Confirms deleting every AI log.
 */
class AiLogDeleteAllForm extends ConfirmFormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ai_logging_delete_all';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $form['actions']['submit']['#attributes']['class'][] = 'button--danger';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete all AI logs?');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    $count = (int) $this->entityTypeManager->getStorage('ai_log')->getQuery()
      ->accessCheck(FALSE)
      ->count()
      ->execute();
    return $this->formatPlural(
      $count,
      'This will delete 1 AI log. This action cannot be undone.',
      'This will delete @count AI logs. This action cannot be undone.',
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete all logs');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('ai_logging.settings_form');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    batch_set(AiLogDeleteBatch::build($this->t('Deleting AI logs')));
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
