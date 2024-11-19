<?php

declare(strict_types=1);

namespace Drupal\ai_api_explorer\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\ai\AiProviderInterface;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\StreamedChatMessageIteratorInterface;
use Drupal\ai\OperationType\GenericType\ImageFile;
use Drupal\ai\Plugin\ProviderProxy;
use Drupal\ai\Service\AiProviderFormHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Provides a form to prompt AI for answers.
 */
class ChatGenerationForm extends FormBase {

  /**
   * The AI LLM Provider Helper.
   *
   * @var \Drupal\ai\AiProviderHelper
   */
  protected $aiProviderHelper;

  /**
   * The Explorer Helper.
   *
   * @var \Drupal\ai_api_explorer\ExplorerHelper
   */
  protected $explorerHelper;

  /**
   * The AI Provider.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected $providerManager;

  /**
   * The current request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ai_api_chat_generation';
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->aiProviderHelper = $container->get('ai.form_helper');
    $instance->explorerHelper = $container->get('ai_api_explorer.helper');
    $instance->providerManager = $container->get('ai.provider');
    $instance->requestStack = $container->get('request_stack');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // If no provider is installed we can't do anything.
    if (!$this->providerManager->hasProvidersForOperationType('chat')) {
      $form['markup'] = [
        '#markup' => '<div class="ai-error">' . $this->t('No AI providers are installed for Chat calls, please %install and %configure one first.', [
          '%install' => Link::createFromRoute($this->t('install'), 'system.modules_list')->toString(),
          '%configure' => Link::createFromRoute($this->t('configure'), 'ai.admin_providers')->toString(),
        ]) . '</div>',
      ];
      return $form;
    }
    $form['#attached']['library'][] = 'ai_api_explorer/explorer';
    $form['#attached']['library'][] = 'ai_api_explorer/stream';

    $form['markup'] = [
      '#markup' => '<div class="ai-three-info">',
    ];

    $form['prompts'] = [
      '#type' => 'details',
      '#title' => $this->t('Chat Messages'),
      '#open' => TRUE,
      '#description' => $this->t('<strong>Please note: This is not a chat, its an explorer of the chat endpoint to build chat logic!</strong> <br />Enter your chat messages here, each message has to have a role and a message. Role will no always be used by all providers/models.'),
    ];

    $form['prompts']['system_prompt'] = [
      '#type' => 'details',
      '#title' => $this->t('System Prompt'),
      '#open' => FALSE,
    ];

    $form['prompts']['system_prompt']['system_message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('System Message'),
      '#attributes' => [
        'placeholder' => $this->t("You are an helpful assistant."),
      ],
      '#required' => FALSE,
    ];

    $form['prompts']['role_1'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Role'),
      '#attributes' => [
        'placeholder' => $this->t('user, system, assistant, etc.'),
      ],
      '#default_value' => 'user',
      '#required' => TRUE,
    ];
    $form['prompts']['message_1'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Message'),
      '#attributes' => [
        'placeholder' => $this->t('Write you message here.'),
      ],
      '#required' => TRUE,
      '#default_value' => '',
    ];
    $form['prompts']['image_1'] = [
      '#type' => 'file',
      // Only jpg, png files are allowed, since that covers most models.
      '#accept' => '.jpg, .png, .jpeg',
      '#title' => $this->t('Image'),
      '#description' => $this->t('Attach an image to the call. Note that not all models support images and will throw an error.'),
    ];

    $form['streamed'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Streamed'),
      '#description' => $this->t('If the provider supports streaming, the response will be streamed. <strong>Currently the image chat will not work with streaming in this explorer (however in the API it works).</strong>'),
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Ask The AI'),
      '#attributes' => [
        'data-response' => 'ai-text-response',
      ],
      '#ajax' => [
        'callback' => '::getResponse',
        'wrapper' => 'ai-text-response',
      ],
    ];

    $form['end_markup'] = [
      '#markup' => '</div>',
    ];

    $form['response'] = [
      '#prefix' => '<div id="ai-text-response" class="ai-three-middle">',
      '#suffix' => '</div>',
      '#type' => 'inline_template',
      '#template' => '{{ texts|raw }}',
      '#weight' => 1000,
      '#context' => [
        'texts' => '<h2>Chat response will appear here.</h2>',
      ],
    ];

    // Load the LLM configurations.
    $form['markup_after_middle'] = [
      '#markup' => '<div class="ai-three-info">',
      '#weight' => 1003,
    ];
    $this->aiProviderHelper->generateAiProvidersForm($form, $form_state, 'chat', 'chat', AiProviderFormHelper::FORM_CONFIGURATION_FULL, 1003);

    $form['markup_end'] = [
      '#markup' => '</div><div class="ai-break"></div>',
      '#weight' => 1004,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getResponse(array &$form, FormStateInterface $form_state) {
    // This runs on streamed.
    $provider = $this->aiProviderHelper->generateAiProviderFromFormSubmit($form, $form_state, 'chat', 'chat');
    $values = $form_state->getValues();
    // Get the messages.
    $messages = [];
    // Get potential files.
    $files = $this->requestStack->getCurrentRequest()->files->all();
    foreach ($values as $key => $value) {
      if (strpos($key, 'role_') === 0) {
        $index = substr($key, 5);
        $role = $value;
        $message = $values['message_' . $index];
        // Load the file.
        $image = "";
        if (isset($files['files']['image_' . $index])) {
          $raw_file = file_get_contents($files['files']['image_' . $index]->getPathname());
          $image = new ImageFile($raw_file, $files['files']['image_' . $index]->getClientMimeType(), $files['files']['image_' . $index]->getClientOriginalName());
        }
        if ($role && $message) {
          $images = [];
          if ($image) {
            $images[] = $image;
          }
          $messages[] = new ChatMessage($role, $message, $images);
        }
      }
    }

    $input = new ChatInput($messages);

    // Check for system message.
    if ($form_state->getValue('system_message')) {
      $provider->setChatSystemRole($form_state->getValue('system_message'));
    }

    $message = NULL;
    $response = NULL;
    try {
      // If we should stream.
      if ($form_state->getValue('streamed')) {
        $provider->streamedOutput();
      }
      $response = $provider->chat($input, $form_state->getValue('chat_ai_model'), ['chat_generation'])->getNormalized();
    }
    catch (\Exception $e) {
      $message = $this->explorerHelper->renderException($e);
    }

    // Generation code for normalization.
    $code = $this->normalizeCodeExample($provider, $form_state, $messages);
    $code .= $this->rawCodeExample($provider, $form_state, $messages);

    if (is_object($response) && get_class($response) == ChatMessage::class) {
      $form['response']['#context']['texts'] = '<h4>Role: ' . $response->getRole() . "</h4><p>" . $response->getText() . '</p>' . $code;
      $form_state->setRebuild();
      return $form['response'];
    }
    elseif (is_object($response) && $response instanceof StreamedChatMessageIteratorInterface) {
      $http_response = new StreamedResponse();
      $http_response->setCallback(function () use ($response, $code) {
        foreach ($response as $key => $chat_message) {
          if ($chat_message->getRole() && !$key) {
            echo '<h4>Role: ' . $chat_message->getRole() . "</h4><p>";
          }
          echo $chat_message->getText();
          ob_flush();
          flush();
        }
        echo $code;
        ob_flush();
        flush();
      });
      $form_state->setResponse($http_response);
    }
    else {
      $form['response']['#context']['texts'] = $message;
      $form_state->setRebuild();
      return $form['response'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // This runs on normal submit.
    $provider = $this->aiProviderHelper->generateAiProviderFromFormSubmit($form, $form_state, 'chat', 'chat');
    $values = $form_state->getValues();
    // Get the messages.
    $messages = [];
    // Get potential files.
    $files = $this->requestStack->getCurrentRequest()->files->all();
    foreach ($values as $key => $value) {
      if (strpos($key, 'role_') === 0) {
        $index = substr($key, 5);
        $role = $value;
        $message = $values['message_' . $index];
        // Load the file.
        $image = "";
        if (isset($files['files']['image_' . $index])) {
          $raw_file = file_get_contents($files['files']['image_' . $index]->getPathname());
          $image = new ImageFile($raw_file, $files['files']['image_' . $index]->getClientMimeType(), $files['files']['image_' . $index]->getClientOriginalName());
        }
        if ($role && $message) {
          $images = [];
          if ($image) {
            $images[] = $image;
          }
          $messages[] = new ChatMessage($role, $message, $images);
        }
      }
    }

    $input = new ChatInput($messages);

    if ($form_state->getValue('system_message')) {
      $provider->setChatSystemRole($form_state->getValue('system_message'));
    }
    $message = NULL;
    $response = NULL;

    try {
      // If we should stream.
      if ($form_state->getValue('streamed')) {
        $provider->streamedOutput();
      }
      $response = $provider->chat($input, $form_state->getValue('chat_ai_model'), ['chat_generation'])->getNormalized();
    }
    catch (\Exception $e) {
      $message = $this->explorerHelper->renderException($e);
    }

    // Generation code for normalization.
    $code = $this->normalizeCodeExample($provider, $form_state, $messages);
    $code .= $this->rawCodeExample($provider, $form_state, $messages);

    if (is_object($response) && get_class($response) == ChatMessage::class) {
      $form['response']['#context']['texts'] = '<h4>Role: ' . $response->getRole() . "</h4><p>" . $response->getText() . '</p>' . $code;
      $form_state->setRebuild();
      return $form['response'];
    }
    elseif (is_object($response) && $response instanceof StreamedChatMessageIteratorInterface) {
      $http_response = new StreamedResponse();
      $http_response->setCallback(function () use ($response, $code) {
        foreach ($response as $key => $chat_message) {
          if ($chat_message->getRole() && !$key) {
            echo '<h4>Role: ' . $chat_message->getRole() . "</h4><p>";
          }
          echo $chat_message->getText();
          ob_flush();
          flush();
        }
        echo $code;
        ob_flush();
        flush();
      });
      $form_state->setResponse($http_response);
    }
    else {
      $form['response']['#context']['texts'] = $message;
      $form_state->setRebuild();
      return $form['response'];
    }
  }

  /**
   * Gets the normalized code example.
   *
   * @param \Drupal\ai\AiProviderInterface|\Drupal\ai\Plugin\ProviderProxy $provider
   *   The provider.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $messages
   *   The messages.
   *
   * @return string
   *   The normalized code example.
   */
  public function normalizeCodeExample(AiProviderInterface|ProviderProxy $provider, FormStateInterface $form_state, array $messages): string {
    $code = "<details class=\"ai-code-wrapper\"><summary>Normalized Code Example</summary><code class=\"ai-code\">";
    $code .= '// Use this when you want to be able to swap the provider. <br>';
    $show_config = count($provider->getConfiguration()) ? TRUE : FALSE;
    if ($show_config) {
      $code .= '$config = [<br>';
      foreach ($provider->getConfiguration() as $key => $value) {
        if (is_string($value)) {
          $code .= '&nbsp;&nbsp;"' . $key . '" => "' . $value . '",<br>';
        }
        else {
          $code .= '&nbsp;&nbsp;"' . $key . '" => ' . $value . ',<br>';
        }
      }
      $code .= '];<br><br>';
    }

    $code .= '$input = new \Drupal\ai\OperationType\Chat\ChatInput([<br>';
    foreach ($messages as $message) {
      if (count($message->getImages())) {
        $code .= '&nbsp;&nbsp;// Assume a File entity being used with variable $file.<br>';
        $code .= '&nbsp;&nbsp;$image = new \Drupal\ai\OperationType\GenericType\ImageFile();<br>';
        $code .= '&nbsp;&nbsp;$image->setFileFromFile($file);<br>';
        $code .= '&nbsp;&nbsp;new \Drupal\ai\OperationType\Chat\ChatMessage("' . $message->getRole() . '", "' . $message->getText() . '", $image),<br>';
      }
      else {
        $code .= '&nbsp;&nbsp;new \Drupal\ai\OperationType\Chat\ChatMessage("' . $message->getRole() . '", "' . $message->getText() . '"),<br>';
      }
    }
    $code .= ']);<br><br>';

    $code .= "\$ai_provider = \Drupal::service('ai.provider')->createInstance('" . $form_state->getValue('chat_ai_provider') . '\');<br>';
    if ($show_config) {
      $code .= "\$ai_provider->setConfiguration(\$config);<br>";
    }
    if ($form_state->getValue('system_message')) {
      $code .= '$ai_provider->setChatSystemRole("' . $form_state->getValue('system_message') . '");<br>';
    }
    if ($form_state->getValue('streamed')) {
      $code .= "// If you want to stream the response normalized you have to make sure<br>";
      $code .= "\$ai_provider->streamedOutput();<br>";
    }
    $code .= "// Normalized \$response will be a ChatMessage object.<br>";
    $code .= "\$response = \$ai_provider->chat(\$input, '" . $form_state->getValue('chat_ai_model') . '\', ["your_module_name"])->getNormalized();<br>';

    // If there is a streaming response.
    if ($form_state->getValue('streamed')) {
      $code .= "<br><br>// If you want to stream the response normalized you have to make sure<br>";
      $code .= "// the provider supports it and have a fallback if not. This shows how. <br><br>";
      $code .= "// It is a stream response.<br>";
      $code .= "if (\$response instanceof \Drupal\ai\OperationType\Chat\StreamedChatMessageIteratorInterface) {<br>";
      $code .= "&nbsp;&nbsp;// This is a stream response.<br>";
      $code .= "&nbsp;&nbsp;// You can loop through the response and output it as it comes in.<br>";
      $code .= "/* @var \Drupal\ai\OperationType\Chat\StreamedChatMessage \$chat_message */<br>";
      $code .= "&nbsp;&nbsp;foreach (\$response as \$chat_message) {<br>";
      $code .= "&nbsp;&nbsp;&nbsp;&nbsp;echo \$chat_message->getText();<br>";
      $code .= "&nbsp;&nbsp;}<br>";
      $code .= "} else {<br>";
      $code .= "&nbsp;&nbsp;// This is a normal response.<br>";
      $code .= "&nbsp;&nbsp;echo \$response->getText();<br>";
      $code .= "}<br>";

    }
    $code .= "</code></details>";
    return $code;
  }

  /**
   * Gets the raw code example.
   *
   * @param \Drupal\ai\AiProviderInterface|\Drupal\ai\Plugin\ProviderProxy $provider
   *   The provider.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $messages
   *   The messages.
   *
   * @return string
   *   The normalized code example.
   */
  public function rawCodeExample(AiProviderInterface|ProviderProxy $provider, FormStateInterface $form_state, array $messages): string {
    $code = "<br><details class=\"ai-code-wrapper\"><summary>Raw Code Example</summary><code class=\"ai-code\">";
    $code .= '// Another way if you know you always will use ' . $provider->getPluginDefinition()['label'] . ' and want its way of doing stuff. Not recommended. <br>';
    $code .= '$config = [<br>';
    foreach ($provider->getConfiguration() as $key => $value) {
      if (is_string($value)) {
        $code .= '&nbsp;&nbsp;"' . $key . '" => "' . $value . '",<br>';
      }
      else {
        $code .= '&nbsp;&nbsp;"' . $key . '" => ' . $value . ',<br>';
      }
    }
    $code .= '];<br><br>';
    $code .= "\$ai_provider = \Drupal::service('ai.provider')->createInstance('" . $form_state->getValue('chat_ai_provider') . '\');<br>';
    if ($form_state->getValue('system_message')) {
      $code .= '$ai_provider->setChatSystemRole("' . $form_state->getValue('system_message') . '");<br>';
    }
    $code .= "\$ai_provider->setConfiguration(\$config);<br>";
    $code .= "// Normalized \$response will be what ever the provider gives back.<br>";
    $code .= "\$response = \$ai_provider->chat(\$expectedInputFromProviderClient, '" . $form_state->getValue('chat_ai_model') . '\', ["your_module_name"])->getRawOutput();';
    $code .= "</code></details>";
    return $code;
  }

}
