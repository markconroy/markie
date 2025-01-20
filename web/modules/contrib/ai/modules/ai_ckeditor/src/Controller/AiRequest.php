<?php

namespace Drupal\ai_ckeditor\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai_ckeditor\PluginInterfaces\AiCKEditorPluginInterface;
use Drupal\ai_ckeditor\PluginManager\AiCKEditorPluginManager;
use Drupal\editor\EditorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Returns responses for CKEditor integration routes.
 */
class AiRequest implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The AI CKEditor plugin manager.
   *
   * @var \Drupal\ai_ckeditor\PluginManager\AiCKEditorPluginManager
   */
  protected $pluginManager;

  /**
   * The provider plugin manager.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected AiProviderPluginManager $aiProviderManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $account;

  /**
   * The logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected LoggerChannelInterface $logger;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * {@inheritdoc}
   */
  public function __construct(AiCKEditorPluginManager $plugin_manager, AiProviderPluginManager $ai_provider_manager, EntityTypeManagerInterface $entity_type_manager, AccountProxyInterface $account, LoggerChannelFactoryInterface $logger_factory, MessengerInterface $messenger) {
    $this->pluginManager = $plugin_manager;
    $this->aiProviderManager = $ai_provider_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->account = $account;
    $this->logger = $logger_factory->get('ai_ckeditor');
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.ai_ckeditor'),
      $container->get('ai.provider'),
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('logger.factory'),
      $container->get('messenger'),
    );
  }

  /**
   * Performs a request to AI for streamed output.
   */
  public function doRequest(Request $request, EditorInterface $editor, AiCKEditorPluginInterface $ai_ckeditor_plugin) {
    $data = json_decode($request->getContent());

    try {
      $settings = $editor->getSettings();
      $configuration = $settings["plugins"]["ai_ckeditor_ai"]["plugins"];
      $preferred_model = $configuration[$ai_ckeditor_plugin->getPluginId()]['provider'];

      if ($preferred_model) {
        $ai_provider = $this->aiProviderManager->loadProviderFromSimpleOption($preferred_model);
        $ai_model = $this->aiProviderManager->getModelNameFromSimpleOption($preferred_model);
      }
      else {
        // Get the default provider.
        $default_provider = $this->aiProviderManager->getDefaultProviderForOperationType('chat');
        if (empty($default_provider['provider_id'])) {
          // If we got nothing return NULL.
          $this->messenger->addError($this->t('No AI provider is set for chat. Please configure one in the "Text format and editors settings" or setup a default Chat model in the %ai_settings_link.', [
            '%ai_settings_link' => Link::createFromRoute($this->t('AI settings'), 'ai.settings_form')->toString(),
          ]));
          throw new \exception('No AI provider is set for chat. Please configure one in the AI default settings or in the ai_content settings form.');
        }
        $ai_provider = $this->aiProviderManager->createInstance($default_provider['provider_id']);
        $ai_model = $default_provider['model_id'];
      }

      // @todo Check config if user wants answers as HTML.
      /** @var \Drupal\filter\FilterFormatInterface $format */
      $format = $editor->getFilterFormat();
      $restrictions = $format->getHtmlRestrictions();

      // Supply the permitted HTML tags to the provider.
      if (!empty($restrictions) && !empty($restrictions['allowed'])) {
        $allowed_tags = "";

        foreach ($restrictions['allowed'] as $tag => $metadata) {
          $allowed_tags .= $tag . " ";
        }

        $data->prompt = "Format the answer using ONLY the following HTML tags: " . $allowed_tags . $data->prompt;
      }
      else {
        $data->prompt = "Format the answer using basic HTML formatting tags." . $data->prompt;
      }
      $data->prompt = "Do not try to use any image, video, or audio tags. Do not use backticks or ```html indicator." . $data->prompt;

      $messages = new ChatInput([
        new ChatMessage('user', $data->prompt),
      ]);

      // Add the system message.
      $ai_provider->setChatSystemRole('You are helpful website assistant for content writing and editing. Do not give responses in the first, second or third person form. Do not add any commentary to the answer.');

      // @todo Not all providers stream.
      // @see: https://www.drupal.org/project/ai/issues/3466906
      $ai_provider->streamedOutput();

      /** @var \Drupal\ai\OperationType\Chat\StreamedChatMessageIteratorInterface $response */
      $response = $ai_provider->chat($messages, $ai_model)->getNormalized();

      return new StreamedResponse(function () use ($response) {
        foreach ($response as $message) {
          echo $message->getText();
          ob_flush();
          flush();
        }
      }, 200, [
        'Cache-Control' => 'no-cache, must-revalidate',
        'Content-Type' => 'text/event-stream',
        'X-Accel-Buffering' => 'no',
      ]);
    }
    catch (\Exception $e) {
      $this->logger->error($e->getMessage());
    }

    return new Response("The request could not be completed.", Response::HTTP_BAD_REQUEST);
  }

}
