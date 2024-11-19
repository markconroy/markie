<?php

declare(strict_types=1);

namespace Drupal\ai_api_explorer\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\ai\OperationType\GenericType\AudioFile;
use Drupal\ai\OperationType\SpeechToText\SpeechToTextInput;
use Drupal\ai\Service\AiProviderFormHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form to prompt AI for transcriptions.
 */
class SpeechToTextGenerationForm extends FormBase {

  /**
   * The AI LLM Provider Helper.
   *
   * @var \Drupal\ai\AiProviderHelper
   */
  protected $aiProviderHelper;

  /**
   * The current request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

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
    return 'ai_api_explorer_speech_to_text_prompt';
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->aiProviderHelper = $container->get('ai.form_helper');
    $instance->requestStack = $container->get('request_stack');
    $instance->explorerHelper = $container->get('ai_api_explorer.helper');
    $instance->providerManager = $container->get('ai.provider');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // If no provider is installed we can't do anything.
    if (!$this->providerManager->hasProvidersForOperationType('speech_to_text')) {
      $form['markup'] = [
        '#markup' => '<div class="ai-error">' . $this->t('No AI providers are installed for Speech To Text calls, please %install and %configure one first.', [
          '%install' => Link::createFromRoute($this->t('install'), 'system.modules_list')->toString(),
          '%configure' => Link::createFromRoute($this->t('configure'), 'ai.admin_providers')->toString(),
        ]) . '</div>',
      ];
      return $form;
    }

    // Get the query string for provider_id, model_id.
    $request = $this->requestStack->getCurrentRequest();
    if ($request->query->get('provider_id')) {
      $form_state->setValue('stt_ai_provider', $request->query->get('provider_id'));
    }
    if ($request->query->get('model_id')) {
      $form_state->setValue('stt_ai_model', $request->query->get('model_id'));
    }

    $form['#attached']['library'][] = 'ai_api_explorer/explorer';

    $form['file'] = [
      '#prefix' => '<div class="ai-left-side">',
      '#type' => 'file',
      // Only mp3 files are allowed in this case, since that covers most models.
      '#accept' => '.mp3',
      '#title' => $this->t('Upload your file here. When submitted, your provider will generate a response. Please note that each query counts against your API usage if your provider is a paid provider.'),
      '#description' => $this->t('Based on the complexity of your prompt, traffic, and other factors, a response can take time to complete. Please allow the operation to finish.'),
      '#required' => TRUE,
    ];

    // Load the LLM configurations.
    $this->aiProviderHelper->generateAiProvidersForm($form, $form_state, 'speech_to_text', 'stt', AiProviderFormHelper::FORM_CONFIGURATION_FULL);

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Generate an Text'),
      '#ajax' => [
        'callback' => '::getResponse',
        'wrapper' => 'ai-text-response',
      ],
    ];

    $form['end_markup'] = [
      '#markup' => '</div>',
    ];

    $form['response'] = [
      '#prefix' => '<div id="ai-text-response" class="ai-right-side">',
      '#suffix' => '</div>',
      '#type' => 'inline_template',
      '#template' => '{{ texts|raw }}',
      '#weight' => 1000,
      '#context' => [
        'texts' => '<h2>Texts will appear here.</h2>',
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
    $provider = $this->aiProviderHelper->generateAiProviderFromFormSubmit($form, $form_state, 'speech_to_text', 'stt');
    $files = $this->requestStack->getCurrentRequest()->files->all();
    $file = reset($files);
    $audio_file = new AudioFile(file_get_contents($file['file']->getPathname()), $file['file']->getMimeType(), $file['file']->getClientOriginalName());
    $raw_file = new SpeechToTextInput($audio_file);

    try {
      $response = $provider->speechToText($raw_file, $form_state->getValue('stt_ai_model'), ['ai_api_explorer'])->getNormalized();
    }
    catch (\Exception $e) {
      $response = $this->explorerHelper->renderException($e);
    }

    // Generation code.
    $code = "<details class=\"ai-code-wrapper\"><summary>Code Example</summary><code style=\"display: block; white-space: pre-wrap; padding: 20px;\">";
    $code .= '$audio = file_get_contents("' . $file['file']->getClientOriginalName() . '");<br>';
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
    $code .= "\$ai_provider = \Drupal::service('ai.provider')->createInstance('" . $form_state->getValue('stt_ai_provider') . '\');<br>';
    $code .= "\$ai_provider->setConfiguration(\$config);<br>";
    $code .= "// \$response will be a string with the text.<br>";
    $code .= "\$response = \$ai_provider->speechToText(\$audio, '" . $form_state->getValue('stt_ai_model') . '\', ["your_module_name"]);';
    $code .= "</code></details>";

    $form['response']['#context'] = [
      'texts' => '<div id="ai-text-response"><h2>Texts will appear here.</h2>' . $response . $code . '</div>',
    ];
    return $form['response'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

}
