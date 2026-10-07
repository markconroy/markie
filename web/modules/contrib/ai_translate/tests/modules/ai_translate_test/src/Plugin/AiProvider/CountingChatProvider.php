<?php

declare(strict_types=1);

namespace Drupal\ai_translate_test\Plugin\AiProvider;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\AiProvider;
use Drupal\ai\Base\AiProviderClientBase;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatInterface;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\ChatOutput;

/**
 * Records chat requests instead of contacting a real provider.
 *
 * Reaching the cache through ChatTranslationProvider needs a provider that
 * implements the chat operation, which CountingTranslateProvider does not: it
 * implements translate_text and so is never proxied to.
 *
 * The answer names the model that produced it, which is what lets a test tell a
 * stale cached translation from a fresh one.
 */
#[AiProvider(
  id: 'ai_translate_counting_chat',
  label: new TranslatableMarkup('Counting chat stub'),
)]
class CountingChatProvider extends AiProviderClientBase implements ChatInterface {

  /**
   * Every model ID the chat operation ran with, in call order.
   *
   * @var string[]
   */
  public static array $calls = [];

  /**
   * {@inheritdoc}
   */
  public function getConfig(): ImmutableConfig {
    return $this->configFactory->get('system.site');
  }

  /**
   * {@inheritdoc}
   */
  public function getApiDefinition(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getModelSettings(string $model_id, array $generalConfig = []): array {
    return $generalConfig;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguredModels(?string $operation_type = NULL, array $capabilities = []): array {
    return [
      'chat-1' => 'Chat one',
      'chat-2' => 'Chat two',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function isUsable(?string $operation_type = NULL, array $capabilities = []): bool {
    return $operation_type === NULL || $operation_type === 'chat';
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedOperationTypes(): array {
    return ['chat'];
  }

  /**
   * {@inheritdoc}
   */
  public function setAuthentication(mixed $authentication): void {
  }

  /**
   * {@inheritdoc}
   */
  public function chat(array|string|ChatInput $input, string $model_id, array $tags = []): ChatOutput {
    static::$calls[] = $model_id;
    return new ChatOutput(
      new ChatMessage('assistant', 'translated by ' . $model_id),
      '',
      []
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getMaxInputTokens(string $model_id): int {
    return 1000;
  }

  /**
   * {@inheritdoc}
   */
  public function getMaxOutputTokens(string $model_id): int {
    return 1000;
  }

}
