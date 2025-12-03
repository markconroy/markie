<?php

namespace Drupal\highlight_js\Form;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\editor\Ajax\EditorDialogSave;
use Drupal\highlight_js\HighlightJsPluginManager;
use Drupal\user\Entity\Role;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Ckeditor dialog form to insert webform submission results in text.
 */
class HighlightJsDialogForm extends FormBase {

  /**
   * The highlight js plugin manager.
   *
   * @var \Drupal\highlight_js\HighlightJsPluginManager
   */
  protected $highlightJsPluginManager;

  /**
   * The ajax wrapper id to use for re-rendering the form.
   *
   * @var string
   */
  protected $ajaxWrapper = 'highlight-js-dialog-form-wrapper';

  /**
   * The form constructor.
   *
   * @param \Drupal\highlight_js\HighlightJsPluginManager $highlight_js_plugin_manager
   *   The highlight js plugin manager.
   */
  public function __construct(HighlightJsPluginManager $highlight_js_plugin_manager) {
    $this->highlightJsPluginManager = $highlight_js_plugin_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.ckedito5_highlight_js')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'highlight_js_dialog_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?string $uuid = NULL) {

    $request = $this->getRequest();

    $config = $form_state->getUserInput()['config'] ?? [];

    $highlight_js_config = $this->config('highlight_js.settings');
    $languages = $highlight_js_config->get('languages') ?: ['c', 'css', 'java', 'javascript', 'markup', 'php'];

    $highlight_js_available_languages = highlight_js_available_languages();
    foreach ($languages as $language) {
      if (isset($highlight_js_available_languages[$language])) {
        $available_languages[$language] = $highlight_js_available_languages[$language];
      }
    }

    if (!$config) {
      $plugin_config = $request->get('plugin_config');
      $plugin_config = !empty($plugin_config) ? Xss::filter($plugin_config) : '';
      $plugin_id = $request->get('plugin_id');
      if ($plugin_id && $plugin_config) {
        $config = [
          'plugin_id' => $plugin_id,
          'plugin_config' => Json::decode($plugin_config),
        ];
      }
    }

    if ($uuid) {
      $form['uuid'] = [
        '#type' => 'value',
        '#value' => $uuid,
      ];
    }
    $definitions = $this->highlightJsPluginManager->getDefinitions();
    if (!$definitions) {
      $form['warning'] = [
        '#type' => 'markup',
        '#markup' => $this->t('No highlight js plugins were defined. Enable the examples module to see some examples.'),
      ];
      return $form;
    }
    $plugin_id = $config['plugin_id'] ?? 'language_select';
    $language = $config['plugin_config']['language'] ?? NULL;
    $role_copy_access = $config['plugin_config']['role_copy_access'] ?? [];
    $role_based_copy = $config['plugin_config']['role_based_copy'] ? TRUE : FALSE;

    $roles = Role::loadMultiple();
    $options = [];
    foreach ($roles as $rid => $role) {
      $options[$rid] = $role->label();
    }

    $form['config'] = [
      '#type' => 'container',
      '#tree' => TRUE,
      '#attributes' => [
        'id' => $this->ajaxWrapper,
      ],
      'plugin_id' => [
        '#type' => 'select',
        '#title' => $this->t('Highlight js'),
        '#empty_option' => $this->t('- Select a type -'),
        '#default_value' => $plugin_id,
        '#options' => array_map(function ($definition) {
          return $definition['label'];
        }, $definitions),
        '#required' => TRUE,
        '#ajax' => [
          'callback' => [$this, 'updateFormElement'],
          'event' => 'change',
          'wrapper' => $this->ajaxWrapper,
        ],
        '#access' => FALSE,
      ],
      'language' => [
        '#type' => 'select',
        '#title' => $this->t('Choose a language'),
        '#empty_option' => $this->t('- Select a type -'),
        '#default_value' => $language,
        '#options' => $available_languages,
        '#required' => TRUE,
        '#ajax' => [
          'callback' => [$this, 'updateFormElement'],
          'event' => 'change',
          'wrapper' => $this->ajaxWrapper,
        ],
      ],
    ];

    $copy_enable = $highlight_js_config->get('copy_enable');
    if ($copy_enable) {
      $form['config']['role_based_copy'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Role Based Copy Button'),
        '#default_value' => $role_based_copy,
        '#description' => $this->t('Enable User Role-Based Copy Button Accessibility per code block.'),
      ];
      $form['config']['role_copy_access'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('User Roles'),
        '#options' => $options,
        '#default_value' => $role_copy_access,
        '#description' => $this->t('The user roles authorized to access the copy button.'),
        '#states' => [
          'visible' => [
            ':input[name="config[role_based_copy]"]' => ['checked' => TRUE],
          ],
        ],
      ];
    }

    if ($plugin_id) {

      /** @var \Drupal\highlight_js\HighlightJsInterface $instance */
      try {
        $instance = $this->highlightJsPluginManager->createInstance($plugin_id, $config['plugin_config'] ?? []);
        $subform = $form['config']['plugin_config'] ?? [];
        $subform_state = SubformState::createForSubform($subform, $form, $form_state);
        $form['config']['plugin_config'] = $instance->buildConfigurationForm([], $subform_state);
        $form['config']['plugin_config']['#tree'] = TRUE;
      }
      catch (\Exception $exception) {
        $form['message'] = [
          '#type' => 'status_messages',
        ];
      }
    }

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Submit'),
        '#ajax' => [
          'callback' => [$this, 'ajaxSubmitForm'],
          'wrapper' => $this->ajaxWrapper,
        ],
      ],
    ];

    return $form;
  }

  /**
   * Update the form after selecting a plugin type.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The form element for webform elements.
   */
  public function updateFormElement(array $form, FormStateInterface $form_state): array {
    return $form['config'];
  }

  /**
   * Ajax submit callback to insert or replace the html in ckeditor.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse|array
   *   Ajax response for injecting html in ckeditor.
   */
  public static function ajaxSubmitForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getErrors()) {
      return $form['config'];
    }
    $config = $form_state->getValue('config');

    $response = new AjaxResponse();

    $config['plugin_config']['language'] = $config['language'];
    $config['plugin_config']['role_copy_access'] = $config['role_copy_access'];
    $config['plugin_config']['role_based_copy'] = $config['role_based_copy'] ? TRUE : FALSE;

    $response->addCommand(new EditorDialogSave([
      'attributes' => [
        'data-plugin-id' => $config['plugin_id'],
        'data-plugin-config' => Json::encode($config['plugin_config']),
      ],
    ]));

    $response->addCommand(new CloseModalDialogCommand());
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\highlight_js\HighlightJsInterface $instance */
    $plugin_id = $form_state->getValue(['config', 'plugin_id']);
    if ($plugin_id) {
      try {
        $instance = $this->highlightJsPluginManager->createInstance($plugin_id, $form_state->getValue([
          'config',
          'plugin_config',
        ]) ?? []);
        $subform = $form['config']['plugin_config'] ?? [];
        $subform_state = SubformState::createForSubform($subform, $form, $form_state);
        $instance->validateConfigurationForm($subform, $subform_state);
        $config = $form_state->getValue('config');
        $form_state->setValue('config', $config);
      }
      catch (\Exception $exception) {
        $form_state->setValue('config', []);
      }
    }
    else {
      $form_state->setValue('config', []);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Required but not used.
  }

}
