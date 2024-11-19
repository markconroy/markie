<?php

declare(strict_types=1);

namespace Drupal\ai_api_explorer\Form;

use Drupal\Core\File\FileExists;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\ai\AiProviderInterface;
use Drupal\ai\Plugin\ProviderProxy;
use Drupal\ai\Service\AiProviderFormHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form to prompt AI for audios.
 */
class TextToSpeechGenerationForm extends FormBase {

  /**
   * The AI LLM Provider Helper.
   *
   * @var \Drupal\ai\AiProviderHelper
   */
  protected $aiProviderHelper;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The file url generator.
   *
   * @var \Drupal\Core\File\FileUrlGenerator
   */
  protected $fileUrlGenerator;

  /**
   * The file system.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

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
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ai_api_explorer_text_to_speech_prompt';
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->aiProviderHelper = $container->get('ai.form_helper');
    $instance->requestStack = $container->get('request_stack');
    $instance->fileUrlGenerator = $container->get('file_url_generator');
    $instance->fileSystem = $container->get('file_system');
    $instance->moduleHandler = $container->get('module_handler');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->explorerHelper = $container->get('ai_api_explorer.helper');
    $instance->providerManager = $container->get('ai.provider');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // If no provider is installed we can't do anything.
    if (!$this->providerManager->hasProvidersForOperationType('text_to_speech')) {
      $form['markup'] = [
        '#markup' => '<div class="ai-error">' . $this->t('No AI providers are installed for Text To Speech calls, please %install and %configure one first.', [
          '%install' => Link::createFromRoute($this->t('install'), 'system.modules_list')->toString(),
          '%configure' => Link::createFromRoute($this->t('configure'), 'ai.admin_providers')->toString(),
        ]) . '</div>',
      ];
      return $form;
    }

    // Get the query string for provider_id, model_id.
    $request = $this->requestStack->getCurrentRequest();
    if ($request->query->get('provider_id')) {
      $form_state->setValue('tts_ai_provider', $request->query->get('provider_id'));
    }
    if ($request->query->get('model_id')) {
      $form_state->setValue('tts_ai_model', $request->query->get('model_id'));
    }
    $input = json_decode($request->query->get('input', '[]'));

    $form['#attached']['library'][] = 'ai_api_explorer/explorer';

    $form['prompt'] = [
      '#prefix' => '<div class="ai-left-side">',
      '#type' => 'textarea',
      '#title' => $this->t('Enter your prompt here. When submitted, your provider will generate a response. Please note that each query counts against your API usage if your provider is a paid provider.'),
      '#description' => $this->t('Based on the complexity of your prompt, traffic, and other factors, a response can take time to complete. Please allow the operation to finish.'),
      '#default_value' => $input,
      '#required' => TRUE,
    ];

    // Load the LLM configurations.
    $this->aiProviderHelper->generateAiProvidersForm($form, $form_state, 'text_to_speech', 'tts_', AiProviderFormHelper::FORM_CONFIGURATION_FULL);

    // If media module exists.
    if ($this->moduleHandler->moduleExists('media')) {
      $media_types = $this->entityTypeManager->getStorage('media_type')->loadMultiple();
      $media_options = [
        '' => $this->t('None'),
      ];
      foreach ($media_types as $media_type) {
        $media_options[$media_type->id()] = $media_type->label();
      }
      $form['save_as_media'] = [
        '#type' => 'select',
        '#title' => $this->t('Save as media'),
        '#options' => $media_options,
        '#description' => $this->t('If you want to save the audio as media, select the media type.'),
      ];
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Generate an Audio Response'),
      '#ajax' => [
        'callback' => '::getResponse',
        'wrapper' => 'ai-audio-response',
      ],
    ];

    $form['end_markup'] = [
      '#markup' => '</div>',
    ];

    $form['response'] = [
      '#prefix' => '<div id="ai-audio-response" class="ai-right-side">',
      '#suffix' => '</div>',
      '#type' => 'inline_template',
      '#template' => '{{ audios|raw }}',
      '#weight' => 101,
      '#context' => [
        'audios' => '<h2>Audio will appear here.</h2>',
      ],
    ];

    $form['markup_end'] = [
      '#markup' => '<div class="ai-break"></div>',
      '#weight' => 1001,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getResponse(array &$form, FormStateInterface $form_state) {
    $provider = $this->aiProviderHelper->generateAiProviderFromFormSubmit($form, $form_state, 'text_to_speech', 'tts_');

    $response = '';
    try {
      $audio = $provider->textToSpeech($form_state->getValue('prompt'), $form_state->getValue('tts_ai_model'), ['ai_api_explorer'])->getNormalized();
      if ($form_state->getValue('save_as_media')) {
        $audio[0]->getAsMediaEntity($form_state->getValue('save_as_media'), '', 'text-to-speech.mp3');
      }
      $audio_normalized = $audio[0]->getAsBinary();
      // Save the binary data to a file.
      $file_url = $this->fileSystem->saveData($audio_normalized, 'public://text-to-speech-test.mp3', FileExists::Replace);
      $response .= '<audio controls><source src="' . $this->fileUrlGenerator->generateAbsoluteString($file_url) . '" type="audio/mpeg"></audio>';
    }
    catch (\Exception $e) {
      $response = $this->explorerHelper->renderException($e);
    }
    // Generation code.
    $code = $this->normalizeCodeExample($provider, $form_state, $form_state->getValue('prompt'));

    $form['response']['#context'] = [
      'audios' => '<h2>Audio will appear here.</h2>' . $response . $code,
    ];
    return $form['response'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * Gets the normalized code example.
   *
   * @param \Drupal\ai\AiProviderInterface|\Drupal\ai\Plugin\ProviderProxy $provider
   *   The provider.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $prompt
   *   The prompt.
   *
   * @return string
   *   The normalized code example.
   */
  public function normalizeCodeExample(AiProviderInterface|ProviderProxy $provider, FormStateInterface $form_state, string $prompt): string {
    $code = "<details class=\"ai-code-wrapper\"><summary>Code Example</summary><code style=\"display: block; white-space: pre-wrap; padding: 20px;\">";
    $code .= '$prompt = "' . $prompt . '";<br>';
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
    $code .= "\$ai_provider = \Drupal::service('ai.provider')->createInstance('" . $form_state->getValue('tts_ai_provider') . '\');<br>';
    $code .= "\$ai_provider->setConfiguration(\$config);<br><br>";
    $code .= "// Trigger a response.<br>";
    $code .= "\$response = \$ai_provider->textToSpeech(\$prompt, '" . $form_state->getValue('tts_ai_model') . '\', ["your_module_name"]);<br><br>';
    $code .= "// This gets an array of \Drupal\ai\OperationType\GenericType\AudioFile.<br>";
    $code .= "\$normalized = \$response->getNormalized();<br><br>";
    $code .= "// Examples Possibility #1 - get binary from the first audio.<br>";
    $code .= '$binaries = $normalized[0]->getAsBinary();<br>';
    $code .= "// Examples Possibility #2 - get as base 64 encoded string from the first audio.<br>";
    $code .= '$base64 = $normalized[0]->getAsBase64EncodedString();<br>';
    $code .= "// Examples Possibility #3 - get as generated media from the first audio.<br>";
    $code .= '$media = $normalized[0]->getAsMediaEntity("audio", "public://", "audio.mp3");<br>';
    $code .= "// Examples Possibility #4 - get as file entity from the first audio.<br>";
    $code .= '$file = $normalized[0]->getAsFileEntity("public://", "audio.mp3");<br><br>';
    $code .= "// Another possibility is to get the raw response from the provider.<br>";
    $code .= '$raw = $response->getRaw();<br>';
    $code .= "</code></details>";

    return $code;
  }

}
