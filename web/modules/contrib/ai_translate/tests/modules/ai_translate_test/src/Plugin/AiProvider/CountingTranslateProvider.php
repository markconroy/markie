<?php

declare(strict_types=1);

namespace Drupal\ai_translate_test\Plugin\AiProvider;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\AiProvider;
use Drupal\ai\Base\AiProviderClientBase;
use Drupal\ai\OperationType\TranslateText\TranslateTextInput;
use Drupal\ai\OperationType\TranslateText\TranslateTextInterface;
use Drupal\ai\OperationType\TranslateText\TranslateTextOutput;

/**
 * Records translation requests instead of contacting a real provider.
 *
 * A real plugin rather than a test double of the plugin manager, because
 * AiProviderPluginManager is final and cannot be mocked or subclassed. Calls
 * therefore travel the production path, through createInstance() and the
 * ProviderProxy wrapper.
 *
 * The counter is static because the container builds its own instance.
 *
 * Lives in a test module rather than tests/src/ because run-tests.sh, which CI
 * uses, collects every .php file under tests/src/ and rejects classes with no
 * test group.
 */
#[AiProvider(
  id: 'ai_translate_counting',
  label: new TranslatableMarkup('Counting translation stub'),
)]
class CountingTranslateProvider extends AiProviderClientBase implements TranslateTextInterface {

  /**
   * Every text passed to translateText(), in call order.
   *
   * @var string[]
   */
  public static array $calls = [];

  /**
   * Input that makes the stub answer with an empty string.
   *
   * Stands in for the real providers' failure mode: ChatTranslationProvider
   * returns an empty string rather than throwing when it cannot resolve the
   * target language.
   */
  public const EMPTY_MARKER = 'RETURN NOTHING';

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
      'counter-1' => 'Counter one',
      'counter-2' => 'Counter two',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function isUsable(?string $operation_type = NULL, array $capabilities = []): bool {
    return $operation_type === NULL || $operation_type === 'translate_text';
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedOperationTypes(): array {
    return ['translate_text'];
  }

  /**
   * {@inheritdoc}
   */
  public function setAuthentication(mixed $authentication): void {
  }

  /**
   * {@inheritdoc}
   */
  public function translateText(TranslateTextInput $input, string $model_id, array $options = []): TranslateTextOutput {
    static::$calls[] = $input->getText();

    if ($input->getText() === self::EMPTY_MARKER) {
      return new TranslateTextOutput('', '', []);
    }

    return new TranslateTextOutput(
      strtoupper($input->getTargetLanguage()) . ': ' . $input->getText(),
      '',
      []
    );
  }

}
