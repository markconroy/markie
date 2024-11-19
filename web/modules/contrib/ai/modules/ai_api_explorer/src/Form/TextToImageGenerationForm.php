<?php

declare(strict_types=1);

namespace Drupal\ai_api_explorer\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\ai\AiProviderInterface;
use Drupal\ai\Plugin\ProviderProxy;
use Drupal\ai\Service\AiProviderFormHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form to prompt AI for images.
 */
class TextToImageGenerationForm extends FormBase {

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
    return 'ai_api_explorer_image_prompt';
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->aiProviderHelper = $container->get('ai.form_helper');
    $instance->requestStack = $container->get('request_stack');
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
    if (!$this->providerManager->hasProvidersForOperationType('text_to_image')) {
      $form['markup'] = [
        '#markup' => '<div class="ai-error">' . $this->t('No AI providers are installed for Text To Image calls, please %install and %configure one first.', [
          '%install' => Link::createFromRoute($this->t('install'), 'system.modules_list')->toString(),
          '%configure' => Link::createFromRoute($this->t('configure'), 'ai.admin_providers')->toString(),
        ]) . '</div>',
      ];
      return $form;
    }

    // Get the query string for provider_id, model_id.
    $request = $this->requestStack->getCurrentRequest();
    if ($request->query->get('provider_id')) {
      $form_state->setValue('image_generator_ai_provider', $request->query->get('provider_id'));
    }
    if ($request->query->get('model_id')) {
      $form_state->setValue('image_generator_ai_model', $request->query->get('model_id'));
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
    $this->aiProviderHelper->generateAiProvidersForm($form, $form_state, 'text_to_image', 'image_generator', AiProviderFormHelper::FORM_CONFIGURATION_FULL);

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
      '#value' => $this->t('Generate an Image'),
      '#ajax' => [
        'callback' => '::getResponse',
        'wrapper' => 'ai-image-response',
      ],
    ];

    $form['end_markup'] = [
      '#markup' => '</div>',
    ];

    $form['response'] = [
      '#prefix' => '<div id="ai-image-response" class="ai-right-side">',
      '#suffix' => '</div>',
      '#type' => 'inline_template',
      '#template' => '{{ images|raw }}',
      '#weight' => 1000,
      '#context' => [
        'images' => '<h2>Image will appear here.</h2>',
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
    $provider = $this->aiProviderHelper->generateAiProviderFromFormSubmit($form, $form_state, 'text_to_image', 'image_generator');
    try {
      $images = $provider->textToImage($form_state->getValue('prompt'), $form_state->getValue('image_generator_ai_model'), ['ai_api_explorer'])->getNormalized();
      $response = '';
      /** @var \Drupal\ai\OperationType\GenericType\ImageFile $image */
      foreach ($images as $image) {
        $response .= '<img src="' . $image->getAsBase64EncodedString() . '" />';
      }
      if ($form_state->getValue('save_as_media')) {
        $images[0]->getAsMediaEntity($form_state->getValue('save_as_media'), 'public://', 'image.png');
      }
    }
    catch (\Exception $e) {
      $response = $this->explorerHelper->renderException($e);
    }

    // Generation code.
    $code = $this->normalizeCodeExample($provider, $form_state, $form_state->getValue('prompt'));

    $form['response']['#context'] = [
      'images' => '<div id="ai-image-response"><h2>Image will appear here.</h2>' . $response . $code . '</div>',
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
    $code .= "\$ai_provider = \Drupal::service('ai.provider')->createInstance('" . $form_state->getValue('image_generator_ai_provider') . '\');<br>';
    $code .= "\$ai_provider->setConfiguration(\$config);<br>";
    $code .= "// Normalize the input.<br>";
    $code .= "\$input = new \Drupal\ai\OperationType\TextToImage\TextToImageInput(\$prompt);<br>";
    $code .= "// This gets an array of \Drupal\ai\OperationType\GenericType\ImageFile.<br>";
    $code .= "\$normalized = \$ai_provider->textToImage(\$input, '" . $form_state->getValue('image_generator_ai_model') . '\', ["tag_1", "tag_2"])->getNormalized();<br><br>';
    $code .= "// Examples Possibility #1 - get binary from the first image.<br>";
    $code .= '$binaries = $normalized[0]->getAsBinary();<br>';
    $code .= "// Examples Possibility #2 - get as base 64 encoded string from the first image.<br>";
    $code .= '$base64 = $normalized[0]->getAsBase64EncodedString();<br>';
    $code .= "// Examples Possibility #3 - get as generated media from the first image.<br>";
    $code .= '$media = $normalized[0]->getAsMediaEntity("image", "public://", "image.png");<br>';
    $code .= "// Examples Possibility #4 - get as image file entity from the first image.<br>";
    $code .= '$file = $normalized[0]->getAsImageEntity("public://", "image.png");<br><br>';
    $code .= "// Another possibility is to get the raw response from the provider.<br>";
    $code .= '$raw = $response->getRaw();<br>';
    $code .= "</code></details>";
    return $code;
  }

}
