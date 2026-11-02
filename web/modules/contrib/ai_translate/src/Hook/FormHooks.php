<?php

declare(strict_types=1);

namespace Drupal\ai_translate\Hook;

use Drupal\ai\AiProviderPluginManager;
use Drupal\ai_translate\ConfigurableFieldTextExtractorInterface;
use Drupal\ai_translate\Controller\AiTranslateInterfaceController;
use Drupal\ai_translate\FieldTextExtractorPluginManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\Element;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Form hook implementations for ai_translate.
 */
class FormHooks {

  use StringTranslationTrait;

  public function __construct(
    #[Autowire(service: 'plugin.manager.text_extractor')]
    protected readonly FieldTextExtractorPluginManagerInterface $textExtractorManager,
    protected readonly AccountInterface $currentUser,
    #[Autowire(service: 'ai.provider')]
    protected readonly AiProviderPluginManager $aiProvider,
    protected readonly RequestStack $requestStack,
  ) {}

  /**
   * Implements hook_form_BASE_FORM_ID_alter() for field_config_form.
   */
  #[Hook('form_field_config_form_alter')]
  public function fieldConfigFormAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    $fieldConfig = $form_state->getFormObject()->getEntity();
    $fieldType = $fieldConfig->getType();
    $subforms = [];
    foreach ($this->textExtractorManager->getDefinitions() as $pluginId => $definition) {
      if (!in_array($fieldType, $definition['field_types'])) {
        continue;
      }
      /** @var \Drupal\ai_translate\ConfigurableFieldTextExtractorInterface $plugin */
      $plugin = $this->textExtractorManager->createInstance($pluginId);
      if (!$plugin instanceof ConfigurableFieldTextExtractorInterface) {
        continue;
      }
      $subform = $plugin->fieldSettingsForm($fieldConfig, $form_state, $form);
      if (!$subform) {
        continue;
      }
      $subforms[] = $subform;
    }
    if (!empty($subforms)) {
      // Only create the fieldset if there is at least one plugin form.
      $wrapper = [];
      if (empty($form['third_party_settings']['ai_translate'])) {
        $wrapper = [
          '#type' => 'fieldset',
          '#title' => $this->t('AI Translate'),
          '#collapsible' => TRUE,
          '#collapsed' => FALSE,
        ];
      }
      // Add all field setting subform elements as a flattened array to the
      // 'ai_translate' third party settings form.
      $form['third_party_settings']['ai_translate'] = array_reduce($subforms, 'array_merge', $wrapper);
    }
  }

  /**
   * Implements hook_form_BASE_FORM_ID_alter() for locale_translate_edit_form.
   */
  #[Hook('form_locale_translate_edit_form_alter')]
  public function localeTranslateEditFormAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    // Bail if no permission.
    if (!$this->currentUser->hasPermission('create ai interface translation')) {
      return;
    }

    // Bail if no translation model set.
    if (!_ai_translate_check_default_provider_and_model()) {
      return;
    }

    if (!empty($form['strings']['#header']) && count($form['strings']['#header']) === 2) {
      $default_model = $this->aiProvider->getDefaultProviderForOperationType('translate_text');
      $form['strings']['#header'][] = $this->t('Translate with @model', [
        '@model' => $default_model['model_id'] ?? 'Default Model',
      ]);

      // Get the current language code for translations.
      // This value is set in TranslateEditForm::buildForm.
      $langcode_for_translation = $form['langcode']['#value'] ?? NULL;
      if (!$langcode_for_translation) {
        return;
      }

      foreach (Element::children($form['strings']) as $key => $string_row_id) {

        // Validate that the row is structured as expected.
        if (
          !isset($form['strings'][$string_row_id]['original']) ||
          !isset($form['strings'][$string_row_id]['translations']) ||
          !is_array($form['strings'][$string_row_id]['translations'])
        ) {
          continue;
        }

        // Add a wrapper around the translations area for AJAX replacement.
        $translation_wrapper_id = 'ai-translate-translations-wrapper-' . $string_row_id;
        $form['strings'][$string_row_id]['translations']['#prefix'] = '<div id="' . $translation_wrapper_id . '">';
        $form['strings'][$string_row_id]['translations']['#suffix'] = '</div>';

        // Prepare AJAX callback.
        $ajax_options = [
          'callback' => [AiTranslateInterfaceController::class, 'translateInterface'],
          'wrapper' => $translation_wrapper_id,
          'event' => 'click',
          'progress' => [
            'type' => 'throbber',
            'message' => $this->t('Translating...'),
          ],
          'options' => [
            // Merge to maintain any existing pager or filter settings. Most
            // are stored in session but some are query string.
            // @see \Drupal\locale\Form\TranslateEditForm.
            'query' => array_merge($this->requestStack->getCurrentRequest()->query->all(), [
              'string_row_id' => $string_row_id,
              'string_row_key' => $key,
              'langcode' => $langcode_for_translation,
            ]),
          ],
        ];

        // Add the AI Translate button to a new cell in the row.
        $form['strings'][$string_row_id]['ai_translate_action'] = [
          '#type' => 'button',
          '#value' => $this->t('AI Translate'),
          '#name' => 'ai_translate_button_' . $string_row_id,
          '#ajax' => $ajax_options,
          '#attributes' => [
            'class' => [
              'button--small',
            ],
          ],
        ];
      }
    }
  }

}
