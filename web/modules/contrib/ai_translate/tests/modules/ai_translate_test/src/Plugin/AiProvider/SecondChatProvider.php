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
 * A second chat provider, so tests can tell one provider from another.
 *
 * CountingChatProvider alone cannot show that a per-language setting picked a
 * different *provider* rather than a different model on the same one. Its
 * models are named distinctly from CountingChatProvider's for the same reason.
 */
#[AiProvider(
  id: 'ai_translate_second_chat',
  label: new TranslatableMarkup('Second chat stub'),
)]
class SecondChatProvider extends AiProviderClientBase implements ChatInterface {

  /**
   * Every model ID the chat operation ran with, in call order.
   *
   * @var string[]
   */
  public static array $calls = [];

  /**
   * Whether getConfiguredModels() should throw.
   *
   * A real provider asks a remote service, or a stored key, for its model
   * list, so this is the call that fails once credentials lapse.
   *
   * @var bool
   */
  public static bool $modelListFails = FALSE;

  /**
   * How many times getConfiguredModels() was called.
   *
   * @var int
   */
  public static int $modelListCalls = 0;

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
    static::$modelListCalls++;
    if (static::$modelListFails) {
      throw new \RuntimeException('The API key is no longer valid.');
    }
    return [
      'second-1' => 'Second one',
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
