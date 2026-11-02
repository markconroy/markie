<?php

namespace Drupal\ai_translate_test\Plugin\AiProvider;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\AiProvider;
use Drupal\ai\Base\AiProviderClientBase;
use Drupal\ai\OperationType\TranslateText\TranslateTextInput;
use Drupal\ai\OperationType\TranslateText\TranslateTextInterface;
use Drupal\ai\OperationType\TranslateText\TranslateTextOutput;

/**
 * A test AI provider that returns a canned translate_text response.
 *
 * This lets kernel tests pilot exactly what the AI model "answers", so
 * TextTranslator's response cleanup can be tested without any network call.
 */
#[AiProvider(
  id: 'ai_translate_test',
  label: new TranslatableMarkup('AI Translate Test'),
)]
class TranslateTestProvider extends AiProviderClientBase implements TranslateTextInterface {

  /**
   * The raw text the next translateText() call should return.
   *
   * @var string
   */
  public static string $response = '';

  /**
   * {@inheritdoc}
   */
  public function getModelSettings(string $model_id, array $generalConfig = []): array {
    return $generalConfig;
  }

  /**
   * {@inheritdoc}
   */
  public function isUsable(?string $operation_type = NULL, array $capabilities = []): bool {
    return TRUE;
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
    return new TranslateTextOutput(self::$response, [], []);
  }

}
