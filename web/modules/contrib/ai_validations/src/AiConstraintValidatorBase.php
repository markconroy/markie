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
 *  - running the XTRUE/XFALSE chat protocol used by the text and image
 *    prompt-based validators.
 *
 * @phpstan-consistent-constructor
 */
abstract class AiConstraintValidatorBase extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * The AI provider plugin manager.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected AiProviderPluginManager $aiPluginManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs an AI constraint validator.
   *
   * @param \Drupal\ai\AiProviderPluginManager $aiPluginManager
   *   The AI provider plugin manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  final public function __construct(AiProviderPluginManager $aiPluginManager, EntityTypeManagerInterface $entityTypeManager) {
    $this->aiPluginManager = $aiPluginManager;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('ai.provider'),
      $container->get('entity_type.manager'),
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
   * Returns NULL when $data is empty (no file uploaded) or after raising a
   * violation when the file entity cannot be loaded.
   *
   * @param mixed $data
   *   The file ID, typically a target_id from an audio/file field.
   * @param string $option
   *   A resolved "provider__model" option for the speech_to_text operation.
   *
   * @return string|null
   *   The transcript, or NULL when transcription should be skipped.
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
    return $provider->speechToText($input, $model)->getNormalized();
  }

  /**
   * Runs the XTRUE/XFALSE chat protocol shared by text/image validators.
   *
   * @param string $option
   *   A resolved "provider__model" option.
   * @param string $prompt
   *   System prompt instructing the model to respond with XTRUE or XFALSE.
   * @param string $userText
   *   User-message body.
   * @param \Drupal\ai\OperationType\GenericType\ImageFile|null $image
   *   Optional image to attach to the user message.
   *
   * @return bool
   *   TRUE when the response contains "XTRUE"; FALSE otherwise (including
   *   "XFALSE" or no recognizable token).
   */
  protected function runBooleanChat(string $option, string $prompt, string $userText, ?ImageFile $image = NULL): bool {
    $provider = $this->aiPluginManager->loadProviderFromSimpleOption($option);
    $model = $this->aiPluginManager->getModelNameFromSimpleOption($option);
    $userMessage = $image === NULL
      ? new ChatMessage('user', $userText)
      : new ChatMessage('user', $userText, [$image]);
    $messages = new ChatInput([
      new ChatMessage('system', $prompt),
      $userMessage,
    ]);
    try {
      $response = $provider->chat($messages, $model)->getNormalized();
    }
    catch (\Exception $e) {
      return FALSE;
    }
    return str_contains($response->getText(), 'XTRUE');
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
