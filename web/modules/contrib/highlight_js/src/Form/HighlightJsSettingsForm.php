<?php

namespace Drupal\highlight_js\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\Role;

/**
 * Defines the HighlightJsSettingsForm class.
 *
 * @package Drupal\highlight_js\Form
 */
class HighlightJsSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'highlight_js_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getEditableConfigNames() {
    return ['highlight_js.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('highlight_js.settings');

    $form['language_config'] = [
      '#type' => 'details',
      '#title' => $this->t('Language Settings'),
      '#collapsible' => TRUE,
      '#open' => FALSE,
      '#description' => $this->t('Configure the available languages on the editor.'),
    ];

    $form['language_config']['languages'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Select available languages'),
      '#options' => highlight_js_available_languages(),
      '#default_value' => $config->get('languages') ?? [
        'c',
        'css',
        'java',
        'javascript',
        'markup',
        'php',
      ],
      '#required' => TRUE,
    ];

    $form['copy_btn_config'] = [
      '#type' => 'details',
      '#title' => $this->t('Copy Button Settings'),
      '#collapsible' => TRUE,
      '#open' => FALSE,
      '#description' => $this->t('Configure the copy button.'),
    ];

    $form['copy_btn_config']['copy_enable'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Copy Button'),
      '#default_value' => $config->get('copy_enable'),
      '#description' => $this->t('Activate this checkbox to enable copy functionality, allowing users to easily copy content with a click.'),
    ];

    $form['copy_btn_config']['copy_bg_transparent'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Copy Button Transparent Background'),
      '#default_value' => $config->get('copy_bg_transparent'),
      '#description' => $this->t('Enable this option for a transparent button background.'),
      '#states' => [
        'visible' => [
          ':input[name="copy_enable"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['copy_btn_config']['copy_bg_color'] = [
      '#type' => 'color',
      '#title' => $this->t('Copy Button Background Color'),
      '#default_value' => $config->get('copy_bg_color') ?? '#4243b1',
      '#description' => $this->t('Choose a background color for the "Copy" button.'),
      '#states' => [
        'visible' => [
          ':input[name="copy_enable"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['copy_btn_config']['copy_txt_color'] = [
      '#type' => 'color',
      '#title' => $this->t('Copy Button Text Color'),
      '#default_value' => $config->get('copy_txt_color') ?? '#ffffff',
      '#description' => $this->t('Choose a background color for the "Copy" button text.'),
      '#states' => [
        'visible' => [
          ':input[name="copy_enable"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['copy_btn_config']['copy_btn_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Copy Button Text'),
      '#default_value' => $config->get('copy_btn_text') ?? '',
      '#textfield' => $this->t('Enter the "Copy" button text.'),
      '#states' => [
        'visible' => [
          ':input[name="copy_enable"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['copy_btn_config']['success_bg_transparent'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Copy Success Transparent Background'),
      '#default_value' => $config->get('success_bg_transparent'),
      '#description' => $this->t('Enable this option for a transparent success message background.'),
      '#states' => [
        'visible' => [
          ':input[name="copy_enable"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['copy_btn_config']['success_bg_color'] = [
      '#type' => 'color',
      '#title' => $this->t('Copy Success Background Color'),
      '#default_value' => $config->get('success_bg_color') ?? '#4243b1',
      '#description' => $this->t('Choose a background color for the success message.'),
      '#states' => [
        'visible' => [
          ':input[name="copy_enable"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['copy_btn_config']['success_txt_color'] = [
      '#type' => 'color',
      '#title' => $this->t('Copy Success Text Color'),
      '#default_value' => $config->get('success_txt_color') ?? '#ffffff',
      '#description' => $this->t('Enter the text for copied to clipboard message font color.'),
      '#states' => [
        'visible' => [
          ':input[name="copy_enable"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['copy_btn_config']['copy_success_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Copy Success Text'),
      '#default_value' => $config->get('copy_success_text') ?? '',
      '#description' => $this->t('Enter the text for copied to clipboard message.'),
      '#states' => [
        'visible' => [
          ':input[name="copy_enable"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $roles = Role::loadMultiple();
    $options = [];
    foreach ($roles as $rid => $role) {
      $options[$rid] = $role->label();
    }

    $form['copy_btn_config']['role_copy_access'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('User Roles'),
      '#options' => $options,
      '#default_value' => $config->get('role_copy_access') ?? [],
      '#description' => $this->t('User Role-Based Copy Button Accessibility.'),
      '#states' => [
        'visible' => [
          ':input[name="copy_enable"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['theme'] = [
      '#type' => 'select',
      '#title' => $this->t('Default theme'),
      '#default_value' => $config->get('theme') ?? 'github',
      '#options' => highlight_js_available_themes(),
      '#description' => $this->t("Select the default theme"),
      '#required' => TRUE,
    ];

    $form['help'] = [
      '#type' => 'markup',
      '#markup' => '<small><em>Note: Flush all caches afer the configuration changes to take effect.</em></small>',
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('highlight_js.settings');
    $values = $form_state->getValues();
    $config->set('copy_enable', $values['copy_enable']);

    $config->set('copy_bg_transparent', $values['copy_bg_transparent']);
    $config->set('copy_bg_color', $values['copy_bg_color']);
    $config->set('copy_txt_color', $values['copy_txt_color']);
    $config->set('copy_btn_text', $values['copy_btn_text']);

    $config->set('success_bg_transparent', $values['success_bg_transparent']);
    $config->set('success_bg_color', $values['success_bg_color']);
    $config->set('success_txt_color', $values['success_txt_color']);
    $config->set('copy_success_text', $values['copy_success_text']);

    $config->set('role_copy_access', $values['role_copy_access']);
    $config->set('languages', $values['languages']);
    $config->set('theme', $values['theme']);
    $config->save();

    parent::submitForm($form, $form_state);
  }

}
