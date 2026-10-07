<?php

namespace Drupal\ai_chatbot\Controller;

use Drupal\ai\PluginManager\ChatProcessorPluginManager;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Access\AccessException;
use Drupal\Core\Controller\ControllerBase;
use Drupal\ai_assistant_api\AiAssistantApiRunner;
use Drupal\Core\Flood\FloodInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

/**
 * Creates a controller to reset chat sessions.
 */
class ResetSession extends ControllerBase {

  /**
   * Number of allowed attempts to reset the session before locking.
   */
  const FLOOD_THRESHOLD = 3;

  /**
   * Constructs a new ResetSession object.
   */
  public function __construct(
    protected AiAssistantApiRunner $aiAssistantApiRunner,
    protected FloodInterface $flood,
    protected ChatProcessorPluginManager $chatProcessorPluginManager,
  ) {
  }

  /**
   * Dependency injection.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ai_assistant_api.runner'),
      $container->get('flood'),
      $container->get(ChatProcessorPluginManager::class)
    );
  }

  /**
   * Resets the sessions of the chatbot for a thread for the user.
   *
   * @param string $assistant_id
   *   The assistant id to reset.
   * @param string $thread_id
   *   The thread id to reset.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Result of the operation.
   */
  public function resetSession(string $assistant_id, string $thread_id) {
    $eventName = 'ai_chatbot.reset_session';
    if (!$this->flood->isAllowed($eventName, self::FLOOD_THRESHOLD)) {
      return new JsonResponse(['success' => FALSE], 429);
    }
    /** @var \Drupal\ai_assistant_api\Entity\AiAssistant */
    $assistant = $this->entityTypeManager()->getStorage('ai_assistant')->load($assistant_id);
    if (!$assistant) {
      return new JsonResponse([
        'success' => FALSE,
      ]);
    }
    $this->aiAssistantApiRunner->setAssistant($assistant);
    try {
      $new_thread_id = $this->aiAssistantApiRunner->resetThread($thread_id);
    }
    catch (AccessException) {
      $this->flood->register($eventName);
      return new JsonResponse(['success' => FALSE], 429);
    }
    catch (ResourceNotFoundException) {
      $this->flood->register($eventName);
      return new JsonResponse(['success' => FALSE], 404);
    }

    return new JsonResponse([
      'success' => TRUE,
      'thread_id' => $new_thread_id,
    ]);
  }

  /**
   * Resets the server-side conversation thread for a processor instance.
   *
   * Rotates the session-stored thread id and deletes the stored history, so
   * the next message starts a fresh conversation. Only ever touches the
   * current user's own session entry and private temp store, so no foreign
   * thread can be reached or cleared through this endpoint.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The HTTP request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The JSON response.
   */
  public function reset(Request $request): JsonResponse {
    $eventName = 'ai_chatbot.reset_session';
    if (!$this->flood->isAllowed($eventName, self::FLOOD_THRESHOLD)) {
      return new JsonResponse(['success' => FALSE], 429);
    }
    $data = Json::decode($request->getContent());
    if (empty($data['thread_id'])) {
      return new JsonResponse(['error' => $this->t('No thread ID provided.')], 400);
    }
    $plugin_id = is_array($data) ? ($data['chat_processor_plugin'] ?? NULL) : NULL;
    if (empty($plugin_id) || !$this->chatProcessorPluginManager->hasDefinition($plugin_id)) {
      return new JsonResponse(['error' => $this->t('Invalid or missing chat processor plugin.')], 400);
    }
    $plugin_configuration = $data['plugin_configuration'] ?? [];
    $plugin = $this->chatProcessorPluginManager->createInstance($plugin_id, $plugin_configuration);
    $new_thread_id = $data['thread_id'];
    if ($plugin) {
      try {
        $new_thread_id = $plugin->resetThread($data['thread_id']);
      }
      catch (AccessException) {
        $this->flood->register($eventName);
        return new JsonResponse(['success' => FALSE], 429);
      }
      catch (ResourceNotFoundException) {
        $this->flood->register($eventName);
        return new JsonResponse(['success' => FALSE], 404);
      }
    }
    return new JsonResponse([
      'success' => TRUE,
      'thread_id' => $new_thread_id,
    ]);
  }

}
