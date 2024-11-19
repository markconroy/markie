<?php

declare(strict_types=1);

namespace Drupal\ai_api_explorer\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\ai\AiProviderInterface;
use Drupal\ai\OperationType\Embeddings\EmbeddingsInput;
use Drupal\ai\OperationType\GenericType\ImageFile;
use Drupal\ai\Plugin\ProviderProxy;
use Drupal\ai\Service\AiProviderFormHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form to prompt AI for embeddings.
 */
class EmbeddingsGenerationForm extends FormBase {

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
    return 'ai_api_explorer_embeddings';
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
    if (!$this->providerManager->hasProvidersForOperationType('embeddings')) {
      $form['markup'] = [
        '#markup' => '<div class="ai-error">' . $this->t('No AI providers are installed for Embeddings calls, please %install and %configure one first.', [
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
    ];

    $form['image'] = [
      '#type' => 'file',
      '#accept' => '.jpg, .jpeg, .png',
      '#title' => $this->t('(OR) upload an image here if its an image embeddings model. When submitted, your provider will generate a response. Please note that each query counts against your API usage if your provider is a paid provider.'),
      '#description' => $this->t('Based on the complexity of your prompt, traffic, and other factors, a response can take time to complete. Please allow the operation to finish.'),
    ];

    // Load the LLM configurations.
    $this->aiProviderHelper->generateAiProvidersForm($form, $form_state, 'embeddings', 'embed', AiProviderFormHelper::FORM_CONFIGURATION_FULL);

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Generate Embeddings'),
      '#ajax' => [
        'callback' => '::getResponse',
        'wrapper' => 'ai-embeddings-response',
      ],
    ];

    $form['end_markup'] = [
      '#markup' => '</div>',
    ];

    $form['response'] = [
      '#prefix' => '<div id="ai-embeddings-response" class="ai-right-side">',
      '#suffix' => '</div>',
      '#type' => 'inline_template',
      '#template' => '{{ embeddings|raw }}',
      '#weight' => 101,
      '#context' => [
        'embeddings' => '<h2>Embeddings will appear here.</h2>',
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
    $provider = $this->aiProviderHelper->generateAiProviderFromFormSubmit($form, $form_state, 'embeddings', 'embed');
    $files = $this->requestStack->getCurrentRequest()->files->all();
    $file = reset($files);

    // Normalize the input.
    $input = new EmbeddingsInput();
    if ($file) {
      $mime_type = $file['image']->getMimeType();
      $raw_file = file_get_contents($file['image']->getPathname());
      $file_name = $file['image']->getClientOriginalName();
      $image_file = new ImageFile($raw_file, $mime_type, $file_name);
      // Because its octect/stream sometimes.
      $image_file->resetMimeTypeFromFileName();
      $input->setImage($image_file);
    }
    else {
      $input->setPrompt($form_state->getValue('prompt'));
    }
    try {
      $embeddings = $provider->embeddings($input, $form_state->getValue('embed_ai_model'), ['ai_api_explorer']);
      $response = implode(', ', $embeddings->getNormalized());
    }
    catch (\Exception $e) {
      $response = $this->explorerHelper->renderException($e);
    }
    $code = $this->normalizeCodeExample($provider, $form_state, $form_state->getValue('prompt'), $file ? $file_name : NULL);

    $form['response']['#context'] = [
      'embeddings' => '<h2>Embeddings will appear here</h2>' . $response . $code,
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
   * @param string $filename
   *   The filename.
   *
   * @return string
   *   The normalized code example.
   */
  public function normalizeCodeExample(AiProviderInterface|ProviderProxy $provider, FormStateInterface $form_state, string $prompt = "", ?string $filename = NULL): string {
    // Generation code.
    $code = "<details class=\"ai-code-wrapper\"><summary>Code Example</summary><code style=\"display: block; white-space: pre-wrap; padding: 20px;\">";
    if ($filename) {
      $code .= '$binary = file_get_contents("' . $filename . '");<br>';
    }
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

    $code .= "\$ai_provider = \Drupal::service('ai.provider')->createInstance('" . $form_state->getValue('embed_ai_provider') . '\');<br>';
    $code .= "\$ai_provider->setConfiguration(\$config);<br>";
    $code .= "// Normalize the input.<br>";
    $code .= "\$input = new \Drupal\ai\OperationType\Embeddings\EmbeddingsInput();<br>";
    if ($filename) {
      $code .= "\$image_file = new \Drupal\ai\OperationType\GenericType\ImageFile(\$binary, 'image/jpg', '" . $filename . "');<br>";
      $code .= "\$input->setImage(\$image_file);<br>";
    }
    else {
      $code .= "\$input->setPrompt('" . $prompt . "');<br>";
    }
    $code .= "\$response = \$ai_provider->embeddings(\$input, '" . $form_state->getValue('embed_ai_model') . '\', ["your_module_name"]);<br><br>';
    $code .= "// This gets an array of vector numbers (unless other output is possible).<br>";
    $code .= "\$normalized = \$response->getNormalized();<br><br>";
    $code .= "// Another possibility is to get the raw response from the provider.<br>";
    $code .= '$raw = $response->getRaw();<br>';
    $code .= "</code></details>";
    return $code;
  }

}
