<?php

namespace Drupal\ai_logging\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\ai\Event\PostGenerateResponseEvent;
use Drupal\ai\OperationType\InputInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * The event that is triggered after a response is generated.
 *
 * @package Drupal\ai_logging\EventSubscriber
 */
class LogPostRequestEventSubscriber implements EventSubscriberInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The AI settings.
   *
   * @var ImmutableConfig
   */
  protected $aiSettings;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructor.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, ConfigFactoryInterface $configFactory, ModuleHandlerInterface $moduleHandler) {
    $this->entityTypeManager = $entityTypeManager;
    $this->aiSettings = $configFactory->get('ai_logging.settings');
    $this->moduleHandler = $moduleHandler;
  }

  /**
   * {@inheritdoc}
   *
   * @return array
   *   The post generate response event.
   */
  public static function getSubscribedEvents(): array {
    return [
      PostGenerateResponseEvent::EVENT_NAME => 'logPostRequest',
    ];
  }

  /**
   * Log if needed after running an AI request.
   *
   * @param \Drupal\ai\Event\PostGenerateResponseEvent $event
   *   The event to log.
   */
  public function logPostRequest(PostGenerateResponseEvent $event) {
    // If logging is enabled, log the prompt and response.
    if ($this->shouldLoggingHappen($event->getOperationType(), $event->getTags())) {
      $storage = $this->entityTypeManager->getStorage('ai_log');
      /** @var \Drupal\ai_logging\Entity\AiLog $log */
      $log = $storage->create([
        'provider' => $event->getProviderId(),
        'model' => $event->getModelId(),
        'operation_type' => $event->getOperationType(),
        'configuration' => json_encode($event->getConfiguration()),
        'bundle' => 'generic',
        'tags' => $event->getTags(),
        'prompt' => $this->getInputText($event->getInput()),
        'extra_data' => json_encode($event->getDebugData()),
      ]);
      if ($this->aiSettings->get('prompt_logging_output')) {
        $log->set('output_text', json_encode($event->getOutput()->getRawOutput()));
      }
      $log->save();
    }
  }

  /**
   * Function to check if logging should happen.
   *
   * @param string $operation_type
   *   The operation type.
   * @param array $tags
   *   Tags to check against.
   *
   * @return bool
   *   If logging should happen.
   */
  protected function shouldLoggingHappen(string $operation_type, array $tags): bool {
    if (empty($this->aiSettings->get('prompt_logging'))) {
      return FALSE;
    }
    // Check if the tags are empty.
    $prompt_logging_tags = $this->aiSettings->get('prompt_logging_tags');
    if (empty($prompt_logging_tags)) {
      return TRUE;
    }
    $normalized_tags = [];
    foreach ($tags as $tag) {
      $normalized_tags[] = strtolower(trim($tag));
    }
    $compare_tags = explode(',', $prompt_logging_tags);
    foreach ($compare_tags as $tag) {
      if (in_array(strtolower(trim($tag)), $normalized_tags)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Get the context link.
   *
   * @param \Drupal\ai\Event\PostGenerateResponseEvent $event
   *   The event to log.
   *
   * @return string
   *   The context link rendered.
   */
  protected function getContextLink(PostGenerateResponseEvent $event): string {
    $route = 'prompt_explorer.prompt_form';
    switch ($event->getOperationType()) {
      case 'chat':
        $route = 'ai_api_explorer.chat_generation_form';
        break;

      case 'embeddings':
        $route = 'ai_api_explorer.embeddings_form';
        break;

      case 'moderation':
        $route = 'ai_api_explorer.moderation_form';
        break;

      case 'text_to_image':
        $route = 'ai_api_explorer.image_generation_form';
        break;

      case 'text_to_speech':
        $route = 'ai_api_explorer.text_to_speech_form';
        break;

      case 'speech_to_text':
        $route = 'ai_api_explorer.speech_to_text_form';
        break;

      default:
        return '';
    }
    $url = Url::fromRoute($route, [], [
      'query' => [
        'input' => $this->getInputText($event->getInput()),
        'provider_id' => $event->getProviderId(),
        'model_id' => $event->getModelId(),
        'config' => json_encode($event->getConfiguration()),
      ],
    ]);
    return Link::fromTextAndUrl('Test AI Request', $url)->toString();
  }

  /**
   * Get the text from the input.
   *
   * @param mixed $input
   *   The input to get the text from.
   *
   * @return string
   *   The text from the input.
   */
  protected function getInputText($input): string {
    if ($input instanceof InputInterface) {
      return $input->toString();
    }
    return json_encode($input);
  }

}
