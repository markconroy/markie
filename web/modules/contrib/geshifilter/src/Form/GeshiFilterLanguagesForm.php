<?php

namespace Drupal\geshifilter\Form;

use Drupal\Core\Form\ConfigFormBase;

use Drupal\Core\Form\FormStateInterface;

use Drupal\geshifilter\GeshiFilterCss;

use Drupal\Core\Cache\Cache;

use Drupal\geshifilter\GeshiFilter;

/**
 * Form used to set enable/disabled for languages.
 */
class GeshiFilterLanguagesForm extends ConfigFormBase {

  /**
   * List of modules to enable.
   *
   * @var array
   */
  public static $modules = ['libraries', 'geshifilter'];

  /**
   * Object with configuration.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'geshifilter_languages';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'geshifilter.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $view = NULL) {
    $config = $this->config('geshifilter.settings');
    // Check if GeSHi library is available.
    $geshi_library = GeshiFilter::loadGeshi();
    if (!$geshi_library['loaded']) {
      drupal_set_message($geshi_library['error message'], 'error');
      return [];
    }
    $add_checkbox = TRUE;
    $add_tag_option = (!$config->get('use_format_specific_options'));
    $form['language_settings'] = $this->perLanguageSettings($view, $add_checkbox, $add_tag_option);
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('geshifilter.settings');

    // Language tags should differ from each other.
    $languages = GeshiFilter::getAvailableLanguages();

    $values = $form_state->getValue('language');
    $config = $this->config('geshifilter.settings');
    $values = array_merge($config->get('language'), $values);
    foreach ($languages as $language1 => $language_data1) {

      if ($values[$language1]['enabled'] == FALSE) {
        continue;
      }

      $tags1 = GeshiFilter::tagSplit($values[$language1]['tags']);

      // Check that other languages do not use these tags.
      foreach ($languages as $language2 => $language_data2) {
        // Check these tags against the tags of other enabled languages.
        if ($language1 == $language2) {
          continue;
        }
        // Get tags for $language2.
        $tags2 = GeshiFilter::tagSplit($values[$language2]['tags']);

        // Get generic tags.
        $generics = GeshiFilter::tagSplit($config->get('tags'));
        $tags2 = array_merge($tags2, $generics);

        // And now we can check tags1 against tags2.
        foreach ($tags1 as $tag1) {
          foreach ($tags2 as $tag2) {
            if ($tag1 == $tag2) {
              $name = "language[{$language2}][tags]";
              $form_state->setErrorByName($name, $this->t('The language tags should differ between languages and from the generic tags.'));
            }
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('geshifilter.settings');
    $languages = $form_state->getValue('language');
    foreach ($languages as $key => $value) {
      if ($value["enabled"] == FALSE) {
        // Remove all disabled languages from config.
        $config->clear("language.{$key}.enabled");
      }
      else {
        // Set only the enabled languages.
        $config->set("language.{$key}.enabled", TRUE);
      }
      if ($value["tags"] == '') {
        // Remove all languages without tags from config.
        $config->clear("language.{$key}.tags");
      }
      else {
        // Set only languages with tags.
        $config->set("language.{$key}.tags", $value["tags"]);
      }
    }
    $config->save();
    // Regenerate language_css.
    if ($config->get('css_mode', GeshiFilter::CSS_INLINE) == GeshiFilter::CSS_CLASSES_AUTOMATIC) {
      GeshiFilterCss::generateLanguagesCssFile();
    }
    Cache::invalidateTags(['geshifilter']);
  }

  /**
   * Function for generating a form table for per language settings.
   *
   * @param string $view
   *   - enabled Only show the enabled languages.
   *   - disabled Only show the disabled languages.
   *   - all Show all languages.
   * @param bool $add_checkbox
   *   When add(TRUE) or not(FALSE) a checkbox to enable languages.
   * @param bool $add_tag_option
   *   When add(TRUE) or not(FALSE) a textbox to set tags.
   *
   * @return array
   *   Return elements to a table with languages.
   */
  protected function perLanguageSettings($view, $add_checkbox, $add_tag_option) {
    $config = $this->config('geshifilter.settings');
    $form = [];
    $header = [
      $this->t('Language'),
      $this->t('GeSHi language code'),
    ];
    if ($add_tag_option) {
      $header[] = $this->t('Tag/language attribute value');
    }
    $form['language'] = [
      '#type' => 'table',
      '#header' => $header,
      '#empty' => $this->t('Nome language is available.'),
    ];

    // Table body.
    $languages = GeshiFilter::getAvailableLanguages();
    foreach ($languages as $language => $language_data) {
      $enabled = $config->get("language.{$language}.enabled", FALSE);
      // Skip items to hide.
      if (($view == 'enabled' && !$enabled) || ($view == 'disabled' && $enabled)) {
        continue;
      }
      // Build language row.
      $form['language'][$language] = [];
      // Add enable/disable checkbox.
      if ($add_checkbox) {
        $form['language'][$language]['enabled'] = [
          '#type' => 'checkbox',
          '#default_value' => $enabled,
          '#title' => $language_data['fullname'],
        ];
      }
      else {
        $form['language'][$language]['fullname'] = [
          '#type' => 'markup',
          '#markup' => $language_data['fullname'],
        ];
      }
      // Language code.
      $form['language'][$language]['name'] = [
        '#type' => 'markup',
        '#markup' => $language,
      ];
      // Add a textfield for tags.
      if ($add_tag_option) {
        $form['language'][$language]['tags'] = [
          '#type' => 'textfield',
          '#default_value' => $config->get("language.{$language}.tags", ''),
          '#size' => 20,
        ];
      }
    }
    return $form;
  }

}
