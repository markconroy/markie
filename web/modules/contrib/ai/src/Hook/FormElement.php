<?php

namespace Drupal\ai\Hook;

use Drupal\Component\Utility\Html;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\WidgetInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Contain hooks for form elements.
 */
class FormElement {

  use StringTranslationTrait;

  /**
   * Implements hook_element_info_alter().
   */
  #[Hook('element_info_alter')]
  public function elementInfoAlter(array &$info) {
    if (isset($info['textarea'])) {
      $info['textarea']['#process'][] = [static::class, 'addMdxEditor'];
    }
  }

  /**
   * Adds the MDXEditor library.
   */
  public static function addMdxEditor($element): array {
    if (!empty($element['#attributes']) && array_key_exists('data-mdxeditor', $element['#attributes'])) {
      $element['#attached']['library'][] = 'ai/mdx_editor';
    }
    return $element;
  }

  /**
   * Implements hook_field_widget_third_party_settings_form().
   */
  #[Hook('field_widget_third_party_settings_form')]
  public function fieldWidgetThirdPartySettingsForm(WidgetInterface $plugin, FieldDefinitionInterface $field_definition, $form_mode, array $form, FormStateInterface $form_state) {
    $element = [];
    if ($plugin->getPluginId() == 'string_textarea') {
      $element['use_mdx_editor'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Use MDX editor'),
        '#default_value' => $plugin->getThirdPartySetting('ai', 'use_mdx_editor', FALSE),
        '#description' => $this->t('If enabled, MDX editor will be used instead.'),
      ];
    }
    return $element;
  }

  /**
   * Implements hook_field_widget_settings_summary_alter().
   */
  #[Hook('field_widget_settings_summary_alter')]
  public function fieldWidgetSettingsSummaryAlter(array &$summary, array $context) {
    if ($context['widget']->getPluginId() == 'string_textarea') {
      if ($context['widget']->getThirdPartySetting('ai', 'use_mdx_editor', FALSE)) {
        $summary[] = $this->t('Using MDX editor.');
      }
    }
  }

  /**
   * Implements hook_field_widget_single_element_form_alter().
   */
  #[Hook('field_widget_single_element_form_alter')]
  public function fieldWidgetSingleElementFormAlter(array &$element, FormStateInterface $form_state, array $context) {
    $use_mdx_editor = $context['widget']->getThirdPartySetting('ai', 'use_mdx_editor', FALSE);
    if ($use_mdx_editor) {
      $editor_id = Html::getUniqueId($context['items']->getFieldDefinition()->getName() . '_' . $context['delta'] . '_editor');
      $element['value']['#attributes']['data-mdxeditor'] = $editor_id;
    }
  }

}
