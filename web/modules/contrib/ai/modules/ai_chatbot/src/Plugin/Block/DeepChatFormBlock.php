<?php

namespace Drupal\ai_chatbot\Plugin\Block;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Serialization\Yaml;
use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\Core\Utility\Token;
use Drupal\ai\PluginManager\ChatProcessorPluginManager;
use Drupal\ai\Service\CommonMarkConverterFactoryInterface;
use Drupal\ai_chatbot\Controller\DeepChatApi;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an AI form block.
 *
 * @Block(
 *   id = "ai_deepchat_block",
 *   admin_label = @Translation("AI Chatbot (DeepChat)"),
 *   category = @Translation("AI")
 * )
 */
class DeepChatFormBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The ChatProcessor plugin that replaced the block level assistant settings.
   */
  const ASSISTANT_PROCESSOR_PLUGIN_ID = 'ai_assistant_api_processor';

  /**
   * Block settings that predate the ChatProcessor plugins.
   */
  const LEGACY_SETTINGS = [
    'ai_assistant',
    'verbose_mode',
    'show_structured_results',
  ];

  /**
   * The verbosity a block that predates the ChatProcessor plugins ran with.
   *
   * 1.4.x defaulted verbose_mode to TRUE, so a legacy block that never saved
   * the setting explicitly - including the one Drupal CMS installs from
   * drupal_cms_ai/recipe.yml - was running verbose.
   */
  const LEGACY_VERBOSE_MODE = TRUE;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected FormBuilderInterface $formBuilder;

  /**
   * The ChatProcessor plugin manager.
   *
   * @var \Drupal\ai\PluginManager\ChatProcessorPluginManager
   */
  protected ChatProcessorPluginManager $chatProcessorManager;

  /**
   * Current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The file url generator.
   *
   * @var \Drupal\Core\File\FileUrlGenerator
   */
  protected $fileUrlGenerator;

  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected Token $token;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The theme manager.
   *
   * @var \Drupal\Core\Theme\ThemeManager
   */
  protected $themeManager;

  /**
   * The theme handler.
   *
   * @var \Drupal\Core\Extension\ThemeHandlerInterface
   */
  protected $themeHandler;

  /**
   * The messages button service.
   *
   * @var \Drupal\ai_chatbot\Service\MessagesButtons
   */
  protected $messagesButton;

  /**
   * The cache service.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The CommonMark converter factory.
   *
   * @var \Drupal\ai\Service\CommonMarkConverterFactoryInterface
   */
  protected CommonMarkConverterFactoryInterface $commonMarkConverterFactory;

  /**
   * The current path.
   *
   * @var \Drupal\Core\Path\CurrentPathStack
   */
  protected $currentPath;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $plugin = new static($configuration, $plugin_id, $plugin_definition);
    $plugin->entityTypeManager = $container->get('entity_type.manager');
    $plugin->formBuilder = $container->get('form_builder');
    $plugin->currentUser = $container->get('current_user');
    $plugin->fileUrlGenerator = $container->get('file_url_generator');
    $plugin->token = $container->get('token');
    $plugin->moduleHandler = $container->get('module_handler');
    $plugin->themeManager = $container->get('theme.manager');
    $plugin->themeHandler = $container->get('theme_handler');
    $plugin->messagesButton = $container->get('ai_chatbot.buttons');
    $plugin->cache = $container->get('cache.default');
    $plugin->logger = $container->get('logger.factory')->get('ai_chatbot');
    $plugin->requestStack = $container->get('request_stack');
    $plugin->currentPath = $container->get('path.current');
    $plugin->chatProcessorManager = $container->get(ChatProcessorPluginManager::class);
    $plugin->commonMarkConverterFactory = $container->get(CommonMarkConverterFactoryInterface::class);
    return $plugin;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    parent::setConfiguration($configuration);
    $this->configuration = $this->upgradeLegacySettings($this->configuration);
  }

  /**
   * Rebuilds the ChatProcessor settings from the pre-ChatProcessor ones.
   *
   * Blocks configured before the ChatProcessor plugins existed talked to the
   * AI Assistant API through block level settings. Those are migrated by
   * ai_chatbot_post_update_chat_processor(), but config that was exported
   * before that update ran - or that is imported, or installed by a recipe,
   * afterwards - still arrives in the old shape. Such a block gets the
   * ChatProcessor plugin that wraps the AI Assistant API, configured from the
   * settings it already has, so that it keeps working. The legacy settings are
   * dropped here, so the block is stored in the new shape the next time it is
   * saved.
   *
   * @param array $configuration
   *   The block configuration, possibly in the legacy shape.
   *
   * @return array
   *   The block configuration, in the ChatProcessor shape.
   */
  protected function upgradeLegacySettings(array $configuration): array {
    if (array_key_exists('ai_assistant', $configuration) && empty($configuration['chat_processor_plugin'])) {
      $configuration['chat_processor_plugin'] = static::ASSISTANT_PROCESSOR_PLUGIN_ID;
      // Anything already set on the plugin wins over the legacy settings.
      $configuration['plugin_configuration'] = ($configuration['plugin_configuration'] ?? []) + [
        'assistant_id' => (string) ($configuration['ai_assistant'] ?? ''),
        'stream_output' => (bool) ($configuration['stream'] ?? FALSE),
        'verbose_mode' => (bool) ($configuration['verbose_mode'] ?? static::LEGACY_VERBOSE_MODE),
        'show_structured_results' => (bool) ($configuration['show_structured_results'] ?? FALSE),
      ];
    }
    foreach (static::LEGACY_SETTINGS as $key) {
      unset($configuration[$key]);
    }
    return $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'bot_name' => 'Assistant',
      'bot_image' => '/modules/contrib/ai/modules/ai_chatbot/assets/ai-icon-gradient.svg',
      'use_username' => FALSE,
      'default_username' => '',
      'use_avatar' => FALSE,
      'default_avatar' => '',
      'first_message' => '',
      'loading_message' => '',
      'agent_delegation_message' => '',
      'stream' => FALSE,
      'toggle_state' => 'remember',
      'width' => '500px',
      'height' => '500px',
      'placement' => 'toolbar',
      'collapse_minimal' => FALSE,
      'style_file' => 'module:ai_chatbot:toolbar.yml',
      'show_copy_icon' => TRUE,
      'expansion_method' => 'expand',
      'disable_csrf' => FALSE,
      'chat_processor_plugin' => '',
      'plugin_configuration' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['#prefix'] = '<div id="ai-chatbot-form-wrapper">';
    $form['#suffix'] = '</div>';

    $form['notice'] = [
      '#theme' => 'status_messages',
      '#message_list' => [
        'warning' => [
          $this->t('Important and recommended to select the appropriate user role for this block to ensure it aligns with the AI assistance functionality and prevents anonymous users from accessing restricted features.'),
        ],
      ],
    ];

    // Get available ChatProcessor plugins.
    $definitions = $this->chatProcessorManager->getDefinitions();
    $plugin_options = [];
    if (empty($definitions)) {
      $markup = $this->t('There are no available Chat Executors.');
    }
    else {
      $markup = $this->t('The following chat executors are available: <ul>');
      foreach ($definitions as $plugin_id => $definition) {
        $plugin_options[$plugin_id] = $definition['label'];
        $markup .= '<li><strong>' . $definition['label'] . ':</strong> ' . $definition['description'] . '</li>';
      }
      $markup .= '</ul>';
    }

    $form['chat_processor_plugin'] = [
      '#type' => 'select',
      '#title' => $this->t('Chat Executor'),
      '#description' => $this->t('Select the type of chat executor to use for this chatbot instance. See descriptions by opening the "Available Chat Executors" section below.'),
      '#options' => $plugin_options,
      '#empty_option' => $this->t('- Select a plugin -'),
      '#default_value' => $this->configuration['chat_processor_plugin'],
      '#required' => TRUE,
      '#ajax' => [
        'callback' => [$this, 'updatePluginConfiguration'],
        'wrapper' => 'plugin-configuration-wrapper',
      ],
    ];

    $form['chat_processor_descriptions'] = [
      '#type' => 'details',
      '#title' => $this->t("Available Chat Executors"),
      '#open' => FALSE,
    ];

    $form['chat_processor_descriptions']['description_markup'] = [
      '#type' => 'markup',
      '#markup' => $markup,
    ];

    // Stable wrapper for the AJAX replace. Rendered as an empty container
    // until an executor is selected, so we don't show an empty fieldset.
    $form['plugin_configuration'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'plugin-configuration-wrapper'],
    ];

    // Load plugin configuration if available.
    $user_input = $form_state->getUserInput();
    $selected_plugin = $user_input['settings']['chat_processor_plugin'] ?? $this->configuration['chat_processor_plugin'];
    if (isset($definitions[$selected_plugin])) {
      try {
        /** @var \Drupal\ai\Plugin\ChatProcessor\ChatProcessorInterface $plugin_instance */
        $plugin_instance = $this->chatProcessorManager->createInstance($selected_plugin);
        $existing_config = $this->configuration['plugin_configuration'] ?? [];
        $plugin_instance->setConfiguration($existing_config);

        $plugin_form = $plugin_instance->buildConfigurationForm([], $form_state);
        if (!empty($plugin_form)) {
          $form['plugin_configuration']['#type'] = 'fieldset';
          $form['plugin_configuration']['#title'] = $this->t('Plugin Configuration');
          $form['plugin_configuration'] += $plugin_form;
        }
      }
      catch (\Exception $e) {
        $form['plugin_configuration']['#type'] = 'fieldset';
        $form['plugin_configuration']['#title'] = $this->t('Plugin Configuration');
        $form['plugin_configuration']['error'] = [
          '#markup' => $this->t('Error loading plugin configuration: @error', ['@error' => $e->getMessage()]),
        ];
      }
    }

    $form['messages'] = [
      '#type' => 'details',
      '#title' => $this->t('Message settings'),
      '#open' => FALSE,
    ];

    $form['messages']['first_message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('First Message'),
      '#description' => $this->t('The first message to start things of. Can take markdown.'),
      '#default_value' => $this->configuration['first_message'],
    ];

    $form['messages']['loading_message'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Loading Message'),
      '#description' => $this->t('The message shown while generating a response. Leave blank to use the default animated ellipsis. <br> Only shown when Verbose Mode is disabled.'),
      '#default_value' => $this->configuration['loading_message'] ?? '',
    ];

    $form['messages']['agent_delegation_message'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Agent Delegation Message'),
      '#description' => $this->t('The message shown while the chatbot is processing agent steps. Leave blank to use the default "Contacting agents...".'),
      '#default_value' => $this->configuration['agent_delegation_message'] ?? '',
    ];

    $form['messages']['bot_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Bot name'),
      '#description' => $this->t('The name of the bot.'),
      '#default_value' => $this->configuration['bot_name'],
    ];

    $form['messages']['bot_image'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Bot image'),
      '#description' => $this->t('The image of the bot.'),
      '#default_value' => $this->configuration['bot_image'],
    ];

    $form['messages']['default_username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default User name'),
      '#description' => $this->t('The name of the user, if not fetched from the user or if not logged in.'),
      '#default_value' => $this->configuration['default_username'],
    ];

    $form['messages']['use_username'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use username'),
      '#description' => $this->t('Use the username in the chat messages if logged in.'),
      '#default_value' => $this->configuration['use_username'],
    ];

    $avatar_description = $this->t('The avatar of the user, if not fetched from the user or if not logged in.');
    $form['messages']['default_avatar'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default Avatar'),
      '#description' => $avatar_description,
      '#default_value' => $this->configuration['default_avatar'],
    ];

    if ($this->moduleHandler->moduleExists('token')) {
      $form['messages']['default_avatar']['#description'] = [
        '#type' => 'inline_template',
        '#template' => '{{ description }} {{ token_link }}',
        '#context' => [
          'description' => $avatar_description . ' ' . $this->t('This field supports tokens.'),
          'token_link' => [
            '#theme' => 'token_tree_link',
            '#token_types' => ['current-user'],
          ],
        ],
      ];
    }

    $form['messages']['use_avatar'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use avatar'),
      '#description' => $this->t('Use the avatar in the chat messages if logged in.'),
      '#default_value' => $this->configuration['use_avatar'],
    ];

    $form['styling'] = [
      '#type' => 'details',
      '#title' => $this->t('Styling settings'),
      '#open' => FALSE,
    ];

    // Get the available styles.
    $styles = $this->getStyles();

    $form['styling']['placement'] = [
      '#type' => 'select',
      '#title' => $this->t('Placement'),
      '#description' => $this->t('The placement of the chat window.'),
      '#required' => TRUE,
      '#options' => [
        'toolbar' => $this->t('Toolbar'),
        'bottom-right' => $this->t('Bottom right'),
        'bottom-left' => $this->t('Bottom left'),
      ],
      '#default_value' => $this->configuration['placement'],
    ];

    $form['styling']['style_file'] = [
      '#type' => 'select',
      '#title' => $this->t('Style'),
      '#description' => $this->t('The style of the chat window.'),
      '#options' => $styles,
      '#default_value' => $this->configuration['style_file'],
      '#required' => TRUE,
      // Only show if the placement is not toolbar.
      '#states' => [
        'visible' => [
          ':input[name="settings[styling][placement]"]' => ['!value' => 'toolbar'],
        ],
      ],
    ];

    $form['styling']['width'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Width'),
      '#description' => $this->t('The width of the chat window.'),
      '#default_value' => $this->configuration['width'],
      // Only show if the placement is not toolbar.
      '#states' => [
        'visible' => [
          ':input[name="settings[styling][placement]"]' => ['!value' => 'toolbar'],
        ],
      ],
    ];

    $form['styling']['height'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Height'),
      '#description' => $this->t('The height of the chat window.'),
      '#default_value' => $this->configuration['height'],
      // Only show if the placement is not toolbar.
      '#states' => [
        'visible' => [
          ':input[name="settings[styling][placement]"]' => ['!value' => 'toolbar'],
        ],
      ],
    ];

    $form['styling']['collapse_minimal'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Collapsed minimal'),
      '#description' => $this->t('Show a minimal toggle button when minimized.'),
      '#default_value' => $this->configuration['collapse_minimal'],
      '#states' => [
        'visible' => [
          ':input[name="settings[styling][placement]"]' => ['!value' => 'toolbar'],
        ],
      ],
    ];
    $form['styling']['show_copy_icon'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Add copy icon'),
      '#description' => $this->t('Adds a copy icon below each text so you can easily copy paste it.'),
      '#default_value' => $this->configuration['show_copy_icon'],
    ];

    $form['styling']['expansion_method'] = [
      '#type' => 'select',
      '#title' => $this->t('Expansion method'),
      '#description' => $this->t('Choose how users can expand the chatbot for improved readability.'),
      '#options' => [
        'none' => $this->t('None'),
        'expand' => $this->t('Expand'),
        'fullscreen' => $this->t('Full screen'),
      ],
      '#default_value' => $this->configuration['expansion_method'] ?? 'expand',
      '#states' => [
        'visible' => [
          ':input[name="settings[styling][placement]"]' => ['value' => 'toolbar'],
        ],
      ],
    ];

    $form['advanced'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced settings'),
      '#open' => FALSE,
    ];

    $form['advanced']['stream'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Stream'),
      '#description' => $this->t('Stream the messages in real-time. Note that this will be disabled for agents based assistants.'),
      '#default_value' => $this->configuration['stream'],
    ];

    $form['advanced']['toggle_state'] = [
      '#type' => 'select',
      '#title' => $this->t('Toggle state'),
      '#description' => $this->t('The state of the toggle button.'),
      '#options' => [
        'remember' => $this->t('Remember'),
        'open' => $this->t('Opened'),
        'close' => $this->t('Closed'),
      ],
      '#default_value' => $this->configuration['toggle_state'],
    ];

    $form['advanced']['disable_csrf'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Disable CSRF Protection'),
      '#description' => $this->t('Disable CSRF token validation for API requests. <strong>Warning:</strong> This reduces security and should only be used in trusted environments.'),
      '#default_value' => $this->configuration['disable_csrf'],
    ];

    return $form;
  }

  /**
   * Ajax callback to update the form.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The updated form.
   */
  public function updateForm(array $form, FormStateInterface $form_state) {
    // Get the triggering element.
    $trigger = $form_state->getTriggeringElement();

    $array_parents = array_slice($trigger['#array_parents'], 0, -1);

    // Get and return the relevant form element.
    $element = NestedArray::getValue($form, $array_parents);

    // Rebuild the form.
    $form_state->setRebuild();

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['bot_name'] = $form_state->getValue('messages')['bot_name'];
    $this->configuration['bot_image'] = $form_state->getValue('messages')['bot_image'];
    $this->configuration['use_username'] = $form_state->getValue('messages')['use_username'];
    $this->configuration['default_username'] = $form_state->getValue('messages')['default_username'];
    $this->configuration['use_avatar'] = $form_state->getValue('messages')['use_avatar'];
    $this->configuration['default_avatar'] = $form_state->getValue('messages')['default_avatar'];
    $this->configuration['first_message'] = $form_state->getValue('messages')['first_message'];
    $this->configuration['loading_message'] = $form_state->getValue('messages')['loading_message'] ?? '';
    $this->configuration['agent_delegation_message'] = $form_state->getValue('messages')['agent_delegation_message'] ?? '';
    $this->configuration['style_file'] = $form_state->getValue('styling')['style_file'];
    $this->configuration['width'] = $form_state->getValue('styling')['width'];
    $this->configuration['height'] = $form_state->getValue('styling')['height'];
    $this->configuration['placement'] = $form_state->getValue('styling')['placement'];
    // If the placement is toolbar, we force the toolbar style.
    if ($this->configuration['placement'] === 'toolbar') {
      $this->configuration['style_file'] = 'module:ai_chatbot:toolbar.yml';
      $this->configuration['width'] = '100%';
      $this->configuration['height'] = 'auto';
    }
    $this->configuration['collapse_minimal'] = $form_state->getValue('styling')['collapse_minimal'];
    $this->configuration['show_copy_icon'] = $form_state->getValue('styling')['show_copy_icon'];
    $this->configuration['chat_processor_plugin'] = $form_state->getValue('chat_processor_plugin');
    $this->configuration['disable_csrf'] = $form_state->getValue('advanced')['disable_csrf'];
    $this->configuration['stream'] = $form_state->getValue('advanced')['stream'] ?? FALSE;
    $this->configuration['toggle_state'] = $form_state->getValue('advanced')['toggle_state'];
    $this->configuration['expansion_method'] = $form_state->getValue('styling')['expansion_method'] ?? 'expand';
    // Save plugin configuration.
    $this->configuration['plugin_configuration'] = $form_state->getValue('plugin_configuration') ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $active_theme = $this->themeManager->getActiveTheme()->getName();
    $block = [];

    $block['#theme'] = 'ai_deepchat';
    $block['#attached']['library'][] = 'ai_chatbot/deepchat';

    $user_data = $this->getUserData();

    // Use a local copy to attach runtime user defaults without mutating the
    // persisted block configuration.
    $runtime_settings = $this->configuration;
    $runtime_settings['default_username'] = $user_data['username'];
    $runtime_settings['default_avatar'] = $user_data['avatar'];
    /** @var \Drupal\ai\Plugin\ChatProcessor\ChatProcessorInterface $plugin_instance */
    $plugin_instance = $this->chatProcessorManager->createInstance($this->configuration['chat_processor_plugin'], $this->configuration['plugin_configuration']);
    $block['#settings'] = $runtime_settings;
    $block['#deepchat_settings'] = $this->getDeepChatParameters($this->configuration['style_file'], $user_data);
    $block['#current_theme'] = 'chatbot-' . $active_theme;
    $block['#attached']['drupalSettings']['ai_deepchat']['thread_id'] = $plugin_instance->getThreadId();
    $block['#attached']['drupalSettings']['ai_deepchat']['bot_name'] = $this->configuration['bot_name'];
    $block['#attached']['drupalSettings']['ai_deepchat']['bot_image'] = $this->configuration['bot_image'];
    $block['#attached']['drupalSettings']['ai_deepchat']['default_username'] = $user_data['username'];
    $block['#attached']['drupalSettings']['ai_deepchat']['default_avatar'] = $user_data['avatar'];
    $block['#attached']['drupalSettings']['ai_deepchat']['toggle_state'] = $this->configuration['toggle_state'];
    $block['#attached']['drupalSettings']['ai_deepchat']['width'] = $this->configuration['placement'] === 'toolbar' ? '100%' : $this->configuration['width'];
    $block['#attached']['drupalSettings']['ai_deepchat']['height'] = $this->configuration['placement'] === 'toolbar' ? 'auto' : $this->configuration['height'];
    $block['#attached']['drupalSettings']['ai_deepchat']['first_message'] = $this->configuration['first_message'];
    $block['#attached']['drupalSettings']['ai_deepchat']['placement'] = $this->configuration['placement'];
    $block['#attached']['drupalSettings']['ai_deepchat']['collapse_minimal'] = $this->configuration['collapse_minimal'];
    $block['#attached']['drupalSettings']['ai_deepchat']['show_copy_icon'] = $this->configuration['show_copy_icon'];
    $block['#attached']['drupalSettings']['ai_deepchat']['disable_csrf'] = $this->configuration['disable_csrf'];
    $block['#attached']['drupalSettings']['ai_deepchat']['expansion_method'] = $this->configuration['expansion_method'] ?? 'expand';
    $block['#attached']['drupalSettings']['ai_deepchat']['messages'] = $this->historicalMessages($plugin_instance->getThreadId() ?? '');
    $block['#attached']['drupalSettings']['ai_deepchat']['session_exists'] = $this->requestStack->getCurrentRequest()->getSession()->isStarted();
    $block['#attached']['drupalSettings']['ai_deepchat']['verbose_mode'] = (bool) ($this->configuration['plugin_configuration']['verbose_mode'] ?? FALSE);
    $block['#attached']['drupalSettings']['ai_deepchat']['loading_message'] = $this->configuration['loading_message'] ?? '';
    $block['#attached']['drupalSettings']['ai_deepchat']['agent_delegation_message'] = $this->configuration['agent_delegation_message'] ?? '';
    $block['#cache']['contexts'][] = 'session.exists';
    return $block;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return 0;
  }

  /**
   * Get the context objects actual output.
   *
   * @param string $style
   *   The style to get the parameters for.
   * @param array|null $user_data
   *   Optional user data (username, avatar) to avoid duplicate lookups.
   *
   * @return array
   *   Return the parameters.
   */
  public function getDeepChatParameters(string $style, ?array $user_data = NULL) {
    $deepchat = [];
    // Some basic settings.
    $style_parameters = $this->getStyleParameters($style);
    // Special solution for style.
    $style = $style_parameters['style'] ?? '';
    // Add ; if its not there.
    if ($style && !str_ends_with($style, ';')) {
      $style .= '; ';
    }
    $height = $this->configuration['placement'] === 'toolbar' ? '100%' : $this->configuration['height'];
    $width = $this->configuration['placement'] === 'toolbar' ? 'auto' : $this->configuration['width'];
    $style .= 'height: ' . $height . '; width: ' . $width . ';';

    if ($style_parameters) {
      unset($style_parameters['style']);
    }
    $deepchat['style'] = $style;
    foreach ($style_parameters as $key => $value) {
      if (isset($deepchat[$key])) {
        $deepchat[$key] = array_merge_recursive($deepchat[$key], $value);
      }
      else {
        $deepchat[$key] = $value;
      }
    }

    // Override the avatars.
    $user_data = $user_data ?? $this->getUserData();
    $deepchat['avatars']['ai']['src'] = $this->configuration['bot_image'];
    if (empty($deepchat['avatars']['ai']['src'])) {
      unset($deepchat['avatars']['ai']);
    }
    $deepchat['avatars']['user']['src'] = $user_data['avatar'];
    if (empty($deepchat['avatars']['user']['src'])) {
      unset($deepchat['avatars']['user']);
    }

    if (is_array($deepchat['avatars']) && !count($deepchat['avatars'])) {
      unset($deepchat['avatars']);
    }

    $deepchat['class'] = 'deepchat-element';
    $deepchat['intromessage']['text'] = $this->configuration['first_message'];
    // @todo remove this in 2.0.0, its just for BC.
    if ($this->configuration['placement'] === 'toolbar') {
      $deepchat['names']['ai']['text'] = $this->configuration['bot_name'];
    }

    $deepchat['htmlClassUtilities']['chat-button']['styles']['default']['width'] = '25px';
    $deepchat['htmlClassUtilities']['chat-button']['styles']['default']['height'] = '25px';
    $deepchat['htmlClassUtilities']['chat-button']['styles']['default']['display'] = 'inline';
    $deepchat['htmlClassUtilities']['chat-button']['styles']['default']['float'] = 'none';

    // Enable displayServiceErrorMessages by default so that we can display
    // specific error messages.
    $deepchat['errorMessages'] = [
      'displayServiceErrorMessages' => TRUE,
    ];

    // Let people run hooks to change this.
    $this->moduleHandler->invokeAll('deepchat_settings', [&$deepchat]);

    $deepchat['id'] = 'chat-element';

    // Create the url.
    $url = Url::fromRoute(
      'ai_chatbot.api',
    );

    // Fix the call.
    $deepchat['connect'] = [
      'url' => $url->toString(),
      'method' => 'POST',
      'stream' => $this->isStreamingSupported(),
      'additionalBodyProps' => [
        'stream' => $this->isStreamingSupported(),
        'show_copy_icon' => $this->configuration['show_copy_icon'],
        'disable_csrf' => $this->configuration['disable_csrf'],
        'chat_processor_plugin' => $this->configuration['chat_processor_plugin'],
        'plugin_configuration' => $this->configuration['plugin_configuration'] ?? [],
        'contexts' => [
          'current_route' => $this->currentPath->getPath(),
        ],
      ],
    ];

    // For now unset any speech to text.
    if (isset($deepchat['speechToText'])) {
      unset($deepchat['speechToText']);
    }
    if (isset($deepchat['microphone'])) {
      unset($deepchat['microphone']);
    }

    // Now do JSON encode on all the settings that should have it.
    foreach ($deepchat as $key => $value) {
      if (is_array($value)) {
        $deepchat[$key] = Json::encode($value);
      }
    }

    return $deepchat;
  }

  /**
   * Get styles available.
   *
   * This will scan this modules folder deepchat_styles and also the enabled
   * themes deepchat_styles folder for styles that can be used.
   *
   * @return array
   *   Return an array of styles.
   */
  public function getStyles() {
    $styles = [];
    $module_list = ['ai_chatbot'];
    $this->moduleHandler->alter('ai_chatbot_style_modules', $module_list);

    foreach ($module_list as $module_name) {
      $module_path = $this->moduleHandler->getModule($module_name)->getPath();
      $styles += $this->getStylesFromPath($module_path . '/deepchat_styles', 'module:' . $module_name);
    }

    // Also get the active themes.
    $themes = $this->themeHandler->listInfo();
    foreach ($themes as $theme) {
      $styles += $this->getStylesFromPath($theme->getPath() . '/deepchat_styles', 'theme:' . $theme->getName());
    }

    return $styles;
  }

  /**
   * Helper function to look for styles.
   *
   * @param string $path
   *   The path to look for styles in.
   * @param string $prefix
   *   The prefix to use for the styles.
   *
   * @return array
   *   Return an array of styles.
   */
  protected function getStylesFromPath(string $path, string $prefix = '') {
    $styles = [];

    if (!is_dir($path)) {
      return $styles;
    }
    foreach (scandir($path) as $file) {
      // If its a yaml or yml file.
      if (preg_match('/\.ya?ml$/', $file)) {
        $contents = file_get_contents($path . '/' . $file);
        if ($contents === FALSE) {
          continue;
        }
        $style = Yaml::decode($contents);
        if (isset($style['name']) && isset($style['parameters'])) {
          $key = $prefix ? $prefix . ':' . $file : $file;
          $styles[$key] = $style['name'];
        }
      }
    }
    return $styles;
  }

  /**
   * Get the style YAML files parameters.
   *
   * @param string $old_style
   *   The style to get the parameters for.
   *
   * @return array
   *   Return the parameters.
   */
  public function getStyleParameters(string $old_style): array {
    // If it's cached, get it cached.
    $parts = explode(':', $old_style);
    if (count($parts) == 3) {
      $type = $parts[0];
      $name = $parts[1];
      $style = $parts[2];
    }
    else {
      // Fallback to the old style.
      $type = 'module';
      $name = 'ai_chatbot';
      $style = $old_style;
    }
    $key = $type . ':name:' . $name . ':style:' . $style;
    $data = $this->cache->get($key);
    if ($data) {
      return $data->data;
    }

    if ($type === 'theme') {
      $type_path = $this->themeHandler->getTheme($name)->getPath();
    }
    else {
      $type_path = $this->moduleHandler->getModule($name)->getPath();
    }
    $path = $type_path . '/deepchat_styles/' . $style;
    $contents = file_get_contents($path);
    if ($contents === FALSE) {
      return [];
    }
    $style = Yaml::decode($contents);
    $this->cache->set($key, $style['parameters'], CacheBackendInterface::CACHE_PERMANENT, [$type . ':type:' . $name . ':style']);
    return $style['parameters'];
  }

  /**
   * Returns the display username and resolved avatar URL for the current user.
   *
   * @return array{username: string|null, avatar: string|null}
   *   Return the avatar and the account.
   */
  public function getUserData(): array {
    $user = $this->currentUser->getAccount();
    $isAuthenticated = $user->isAuthenticated();

    $username = $this->configuration['default_username'];
    if ($this->configuration['use_username'] && $isAuthenticated) {
      $username = $user->getDisplayName();
    }

    $default_avatar = $this->configuration['default_avatar'] ?? '';
    /** @var \Drupal\user\UserInterface|null $userEntity */
    $userEntity = ($isAuthenticated && ($this->configuration['use_avatar'] || $default_avatar !== ''))
      ? $this->entityTypeManager->getStorage('user')->load($user->id())
      : NULL;

    $token_context = [];
    if ($userEntity) {
      $token_context = [
        'user' => $userEntity,
        'current-user' => $userEntity,
      ];
    }

    if ($userEntity && $this->entityTypeManager->hasDefinition('profile')) {
      $avatar_parts = explode(':', $default_avatar);
      $profileType = (count($avatar_parts) > 2 && str_starts_with($avatar_parts[0], '[current-user'))
        ? $avatar_parts[1]
        : NULL;
      $profile = NULL;
      if ($profileType) {
        // Pattern of the
        // token: [current-user:admin_profile:field_profile_image].
        /** @var \Drupal\profile\Entity\ProfileInterface[] $loaded */
        $loaded = $this->entityTypeManager
          ->getStorage('profile')
          ->loadByProperties(['uid' => $user->id(), 'type' => $profileType]);
        $profile = !empty($loaded) ? reset($loaded) : NULL;
      }
      $token_context += [
        'profile' => $profile,
      ];
    }

    $avatar = '';
    if ($default_avatar) {
      $avatar = $this->token->replace($default_avatar, $token_context, ['clear' => TRUE]);
      // Image field tokens render as a full <img> tag; extract the src URL.
      if ($avatar && str_contains($avatar, '<img')) {
        preg_match('/src=["\']([^"\']+)["\']/', $avatar, $matches);
        $avatar = $matches[1] ?? '';
      }
    }

    // Fall back to the user picture
    // field when token resolution yielded nothing.
    if ($userEntity && empty($avatar) && !empty($userEntity->user_picture->entity)) {
      $avatar = $this->fileUrlGenerator->generateAbsoluteString($userEntity->user_picture->entity->getFileUri());
    }

    // Normalize any remaining stream wrapper URIs (e.g. from token output).
    if ($avatar && (str_starts_with($avatar, 'public://') || str_starts_with($avatar, 'private://'))) {
      $avatar = $this->fileUrlGenerator->generateAbsoluteString($avatar);
    }

    return [
      'username' => $username,
      'avatar' => $avatar,
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    $plugin_id = $this->configuration['chat_processor_plugin'] ?? '';
    if (!$plugin_id || !$this->chatProcessorManager->hasDefinition($plugin_id)) {
      // An unconfigured block is useless; hide it.
      return AccessResult::forbidden('No chat processor plugin configured.');
    }
    /** @var \Drupal\ai\Plugin\ChatProcessor\ChatProcessorInterface $processor */
    $processor = $this->chatProcessorManager->createInstance($plugin_id, $this->configuration['plugin_configuration'] ?? []);
    return $processor->access($account);
  }

  /**
   * Get historical messages.
   *
   * @param string $thread_id
   *   The id of chat thread.
   *
   * @return array<mixed>
   *   Return the historical messages.
   */
  public function historicalMessages($thread_id = ''): array {
    $plugin_id = $this->configuration['chat_processor_plugin'] ?? '';
    if (!$plugin_id || !$this->chatProcessorManager->hasDefinition($plugin_id)) {
      return [];
    }
    $plugin_configuration = $this->configuration['plugin_configuration'] ?? [];
    /** @var \Drupal\ai\Plugin\ChatProcessor\ChatProcessorInterface $processor */
    $processor = $this->chatProcessorManager->createInstance($plugin_id, $plugin_configuration);
    // The plugin resolves its own thread id (see getThreadId()).
    try {
      $processor->setThreadId($thread_id);
      $history = $processor->getMessageHistory();
    }
    catch (\Exception $e) {
      // A misconfigured processor (e.g. deleted assistant) must not break
      // the page render.
      $this->logger->warning('Could not load the chat history: @message', ['@message' => $e->getMessage()]);
      return [];
    }

    $converter = $this->commonMarkConverterFactory->fromOptions(['html_input' => 'escape']);
    $messages = [];
    foreach ($history as $message) {
      // Only show messages newer than one day.
      if (isset($message['timestamp']) && $message['timestamp'] > strtotime('-1 day')) {
        $html = $converter->convert($message['message'])->getContent();
        $messages[] = [
          'role' => $message['role'],
          // The stored messages contain user input and LLM output, so they
          // get the same sanitization as the live response paths.
          'html' => Xss::filter($html, DeepChatApi::ALLOWED_TAGS),
        ];
      }
    }
    return $messages;
  }

  /**
   * Function to check if streaming actually works.
   *
   * @return bool
   *   Return TRUE if streaming is supported, FALSE otherwise.
   */
  public function isStreamingSupported() {
    // Otherwise return block settings.
    return $this->configuration['stream'] ?? FALSE;
  }

  /**
   * Ajax callback to update the plugin configuration form.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The updated plugin configuration form.
   */
  public function updatePluginConfiguration(array $form, FormStateInterface $form_state) {
    $this->configuration['chat_processor_plugin'] = $form_state->getUserInput()['settings']['chat_processor_plugin'] ?? $this->configuration['chat_processor_plugin'];

    // Rebuild the form with the new AI assistant.
    $form_state->setRebuild();

    return $form['settings']['plugin_configuration'];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    return Cache::mergeTags(parent::getCacheTags(), [
      'block:' . $this->getPluginId(),
    ]);
  }

}
