<?php

namespace Drupal\ai_automators\Traits;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;

/**
 * Shared rich-text image description behavior for opt-in automators.
 */
trait RichTextImageDescriptionTrait {

  /**
   * Collected image description metadata for current generation.
   *
   * @var array<int,array<string,mixed>>
   */
  protected array $imageDescriptionMetadata = [];

  /**
   * If candidate count exceeded configured max images.
   */
  protected bool $imageDescriptionLimitExceeded = FALSE;

  /**
   * Total eligible images found in context.
   */
  protected int $imageDescriptionTotalCount = 0;

  /**
   * Number of images actually processed.
   */
  protected int $imageDescriptionProcessedCount = 0;

  /**
   * Number of images skipped due to max limit.
   */
  protected int $imageDescriptionSkippedCount = 0;

  /**
   * Number of encountered image elements in source HTML.
   */
  protected int $imageDescriptionEncounteredCount = 0;

  /**
   * Clears image metadata before building prompts.
   */
  protected function initializeImageDescriptionMetadata(): void {
    $this->imageDescriptionMetadata = [];
    $this->imageDescriptionLimitExceeded = FALSE;
    $this->imageDescriptionTotalCount = 0;
    $this->imageDescriptionProcessedCount = 0;
    $this->imageDescriptionSkippedCount = 0;
    $this->imageDescriptionEncounteredCount = 0;
  }

  /**
   * Appends discovered image descriptions to tokenized context.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity being processed.
   * @param array<string,mixed> $automatorConfig
   *   The automator configuration.
   * @param int $delta
   *   The field delta being processed.
   * @param array<string,mixed> $tokens
   *   Existing prompt tokens.
   *
   * @return array<string,mixed>
   *   Tokens with optional image description context.
   */
  protected function appendImageDescriptionsToTokens(ContentEntityInterface $entity, array $automatorConfig, int $delta, array $tokens): array {
    $rawContext = (string) ($tokens['raw_context'] ?? '');
    $context = (string) ($tokens['context'] ?? strip_tags($rawContext));
    $imageDescriptions = '';

    if ($this->shouldIncludeRichTextImageDescriptions($entity, $automatorConfig, $delta)) {
      $imageDescriptions = $this->generateImageDescriptionsFromRawContext($rawContext, $entity, $automatorConfig, $delta);
      if ($imageDescriptions !== '') {
        $context .= "\n\nImage descriptions:\n" . $imageDescriptions;
      }
      if ($this->hasImageDescriptionLimitExceeded()) {
        $context .= "\n\n" . $this->getImageDescriptionLimitMessage();
      }
    }

    $tokens['raw_context'] = $rawContext;
    $tokens['context'] = $context;
    $tokens['image_descriptions'] = $imageDescriptions;
    return $tokens;
  }

  /**
   * Adds rich-text image description controls to plugin advanced settings.
   *
   * @param array<string,mixed> $form
   *   The advanced form array.
   * @param array<string,mixed> $defaultValues
   *   The stored automator configuration values used as form defaults.
   *
   * @return array<string,mixed>
   *   The updated form.
   */
  protected function addImageDescriptionConfigurationForm(array $form, array $defaultValues = []): array {
    $form['automator_include_image_descriptions'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include image descriptions in output'),
      '#description' => $this->t('Analyze embedded images from the formatted base field and append their descriptions to the prompt context.'),
      '#default_value' => $defaultValues['automator_include_image_descriptions'] ?? FALSE,
    ];

    $form['automator_image_description_metadata_field'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Metadata field name'),
      '#description' => $this->t('Optional field machine name to store image description metadata as JSON.'),
      '#default_value' => $defaultValues['automator_image_description_metadata_field'] ?? '',
      '#states' => [
        'visible' => [
          ':input[name="automator_include_image_descriptions"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['automator_include_external_images'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include external image URLs'),
      '#description' => $this->t('Allow describing secure external images (HTTPS only). External images are untrusted and may contain hidden or visible instructions that attempt prompt injection, data exfiltration, or manipulation of later AI actions. Guardrails can help alleviate these risks, but cannot fully prevent them, so enable this only on your own responsibility and for trusted sources.'),
      '#default_value' => $defaultValues['automator_include_external_images'] ?? FALSE,
      '#states' => [
        'visible' => [
          ':input[name="automator_include_image_descriptions"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['automator_max_image_descriptions'] = [
      '#type' => 'number',
      '#title' => $this->t('Max images to describe'),
      '#default_value' => $defaultValues['automator_max_image_descriptions'] ?? 5,
      '#min' => 1,
      '#max' => 20,
      '#step' => 1,
      '#states' => [
        'visible' => [
          ':input[name="automator_include_image_descriptions"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['automator_image_description_prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Image description prompt'),
      '#description' => $this->t('Prompt used for each discovered image. Keep this prompt strict and instruction-limited because image text/content may attempt prompt injection.'),
      '#default_value' => $defaultValues['automator_image_description_prompt'] ?? 'Describe this image for editorial understanding in one or two concise sentences.',
      '#states' => [
        'visible' => [
          ':input[name="automator_include_image_descriptions"]' => ['checked' => TRUE],
        ],
      ],
    ];

    return $form;
  }

  /**
   * Store generated image description metadata if configured.
   */
  protected function storeImageDescriptionsMetadata(ContentEntityInterface $entity, array $automatorConfig): void {
    if (empty($automatorConfig['image_description_metadata_field']) || empty($this->imageDescriptionMetadata)) {
      return;
    }
    $this->getRichTextImageHelper()->storeMetadata($entity, $automatorConfig['image_description_metadata_field'], array_values($this->imageDescriptionMetadata));
  }

  /**
   * Checks if rich-text images should be included in context.
   */
  protected function shouldIncludeRichTextImageDescriptions(ContentEntityInterface $entity, array $automatorConfig, int $delta): bool {
    if (empty($automatorConfig['include_image_descriptions']) || empty($automatorConfig['base_field'])) {
      return FALSE;
    }
    if (!$entity->hasField($automatorConfig['base_field'])) {
      return FALSE;
    }
    $fieldDefinition = $entity->getFieldDefinition($automatorConfig['base_field']);
    if (!$fieldDefinition) {
      return FALSE;
    }
    if (!in_array($fieldDefinition->getType(), ['text', 'text_long', 'text_with_summary'], TRUE)) {
      return FALSE;
    }
    $value = $entity->get($automatorConfig['base_field'])->getValue();
    return !empty($value[$delta]['value']);
  }

  /**
   * Generates descriptions for images found in raw context HTML.
   */
  protected function generateImageDescriptionsFromRawContext(string $rawContext, ContentEntityInterface $entity, array $automatorConfig, int $delta): string {
    $includeExternal = !empty($automatorConfig['include_external_images']);
    $maxImages = (int) ($automatorConfig['max_image_descriptions'] ?? 5);
    $prompt = (string) ($automatorConfig['image_description_prompt'] ?? 'Describe this image for editorial understanding in one or two concise sentences.');
    $prompt = trim($prompt);
    if ($prompt === '') {
      $prompt = 'Describe this image for editorial understanding in one or two concise sentences.';
    }

    $visionConfig = $automatorConfig;
    $visionConfig['ai_provider'] = 'default_vision';

    try {
      $instance = $this->prepareLlmInstance('chat', $visionConfig);
      $model = $this->getModel($visionConfig);
    }
    catch (\Throwable $exception) {
      \Drupal::logger('ai_automator')->warning('Could not prepare vision model for image descriptions: @message', [
        '@message' => $exception->getMessage(),
      ]);
      return '';
    }
    $result = $this->getRichTextImageDescriptionService()->processRawContext(
      $rawContext,
      $includeExternal,
      $maxImages,
      $delta,
      $prompt,
      [
        'provider' => $visionConfig['ai_provider'] ?? '',
        'model' => $model,
      ],
      function ($image) use ($entity, $instance, $model, $prompt, &$visionConfig): string {
        $input = new ChatInput([
          new ChatMessage('user', $prompt, [$image]),
        ]);
        $this->attachEntityContext($input, $entity, $visionConfig);
        /** @var \Drupal\ai\OperationType\Chat\ChatMessage $response */
        $response = $instance->chat($input, $model, $this->getTags($prompt, $visionConfig, $instance, $entity))->getNormalized();
        return $response->getText();
      }
    );

    $this->imageDescriptionEncounteredCount = (int) ($result['encountered_count'] ?? 0);
    $this->imageDescriptionTotalCount = (int) ($result['total_count'] ?? 0);
    $this->imageDescriptionProcessedCount = (int) ($result['processed_count'] ?? 0);
    $this->imageDescriptionSkippedCount = (int) ($result['skipped_count'] ?? 0);
    $this->imageDescriptionLimitExceeded = !empty($result['limit_exceeded']);
    foreach ($result['metadata'] ?? [] as $metadataRow) {
      $this->imageDescriptionMetadata[] = $metadataRow;
    }

    return (string) ($result['descriptions'] ?? '');
  }

  /**
   * Indicates if image processing limit was exceeded.
   */
  protected function hasImageDescriptionLimitExceeded(): bool {
    return $this->imageDescriptionLimitExceeded;
  }

  /**
   * Gets warning message for image limit overflow.
   */
  protected function getImageDescriptionLimitMessage(): string {
    $total = $this->imageDescriptionEncounteredCount ?: $this->imageDescriptionTotalCount;
    return (string) $this->t('Image analysis incomplete: only @processed of @total detected images were analyzed. This content requires human moderation.', [
      '@processed' => $this->imageDescriptionProcessedCount,
      '@total' => $total,
    ]);
  }

  /**
   * Fetches and validates a remote image payload.
   */
  protected function fetchRemoteImageData(string $url): ?array {
    return $this->getRichTextImageDescriptionService()->fetchRemoteImageData($url);
  }

  /**
   * Gets rich text image helper.
   *
   * @return \Drupal\ai_automators\Rulehelpers\RichTextImageHelper
   *   The rich text image helper service.
   */
  protected function getRichTextImageHelper() {
    return \Drupal::service('ai_automator.rule_helper.rich_text_image');
  }

  /**
   * Gets rich text image description orchestration service.
   */
  protected function getRichTextImageDescriptionService() {
    return \Drupal::service('ai_automator.rich_text_image_description');
  }

}
