<?php

declare(strict_types=1);

namespace Drupal\ai_logging\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\ai_logging\Batch\AiLogDeleteBatch;
use Drupal\ai_logging\Thread\ThreadRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Confirms deleting every AI log of one conversation thread.
 *
 * A thread is not an entity but the logs sharing a thread ID, under any of
 * the thread tag prefixes, so all of those logs are deleted.
 */
class AiLogThreadDeleteForm extends ConfirmFormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The thread repository.
   *
   * @var \Drupal\ai_logging\Thread\ThreadRepository
   */
  protected ThreadRepository $threadRepository;

  /**
   * The ID of the thread to delete.
   *
   * @var string
   */
  protected string $threadId;

  /**
   * The number of logs in the thread.
   *
   * @var int
   */
  protected int $count;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->threadRepository = $container->get('ai_logging.thread_repository');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ai_logging_thread_delete';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, string $thread_id = '') {
    $this->threadId = $thread_id;
    $tags = $this->threadRepository->getTagsForThreadId($thread_id);
    $this->count = $thread_id === '' || !$tags ? 0 : (int) $this->entityTypeManager->getStorage('ai_log')->getQuery()
      ->accessCheck(FALSE)
      ->condition('tags', $tags, 'IN')
      ->count()
      ->execute();
    if ($this->count === 0) {
      throw new NotFoundHttpException();
    }
    $form = parent::buildForm($form, $form_state);
    $form['actions']['submit']['#attributes']['class'][] = 'button--danger';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete thread %thread?', ['%thread' => $this->threadId]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->formatPlural(
      $this->count,
      'This will delete the 1 AI log of this thread. This action cannot be undone.',
      'This will delete all @count AI logs of this thread. This action cannot be undone.',
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete thread');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('entity.ai_log.thread', ['thread_id' => $this->threadId]);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    batch_set(AiLogDeleteBatch::build(
      $this->t('Deleting thread @thread', ['@thread' => $this->threadId]),
      $this->threadRepository->getTagsForThreadId($this->threadId),
    ));
    $form_state->setRedirect('entity.ai_log.threads');
  }

}
