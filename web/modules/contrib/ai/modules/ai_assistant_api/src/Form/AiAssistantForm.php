<?php

declare(strict_types=1);

namespace Drupal\ai_assistant_api\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Extension\ExtensionPathResolver;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Site\Settings;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\Service\AiProviderFormHelper;
use Drupal\ai\Utility\CastUtility;
use Drupal\ai_assistant_api\AiAssistantActionPluginManager;
use Drupal\ai_assistant_api\Entity\AiAssistant;

/**
 * AI Assistant form.
 */
final class AiAssistantForm extends EntityForm {

  /**
   * The ai assistant action plugin manager.
   *
   * @var \Drupal\ai_assistant_api\AiAssistantActionPluginManager
   */
  protected $actionPluginManager;

  /**
   * The path extension resolver.
   *
   * @var \Drupal\Core\Extension\ExtensionPathResolver
   */
  protected $extensionPathResolver;

  /**
   * The AI form helper.
   *
   * @var \Drupal\ai\Service\AiProviderFormHelper
   */
  protected $formHelper;

  /**
   * The AI Provider.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected $aiProvider;

  /**
   * Constructs a new AiAssistantForm object.
   */
  public function __construct(
    AiAssistantActionPluginManager $action_plugin_manager,
    ExtensionPathResolver $extension_path_resolver,
    AiProviderFormHelper $form_helper,
    AiProviderPluginManager $ai_provider,
  ) {
    $this->actionPluginManager = $action_plugin_manager;
    $this->extensionPathResolver = $extension_path_resolver;
    $this->formHelper = $form_helper;
    $this->aiProvider = $ai_provider;
  }

  /**
   * {@inheritdoc}
   */
  public static function create($container) {
    return new static(
      $container->get('ai_assistant_api.action_plugin.manager'),
      $container->get('extension.path.resolver'),
      $container->get('ai.form_helper'),
      $container->get('ai.provider')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    /** @var \Drupal\ai_assistant_api\Entity\AiAssistant $entity */
    $entity = $this->entity;
    $form = parent::form($form, $form_state);

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $entity->label(),
      '#description' => $this->t('This is the title of the AI Assistant'),
      '#required' => TRUE,
      '#attributes' => [
        'placeholder' => $this->t('Article finder assistant'),
      ],
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $entity->id(),
      '#machine_name' => [
        'exists' => [AiAssistant::class, 'load'],
      ],
      '#disabled' => !$entity->isNew(),
    ];

    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => $entity->status(),
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $entity->get('description'),
      '#description' => $this->t('A 1-2 sentence description of the AI assistant and what it does.'),
      '#attributes' => [
        'rows' => 2,
        'placeholder' => $this->t('An assistant that can find old articles and also publish and unpublish them.'),
      ],
    ];

    $form['allow_history'] = [
      '#type' => 'select',
      '#title' => $this->t('Allow History'),
      '#default_value' => $entity->get('allow_history') ?? 'session',
      '#description' => $this->t('If enabled, the AI Assistant will try store the questions and answers in history during a session. This makes it possible to ask follow-up questions to the Assistant. Note that this raises the price and size of AI calls, and might not be needed for all assistants. Sessions means that it will be stored in the session until the page is reloaded. (coming) Database means that it will be stored in the database with an ID and can be continued later in multiple threads.'),
      '#options' => [
        'none' => $this->t('None'),
        'session' => $this->t('Session'),
      ],
    ];

    $form['system_role'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Pre-prompt System role'),
      '#default_value' => $entity->get('system_role'),
      '#description' => $this->t('The pre-prompt system role that this AI assistant should have for the prompt. This is used to determine how and with what the AI Assistant should act.'),
      '#required' => TRUE,
      '#attributes' => [
        'rows' => 2,
        'placeholder' => $this->t('You are an assistant helping people find old articles in the archive using natural language. Answer in a professional and neutral tone. Be short and concise. Answer in markdown.'),
      ],
    ];

    $form['preprompt_instructions'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Pre-prompt Instructions'),
      '#default_value' => $entity->get('preprompt_instructions'),
      '#description' => $this->t('Extra instruction to the pre-prompt. This can be used to control the content of the pre-prompt.'),
      '#required' => FALSE,
      '#attributes' => [
        'rows' => 2,
        'placeholder' => $this->t('If the user asks questions about unpublished articles, make sure to add status unpublished somewhere in the lookup.'),
      ],
    ];

    foreach ($this->actionPluginManager->getDefinitions() as $definition) {
      $form['action_plugin_' . $definition['id']] = [
        '#type' => 'details',
        '#title' => $definition['label'],
        '#open' => TRUE,
        '#description' => $this->t('Configure the %label settings for this AI assistant.', [
          '%label' => $definition['label'],
        ]),
      ];

      $form['action_plugin_' . $definition['id']]['enabled'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Enable %label', ['%label' => $definition['label']]),
        '#default_value' => isset($entity->get('actions_enabled')[$definition['id']]),
      ];

      $form['action_plugin_' . $definition['id']]['plugin_id'] = [
        '#type' => 'hidden',
        '#value' => $definition['id'],
      ];

      $form['action_plugin_' . $definition['id']]['configuration'] = [
        '#type' => 'details',
        '#title' => $this->t('%label settings', [
          '%label' => $definition['label'],
        ]),
        '#open' => TRUE,
        'states' => [
          'visible' => [
            ':input[name="' . $definition['id'] . '_enabled"]' => ['checked' => TRUE],
          ],
        ],
        '#description' => $this->t('Configure the %label settings for this AI assistant.', [
          '%label' => $definition['label'],
        ]),
      ];

      $instance = $this->actionPluginManager->createInstance($definition['id'], $entity->get('actions_enabled')[$definition['id']] ?? []);
      $subform = $form['action_plugin_' . $definition['id']]['configuration'] ?? [];
      $subform_state = SubformState::createForSubform($subform, $form, $form_state);

      $form['action_plugin_' . $definition['id']]['configuration'] = $instance->buildConfigurationForm([], $subform_state);
      $form['action_plugin_' . $definition['id']]['#tree'] = TRUE;
    }

    $assistant_message = file_get_contents($this->extensionPathResolver->getPath('module', 'ai_assistant_api') . '/resources/assistant_message.txt');

    $form['assistant_message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Assistant message'),
      '#description' => $this->t('The assistant message is the system role for responses created by the LLM after an action like RAG lookup or agent actions are taken, they will get contexts from the different action providers like RAG and the chat history if session is enabled.'),
      '#default_value' => $entity->get('assistant_message') ?? $assistant_message,
      '#attributes' => [
        'placeholder' => $assistant_message,
        'rows' => 15,
      ],
    ];

    $form['error_message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Error message'),
      '#description' => $this->t('This is the answer if we run into any error on the way. You may use the token [error_message] to get the error message from the backend in your message, but it might be a security concern to show this to none escalated users.'),
      '#default_value' => $entity->get('error_message') ?? $this->t('I am sorry, something went terribly wrong. Please try to ask me again.'),
      '#attributes' => [
        'placeholder' => $this->t('I am sorry, something went terribly wrong. Please try to ask me again.'),
        'rows' => 2,
      ],
    ];

    // Set form state if empty.
    if ($form_state->getValue('llm_ai_provider') == NULL) {
      $form_state->setValue('llm_ai_provider', $entity->get('llm_provider'));
    }
    if ($form_state->getValue('llm_ai_model') == NULL) {
      $form_state->setValue('llm_ai_model', $entity->get('llm_model'));
    }

    $this->formHelper->generateAiProvidersForm($form, $form_state, 'chat', 'llm', AiProviderFormHelper::FORM_CONFIGURATION_FULL, 0, '', $this->t('Advanced LLM'), $this->t('The AI Provider to use for the advanced interactions.'), TRUE);

    // Set default values.
    $llm_configs = $entity->get('llm_configuration');
    if ($llm_configs && count($llm_configs)) {
      foreach ($llm_configs as $key => $value) {
        $form['llm_ajax_prefix']['llm_ajax_prefix_configuration_' . $key]['#default_value'] = $value;
      }
    }
    // phpcs:ignore
    $pre_action_prompt = file_get_contents($this->extensionPathResolver->getPath('module', 'ai_assistant_api') . '/resources/pre_action_prompt.txt');

    $form['advanced'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced settings'),
      '#open' => FALSE,
    ];

    $form['advanced']['pre_action_prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Pre Action Prompt'),
      '#default_value' => $entity->get('pre_action_prompt') ?? $pre_action_prompt,
      '#description' => $this->t("This field can be enabled by adding <strong>\$settings['ai_assistant_advanced_mode_enabled'] = TRUE;</strong> in settings.php. The pre prompts gets a list of actions that it can take, including RAG databases and either gives back actions that the Assistant can take or an outputted answer. You may use [list_of_actions] to list the actions that the Assistant can take. You can only change this via manual config change. DO NOT CHANGE THIS UNLESS YOU KNOW WHAT YOU ARE DOING. <br><br><strong>The following placesholders can be used:</strong><br>
      <em>[list_of_actions]</em> - The list of actions that the Assistant can take.<br>
      <em>[pre_prompt]</em> - The setup pre_prompt.<br>
      <em>[system_role]</em> - The system role of the Assistant.<br>
      <em>[is_logged_in]</em> - A message if the person is logged in or not.<br>
      <em>[user_name]</em> - The username of the user.<br>
      <em>[user_roles]</em> - The roles of the user.<br>
      <em>[user_email]</em> - The email of the user.<br>
      <em>[user_id]</em> - The user id of the user.<br>
      <em>[user_language]</em> - The language of the user.<br>
      <em>[user_timezone]</em> - The timezone of the user.<br>
      <em>[page_title]</em> - The title of the page.<br>
      <em>[page_path]</em> - The path of the page.<br>
      <em>[page_language]</em> - The language of the page.<br>
      <em>[site_name]</em> - The name of the site.<br>
      "),
      '#required' => TRUE,
      '#disabled' => !Settings::get('ai_assistant_advanced_mode_enabled', FALSE),
      '#attributes' => [
        'rows' => 75,
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    $this->formHelper->validateAiProvidersConfig($form, $form_state, 'chat', 'llm');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    /** @var \Drupal\ai_assistant_api\Entity\AiAssistant $entity */
    $entity = $this->entity;

    // RAG settings.
    $action_plugins = [];
    foreach ($form_state->getValues() as $key => $val) {
      if (strpos($key, 'action_plugin_') === 0) {
        if ($val['enabled']) {
          $action_plugins[$val['plugin_id']] = $val['configuration'] ?? [];
        }
      }
    }
    $entity->set('actions_enabled', $action_plugins);

    // LLM provider.
    $entity->set('llm_provider', $form_state->getValue('llm_ai_provider'));
    // If its default, we don't set the last.
    if ($form_state->getValue('llm_ai_provider') !== '__default__') {
      $entity->set('llm_model', $form_state->getValue('llm_ai_model'));
      $llm_config = [];
      $provider = $this->aiProvider->createInstance($form_state->getValue('llm_ai_provider'));
      $schema = $provider->getAvailableConfiguration('chat', $form_state->getValue('llm_ai_model'));
      foreach ($form_state->getValues() as $key => $val) {
        if (strpos($key, 'llm_') === 0 && $key !== 'llm_ai_provider' && $key !== 'llm_ai_model') {

          $real_key = str_replace('llm_ajax_prefix_configuration_', '', $key);
          $type = $schema[$real_key]['type'] ?? 'string';
          $llm_config[$real_key] = CastUtility::typeCast($type, $val);
        }
      }
      $entity->set('llm_configuration', $llm_config);
    }
    else {
      $entity->set('llm_configuration', []);
      $entity->set('llm_model', '');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $result = parent::save($form, $form_state);
    $message_args = ['%label' => $this->entity->label()];
    $this->messenger()->addStatus(
      match($result) {
        \SAVED_NEW => $this->t('Created new example %label.', $message_args),
        \SAVED_UPDATED => $this->t('Updated example %label.', $message_args),
      }
    );
    $form_state->setRedirectUrl($this->entity->toUrl('collection'));
    return $result;
  }

}
