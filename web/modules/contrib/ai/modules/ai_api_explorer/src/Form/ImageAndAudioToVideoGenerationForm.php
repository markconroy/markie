<?php

declare(strict_types=1);

namespace Drupal\ai_api_explorer\Form;

use Drupal\Core\File\FileExists;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\ai\AiProviderInterface;
use Drupal\ai\OperationType\GenericType\AudioFile;
use Drupal\ai\OperationType\GenericType\ImageFile;
use Drupal\ai\OperationType\ImageAndAudioToVideo\ImageAndAudioToVideoInput;
use Drupal\ai\Plugin\ProviderProxy;
use Drupal\ai\Service\AiProviderFormHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form to prompt AI for image and audio to video.
 */
class ImageAndAudioToVideoGenerationForm extends FormBase {

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
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ai_api_explorer_image_and_audio_to_video_prompt';
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
    $instance->fileUrlGenerator = $container->get('file_url_generator');
    $instance->fileSystem = $container->get('file_system');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // If no provider is installed we can't do anything.
    if (!$this->providerManager->hasProvidersForOperationType('image_and_audio_to_video')) {
      $form['markup'] = [
        '#markup' => '<div class="ai-error">' . $this->t('No AI providers are installed for Audio to Audio calls, please %install and %configure one first.', [
          '%install' => Link::createFromRoute($this->t('install'), 'system.modules_list')->toString(),
          '%configure' => Link::createFromRoute($this->t('configure'), 'ai.admin_providers')->toString(),
        ]) . '</div>',
      ];
      return $form;
    }

    // Get the query string for provider_id, model_id.
    $request = $this->requestStack->getCurrentRequest();
    if ($request->query->get('provider_id')) {
      $form_state->setValue('ata_ai_provider', $request->query->get('provider_id'));
    }
    if ($request->query->get('model_id')) {
      $form_state->setValue('ata_ai_model', $request->query->get('model_id'));
    }

    $form['#attached']['library'][] = 'ai_api_explorer/explorer';

    $form['file'] = [
      '#prefix' => '<div class="ai-left-side">',
      '#type' => 'file',
      // Only mp3 files are allowed in this case, since that covers most models.
      '#accept' => '.mp3',
      '#title' => $this->t('Upload your audio here. When submitted, your provider will generate a response. Please note that each query counts against your API usage if your provider is a paid provider.'),
      '#description' => $this->t('Based on the complexity of your prompt, traffic, and other factors, a response can take time to complete. Please allow the operation to finish.'),
      '#required' => TRUE,
    ];

    $form['image'] = [
      '#type' => 'file',
      '#accept' => '.jpg, .jpeg, .png',
      '#title' => $this->t('Upload your image here. When submitted, your provider will generate a response. Please note that each query counts against your API usage if your provider is a paid provider.'),
      '#description' => $this->t('Based on the complexity of your prompt, traffic, and other factors, a response can take time to complete. Please allow the operation to finish.'),
      '#required' => TRUE,
    ];

    // Load the LLM configurations.
    $this->aiProviderHelper->generateAiProvidersForm($form, $form_state, 'image_and_audio_to_video', 'ata', AiProviderFormHelper::FORM_CONFIGURATION_FULL);

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Generate an Video File'),
      '#ajax' => [
        'callback' => '::getResponse',
        'wrapper' => 'ai-video-response',
      ],
    ];

    $form['end_markup'] = [
      '#markup' => '</div>',
    ];

    $form['response'] = [
      '#prefix' => '<div id="ai-video-response" class="ai-right-side">',
      '#suffix' => '</div>',
      '#type' => 'inline_template',
      '#template' => '{{ videos|raw }}',
      '#weight' => 1000,
      '#context' => [
        'videos' => '<h2>Video will appear here.</h2>',
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
    $provider = $this->aiProviderHelper->generateAiProviderFromFormSubmit($form, $form_state, 'image_and_audio_to_video', 'ata');
    $files = $this->requestStack->getCurrentRequest()->files->all();
    $file = reset($files);
    $mime_type = $file['file']->getMimeType();
    $raw_file = file_get_contents($file['file']->getPathname());
    $file_name = $file['file']->getClientOriginalName();
    // Normalize the input.
    $audio_file = new AudioFile($raw_file, $mime_type, $file_name);

    $mime_type = $file['image']->getMimeType();
    $raw_file = file_get_contents($file['image']->getPathname());
    $file_name = $file['image']->getClientOriginalName();

    // Normalize the input.
    $image_file = new ImageFile($raw_file, $mime_type, $file_name);
    $input = new ImageAndAudioToVideoInput($image_file, $audio_file);
    $response = '';
    $video_normalized = [];
    try {
      $video_normalized = $provider->ImageAndAudioToVideo($input, $form_state->getValue('ata_ai_model'), ['ai_api_explorer'])->getNormalized();
    }
    catch (\Exception $e) {
      $response = $this->explorerHelper->renderException($e);
    }

    $code = "";
    // Save the binary data to a file.
    if ($video_normalized) {
      $file_url = $this->fileSystem->saveData($video_normalized->getBinary(), 'public://image-and-audio-to-video-test.mp4', FileExists::Replace);
      $response .= '<video controls><source src="' . $this->fileUrlGenerator->generateAbsoluteString($file_url) . '" type="audio/mpeg"></video>';

      $code = $this->normalizeCodeExample($provider, $form_state, $file_name);
    }
    $form['response']['#context'] = [
      'videos' => '<h2>Video will appear here.</h2>' . $response . $code,
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
   * @param string $filename
   *   The filename.
   *
   * @return string
   *   The normalized code example.
   */
  public function normalizeCodeExample(AiProviderInterface|ProviderProxy $provider, FormStateInterface $form_state, string $filename): string {
    // Generation code.
    $code = "<details class=\"ai-code-wrapper\"><summary>Code Example</summary><code style=\"display: block; white-space: pre-wrap; padding: 20px;\">";
    $code .= '$binary = file_get_contents("' . $filename . '");<br>';
    if (count($provider->getConfiguration())) {
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
    $code .= "\$ai_provider = \Drupal::service('ai.provider')->createInstance('" . $form_state->getValue('ata_ai_provider') . '\');<br>';
    $code .= "\$ai_provider->setConfiguration(\$config);<br>";
    $code .= "// Normalize the input.<br>";
    $code .= "\$audio_file = new \Drupal\ai\OperationType\GenericType\AudioFile(\$binary, 'audio/mp3', '" . $filename . "');<br>";
    $code .= "\$input = new \Drupal\ai\OperationType\AudioToAudio\AudioToAudioInput(\$audio_file);<br>";
    $code .= "// \$response will be a AudioFile with the text.<br>";
    $code .= "\$response = \$ai_provider->audioToAudio(\$input, '" . $form_state->getValue('ata_ai_model') . '\', ["your_module_name"]);';
    $code .= "</code></details>";
    return $code;
  }

}
