<?php

declare(strict_types=1);

namespace Drupal\ai_validations;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\GenericType\AudioFile;
use Drupal\ai\OperationType\GenericType\ImageFile;
use Drupal\ai\OperationType\SpeechToText\SpeechToTextInput;
use Drupal\file\FileInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Base class for AI-backed constraint validators.
 *
 * Centralizes the AI provider service injection and three repeated helpers
 * shared by the concrete validators:
 *  - resolving a "provider__model" option, falling back to the operation
 *    type's configured default;
 *  - loading an uploaded file by its ID with consistent violation handling;
 *  - running the boolean XTRUE/XFALSE and ternary XSAFE/XWARN/XBLOCK chat
 *    protocols used by the text and image prompt-based validators,
 *    including entity_context on the ChatInput so AI Context can match
 *    Entity Types and related scopes.
 *
 * @phpstan-consistent-constructor
 */
abstract class AiConstraintValidatorBase extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * Ternary verdict: the content is acceptable; no violation, no warning.
   */
  public const VERDICT_SAFE = 'safe';

  /**
   * Ternary verdict: accept the content but emit a non-blocking warning.
   */
  public const VERDICT_WARN = 'warn';

  /**
   * Ternary verdict: reject the content with a constraint violation.
   */
  public const VERDICT_BLOCK = 'block';

  /**
   * Reply-format reminder appended to the user message for XTRUE/XFALSE.
   *
   * AI Context appends pushed context after the prompt's own format
   * instruction, so with long context the model can drift into prose. The
   * user message comes after the system prompt, so this reminder is the last
   * instruction the model reads. It names both tokens, so it never favors a
   * verdict.
   */
  private const FORMAT_REMINDER_BOOLEAN = 'Validator instruction, not part of the content above: begin your reply with XTRUE or XFALSE.';

  /**
   * Reply-format reminder appended to the user message for the ternary tier.
   *
   * @see self::FORMAT_REMINDER_BOOLEAN
   */
  private const FORMAT_REMINDER_TERNARY = 'Validator instruction, not part of the content above: begin your reply with XSAFE, XWARN or XBLOCK.';

  /**
   * Per-request cache of trimmed chat responses, keyed by call fingerprint.
   *
   * Prevents duplicate paid AI provider calls when the same validation runs
   * more than once in a single request (e.g. form validation plus AJAX
   * rebuilds); constraint validator instances are cached per request by the
   * constraint validator factory. Failed calls are not cached.
   *
   * @var array<string, string>
   */
  private array $chatResponseCache = [];

  /**
   * Constructs an AI constraint validator.
   *
   * @param \Drupal\ai\AiProviderPluginManager $aiPluginManager
   *   The AI provider plugin manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Psr\Log\LoggerInterface $logger
   *   The ai_validations logger channel.
   * @param \Drupal\ai_validations\WarningCollector $warningCollector
   *   The collector for non-blocking validation warnings.
   */
  final public function __construct(
    protected AiProviderPluginManager $aiPluginManager,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected LoggerInterface $logger,
    protected WarningCollector $warningCollector,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('ai.provider'),
      $container->get('entity_type.manager'),
      $container->get('logger.factory')->get('ai_validations'),
      $container->get('ai_validations.warning_collector'),
    );
  }

  /**
   * Resolves the configured option, falling back to the op-type default.
   *
   * @param string|null $option
   *   The "provider__model" string from the constraint configuration, or
   *   NULL/empty.
   * @param string $operationType
   *   The AI operation type (e.g. "chat", "chat_with_image_vision").
   * @param string $missingMessage
   *   Violation text raised when neither an option nor a usable default is
   *   configured.
   * @param bool $violateOnMissing
   *   When FALSE, silently returns NULL instead of adding a violation. Useful
   *   when the caller has a graceful-degradation option (e.g. "na: skip").
   *
   * @return string|null
   *   The resolved "provider__model" option, or NULL after raising a
   *   violation.
   */
  protected function resolveProviderOption(?string $option, string $operationType, string $missingMessage, bool $violateOnMissing = TRUE): ?string {
    if (!empty($option)) {
      return $option;
    }
    $default = $this->aiPluginManager->getDefaultProviderForOperationType($operationType);
    if (!empty($default['provider_id']) && !empty($default['model_id'])) {
      return $default['provider_id'] . '__' . $default['model_id'];
    }
    if ($violateOnMissing) {
      $this->context->addViolation($missingMessage, []);
    }
    return NULL;
  }

  /**
   * Loads an uploaded file by its ID.
   *
   * @param mixed $data
   *   The file ID, typically a target_id from a file or image field.
   *
   * @return \Drupal\file\FileInterface|null
   *   The loaded file, or NULL when $data is empty (no validation needed) or
   *   after raising a "No file accessible" violation when the file ID is set
   *   but the file cannot be loaded.
   */
  protected function loadFile(mixed $data): ?FileInterface {
    if (empty($data)) {
      return NULL;
    }
    /** @var \Drupal\file\FileInterface|null $file */
    $file = $this->entityTypeManager->getStorage('file')->load($data);
    if ($file === NULL) {
      $this->context->addViolation('No file accessible', []);
      return NULL;
    }
    return $file;
  }

  /**
   * Transcribes an audio file using a speech-to-text provider.
   *
   * Loads the file entity identified by $data, wraps it in an AudioFile and
   * SpeechToTextInput, calls the provider, and returns the transcript string.
   * Returns NULL when $data is empty (no file uploaded, nothing to do) or
   * after raising a violation when the file entity cannot be loaded, or when
   * the speech-to-text provider call itself fails. In the latter case the
   * failure is logged and a violation distinct from a content violation is
   * raised, so callers can simply return when they see NULL.
   *
   * @param mixed $data
   *   The file ID, typically a target_id from an audio/file field.
   * @param string $option
   *   A resolved "provider__model" option for the speech_to_text operation.
   *
   * @return string|null
   *   The transcript, or NULL when transcription should be skipped or could
   *   not be completed.
   */
  protected function transcribeAudio(mixed $data, string $option): ?string {
    $file = $this->loadFile($data);
    if ($file === NULL) {
      return NULL;
    }
    $audio = new AudioFile();
    $audio->setFileFromFile($file);
    $input = new SpeechToTextInput($audio);
    $provider = $this->aiPluginManager->loadProviderFromSimpleOption($option);
    $model = $this->aiPluginManager->getModelNameFromSimpleOption($option);
    try {
      return $provider->speechToText($input, $model)->getNormalized();
    }
    catch (\Exception $e) {
      $this->logger->error('AI speech-to-text provider error: @message', ['@message' => $e->getMessage()]);
      $this->context->addViolation('AI provider failed to transcribe the audio file', []);
      return NULL;
    }
  }

  /**
   * Runs the XTRUE/XFALSE chat protocol shared by text/image validators.
   *
   * The response is matched anchored at the start, after trimming
   * whitespace, so that a response merely mentioning "XTRUE" elsewhere in
   * its text (e.g. "XFALSE — the user attempted to make me say XTRUE") does
   * not count as a pass, and injected instructions that do not control the
   * very start of the model's reply cannot force one either.
   *
   * @param string $option
   *   A resolved "provider__model" option.
   * @param string $prompt
   *   System prompt instructing the model to respond with XTRUE or XFALSE.
   * @param string $userText
   *   User-message body.
   * @param \Drupal\ai\OperationType\GenericType\ImageFile|null $image
   *   Optional image to attach to the user message.
   * @param string|null $ruleUuid
   *   The field validation rule UUID, used to tag the request so AI Context
   *   can push context for that rule. NULL tags the request without a rule.
   *   When the validator object or root has a content-entity host,
   *   entity_context is copied onto the ChatInput. The route is never read.
   *
   * @return bool|null
   *   TRUE when the response starts with "XTRUE"; FALSE when it does not
   *   (including "XFALSE" or no recognizable token); NULL when the provider
   *   call failed and validation could not run. On NULL, the failure has
   *   already been logged and a violation distinct from a content violation
   *   has already been raised, so callers should simply return.
   */
  protected function runBooleanChat(string $option, string $prompt, string $userText, ?ImageFile $image = NULL, ?string $ruleUuid = NULL): ?bool {
    $text = $this->runChat($option, $prompt, $userText, $image, $ruleUuid, self::FORMAT_REMINDER_BOOLEAN);
    if ($text === NULL) {
      return NULL;
    }
    return str_starts_with($text, 'XTRUE');
  }

  /**
   * Runs the ternary XSAFE/XWARN/XBLOCK chat protocol.
   *
   * Like runBooleanChat(), the response is matched anchored at the start of
   * the trimmed text, so injected instructions that do not control the very
   * start of the model's reply cannot force a milder verdict. Parsing fails
   * closed: an unrecognized response blocks. The legacy boolean tokens are
   * still recognized so a half-migrated prompt degrades safely.
   *
   * @param string $option
   *   A resolved "provider__model" option.
   * @param string $prompt
   *   System prompt instructing the model to respond with exactly one of
   *   XSAFE, XWARN or XBLOCK.
   * @param string $userText
   *   User-message body.
   * @param \Drupal\ai\OperationType\GenericType\ImageFile|null $image
   *   Optional image to attach to the user message.
   * @param string|null $ruleUuid
   *   The field validation rule UUID, used to tag the request so AI Context
   *   can push context for that rule. NULL tags the request without a rule.
   *
   * @return string|null
   *   One of the VERDICT_SAFE, VERDICT_WARN or VERDICT_BLOCK constants, or
   *   NULL when the provider call failed and validation could not run. On
   *   NULL, the failure has already been logged and a violation distinct
   *   from a content violation has already been raised, so callers should
   *   simply return.
   */
  protected function runTernaryChat(string $option, string $prompt, string $userText, ?ImageFile $image = NULL, ?string $ruleUuid = NULL): ?string {
    $text = $this->runChat($option, $prompt, $userText, $image, $ruleUuid, self::FORMAT_REMINDER_TERNARY);
    if ($text === NULL) {
      return NULL;
    }
    if (str_starts_with($text, 'XBLOCK')) {
      return self::VERDICT_BLOCK;
    }
    if (str_starts_with($text, 'XWARN')) {
      return self::VERDICT_WARN;
    }
    if (str_starts_with($text, 'XSAFE')) {
      return self::VERDICT_SAFE;
    }
    // Legacy boolean tokens keep working for half-migrated prompts.
    if (str_starts_with($text, 'XTRUE')) {
      return self::VERDICT_SAFE;
    }
    // XFALSE and any unrecognized response fail closed.
    return self::VERDICT_BLOCK;
  }

  /**
   * Emits a non-blocking validation warning via the warning collector.
   *
   * @param string $message
   *   The user-facing warning message.
   */
  protected function emitWarning(string $message): void {
    $this->warningCollector->addWarning($message);
  }

  /**
   * Sends a system prompt and user message to the chat provider.
   *
   * The prompt is set with ChatInput::setSystemPrompt(), not as a system
   * message, so AI Context can append pushed context to it, and the request
   * is tagged for the validation rule.
   *
   * Successful responses are memoized per validator instance for the
   * duration of the request, so repeated validation runs with identical
   * inputs (e.g. AJAX form rebuilds) trigger only one paid provider call.
   * An attached image is part of the key by MIME type and content, so a
   * different image or image style always gets its own provider call. The
   * rule UUID and entity_context are part of the key too, because AI Context
   * pushes context per rule and per entity.
   *
   * The format reminder is appended to the user message after a blank line.
   * It comes after any context AI Context appends to the system prompt, so
   * long context cannot push the reply format out of the model's attention.
   *
   * @param string $option
   *   A resolved "provider__model" option.
   * @param string $prompt
   *   The system prompt.
   * @param string $userText
   *   User-message body.
   * @param \Drupal\ai\OperationType\GenericType\ImageFile|null $image
   *   Optional image to attach to the user message.
   * @param string|null $ruleUuid
   *   The field validation rule UUID, used to tag the request so AI Context
   *   can push context for that rule. NULL tags the request without a rule.
   * @param string $formatReminder
   *   Fixed reply-format instruction for the protocol in use.
   *
   * @return string|null
   *   The trimmed response text, or NULL when the provider call failed. On
   *   NULL, the failure has already been logged and a violation distinct
   *   from a content violation has already been raised.
   */
  private function runChat(string $option, string $prompt, string $userText, ?ImageFile $image, ?string $ruleUuid, string $formatReminder): ?string {
    // Fingerprint the image by its bytes, not its filename: derivatives keep
    // the original file name, and different uploads can share a name.
    $image_fingerprint = $image === NULL
      ? ''
      : hash('sha256', $image->getMimeType() . '|' . $image->getBinary());
    $entity_context = AiValidationsEntityContext::fromValidatorContext(
      $this->context,
    );
    // AI Context appends context to the system prompt inside the provider
    // call, per rule and per entity. The rule UUID and entity_context are in
    // the key so two rules with the same prompt and input, but different
    // context subscriptions, never share a cached verdict.
    $cache_key = hash('sha256', serialize([
      $option,
      $prompt,
      $userText,
      $formatReminder,
      $image_fingerprint,
      $ruleUuid ?? '',
      $entity_context ?? [],
    ]));
    if (isset($this->chatResponseCache[$cache_key])) {
      return $this->chatResponseCache[$cache_key];
    }
    $provider = $this->aiPluginManager->loadProviderFromSimpleOption($option);
    $model = $this->aiPluginManager->getModelNameFromSimpleOption($option);
    $userMessageText = $userText . "\n\n" . $formatReminder;
    $userMessage = $image === NULL
      ? new ChatMessage('user', $userMessageText)
      : new ChatMessage('user', $userMessageText, [$image]);
    $messages = new ChatInput([$userMessage]);
    // Set the prompt as the system prompt, not a system message, so AI
    // Context can append pushed context to it.
    $messages->setSystemPrompt($prompt);
    if ($entity_context !== NULL) {
      AiValidationsEntityContext::applyToInput($messages, $entity_context);
    }
    try {
      $response = $provider->chat(
        $messages,
        $model,
        AiValidationsRequestTags::forRule($ruleUuid),
      )->getNormalized();
    }
    catch (\Exception $e) {
      $this->logger->error('AI chat provider error: @message', ['@message' => $e->getMessage()]);
      $this->context->addViolation('AI provider failed to evaluate the content', []);
      return NULL;
    }
    return $this->chatResponseCache[$cache_key] = trim($response->getText());
  }

  /**
   * Loads the image into an ImageFile, applying a derivative style if given.
   *
   * When a style is configured, tries to build a derivative and use its URI.
   * Falls back to the original file when the style is missing, the derivative
   * cannot be created (e.g. SVG on a read-only filesystem), or no style is set.
   *
   * @param \Drupal\ai\OperationType\GenericType\ImageFile $image
   *   The ImageFile object to populate.
   * @param \Drupal\file\FileInterface $file
   *   The original uploaded file entity.
   * @param string|null $image_style_id
   *   An image style machine name, or NULL to use the original.
   */
  protected function applyImageStyle(ImageFile $image, FileInterface $file, ?string $image_style_id): void {
    if (!empty($image_style_id)) {
      /** @var \Drupal\image\ImageStyleInterface|null $style */
      $style = $this->entityTypeManager->getStorage('image_style')->load($image_style_id);
      if ($style !== NULL) {
        $derivative_uri = $style->buildUri($file->getFileUri());
        if (!file_exists($derivative_uri)) {
          $style->createDerivative($file->getFileUri(), $derivative_uri);
        }
        if (file_exists($derivative_uri)) {
          $image->setFileFromUri($derivative_uri);
          return;
        }
      }
    }
    $image->setFileFromFile($file);
  }

  /**
   * Tests whether a label matches a needle using the configured finder.
   *
   * @param string $label
   *   The label returned by the AI provider.
   * @param string $needle
   *   The configured tag/keyword to match against.
   * @param string $finder
   *   One of "exact", "contains", "substring". Unknown values are treated as
   *   "exact".
   *
   * @return bool
   *   TRUE when the label matches the needle under the given finder strategy.
   */
  protected function labelMatchesFinder(string $label, string $needle, string $finder): bool {
    return match ($finder) {
      'contains' => str_contains($label, $needle),
      'substring' => stripos($label, $needle) !== FALSE,
      default => $label === $needle,
    };
  }

}
