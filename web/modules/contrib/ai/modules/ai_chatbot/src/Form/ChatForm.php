<?php

namespace Drupal\ai_chatbot\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai_assistant_api\AiAssistantApiRunner;
use Drupal\ai_assistant_api\Data\UserMessage;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Provides a chat bot.
 */
class ChatForm extends FormBase {

  use DependencySerializationTrait;

  /**
   * Construct the chat.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\ai_assistant_api\AiAssistantApiRunner $aiAssistantClient
   *   The AI Assistant API client.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatcher
   *   The route match.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AiAssistantApiRunner $aiAssistantClient,
    private readonly RouteMatchInterface $routeMatcher,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('ai_assistant_api.runner'),
      $container->get('current_route_match'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ai_foundation_chat';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Set the assistant if its not set.
    $context = [];
    foreach ($this->routeMatcher->getParameters()->all() as $key => $data) {
      $context[$key] = $this->routeMatcher->getParameter($key);
    }
    // Setup the assistant.
    $this->aiAssistantClient->setContext($context);

    if (!$this->getRequest()->isXmlHttpRequest()) {
      // Set the assistant id if its the page load.
      $form['#attached']['drupalSettings']['ai_chatbot']['assistant_id'] = $this->aiAssistantClient->getThreadsKey();
    }

    $response_id = Html::getId($form_state->getBuildInfo()['block_id'] . '-response');

    $form['query'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Ask me a question'),
      '#title_display' => 'invisible',
      '#attributes' => [
        'placeholder' => $this->t('Ask me a question'),
        'class' => ['chat-form-query'],
      ],
      '#rows' => 1,
    ];

    $form['assistant_id'] = [
      '#type' => 'hidden',
      '#default_value' => '',
      '#attributes' => [
        'class' => ['chat-form-assistant-id'],
      ],
    ];

    $form['#attached']['library'][] = 'ai_chatbot/form-stream';

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Send'),
      '#attributes' => [
        'data-ai-ajax' => $response_id,
        'class' => ['chat-form-send'],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Set the assistant id.
    $this->aiAssistantClient->setThreadsKey($form_state->getValue('assistant_id'));
    // Set the user message.
    $this->aiAssistantClient->setUserMessage(new UserMessage($form_state->getValue('query')));

    // Send the query to OpenAI.
    if ($this->getRequest()->isXmlHttpRequest()) {
      try {
        $http_response = new StreamedResponse();
        // Process.
        $response = $this->aiAssistantClient->process();
        // If its a failure, the variable is a string, just output;.
        if ($response->getNormalized() instanceof ChatMessage) {
          $http_response = new Response($response->getNormalized()->getText());
          $this->aiAssistantClient->setAssistantMessage($response->getNormalized()->getText());
          $form_state->setResponse($http_response);
        }
        else {
          $http_response->setCallback(function () use ($response) {
            $full_response = "";
            foreach ($response->getNormalized() as $message) {
              echo $message->getText();
              $full_response .= $message->getText();
              ob_flush();
              flush();
            }

            $this->aiAssistantClient->setAssistantMessage($full_response);
          });
          $form_state->setResponse($http_response);
        }
      }
      catch (\Exception $exception) {
        $this->messenger()
          ->addError("Chat exception: {$exception->getMessage()}");
        return;
      }
    }
    else {
      $response = $this->aiAssistantClient->process();
      $form_state->setRebuild();
      $form_state->set('response', $response->getNormalized()->getText());
      $this->aiAssistantClient->setAssistantMessage($response->getNormalized()->getText());
    }
  }

  /**
   * Get all the Chat config from build info with defaults.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state to get build info from.
   *
   * @return array
   *   The array of chat config with defaults where required.
   */
  protected function getChatConfig(FormStateInterface $form_state) {
    $config = $form_state->getBuildInfo()['chat_config'] ?? [];
    return $config;
  }

}
