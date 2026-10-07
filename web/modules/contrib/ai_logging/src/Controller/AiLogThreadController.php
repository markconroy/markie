<?php

declare(strict_types=1);

namespace Drupal\ai_logging\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\ai_logging\AiLogInterface;
use Drupal\ai_logging\ResponseTextFormatter;
use Drupal\ai_logging\Thread\ThreadLabelResolverInterface;
use Drupal\ai_logging\Thread\ThreadRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Lists AI logs grouped by conversation thread.
 *
 * The AI Logs view cannot group rows by one value of the multi-value tags
 * field, so this lists one row per thread, and a page per thread with its
 * calls in order.
 */
class AiLogThreadController extends ControllerBase {

  /**
   * Threads shown per page of the list.
   */
  protected const THREADS_PER_PAGE = 25;

  /**
   * The thread repository.
   *
   * @var \Drupal\ai_logging\Thread\ThreadRepository
   */
  protected ThreadRepository $threadRepository;

  /**
   * The thread label resolver.
   *
   * @var \Drupal\ai_logging\Thread\ThreadLabelResolverInterface
   */
  protected ThreadLabelResolverInterface $threadLabelResolver;

  /**
   * The reply text formatter.
   *
   * @var \Drupal\ai_logging\ResponseTextFormatter
   */
  protected ResponseTextFormatter $responseTextFormatter;

  /**
   * The date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->threadRepository = $container->get('ai_logging.thread_repository');
    $instance->threadLabelResolver = $container->get('ai_logging.thread_label_resolver');
    $instance->responseTextFormatter = $container->get('ai_logging.response_text_formatter');
    $instance->dateFormatter = $container->get('date.formatter');
    return $instance;
  }

  /**
   * Checks that the thread pages are turned on.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account to check access for.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   Allowed when at least one thread tag prefix is configured.
   */
  public function access(AccountInterface $account): AccessResultInterface {
    return AccessResult::allowedIf($this->threadRepository->isEnabled())
      ->addCacheableDependency($this->config('ai_logging.settings'));
  }

  /**
   * Lists conversation threads, most recently active first.
   *
   * @return array
   *   A render array.
   */
  public function listThreads(): array {
    $header = [
      ['data' => $this->t('Thread'), 'field' => 'thread_id'],
      $this->t('First question'),
      ['data' => $this->t('Started'), 'field' => 'started'],
      ['data' => $this->t('Last activity'), 'field' => 'last_activity', 'sort' => 'desc'],
      ['data' => $this->t('Calls'), 'field' => 'calls'],
      ['data' => $this->t('Tokens'), 'field' => 'tokens'],
    ];

    $rows = [];
    foreach ($this->threadRepository->listThreads($header, static::THREADS_PER_PAGE) as $thread) {
      $thread_id = (string) $thread->thread_id;
      $rows[] = [
        [
          'data' => [
            '#type' => 'link',
            '#title' => $thread_id,
            '#url' => Url::fromRoute('entity.ai_log.thread', ['thread_id' => $thread_id]),
          ],
        ],
        $this->threadLabelResolver->getLabel($thread_id) ?? $this->t('(no question found)'),
        $this->dateFormatter->format((int) $thread->started, 'short'),
        $this->dateFormatter->format((int) $thread->last_activity, 'short'),
        $thread->calls,
        $thread->tokens ?? $this->t('n/a'),
      ];
    }

    $build = [
      'table' => [
        '#type' => 'table',
        '#header' => $header,
        '#rows' => $rows,
        '#empty' => $this->t('No conversation threads have been logged yet. Only AI calls with a request tag starting with one of the thread tag prefixes on the <a href=":settings">settings page</a> are grouped into threads, such as agents run through an AI Assistant.', [
          ':settings' => Url::fromRoute('ai_logging.settings_form')->toString(),
        ]),
      ],
      'pager' => [
        '#type' => 'pager',
      ],
    ];
    $this->addCacheMetadata($build);
    return $build;
  }

  /**
   * Shows every logged AI call within one conversation thread, in order.
   *
   * @param string $thread_id
   *   The thread ID.
   *
   * @return array
   *   A render array.
   */
  public function viewThread(string $thread_id): array {
    $entries = $this->threadRepository->loadThreadEntries($thread_id);
    if (!$entries) {
      throw new NotFoundHttpException();
    }

    $build = [
      '#attached' => [
        'library' => ['ai_logging/threads'],
      ],
    ];
    $cacheability = new CacheableMetadata();
    foreach ($entries as $entry) {
      $access = $entry->access('view', NULL, TRUE);
      $cacheability->addCacheableDependency($access);
      if (!$access->isAllowed()) {
        continue;
      }
      $cacheability->addCacheableDependency($entry);
      $build['entry_' . $entry->id()] = $this->buildEntry($entry);
    }
    $cacheability->applyTo($build);
    $this->addCacheMetadata($build);
    return $build;
  }

  /**
   * Title callback for the thread page.
   *
   * @param string $thread_id
   *   The thread ID.
   *
   * @return string
   *   The page title.
   */
  public function threadTitle(string $thread_id): string {
    return (string) $this->t('Thread @thread', ['@thread' => $thread_id]);
  }

  /**
   * Builds the details element for one call in a thread.
   *
   * @param \Drupal\ai_logging\AiLogInterface $entry
   *   The log.
   *
   * @return array
   *   A render array.
   */
  protected function buildEntry(AiLogInterface $entry): array {
    $title = $this->t('@time — @operation (@provider / @model)', [
      '@time' => $this->dateFormatter->format((int) $entry->get('created')->value, 'short'),
      '@operation' => $entry->get('operation_type')->value ?: $this->t('n/a'),
      '@provider' => $entry->get('provider')->value ?: $this->t('n/a'),
      '@model' => $entry->get('model')->value ?: $this->t('n/a'),
    ]);
    $tokens_total = $entry->get('tokens_total')->value;
    if ($tokens_total !== NULL) {
      $title = $this->t('@title — @total tokens (@input in / @output out)', [
        '@title' => $title,
        '@total' => $tokens_total,
        '@input' => $entry->get('tokens_input')->value ?? '?',
        '@output' => $entry->get('tokens_output')->value ?? '?',
      ]);
    }

    $tags = [];
    foreach ($entry->get('tags') as $item) {
      $tags[] = $item->value;
    }

    $element = [
      '#type' => 'details',
      '#title' => $title,
      '#open' => TRUE,
      'tags' => $this->responseTextFormatter->buildPreformatted($this->t('Tags'), implode(', ', $tags)),
      'prompt' => $this->responseTextFormatter->buildTranscript($this->t('Prompt'), (string) $entry->get('prompt')->value),
    ];

    $response_text = (string) $entry->get('response_text')->value;
    $output_text = (string) $entry->get('output_text')->value;
    if ($response_text !== '') {
      $element['response'] = $this->responseTextFormatter->build($response_text);
    }
    elseif ($output_text !== '') {
      $element['response'] = $this->responseTextFormatter->buildPreformatted($this->t('Raw output'), $output_text);
    }

    $element['raw_link'] = [
      '#type' => 'link',
      '#title' => $this->t('View log entry'),
      '#url' => $entry->toUrl(),
    ];
    return $element;
  }

  /**
   * Adds the cache metadata shared by both thread pages.
   *
   * @param array $build
   *   The render array.
   */
  protected function addCacheMetadata(array &$build): void {
    CacheableMetadata::createFromRenderArray($build)
      ->addCacheTags(['ai_log_list'])
      ->addCacheContexts(['url.query_args', 'user.permissions'])
      ->addCacheableDependency($this->config('ai_logging.settings'))
      ->applyTo($build);
  }

}
