<?php

namespace Drupal\ai_ckeditor\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\ai_ckeditor\PluginManager\AiCKEditorPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for displaying dialog options in CKEditor.
 */
class AiCKEditorDialogForm extends FormBase {

  /**
   * The AI CKEditor plugin manager.
   *
   * @var \Drupal\ai_ckeditor\PluginManager\AiCKEditorPluginManager
   */
  protected $aiCKEditorPluginManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The ajax wrapper id to use for re-rendering the form.
   *
   * @var string
   */
  protected $ajaxWrapper = 'ai-ckeditor-dialog-form-wrapper';

  /**
   * The form constructor.
   *
   * @param \Drupal\ai_ckeditor\PluginManager\AiCKEditorPluginManager $ai_ckeditor_plugin_manager
   *   The AI CKEditor plugin manager.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   */
  public function __construct(AiCKEditorPluginManager $ai_ckeditor_plugin_manager, AccountProxyInterface $current_user) {
    $this->aiCKEditorPluginManager = $ai_ckeditor_plugin_manager;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.ai_ckeditor'),
      $container->get('current_user'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ckeditor5_ai_ckeditor_dialog_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $request = $this->getRequest();
    $query_parameters = $request->query->all();
    // Since we can't load the editor instance, we can't load the config in the
    // way the CKEditor5PluginManager does. We have to rely on the query string
    // and load the config from the configuration.
    if (!isset($query_parameters['editor_id'])) {
      throw new \InvalidArgumentException('We cannot determine that this is a CKEditor field.');
    }

    // Check that the settings exists.
    $editor_config = $this->configFactory()->get('editor.editor.' . $query_parameters['editor_id']);
    if (empty($editor_config->get('settings'))) {
      throw new \InvalidArgumentException('The editor configuration is empty.');
    }

    // Since this is custom form outside the CKEditor5 context, we have to
    // check that the user has permissions to this specific text format.
    if (!$this->currentUser()->hasPermission('use text format ' . $editor_config->get('format'))) {
      throw new \InvalidArgumentException('The user does not have permission to use this text format.');
    }

    // This parameter is passed when clicking on a drop down in CKEditor.
    if (!isset($query_parameters['plugin_id'])) {
      throw new \InvalidArgumentException('No action was selected.');
    }

    $instance_config = $editor_config->get('settings')['plugins']['ai_ckeditor_ai'] ?? [];

    if (empty($instance_config['plugins'])) {
      $form['warning'] = [
        '#type' => 'markup',
        '#markup' => $this->t('No AI CKEditor plugins were detected.'),
      ];
      return $form;
    }

    if (!empty($query_parameters['plugin_id'])) {
      /** @var \Drupal\ai_ckeditor\PluginInterfaces\AiCKEditorPluginInterface $instance */
      try {
        $plugin_id = $query_parameters['plugin_id'];
        $config_id = $query_parameters['plugin_id'];

        // Check if its a multi instance plugin.
        if (strpos($query_parameters['plugin_id'], '__') !== FALSE) {
          $parts = explode('__', $query_parameters['plugin_id']);
          $plugin_id = $parts[0];
          $config_id = $parts[1];
        }
        $instance = $this->aiCKEditorPluginManager->createInstance($plugin_id, $instance_config['plugins'][$config_id] ?? []);
        $subform = $form['plugin_config'] ?? [];
        $subform_state = SubformState::createForSubform($subform, $form, $form_state);

        if (isset($query_parameters['selected_text'])) {
          $subform_state->setStorage(['selected_text' => $query_parameters['selected_text']]);
        }

        $form['plugin_config'] = $instance->buildCkEditorModalForm([], $subform_state, [
          'config_id' => $config_id,
          'editor_id' => $query_parameters['editor_id'],
          'plugin_id' => $plugin_id,
        ]);
        $form['plugin_config']['#tree'] = TRUE;
        $form['editor_id'] = [
          '#type' => 'hidden',
          '#value' => $query_parameters['editor_id'],
        ];
      }
      catch (\Exception $exception) {
        $form['message'] = [
          '#type' => 'status_messages',
        ];
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

  }

}
