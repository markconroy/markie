<?php

declare(strict_types=1);

namespace Drupal\ai_validations_test\Plugin\AiProvider;

use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\AiProvider;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\ChatOutput;
use Drupal\ai\OperationType\SpeechToText\SpeechToTextInput;
use Drupal\ai\OperationType\SpeechToText\SpeechToTextOutput;
use Drupal\ai\OperationType\TextClassification\TextClassificationInput;
use Drupal\ai\OperationType\TextClassification\TextClassificationItem;
use Drupal\ai\OperationType\TextClassification\TextClassificationOutput;
use Drupal\ai_test\Plugin\AiProvider\EchoProvider;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A test provider whose output across operation types is driven by state.
 *
 * The bundled EchoProvider only echoes back the labels supplied on the
 * input (for classification) or a canned "Hello world" message (for chat),
 * neither of which is controllable enough to drive the AI Validations
 * validators through their match/confidence/not-available paths. This
 * provider lets a test set the exact classification rows, the literal chat
 * response text, or force any of the three operations to throw, all via
 * state, so validator behavior can be exercised through the real provider
 * manager rather than by mocking it away.
 */
#[AiProvider(
  id: 'classifier_test',
  label: new TranslatableMarkup('Classifier Test'),
)]
class ClassifierTestProvider extends EchoProvider {

  /**
   * State key holding the classifications (array of label/confidence rows).
   */
  public const STATE_CLASSIFICATIONS = 'ai_validations_test.classifications';

  /**
   * State key that, when TRUE, makes the provider throw.
   */
  public const STATE_THROW = 'ai_validations_test.throw';

  /**
   * State key holding the literal chat response text to return.
   *
   * When unset, chat() falls back to the parent EchoProvider's canned
   * response.
   */
  public const STATE_CHAT_RESPONSE = 'ai_validations_test.chat_response';

  /**
   * State key counting the number of chat() calls the provider received.
   *
   * Lets tests assert that response memoization in the validator base
   * prevents duplicate provider calls.
   */
  public const STATE_CHAT_CALL_COUNT = 'ai_validations_test.chat_call_count';

  /**
   * State key recording the last chat call.
   *
   * Holds the system prompt, the last user message text, the request tags
   * and entity_context.
   */
  public const STATE_LAST_CHAT = 'ai_validations_test.last_chat';

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected StateInterface $state;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->state = $container->get('state');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function textClassification(string|TextClassificationInput $input, string $model_id, array $tags = []): TextClassificationOutput {
    if ($this->state->get(self::STATE_THROW, FALSE)) {
      throw new \RuntimeException('Simulated provider failure.');
    }

    $items = [];
    $response = [];
    foreach ($this->state->get(self::STATE_CLASSIFICATIONS, []) as $row) {
      $items[] = new TextClassificationItem($row['label'], $row['confidence']);
      $response[] = $row;
    }

    return new TextClassificationOutput($items, $response, []);
  }

  /**
   * {@inheritdoc}
   */
  public function chat(array|string|ChatInput $input, string $model_id, array $tags = []): ChatOutput {
    $this->state->set(self::STATE_CHAT_CALL_COUNT, (int) $this->state->get(self::STATE_CHAT_CALL_COUNT, 0) + 1);
    $messages = $input instanceof ChatInput ? $input->getMessages() : [];
    $last_message = $messages === [] ? NULL : $messages[array_key_last($messages)];
    $this->state->set(self::STATE_LAST_CHAT, [
      'system_prompt' => $input instanceof ChatInput ? $input->getSystemPrompt() : NULL,
      'user_text' => $last_message?->getText(),
      'tags' => $tags,
      'entity_context' => ($input instanceof ChatInput
        && method_exists($input, 'getRequestMetadataValue'))
        ? $input->getRequestMetadataValue('entity_context')
        : NULL,
    ]);
    if ($this->state->get(self::STATE_THROW, FALSE)) {
      throw new \RuntimeException('Simulated provider failure.');
    }

    $text = $this->state->get(self::STATE_CHAT_RESPONSE);
    if ($text === NULL) {
      return parent::chat($input, $model_id, $tags);
    }

    $message = new ChatMessage('assistant', $text);
    return new ChatOutput($message, ['text' => $text], []);
  }

  /**
   * {@inheritdoc}
   */
  public function speechToText(SpeechToTextInput|string $input, string $model_id, array $tags = []): SpeechToTextOutput {
    if ($this->state->get(self::STATE_THROW, FALSE)) {
      throw new \RuntimeException('Simulated provider failure.');
    }

    return parent::speechToText($input, $model_id, $tags);
  }

}
