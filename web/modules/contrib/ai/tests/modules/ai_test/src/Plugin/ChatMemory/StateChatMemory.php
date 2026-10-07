<?php

namespace Drupal\ai_test\Plugin\ChatMemory;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\ChatMemory;
use Drupal\ai\Base\ChatMemoryPluginBase;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A state backed chat memory for testing purposes.
 */
#[ChatMemory(
  id: 'state_memory',
  label: new TranslatableMarkup('State Memory'),
  description: new TranslatableMarkup('A simple chat memory that stores threads in state, for testing purposes.'),
)]
class StateChatMemory extends ChatMemoryPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The state key prefix used for threads.
   */
  const STATE_PREFIX = 'ai_test_chat_memory_';

  /**
   * Constructs a StateChatMemory plugin.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected StateInterface $state,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('state'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function createThreadId(): string {
    return bin2hex(random_bytes(16));
  }

  /**
   * {@inheritdoc}
   */
  public function hasThread(string $thread_id): bool {
    return $this->state->get(self::STATE_PREFIX . $thread_id) !== NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function loadMessages(string $thread_id): array {
    $messages = [];
    foreach ($this->state->get(self::STATE_PREFIX . $thread_id, []) as $message) {
      $messages[] = ChatMessage::fromArray($message);
    }
    return $messages;
  }

  /**
   * {@inheritdoc}
   */
  public function saveMessages(string $thread_id, array $messages): void {
    $this->state->set(self::STATE_PREFIX . $thread_id, array_map(fn (ChatMessage $message) => $message->toArray(), $messages));
  }

  /**
   * {@inheritdoc}
   */
  public function deleteThread(string $thread_id): void {
    $this->state->delete(self::STATE_PREFIX . $thread_id);
  }

}
