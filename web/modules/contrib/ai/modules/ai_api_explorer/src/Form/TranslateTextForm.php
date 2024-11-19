<?php

declare(strict_types=1);

namespace Drupal\ai_api_explorer\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\ai\OperationType\TranslateText\TranslateTextInput;
use Drupal\ai\Service\AiProviderFormHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form to prompt AI for transcriptions.
 */
class TranslateTextForm extends FormBase {

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
    return 'ai_api_explorer_translate_text';
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
    if (!$this->providerManager->hasProvidersForOperationType('translate_text')) {
      $form['markup'] = [
        '#markup' => '<div class="ai-error">' . $this->t('No AI providers are installed for Translate Text calls, please %install and %configure one first.', [
          '%install' => Link::createFromRoute($this->t('install'), 'system.modules_list')->toString(),
          '%configure' => Link::createFromRoute($this->t('configure'), 'ai.admin_providers')->toString(),
        ]) . '</div>',
      ];
      return $form;
    }

    // Get the query string for provider_id, model_id.
    $request = $this->requestStack->getCurrentRequest();
    if ($request->query->get('provider_id')) {
      $form_state->setValue('tt_ai_provider', $request->query->get('provider_id'));
    }
    if ($request->query->get('model_id')) {
      $form_state->setValue('tt_ai_model', $request->query->get('model_id'));
    }

    $input = json_decode($request->query->get('input', '[]'));

    $form['#attached']['library'][] = 'ai_api_explorer/explorer';

    $form['text'] = [
      '#prefix' => '<div class="ai-left-side">',
      '#type' => 'textarea',
      '#title' => $this->t('Enter your prompt here. When submitted, your provider will generate a response.'),
      '#description' => $this->t('Please note that each query counts against your API usage if your provider is a paid provider. Based on the complexity of your prompt, traffic, and other factors, a response can take time to complete. Please allow the operation to finish.'),
      '#default_value' => $input,
      '#required' => TRUE,
    ];
    $form['source_language'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Source language'),
      '#description' => $this->t('The language code of the text you are translating.'),
    ];
    $form['target_language'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Target language'),
      '#description' => $this->t('The language code you want to translate the text to.'),
      '#required' => TRUE,
    ];

    // Load the LLM configurations.
    $this->aiProviderHelper->generateAiProvidersForm($form, $form_state, 'translate_text', 'tt', AiProviderFormHelper::FORM_CONFIGURATION_FULL);

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Translate the text'),
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
    $provider = $this->aiProviderHelper->generateAiProviderFromFormSubmit($form, $form_state, 'translate_text', 'tt');
    $text = $form_state->getValue('text');
    $sourceLanguage = $form_state->getValue('source_language') ?? NULL;
    $targetLang = $form_state->getValue('target_language');
    $model = $form_state->getValue('tt_ai_model');
    $input = new TranslateTextInput($text, $sourceLanguage, $targetLang);

    try {
      $translation = $provider->translateText($input, $model, []);
      $response = $translation->getNormalized();
    }
    catch (\Exception $e) {
      $response = $this->explorerHelper->renderException($e);
    }

    $form['response']['#context'] = [
      'texts' => '<div id="ai-text-response"><h2>Texts will appear here.</h2>' . $response . '</div>',
    ];
    return $form['response'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

}
