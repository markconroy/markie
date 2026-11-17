<?php

declare(strict_types=1);

namespace Drupal\ai_logging\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Http\Exception\CacheableAccessDeniedHttpException;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\ai_logging\ResponseTextFormatter;
use Drupal\ai_logging\Thread\ThreadCall;
use Drupal\ai_logging\Thread\ThreadLabelResolverInterface;
use Drupal\ai_logging\Thread\ThreadRepository;
use Drupal\ai_logging\Thread\ThreadTimelineBuilder;
use Drupal\ai_logging\Thread\ThreadTimelineItem;
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
   * The thread timeline builder.
   *
   * @var \Drupal\ai_logging\Thread\ThreadTimelineBuilder
   */
  protected ThreadTimelineBuilder $threadTimelineBuilder;

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
    $instance->threadTimelineBuilder = $container->get('ai_logging.thread_timeline_builder');
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

    $cacheability = new CacheableMetadata();
    $cacheability->addCacheTags(['ai_log_list'])->addCacheContexts(['user.permissions']);
    $visible = [];
    foreach ($entries as $entry) {
      $access = $entry->access('view', NULL, TRUE);
      $cacheability->addCacheableDependency($access);
      if ($access->isAllowed()) {
        $cacheability->addCacheableDependency($entry);
        $visible[] = $entry;
      }
    }
    if (!$visible) {
      throw new CacheableAccessDeniedHttpException($cacheability);
    }

    $timeline = $this->threadTimelineBuilder->build($visible, $thread_id);
    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['ai-logging-thread-page']],
      '#attached' => [
        'library' => ['ai_logging/threads'],
      ],
      'contents' => $this->buildContents($timeline, $visible),
      'timeline' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['ai-logging-thread']],
      ],
    ];
    foreach ($timeline as $item) {
      $build['timeline'][$item->getElementId()] = match ($item->type) {
        ThreadTimelineItem::QUESTION => $this->buildHighlight($item, $this->t('Question · @time', [
          '@time' => $this->formatTime($item->call),
        ])),
        ThreadTimelineItem::ANSWER => $this->buildHighlight($item, $this->t('Answer · @time · from call @number', [
          '@time' => $this->formatTime($item->call),
          '@number' => $item->call->number,
        ])),
        default => $this->buildCall($item),
      };
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
   * Builds the thread's contents: its questions, calls and answers.
   *
   * @param \Drupal\ai_logging\Thread\ThreadTimelineItem[] $timeline
   *   The thread's timeline.
   * @param \Drupal\ai_logging\AiLogInterface[] $entries
   *   The thread's log entries, oldest first.
   *
   * @return array
   *   A render array.
   */
  protected function buildContents(array $timeline, array $entries): array {
    $items = [];
    foreach ($timeline as $item) {
      $call = $item->call;
      $items[] = [
        'type' => $item->type,
        'id' => $item->getElementId(),
      ] + match ($item->type) {
        ThreadTimelineItem::QUESTION => [
          'marker' => $this->t('Q'),
          'label' => $this->responseTextFormatter->summarize($item->text, 120),
          'meta' => $this->formatTime($call),
        ],
        ThreadTimelineItem::ANSWER => [
          'marker' => $this->t('A'),
          'label' => $this->t('Answer: @text', ['@text' => $this->responseTextFormatter->summarize($item->text, 100)]),
          'meta' => $this->formatTime($call),
        ],
        default => [
          'marker' => $call->number,
          'label' => $this->getContentsLabel($call),
          'meta' => $this->formatTiming($call),
        ],
      };
    }

    $tokens = 0;
    foreach ($entries as $entry) {
      $tokens += (int) $entry->get('tokens_total')->value;
    }
    $first = (int) reset($entries)->get('created')->value;
    $last = (int) end($entries)->get('created')->value;
    return [
      '#theme' => 'ai_logging_thread_contents',
      '#items' => $items,
      '#totals' => $this->formatPlural(count($entries), '@date · 1 call · @tokens tokens', '@date · @count calls · @duration · @tokens tokens', [
        '@date' => $this->dateFormatter->format($first, 'short'),
        '@duration' => $this->dateFormatter->formatInterval($last - $first),
        '@tokens' => number_format($tokens),
      ]),
    ];
  }

  /**
   * Gets a call's label in the thread's contents.
   *
   * @param \Drupal\ai_logging\Thread\ThreadCall $call
   *   The call.
   *
   * @return string
   *   The tools it called, or what kind of answer it got back, followed by
   *   what it was for.
   */
  protected function getContentsLabel(ThreadCall $call): string {
    $label = $call->kind === ThreadCall::KIND_TOOL_CALLS
      ? $this->responseTextFormatter->summarize((string) $call->entry->get('response_text')->value)
      : (string) $this->getKindLabel($call);
    return $call->purposeTags ? $label . ' · ' . implode(', ', $call->purposeTags) : $label;
  }

  /**
   * Gets the label for what kind of answer a call got back from the model.
   *
   * @param \Drupal\ai_logging\Thread\ThreadCall $call
   *   The call.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The label.
   */
  protected function getKindLabel(ThreadCall $call): TranslatableMarkup {
    return match ($call->kind) {
      ThreadCall::KIND_TOOL_CALLS => $this->t('Tool calls'),
      ThreadCall::KIND_STRUCTURED => $this->t('Structured result'),
      ThreadCall::KIND_TEXT => $this->t('Text response'),
      ThreadCall::KIND_RAW => $this->t('Raw output'),
      default => $this->t('No response'),
    };
  }

  /**
   * Formats the time of day a call was made.
   *
   * @param \Drupal\ai_logging\Thread\ThreadCall $call
   *   The call.
   *
   * @return string
   *   For example "10:42:07".
   */
  protected function formatTime(ThreadCall $call): string {
    return $this->dateFormatter->format($call->getCreated(), 'html_time');
  }

  /**
   * Formats when a call was made in the thread, and its tokens.
   *
   * @param \Drupal\ai_logging\Thread\ThreadCall $call
   *   The call.
   *
   * @return string
   *   For example "+16 sec · 6,813 tokens".
   */
  protected function formatTiming(ThreadCall $call): string {
    $elapsed = (string) $this->t('+@elapsed', ['@elapsed' => $this->dateFormatter->formatInterval($call->elapsed)]);
    $tokens = $call->entry->get('tokens_total')->value;
    return $tokens === NULL ? $elapsed : (string) $this->t('@elapsed · @tokens tokens', [
      '@elapsed' => $elapsed,
      '@tokens' => number_format((int) $tokens),
    ]);
  }

  /**
   * Builds a highlighted question or answer between the calls.
   *
   * An answer written in Markdown, as an assistant's usually is, is shown
   * rendered.
   *
   * @param \Drupal\ai_logging\Thread\ThreadTimelineItem $item
   *   The question or answer.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $title
   *   The title.
   *
   * @return array
   *   A render array.
   */
  protected function buildHighlight(ThreadTimelineItem $item, TranslatableMarkup $title): array {
    $markdown = $item->type === ThreadTimelineItem::ANSWER && $this->responseTextFormatter->isMarkdown($item->text);
    return [
      '#theme' => 'ai_logging_thread_highlight',
      '#id' => $item->getElementId(),
      '#kind' => $item->type,
      '#title' => $title,
      '#text' => $markdown ? $this->responseTextFormatter->renderMarkdown($item->text) : $item->text,
      '#markdown' => $markdown,
    ];
  }

  /**
   * Builds the summary shown on a call while it is collapsed.
   *
   * @param \Drupal\ai_logging\Thread\ThreadCall $call
   *   The call.
   *
   * @return array
   *   A render array: the call's number, kind, purpose, timing and model,
   *   then the tools it called or the start of its answer.
   */
  protected function buildSummary(ThreadCall $call): array {
    $tool_calls = [];
    foreach ($call->toolCalls as $tool_call) {
      $tool_calls[] = [
        'name' => $tool_call['name'],
        'arguments' => $this->responseTextFormatter->summarizeArguments($tool_call['arguments'], 120),
      ];
    }
    return [
      '#theme' => 'ai_logging_thread_call_summary',
      '#title' => $this->t('@number · @kind', [
        '@number' => $call->number,
        '@kind' => $this->getKindLabel($call),
      ]),
      '#purpose_tags' => $call->purposeTags,
      '#meta' => $this->t('@timing · @model', [
        '@timing' => $this->formatTiming($call),
        '@model' => $call->entry->get('model')->value ?: $this->t('n/a'),
      ]),
      '#tool_calls' => $tool_calls,
      '#preview' => $call->kind !== ThreadCall::KIND_TOOL_CALLS && $call->text !== '' ? $this->responseTextFormatter->summarize($call->text, 160) : NULL,
    ];
  }

  /**
   * Builds the collapsed details element for one call in a thread.
   *
   * When the call's prompt continues the previous one made for the same
   * purpose, only what was added is shown up front, with the full prompt
   * collapsed underneath.
   *
   * @param \Drupal\ai_logging\Thread\ThreadTimelineItem $item
   *   The call.
   *
   * @return array
   *   A render array.
   */
  protected function buildCall(ThreadTimelineItem $item): array {
    $call = $item->call;
    $entry = $call->entry;
    $element = [
      '#type' => 'details',
      '#title' => $this->buildSummary($call),
      '#open' => FALSE,
      '#attributes' => [
        'id' => $item->getElementId(),
        'class' => ['ai-logging-call'],
      ],
      'meta' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#attributes' => ['class' => ['ai-logging-call__details']],
        '#value' => $this->t('@date · @operation · @provider / @model · @input in / @output out', [
          '@date' => $this->dateFormatter->format($call->getCreated(), 'short'),
          '@operation' => $entry->get('operation_type')->value ?: $this->t('n/a'),
          '@provider' => $entry->get('provider')->value ?: $this->t('n/a'),
          '@model' => $entry->get('model')->value ?: $this->t('n/a'),
          '@input' => $entry->get('tokens_input')->value ?? '?',
          '@output' => $entry->get('tokens_output')->value ?? '?',
        ]),
      ],
    ];

    $prompt = (string) $entry->get('prompt')->value;
    if ($call->newPromptText !== NULL) {
      $element['new_prompt'] = $this->responseTextFormatter->buildTranscript($this->t('New in this call'), $call->newPromptText);
      $element['prompt'] = [
        '#type' => 'details',
        '#title' => $this->t('Full prompt'),
        '#open' => FALSE,
        'text' => $this->responseTextFormatter->buildTranscript($this->t('Prompt'), $prompt),
      ];
    }
    else {
      $element['prompt'] = $this->responseTextFormatter->buildTranscript($this->t('Prompt'), $prompt);
    }

    $response_text = (string) $entry->get('response_text')->value;
    if ($response_text !== '') {
      $element['response'] = $this->responseTextFormatter->build($response_text);
    }
    elseif ($call->kind === ThreadCall::KIND_RAW) {
      $element['response'] = $this->responseTextFormatter->buildPreformatted($this->t('Raw output'), $call->text);
    }

    $element['tags'] = $this->responseTextFormatter->buildPreformatted($this->t('Tags'), implode(', ', $call->tags));
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
