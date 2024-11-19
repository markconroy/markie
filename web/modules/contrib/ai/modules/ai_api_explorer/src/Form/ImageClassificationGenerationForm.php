<?php

declare(strict_types=1);

namespace Drupal\ai_api_explorer\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\ai\AiProviderInterface;
use Drupal\ai\OperationType\GenericType\ImageFile;
use Drupal\ai\OperationType\ImageClassification\ImageClassificationInput;
use Drupal\ai\Plugin\ProviderProxy;
use Drupal\ai\Service\AiProviderFormHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form to prompt AI for image classification.
 */
class ImageClassificationGenerationForm extends FormBase {

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
    return 'ai_api_explorer_image_classification';
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
    if (!$this->providerManager->hasProvidersForOperationType('image_classification')) {
      $form['markup'] = [
        '#markup' => '<div class="ai-error">' . $this->t('No AI providers are installed for Image Classification calls, please %install and %configure one first.', [
          '%install' => Link::createFromRoute($this->t('install'), 'system.modules_list')->toString(),
          '%configure' => Link::createFromRoute($this->t('configure'), 'ai.admin_providers')->toString(),
        ]) . '</div>',
      ];
      return $form;
    }

    // Get the query string for provider_id, model_id.
    $request = $this->requestStack->getCurrentRequest();
    if ($request->query->get('provider_id')) {
      $form_state->setValue('image_class_ai_provider', $request->query->get('provider_id'));
    }
    if ($request->query->get('model_id')) {
      $form_state->setValue('image_class_ai_model', $request->query->get('model_id'));
    }

    $form['#attached']['library'][] = 'ai_api_explorer/explorer';

    $form['file'] = [
      '#prefix' => '<div class="ai-left-side">',
      '#type' => 'file',
      '#accept' => '.jpg, .jpeg, .png',
      '#title' => $this->t('Upload your image here. When submitted, your provider will generate a classification. Please note that each query counts against your API usage if your provider is a paid provider.'),
      '#description' => $this->t('Based on the complexity of your prompt, traffic, and other factors, a response can take time to complete. Please allow the operation to finish.'),
      '#required' => TRUE,
    ];

    $form['labels'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Labels'),
      '#description' => $this->t('New line separated list of labels to filter the classification if the model takes it.'),
      '#attributes' => [
        'placeholder' => "is_hotdog\nis_not_hotdog\n",
      ],
    ];

    // Load the LLM configurations.
    $this->aiProviderHelper->generateAiProvidersForm($form, $form_state, 'image_classification', 'image_class', AiProviderFormHelper::FORM_CONFIGURATION_FULL);

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Classify Image'),
      '#ajax' => [
        'callback' => '::getResponse',
        'wrapper' => 'image-classify-response',
      ],
    ];

    $form['end_markup'] = [
      '#markup' => '</div>',
    ];

    $form['response'] = [
      '#prefix' => '<div id="image-classify-response" class="ai-right-side">',
      '#suffix' => '</div>',
      '#type' => 'inline_template',
      '#template' => '{{ classification|raw }}',
      '#weight' => 1000,
      '#context' => [
        'classification' => '<h2>Classification will appear here.</h2>',
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
    $provider = $this->aiProviderHelper->generateAiProviderFromFormSubmit($form, $form_state, 'image_classification', 'image_class');
    $files = $this->requestStack->getCurrentRequest()->files->all();
    $file = reset($files);
    $mime_type = $file['file']->getMimeType();
    $raw_file = file_get_contents($file['file']->getPathname());
    $file_name = $file['file']->getClientOriginalName();
    // Normalize the input.
    $image_file = new ImageFile($raw_file, $mime_type, $file_name);

    // Get the labels.
    $labels = explode("\n", $form_state->getValue('labels'));
    $labels = array_map('trim', $labels);
    // Make sure we don't have empty labels.
    $labels = array_filter($labels, function ($label) {
      return !empty(trim($label));
    });

    $input = new ImageClassificationInput($image_file, $labels);
    $response = '';
    $classification = NULL;
    try {
      $classification = $provider->imageClassification($input, $form_state->getValue('image_class_ai_model'), ['ai_api_explorer'])->getNormalized();
    }
    catch (\Exception $e) {
      $response = $this->explorerHelper->renderException($e);
    }

    $code = "";
    // Save the binary image class to a file.
    if ($classification) {
      foreach ($classification as $row) {
        $response .= '<strong>' . $row->getLabel() . '</strong>: <em>' . $row->getConfidenceScore() . '</em><br>';
      }

      $code = $this->normalizeCodeExample($provider, $form_state, $file_name, $labels);
    }
    $form['response']['#context'] = [
      'classification' => '<h2>Classification will appear here.</h2>' . $response . $code,
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
   * @param string[] $labels
   *   The labels.
   *
   * @return string
   *   The normalized code example.
   */
  public function normalizeCodeExample(AiProviderInterface|ProviderProxy $provider, FormStateInterface $form_state, string $filename, array $labels): string {
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
    $code .= "\$ai_provider = \Drupal::service('ai.provider')->createInstance('" . $form_state->getValue('image_class_ai_provider') . '\');<br>';
    if (count($provider->getConfiguration())) {
      $code .= "\$ai_provider->setConfiguration(\$config);<br>";
    }
    $code .= "// Normalize the input.<br>";
    $code .= "\$image = new \Drupal\ai\OperationType\GenericType\ImageFile(\$binary, 'image/jpeg', '" . $filename . "');<br>";

    if (count($labels)) {
      $code .= "\$labels = [<br>";
      foreach ($labels as $label) {
        $code .= '&nbsp;&nbsp;"' . $label . '",<br>';
      }
      $code .= '];<br>';
      $code .= "\$input = new \Drupal\ai\OperationType\ImageClassification\ImageClassificationInput(\$image, \$labels);<br><br>";
    }
    else {
      $code .= "\$input = new \Drupal\ai\OperationType\ImageClassification\ImageClassificationInput(\$image);<br><br>";
    }
    $code .= "// Run the classification.<br>";
    $code .= "\$response = \$ai_provider->imageClassification(\$input, '" . $form_state->getValue('image_class_ai_model') . '\', ["your_module_name"]);<br><br>';
    $code .= "// Output is an array of classification objects.<br>";
    $code .= "\$classifications = \$response->getNormalized();<br>";
    $code .= "</code></details>";
    return $code;
  }

}
