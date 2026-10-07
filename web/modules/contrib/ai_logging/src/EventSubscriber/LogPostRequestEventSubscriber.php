<?php

namespace Drupal\ai_logging\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\ai\Event\PostGenerateResponseEvent;
use Drupal\ai\Event\PostStreamingResponseEvent;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\ChatOutput;
use Drupal\ai\OperationType\InputInterface;
use Drupal\ai\OperationType\OutputInterface;
use Drupal\ai_logging\AiLogInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * The event that is triggered after a response is generated.
 *
 * @package Drupal\ai_logging\EventSubscriber
 */
class LogPostRequestEventSubscriber implements EventSubscriberInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The AI settings.
   *
   * @var ImmutableConfig
   */
  protected $aiSettings;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * UUID to log for streaming.
   *
   * @var array
   */
  protected $streamingUuids = [];

  /**
   * Constructor.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, ConfigFactoryInterface $configFactory, ModuleHandlerInterface $moduleHandler) {
    $this->entityTypeManager = $entityTypeManager;
    $this->aiSettings = $configFactory->get('ai_logging.settings');
    $this->moduleHandler = $moduleHandler;
  }

  /**
   * {@inheritdoc}
   *
   * @return array
   *   The post generate response event.
   */
  public static function getSubscribedEvents(): array {
    return [
      PostGenerateResponseEvent::EVENT_NAME => 'logPostRequest',
      PostStreamingResponseEvent::EVENT_NAME => 'logPostStream',
    ];
  }

  /**
   * Log if needed after running an AI request.
   *
   * @param \Drupal\ai\Event\PostGenerateResponseEvent $event
   *   The event to log.
   */
  public function logPostRequest(PostGenerateResponseEvent $event) {
    // If logging is enabled, log the prompt and response.
    if ($this->shouldLoggingHappen($event->getOperationType(), $event->getTags())) {
      $storage = $this->entityTypeManager->getStorage('ai_log');
      /** @var \Drupal\ai_logging\Entity\AiLog $log */
      $log = $storage->create([
        'provider' => $event->getProviderId(),
        'model' => $event->getModelId(),
        'operation_type' => $event->getOperationType(),
        'configuration' => json_encode($event->getConfiguration()),
        'bundle' => 'generic',
        'tags' => $event->getTags(),
        'prompt' => $this->getPromptText($event->getInput()),
        'extra_data' => json_encode($event->getDebugData()),
      ]);
      if ($this->aiSettings->get('prompt_logging_output')) {
        $log->set('output_text', json_encode($event->getOutput()->getRawOutput()));
        // A streamed reply is not known yet, logPostStream() sets it.
        $log->set('response_text', $this->getResponseText($event->getOutput()));
      }
      // Token usage is already final for non-streamed responses. For
      // streamed ones it is only final once logPostStream() runs, so this
      // call is a no-op until then.
      $this->setTokenUsageFields($log, $event->getOutput());
      $log->save();
      // Remember the log entry so a streamed response can update it with the
      // final output text and/or final token usage once it completes,
      // regardless of whether output logging is enabled.
      if ($event->getOutput()->getNormalized() instanceof \IteratorAggregate) {
        $this->streamingUuids[$event->getRequestThreadId()] = $log->id();
      }
    }
  }

  /**
   * If the log was a streaming object, we need to update with the response.
   *
   * @param \Drupal\ai\Event\PostStreamingResponseEvent $event
   *   The event to log.
   */
  public function logPostStream(PostStreamingResponseEvent $event) {
    if (!isset($this->streamingUuids[$event->getRequestThreadId()])) {
      return;
    }
    $log_id = $this->streamingUuids[$event->getRequestThreadId()];
    unset($this->streamingUuids[$event->getRequestThreadId()]);

    if (!$event->getOutput()) {
      return;
    }
    // Load to update.
    $storage = $this->entityTypeManager->getStorage('ai_log');
    /** @var \Drupal\ai_logging\Entity\AiLog $log */
    $log = $storage->load($log_id);
    if (!$log) {
      return;
    }

    $changed = $this->setTokenUsageFields($log, $event->getOutput());
    // If response logging is enabled, add the streamed response.
    if ($this->aiSettings->get('prompt_logging_output')) {
      $log->set('output_text', json_encode($event->getOutput()->getRawOutput()));
      $log->set('response_text', $this->getResponseText($event->getOutput()));
      $changed = TRUE;
    }
    if ($changed) {
      $log->save();
    }
  }

  /**
   * Gets the text the model replied with.
   *
   * Unlike the raw output, the normalized text reads the same for every
   * provider. The prompt only records what a call saw, so without this the
   * reply to a call that nothing continues (a structured JSON schema call, or
   * the final turn of an agent) is not visible anywhere.
   *
   * @param \Drupal\ai\OperationType\OutputInterface $output
   *   The output to read the reply from.
   *
   * @return string|null
   *   The reply text, or NULL for a call that is not chat, a streamed reply
   *   that is not assembled yet, or a turn that only calls tools.
   */
  protected function getResponseText(OutputInterface $output): ?string {
    if (!$output instanceof ChatOutput) {
      return NULL;
    }
    $message = $output->getNormalized();
    if (!$message instanceof ChatMessage) {
      return NULL;
    }
    $text = $message->getText();
    return $text === '' ? NULL : $text;
  }

  /**
   * Copies available token usage data from the output onto the log entity.
   *
   * @param \Drupal\ai_logging\AiLogInterface $log
   *   The log entity to update.
   * @param \Drupal\ai\OperationType\OutputInterface $output
   *   The output to read token usage from.
   *
   * @return bool
   *   TRUE if at least one token usage field was set on the log entity.
   */
  protected function setTokenUsageFields(AiLogInterface $log, OutputInterface $output): bool {
    // Not every operation type's output tracks token usage (e.g. embeddings,
    // moderation), and streamed outputs only report it once fully consumed.
    if (!method_exists($output, 'getTokenUsage')) {
      return FALSE;
    }
    $usage = $output->getTokenUsage();
    $changed = FALSE;
    foreach ([
      'tokens_input' => $usage->input,
      'tokens_output' => $usage->output,
      'tokens_total' => $usage->total,
      'tokens_reasoning' => $usage->reasoning,
      'tokens_cached' => $usage->cached,
    ] as $field_name => $value) {
      if ($value !== NULL && $log->hasField($field_name)) {
        $log->set($field_name, $value);
        $changed = TRUE;
      }
    }
    return $changed;
  }

  /**
   * Function to check if logging should happen.
   *
   * Excluded tags take precedence: if a request carries any excluded tag it is
   * never logged, regardless of the allow list. Otherwise an empty allow list
   * logs everything and a non-empty one requires at least one matching tag.
   * Tags are compared case-insensitively, ignoring surrounding whitespace.
   *
   * @param string $operation_type
   *   The operation type.
   * @param array $tags
   *   Tags to check against.
   *
   * @return bool
   *   If logging should happen.
   */
  protected function shouldLoggingHappen(string $operation_type, array $tags): bool {
    if (empty($this->aiSettings->get('prompt_logging'))) {
      return FALSE;
    }

    // Normalize tags for comparison.
    $normalized_tags = [];
    foreach ($tags as $tag) {
      $normalized_tags[] = strtolower(trim($tag));
    }

    // Check excluded tags first - if any match, don't log.
    $prompt_logging_excluded_tags = $this->aiSettings->get('prompt_logging_excluded_tags');
    if (!empty($prompt_logging_excluded_tags)) {
      $excluded_tags = array_filter(
        array_map(
          static fn(string $tag): string => strtolower(trim($tag)),
          explode(',', $prompt_logging_excluded_tags)
        ),
        static fn(string $tag): bool => $tag !== '',
      );
      if (array_intersect($excluded_tags, $normalized_tags)) {
        return FALSE;
      }
    }

    // Check if the included tags are empty.
    $prompt_logging_tags = $this->aiSettings->get('prompt_logging_tags');
    if (empty($prompt_logging_tags)) {
      return TRUE;
    }
    $compare_tags = explode(',', $prompt_logging_tags);
    foreach ($compare_tags as $tag) {
      if (in_array(strtolower(trim($tag)), $normalized_tags)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Get the context link.
   *
   * @param \Drupal\ai\Event\PostGenerateResponseEvent $event
   *   The event to log.
   *
   * @return string
   *   The context link rendered.
   */
  protected function getContextLink(PostGenerateResponseEvent $event): string {
    $route = 'prompt_explorer.prompt_form';
    switch ($event->getOperationType()) {
      case 'chat':
        $route = 'ai_api_explorer.chat_generation_form';
        break;

      case 'embeddings':
        $route = 'ai_api_explorer.embeddings_form';
        break;

      case 'moderation':
        $route = 'ai_api_explorer.moderation_form';
        break;

      case 'text_to_image':
        $route = 'ai_api_explorer.image_generation_form';
        break;

      case 'text_to_speech':
        $route = 'ai_api_explorer.text_to_speech_form';
        break;

      case 'speech_to_text':
        $route = 'ai_api_explorer.speech_to_text_form';
        break;

      default:
        return '';
    }
    $url = Url::fromRoute($route, [], [
      'query' => [
        'input' => $this->getInputText($event->getInput()),
        'provider_id' => $event->getProviderId(),
        'model_id' => $event->getModelId(),
        'config' => json_encode($event->getConfiguration()),
      ],
    ]);
    return Link::fromTextAndUrl('Test AI Request', $url)->toString();
  }

  /**
   * Get the text from the input.
   *
   * @param mixed $input
   *   The input to get the text from.
   *
   * @return string
   *   The text from the input.
   */
  protected function getInputText($input): string {
    if ($input instanceof InputInterface) {
      return $input->toString();
    }
    return json_encode($input);
  }

  /**
   * Builds the transcript stored as a log entry's prompt.
   *
   * ChatInput::toString() renders every message as "role\ntext", which
   * drops all tool use: an assistant turn that only calls tools has no
   * text of its own, so it logs as a bare "assistant" line with nothing
   * under it, and each tool result logs under an equally bare "tool" line
   * with no indication of which tool produced it. A turn calling several
   * tools therefore reads back as a run of identical, unlabeled blocks
   * that can only be told apart by guessing from their content.
   *
   * The messages themselves carry what's missing - the requested calls and
   * their arguments on the assistant message (ChatMessage::getTools()),
   * and the id of the call each result answers
   * (ChatMessage::getToolsId()) - so name each call and attribute each
   * result to it here.
   *
   * @param mixed $input
   *   The input to build the transcript from.
   *
   * @return string
   *   The transcript.
   */
  protected function getPromptText($input): string {
    if (!$input instanceof ChatInput) {
      return $this->getInputText($input);
    }

    // Tool results arrive as their own later messages carrying only the id
    // of the call they answer, so map id => name off the assistant
    // messages as they go past, before any result needs looking up.
    $tool_names = [];
    $transcript = '';
    foreach ($input->getMessages() as $message) {
      if (!$message instanceof ChatMessage) {
        continue;
      }

      $role = $message->getRole();
      $tool_id = $message->getToolsId();
      if ($role === 'tool' && $tool_id !== NULL && isset($tool_names[$tool_id])) {
        $role .= ' (' . $tool_names[$tool_id] . ')';
      }
      $transcript .= $role . "\n";

      foreach ($message->getTools() ?? [] as $tool) {
        $rendered = $tool->getOutputRenderArray();
        $name = $rendered['function']['name'] ?? '';
        if (isset($rendered['id'])) {
          $tool_names[$rendered['id']] = $name;
        }
        $transcript .= '[calls ' . $name . '] ' . ($rendered['function']['arguments'] ?? '') . "\n";
      }

      $transcript .= $message->getText() . "\n";
    }
    return $transcript;
  }

}
