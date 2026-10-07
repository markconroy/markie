<?php

namespace Drupal\ai_ckeditor\Plugin\AiCKEditor;

use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\Utility\Textarea;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_ckeditor\AiCKEditorPluginBase;
use Drupal\ai_ckeditor\Attribute\AiCKEditor;
use Drupal\ai_ckeditor\Command\AiRequestCommand;
use Drupal\Core\Template\TwigEnvironment;
use Drupal\taxonomy\Entity\Term;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Plugin to convert tone of selected text.
 */
#[AiCKEditor(
  id: 'ai_ckeditor_tone',
  label: new TranslatableMarkup('Tone'),
  description: new TranslatableMarkup('Convert tone of selected text.'),
  module_dependencies: ['taxonomy'],
)]
final class Tone extends AiCKEditorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    AiProviderPluginManager $ai_provider_manager,
    EntityTypeManagerInterface $entity_type_manager,
    AccountProxyInterface $account,
    RequestStack $requestStack,
    LoggerChannelFactoryInterface $logger_factory,
    LanguageManagerInterface $language_manager,
    protected TwigEnvironment $twig,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $ai_provider_manager, $entity_type_manager, $account, $requestStack, $logger_factory, $language_manager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ai.provider'),
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('request_stack'),
      $container->get('logger.factory'),
      $container->get('language_manager'),
      $container->get('twig'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'autocreate' => FALSE,
      'provider' => NULL,
      'tone_vocabulary' => NULL,
      'use_description' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $vocabularies = $this->entityTypeManager->getStorage('taxonomy_vocabulary')->loadMultiple();

    if (empty($vocabularies)) {
      return [
        '#markup' => 'You must add at least one taxonomy vocabulary before you can configure this plugin.',
      ];
    }

    $vocabulary_options = [];

    foreach ($vocabularies as $vocabulary) {
      $vocabulary_options[$vocabulary->id()] = $vocabulary->label();
    }

    $form['tone_vocabulary'] = [
      '#type' => 'select',
      '#title' => $this->t('Choose default vocabulary for tone options'),
      '#options' => $vocabulary_options,
      '#description' => $this->t('Select the vocabulary that contains tone options.'),
      '#default_value' => $this->configuration['tone_vocabulary'],
    ];

    $form['autocreate'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow autocreate'),
      '#description' => $this->t('If enabled, users with access to this format are able to autocreate new terms in the chosen vocabulary, instead of a select list.'),
      '#default_value' => $this->configuration['autocreate'] ?? FALSE,
    ];

    $form['use_description'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use term description for tone description'),
      '#description' => $this->t('If enabled and a description field is filled out, the tone will use this description to explain how the AI should rewrite in that tone of voice.'),
      '#default_value' => $this->configuration['use_description'] ?? FALSE,
    ];

    $options = $this->aiProviderManager->getSimpleProviderModelOptions('chat', FALSE);
    $form['provider'] = [
      '#type' => 'select',
      "#empty_option" => $this->t('-- Default from AI module (chat) --'),
      '#title' => $this->t('AI provider'),
      '#options' => $options,
      '#default_value' => $this->configuration['provider'] ?? $this->aiProviderManager->getSimpleDefaultProviderOptions('chat'),
      '#description' => $this->t('Select which provider to use for this plugin. See the <a href=":link">Provider overview</a> for details about each provider.', [':link' => '/admin/config/ai/providers']),
    ];
    $prompts_config = $this->getConfigFactory()->get('ai_ckeditor.settings');
    $prompt_tone = $prompts_config->get('prompts.tone');
    $form['prompt'] = [
      '#type' => 'ai_prompt',
      '#title' => $this->t('Change tone prompt'),
      '#prompt_types' => ['ai_ckeditor_tone'],
      '#default_value' => $prompt_tone,
      '#description' => $this->t('This prompt will be used to change the tone of voice. {tone} is the target tone of voice that is chosen.'),
      '#parents' => [
        'editor',
        'settings',
        'plugins',
        'ai_ckeditor_ai',
        'plugins',
        'ai_ckeditor_tone',
        'prompt',
      ],
      '#states' => [
        'required' => [
          ':input[name="editor[settings][plugins][ai_ckeditor_ai][plugins][ai_ckeditor_tone][enabled]"]' => ['checked' => TRUE],
        ],
      ],
      // This property will land into core soon, see
      // https://www.drupal.org/project/drupal/issues/3202631. It can stay
      // after this is added to Drupal core.
      '#normalize_newlines' => TRUE,
      // Until that the custom value callback is needed. Should be removed
      // after the issue mentioned above is merged into core and the minimum
      // supported Drupal version includes `#normalize_newlines` property.
      '#value_callback' => [Textarea::class, 'valueCallback'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function getGenerateButtonLabel() {
    return $this->t('Change the tone');
  }

  /**
   * {@inheritdoc}
   */
  protected function getSelectedTextLabel() {
    return $this->t('Selected text to convert');
  }

  /**
   * {@inheritdoc}
   */
  protected function getAiResponseLabel() {
    return $this->t('Suggested conversion');
  }

  /**
   * {@inheritdoc}
   */
  protected function getNoSelectedTextMessage(): TranslatableMarkup {
    return $this->t('You must select some text before you can change the tone.');
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['provider'] = $form_state->getValue('provider');
    $this->configuration['autocreate'] = (bool) $form_state->getValue('autocreate');
    $this->configuration['tone_vocabulary'] = $form_state->getValue('tone_vocabulary');
    $this->configuration['use_description'] = (bool) $form_state->getValue('use_description');
    $newPrompt = $form_state->getValue('prompt');
    $prompts_config = $this->getConfigFactory()->getEditable('ai_ckeditor.settings');
    $prompts_config->set('prompts.tone', $newPrompt)->save();
  }

  /**
   * {@inheritdoc}
   */
  public function buildCkEditorModalForm(array $form, FormStateInterface $form_state, array $settings = []) {
    $form = parent::buildCkEditorModalForm($form, $form_state, $settings);

    // If no text was selected, don't append plugin-specific fields.
    $storage = $form_state->getStorage();
    if (empty($storage['selected_text'])) {
      return $form;
    }

    $form['tone'] = [
      '#type' => $this->configuration['autocreate'] ? 'entity_autocomplete' : 'select',
      '#title' => $this->t('Choose tone'),
      '#tags' => FALSE,
      '#required' => TRUE,
      '#description' => $this->t('Selecting one of the options will adjust/reword the body content to be appropriate for the target audience.'),
    ];

    if ($this->configuration['autocreate']) {
      $form['tone']['#target_type'] = 'taxonomy_term';
      $form['tone']['#selection_settings'] = [
        'target_bundles' => [$this->configuration['tone_vocabulary']],
      ];
    }
    else {
      $form['tone']['#options'] = $this->getTermOptions($this->configuration['tone_vocabulary']);
    }

    if ($this->configuration['autocreate'] && $this->account->hasPermission('create terms in ' . $this->configuration['tone_vocabulary'])) {
      $form['tone']['#autocreate'] = [
        'bundle' => $this->configuration['tone_vocabulary'],
      ];
    }

    return $form;
  }

  /**
   * Generate text callback.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return mixed
   *   The result of the AJAX operation.
   */
  public function ajaxGenerate(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    try {
      if (is_array($values['plugin_config']['tone']) && reset($values['plugin_config']['tone']) instanceof Term) {
        $term = reset($values['plugin_config']['tone']);
      }
      else {
        $term = $this->entityTypeManager->getStorage('taxonomy_term')
          ->load($values['plugin_config']['tone']);
      }

      if (empty($term)) {
        throw new \Exception('Term could not be loaded.');
      }

      if (!$term->isNew()) {
        $language = $this->languageManager->getCurrentLanguage()->getId();
        if ($term->hasTranslation($language)) {
          $term = $term->getTranslation($language);
        }
      }

      if ($term->isNew() && $this->configuration['autocreate'] && $this->account->hasPermission('create terms in ' . $this->configuration['tone_vocabulary'])) {
        $term->save();
      }
      $prompts_config = $this->getConfigFactory()->get('ai_ckeditor.settings');
      $promptId = $prompts_config->get('prompts.tone');
      $promptText = $this->getConfigFactory()->get('ai.ai_prompt.' . $promptId)?->get('prompt') ?? '';
      // Replace the placeholders.
      $toneDescription = '';
      if ($this->configuration['use_description'] && !empty($term->getDescription())) {
        $toneDescription = strip_tags($term->getDescription());
      }

      // Use Twig to render the prompt with conditional logic for
      // the use_description setting. This must happen before the
      // placeholders are replaced, so that the selected text, the tone name
      // and the tone description are never evaluated as a Twig template.
      $promptText = (string) $this->twig->renderInline($promptText, [
        'use_description' => (bool) $this->configuration['use_description'],
      ]);

      $promptText = strtr($promptText, [
        '{tone}' => $term->label(),
        '{toneDescription}' => $toneDescription,
        '{inputText}' => $values['plugin_config']['selected_text'],
      ]);

      $response = new AjaxResponse();
      $values = $form_state->getValues();
      assert(is_array($this->pluginDefinition));
      $response->addCommand(new AiRequestCommand($promptText, $values['editor_id'], $this->pluginDefinition['id'], 'ai-ckeditor-response'));
      return $response;
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('ai_ckeditor')->error("There was an error in the Tone AI plugin for CKEditor.");
      return $form['plugin_config']['response_wrapper']['response_text']['#value'] = 'There was an error in the Tone AI plugin for CKEditor.';
    }
  }

  /**
   * Helper function to get all terms as an options array.
   *
   * @param string $vid
   *   The vocabulary ID.
   *
   * @return array
   *   The options array.
   */
  protected function getTermOptions(string $vid): array {
    $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadTree($vid, 0, NULL, TRUE);
    $options = [];
    $language = $this->languageManager->getCurrentLanguage()->getId();

    foreach ($terms as $term) {
      if ($term->hasTranslation($language)) {
        $options[$term->id()] = $term->getTranslation($language)->label();
      }
      else {
        $options[$term->id()] = $term->label();
      }
    }

    return $options;
  }

}
