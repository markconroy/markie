<?php

namespace Drupal\ai_assistant_api\Plugin\ChatMemory;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\ai\Base\ChatMemoryPluginBase;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai_assistant_api\TempStore\CachedOwnerPrivateTempStore;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Base for ChatMemory plugins that store threads in a private tempstore.
 *
 * Threads are scoped per user, or per session for anonymous users, the same
 * way the private temp store scopes its data: authenticated users are keyed
 * by their user id and anonymous users by a random owner key kept in their
 * session. One user can therefore never read another user's threads.
 *
 * Storage is backed by
 * \Drupal\ai_assistant_api\TempStore\CachedOwnerPrivateTempStore
 * rather than the core private tempstore directly, so the owner is resolved
 * once per request and cached, meaning messages can still be appended while
 * a streamed response is being sent, when the session has already been
 * closed and can no longer be accessed. That store also locks writes the
 * same way the core private tempstore does.
 *
 * Concrete subclasses only differ in their #[ChatMemory] attribute and in
 * hasPersistentThread(): which thread id a given call is storing under is
 * decided entirely by the caller, not by this storage layer, so a single
 * persistent thread per owner and a rotating pool of several concurrent
 * threads are both just this same storage used with different keys.
 */
abstract class TempStoreChatMemoryBase extends ChatMemoryPluginBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * The key value collection used for the threads.
   */
  const COLLECTION = 'ai_chat_memory';

  /**
   * The tempstore used for the threads, built lazily with expiry.
   *
   * @var \Drupal\ai_assistant_api\TempStore\CachedOwnerPrivateTempStore|null
   */
  protected ?CachedOwnerPrivateTempStore $store = NULL;

  /**
   * Constructs a TempStoreChatMemoryBase plugin.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin id.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface $keyValueFactory
   *   The expirable key value factory.
   * @param \Drupal\Core\Lock\LockBackendInterface $lockBackend
   *   The lock backend.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected KeyValueExpirableFactoryInterface $keyValueFactory,
    protected LockBackendInterface $lockBackend,
    protected AccountProxyInterface $currentUser,
    protected RequestStack $requestStack,
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
      $container->get('keyvalue.expirable'),
      $container->get('lock'),
      $container->get('current_user'),
      $container->get('request_stack'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'expiry' => 86400,
      'max_messages' => 0,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['expiry'] = [
      '#type' => 'number',
      '#title' => $this->t('Thread expiry'),
      '#description' => $this->t('The number of seconds after the last update until a thread is considered expired.'),
      '#default_value' => $this->configuration['expiry'] ?? 86400,
      '#min' => 60,
      '#required' => TRUE,
    ];
    $form['max_messages'] = [
      '#type' => 'number',
      '#title' => $this->t('Max messages'),
      '#description' => $this->t('The maximum amount of message pairs to keep per thread. The oldest messages are removed first. Use 0 for unlimited.'),
      '#default_value' => $this->configuration['max_messages'] ?? 0,
      '#min' => 0,
      '#required' => TRUE,
    ];
    return $form;
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
    return $this->getThread($thread_id) !== NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function loadMessages(string $thread_id): array {
    $thread = $this->getThread($thread_id);
    $messages = [];
    foreach ($thread['messages'] ?? [] as $message) {
      $messages[] = ChatMessage::fromArray($message);
    }
    return $messages;
  }

  /**
   * {@inheritdoc}
   */
  public function saveMessages(string $thread_id, array $messages): void {
    foreach ($messages as $message) {
      if (!$message instanceof ChatMessage) {
        throw new \InvalidArgumentException('Messages must be instances of ChatMessage.');
      }
    }
    $max_messages = (int) ($this->configuration['max_messages'] ?? 0);
    if ($max_messages > 0) {
      // Send the last message + n pairs of user and system messages (where
      // n=config value from max messages setting).
      $max_messages = $max_messages * 2 + 1;
      $messages = array_slice($messages, -($max_messages), $max_messages);
    }
    $thread = $this->getThread($thread_id);
    $this->getStorage()->set($thread_id, [
      'created' => $thread['created'] ?? time(),
      'updated' => time(),
      'messages' => array_map(fn (ChatMessage $message) => $message->toArray(), $messages),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteThread(string $thread_id): void {
    $this->getStorage()->delete($thread_id);
  }

  /**
   * Gets a thread from the storage.
   *
   * @param string $thread_id
   *   The thread id.
   *
   * @return array|null
   *   The thread data, or NULL if not found or expired.
   */
  protected function getThread(string $thread_id): ?array {
    $thread = $this->getStorage()->get($thread_id);
    return is_array($thread) ? $thread : NULL;
  }

  /**
   * Gets the tempstore used for the threads.
   *
   * Built lazily and cached on the plugin instance, both so the configured
   * expiry only has to be read once and so the underlying store's own owner
   * cache survives for the rest of the request.
   *
   * @return \Drupal\ai_assistant_api\TempStore\CachedOwnerPrivateTempStore
   *   The tempstore.
   */
  protected function getStorage(): CachedOwnerPrivateTempStore {
    if ($this->store === NULL) {
      $this->store = new CachedOwnerPrivateTempStore(
        $this->keyValueFactory->get('tempstore.private.' . static::COLLECTION),
        $this->lockBackend,
        $this->currentUser,
        $this->requestStack,
        (int) ($this->configuration['expiry'] ?? 86400),
      );
    }
    return $this->store;
  }

}
