<?php

namespace Drupal\ckeditor5\Hook;

use Drupal\Core\Hook\Order\OrderAfter;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Asset\AttachedAssetsInterface;
use Drupal\Core\Render\Element;
use Drupal\ckeditor5\Plugin\Editor\CKEditor5;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for ckeditor5.
 */
class Ckeditor5Hooks {

  use StringTranslationTrait;

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, RouteMatchInterface $route_match): ?string {
    switch ($route_name) {
      case 'help.page.ckeditor5':
        $output = '';
        $output .= '<h2>' . $this->t('About') . '</h2>';
        $output .= '<p>' . $this->t('The CKEditor 5 module provides a highly-accessible, highly-usable visual text editor and adds a toolbar to text fields. Users can use buttons to format content and to create semantically correct and valid HTML. The CKEditor module uses the framework provided by the <a href=":text_editor">Text Editor module</a>. It requires JavaScript to be enabled in the browser. For more information, see the <a href=":doc_url">online documentation for the CKEditor 5 module</a> and the <a href=":cke5_url">CKEditor 5 website</a>.', [
          ':doc_url' => 'https://www.drupal.org/docs/contributed-modules/ckeditor-5',
          ':cke5_url' => 'https://ckeditor.com/ckeditor-5/',
          ':text_editor' => Url::fromRoute('help.page', [
            'name' => 'editor',
          ])->toString(),
        ]) . '</p>';
        $output .= '<h2>' . $this->t('Uses') . '</h2>';
        $output .= '<dl>';
        $output .= '<dt>' . $this->t('Enabling CKEditor 5 for individual text formats') . '</dt>';
        $output .= '<dd>' . $this->t('CKEditor 5 has to be installed and configured separately for individual text formats from the <a href=":formats">Text formats and editors page</a> because the filter settings for each text format can be different. For more information, see the <a href=":text_editor">Text Editor help page</a> and <a href=":filter">Filter help page</a>.', [
          ':formats' => Url::fromRoute('filter.admin_overview')->toString(),
          ':text_editor' => Url::fromRoute('help.page', [
            'name' => 'editor',
          ])->toString(),
          ':filter' => Url::fromRoute('help.page', [
            'name' => 'filter',
          ])->toString(),
        ]) . '</dd>';
        $output .= '<dt>' . $this->t('Configuring the toolbar') . '</dt>';
        $output .= '<dd>' . $this->t('When CKEditor 5 is chosen from the <em>Text editor</em> drop-down menu, its toolbar configuration is displayed. You can add and remove buttons from the <em>Active toolbar</em> by dragging and dropping them. Separators and rows can be added to organize the buttons.') . '</dd>';
        $output .= '<dt>' . $this->t('Filtering HTML content') . '</dt>';
        $output .= '<dd>' . $this->t("Unlike other text editors, plugin configuration determines the tags and attributes allowed in text formats using CKEditor 5. If using the <em>Limit allowed HTML tags and correct faulty HTML</em> filter, this filter's values will be automatically set based on enabled plugins and toolbar items.");
        $output .= '<dt>' . $this->t('Toggling between formatted text and HTML source') . '</dt>';
        $output .= '<dd>' . $this->t('If the <em>Source</em> button is available in the toolbar, users can click this button to disable the visual editor and edit the HTML source directly. After toggling back, the visual editor uses the HTML tags allowed via plugin configuration (and not explicity disallowed by filters) to format the text. Tags not enabled via plugin configuration will be stripped out of the HTML source when the user toggles back to the text editor.') . '</dd>';
        $output .= '<dt>' . $this->t('Developing CKEditor 5 plugins in Drupal') . '</dt>';
        $output .= '<dd>' . $this->t('See the <a href=":dev_docs_url">online documentation</a> for detailed information on developing CKEditor 5 plugins for use in Drupal.', [
          ':dev_docs_url' => 'https://www.drupal.org/docs/contributed-modules/ckeditor-5/plugin-and-contrib-module-development',
        ]) . '</dd>';
        $output .= '</dd>';
        $output .= '<dt>' . $this->t('Accessibility features') . '</dt>';
        $output .= '<dd>' . $this->t('The built in WYSIWYG editor (CKEditor 5) comes with a number of accessibility features. CKEditor 5 comes with built in <a href=":shortcuts">keyboard shortcuts</a>, which can be beneficial for both power users and keyboard only users.', [
          ':shortcuts' => 'https://ckeditor.com/docs/ckeditor5/latest/features/keyboard-support.html',
        ]) . '</dd>';
        $output .= '<dt>' . $this->t('Generating accessible content') . '</dt>';
        $output .= '<dd>';
        $output .= '<ul>';
        $output .= '<li>' . $this->t('HTML tables can be created with table headers and caption/summary elements.') . '</li>';
        $output .= '<li>' . $this->t('Alt text is required by default on images added through CKEditor (note that this can be overridden).') . '</li>';
        $output .= '<li>' . $this->t('Semantic HTML5 figure/figcaption are available to add captions to images.') . '</li>';
        $output .= '<li>' . $this->t('To support multilingual page content, CKEditor 5 can be configured to include a language button in the toolbar.') . '</li>';
        $output .= '</ul>';
        $output .= '</dd>';
        $output .= '</dl>';
        $output .= '<h3 id="migration-settings">' . $this->t('Migrating an Existing Text Format to CKEditor 5') . '</h2>';
        $output .= '<p>' . $this->t('When switching an existing text format to use CKEditor 5, an automatic process is initiated that helps text formats switching to CKEditor 5 from CKEditor 4 (or no text editor) to do so with minimal effort and zero data loss.') . '</p>';
        $output .= '<p>' . $this->t("This process is designed for there to be no data loss risk in switching to CKEditor 5. However some of your editor's functionality may not be 100% equivalent to what was available previously. In most cases, these changes are minimal. After the process completes, status and/or warning messages will summarize any changes that occurred, and more detailed information will be available in the site's logs.") . '</p>';
        $output .= '<p>' . $this->t('CKEditor 5 will attempt to enable plugins that provide equivalent toolbar items to those used prior to switching to CKEditor 5. All core CKEditor 4 plugins and many popular contrib plugins already have CKEditor 5 equivalents. In some cases, functionality that required contrib modules is now built into CKEditor 5. In instances where a plugin does not have an equivalent, no data loss will occur but elements previously provided via the plugin may need to be added manually as HTML via source editing.') . '</p>';
        $output .= '<h4>' . $this->t('Additional migration considerations for text formats with restricted HTML') . '</h4>';
        $output .= '<dl>';
        $output .= '<dt>' . $this->t('The “Allowed HTML tags" field in the “Limit allowed HTML tags and correct Faulty HTML" filter is now read-only') . '</dt>';
        $output .= '<dd>' . $this->t('This field accurately represents the tags/attributes allowed by a text format, but the allowed tags are based on which plugins are enabled and how they are configured. For example, enabling the Underline plugin adds the &lt;u&gt; tag to “Allowed HTML tags".') . '</dd>';
        $output .= '<dt id="required-tags">' . $this->t('The &lt;p&gt; and &lt;br &gt; tags will be automatically added to your text format.') . '</dt>';
        $output .= '<dd>' . $this->t('CKEditor 5 requires the &lt;p&gt; and &lt;br &gt; tags to achieve basic functionality. They will be automatically added to “Allowed HTML tags" on formats that previously did not allow them.') . '</dd>';
        $output .= '<dt id="source-editing">' . $this->t('Tags/attributes that are not explicitly supported by any plugin are supported by Source Editing') . '</dt>';
        $output .= '<dd>' . $this->t('When a necessary tag/attribute is not directly supported by an available plugin, the "Source Editing" plugin is enabled. This plugin is typically used for by passing the CKEditor 5 UI and editing contents as HTML source. In the settings for Source Editing, tags/attributes that aren\'t available via other plugins are added to Source Editing\'s "Manually editable HTML tags" setting so they are supported by the text format.') . '</dd>';
        $output .= '</dl>';
        return $output;
    }
    return NULL;
  }

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme() : array {
    return ['ckeditor5_settings_toolbar' => ['render element' => 'form']];
  }

  /**
   * Implements hook_form_FORM_ID_alter().
   *
   * This module's implementation of form_filter_format_form_alter() must
   * happen after the editor module's implementation, as that implementation
   * adds the active editor to $form_state. It must also happen after the media
   * module's implementation so media_filter_format_edit_form_validate can be
   * removed from the validation chain, as that validator is not needed with
   * CKEditor 5 and will trigger a false error.
   */
  #[Hook('form_filter_format_form_alter',
    order: new OrderAfter(
      modules: ['editor', 'media'],
    )
  )]
  public function formFilterFormatFormAlter(array &$form, FormStateInterface $form_state, $form_id) : void {
    $editor = $form_state->get('editor');
    // CKEditor 5 plugin config determines the available HTML tags. If an HTML
    // restricting filter is enabled and the editor is CKEditor 5, the 'Allowed
    // HTML tags' field is made read only and automatically populated with the
    // values needed by CKEditor 5 plugins.
    // @see \Drupal\ckeditor5\Plugin\Editor\CKEditor5::buildConfigurationForm()
    if ($editor && $editor->getEditor() === 'ckeditor5') {
      if (isset($form['filters']['settings']['filter_html']['allowed_html'])) {
        $filter_allowed_html =& $form['filters']['settings']['filter_html']['allowed_html'];
        $filter_allowed_html['#value_callback'] = [CKEditor5::class, 'getGeneratedAllowedHtmlValue'];
        // Set readonly and add the form-disabled wrapper class as using
        // #disabled or the disabled attribute will prevent the new values from
        // being validated.
        $filter_allowed_html['#attributes']['readonly'] = TRUE;
        $filter_allowed_html['#wrapper_attributes']['class'][] = 'form-disabled';
        $filter_allowed_html['#description'] = $this->t('With CKEditor 5 this is a
          read-only field. The allowed HTML tags and attributes are determined
          by the CKEditor 5 configuration. Manually removing tags would break
          enabled functionality, and any manually added tags would be removed by
          CKEditor 5 on render.');
        // The media_filter_format_edit_form_validate validator is not needed
        // with CKEditor 5 as it exists to enforce the inclusion of specific
        // allowed tags that are added automatically by CKEditor 5. The
        // validator is removed so it does not conflict with the automatic
        // addition of those allowed tags.
        $key = array_search('media_filter_format_edit_form_validate', $form['#validate']);
        if ($key !== FALSE) {
          unset($form['#validate'][$key]);
        }
      }
    }
    // Override the AJAX callbacks for changing editors, so multiple areas of
    // the form can be updated on change.
    $form['editor']['editor']['#ajax'] = [
      'callback' => '_update_ckeditor5_html_filter',
      'trigger_as' => [
        'name' => 'editor_configure',
      ],
    ];
    $form['editor']['configure']['#ajax'] = ['callback' => '_update_ckeditor5_html_filter'];
    $form['editor']['settings']['subform']['toolbar']['items']['#ajax'] = [
      'callback' => '_update_ckeditor5_html_filter',
      'trigger_as' => [
        'name' => 'editor_configure',
      ],
      'event' => 'change',
      'ckeditor5_only' => 'true',
    ];
    foreach (Element::children($form['filters']['status']) as $filter_type) {
      $form['filters']['status'][$filter_type]['#ajax'] = [
        'callback' => '_update_ckeditor5_html_filter',
        'trigger_as' => [
          'name' => 'editor_configure',
        ],
        'event' => 'change',
        'ckeditor5_only' => 'true',
      ];
    }
    /*
     * Recursively adds AJAX listeners to plugin settings elements.
     *
     * These are added so allowed tags and other fields that have values
     * dependent on plugin settings can be updated via AJAX when these settings
     * are changed in the editor form.
     *
     * @param array $plugins_config_form
     *   The plugins config subform render array.
     */
    $add_listener = function (array &$plugins_config_form) use (&$add_listener) : void {
      $field_types = ['checkbox', 'select', 'radios', 'textarea'];
      if (isset($plugins_config_form['#type']) && in_array($plugins_config_form['#type'], $field_types) && !isset($plugins_config_form['#ajax'])) {
        $plugins_config_form['#ajax'] = [
          'callback' => '_update_ckeditor5_html_filter',
          'trigger_as' => [
            'name' => 'editor_configure',
          ],
          'event' => 'change',
          'ckeditor5_only' => 'true',
        ];
      }
      foreach ($plugins_config_form as $key => &$value) {
        if (is_array($value) && !str_contains((string) $key, '#')) {
          $add_listener($value);
        }
      }
    };
    if (isset($form['editor']['settings']['subform']['plugins'])) {
      $add_listener($form['editor']['settings']['subform']['plugins']);
    }
    // Add an ID to the filter settings vertical tabs wrapper to facilitate AJAX
    // updates.
    $form['filter_settings']['#wrapper_attributes']['id'] = 'filter-settings-wrapper';
    $form['#after_build'][] = [
      CKEditor5::class,
      'assessActiveTextEditorAfterBuild',
    ];
    $form['#validate'][] = [CKEditor5::class, 'validateSwitchingToCKEditor5'];
    array_unshift($form['actions']['submit']['#submit'], 'ckeditor5_filter_format_edit_form_submit');
  }

  /**
   * Implements hook_library_info_alter().
   */
  #[Hook('library_info_alter')]
  public function libraryInfoAlter(&$libraries, $extension): void {
    if ($extension === 'filter') {
      $libraries['drupal.filter.admin']['dependencies'][] = 'ckeditor5/internal.drupal.ckeditor5.filter.admin';
    }
    $moduleHandler = \Drupal::moduleHandler();
    if ($extension === 'ckeditor5') {
      // Add paths to stylesheets specified by a theme's ckeditor5-stylesheets
      // config property.
      $css = _ckeditor5_theme_css();
      $libraries['internal.drupal.ckeditor5.stylesheets'] = ['css' => ['theme' => array_fill_keys(array_values($css), [])]];
    }
    if ($extension === 'core') {
      // CSS rule to resolve the conflict with z-index between CKEditor 5 and
      // jQuery UI.
      $libraries['drupal.dialog']['css']['component']['modules/ckeditor5/css/ckeditor5.dialog.fix.css'] = [];
      // Fix the CKEditor 5 focus management in dialogs. Modify the library
      // declaration to ensure this file is always loaded after
      // drupal.dialog.jquery-ui.js.
      $libraries['drupal.dialog']['js']['modules/ckeditor5/js/ckeditor5.dialog.fix.js'] = [];
    }
    // Only add translation processing if the locale module is enabled.
    if (!$moduleHandler->moduleExists('locale')) {
      return;
    }
    // All possibles CKEditor 5 languages that can be used by Drupal.
    $ckeditor_langcodes = array_values(_ckeditor5_get_langcode_mapping());
    if ($extension === 'core') {
      // Generate libraries for each of the CKEditor 5 translation files so that
      // the correct translation file can be attached depending on the current
      // language. This makes sure that caching caches the appropriate language.
      // Only create libraries for languages that have a mapping to Drupal.
      foreach ($ckeditor_langcodes as $langcode) {
        $libraries['ckeditor5.translations.' . $langcode] = [
          'remote' => $libraries['ckeditor5']['remote'],
          'version' => $libraries['ckeditor5']['version'],
          'license' => $libraries['ckeditor5']['license'],
          'dependencies' => [
            'core/ckeditor5',
            'core/ckeditor5.translations',
          ],
        ];
      }
    }
    // Copied from
    // \Drupal\Core\Asset\LibraryDiscoveryParser::buildByExtension().
    if ($extension === 'core') {
      $path = 'core';
    }
    else {
      if ($moduleHandler->moduleExists($extension)) {
        $extension_type = 'module';
      }
      else {
        $extension_type = 'theme';
      }
      $path = \Drupal::getContainer()->get('extension.path.resolver')->getPath($extension_type, $extension);
    }
    foreach ($libraries as &$library) {
      // The way to know if a library has a translation is to depend on the
      // special "core/ckeditor5.translations" library.
      if (empty($library['js']) || empty($library['dependencies']) || !in_array('core/ckeditor5.translations', $library['dependencies'])) {
        continue;
      }
      foreach ($library['js'] as $file => $options) {
        // Only look for translations on libraries defined with a relative path.
        if (!empty($options['type']) && $options['type'] === 'external') {
          continue;
        }
        // Path relative to the current extension folder.
        $dirname = dirname($file);
        // Path of the folder in the filesystem relative to the Drupal root.
        $dir = $path . '/' . $dirname;
        // Exclude protocol-free URI.
        if (str_starts_with($dirname, '//')) {
          continue;
        }
        // CKEditor 5 plugins are most likely added through composer and
        // installed in the module exposing it. Suppose the file path is
        // relative to the module and not in the /libraries/ folder.
        // Collect translations based on filename, and add all existing
        // translations files to the plugin library. Unnecessary translations
        // will be filtered in ckeditor5_js_alter() hook.
        $files = scandir("{$dir}/translations");
        foreach ($files as $file) {
          if (str_ends_with($file, '.js')) {
            $langcode = basename($file, '.js');
            // Only add languages that Drupal can understands.
            if (in_array($langcode, $ckeditor_langcodes)) {
              $library['js']["{$dirname}/translations/{$langcode}.js"] = ['ckeditor5_langcode' => $langcode, 'minified' => TRUE, 'preprocess' => TRUE];
            }
          }
        }
      }
    }
  }

  /**
   * Implements hook_js_alter().
   */
  #[Hook('js_alter')]
  public function jsAlter(&$javascript, AttachedAssetsInterface $assets, LanguageInterface $language): void {
    // This file means CKEditor 5 translations are in use on the page.
    // @see locale_js_alter()
    $placeholder_file = 'core/assets/vendor/ckeditor5/translation.js';
    // This file is used to get a weight that will make it possible to aggregate
    // all translation files in a single aggregate.
    $ckeditor_dll_file = 'core/assets/vendor/ckeditor5/ckeditor5-dll/ckeditor5-dll.js';
    if (isset($javascript[$placeholder_file])) {
      // Use the placeholder file weight to set all the translations files
      // weights so they can be aggregated together as expected.
      $default_weight = $javascript[$placeholder_file]['weight'];
      if (isset($javascript[$ckeditor_dll_file])) {
        $default_weight = $javascript[$ckeditor_dll_file]['weight'];
      }
      // The placeholder file is not a real file, remove it from the list.
      unset($javascript[$placeholder_file]);
      // When the locale module isn't installed there are no translations.
      if (!\Drupal::moduleHandler()->moduleExists('locale')) {
        return;
      }
      $ckeditor5_language = _ckeditor5_get_langcode_mapping($language->getId());
      // Remove all CKEditor 5 translations files that are not in the current
      // language.
      foreach ($javascript as $index => &$item) {
        // This is not a CKEditor 5 translation file, skip it.
        if (empty($item['ckeditor5_langcode'])) {
          continue;
        }
        // This file is the correct translation for this page.
        if ($item['ckeditor5_langcode'] === $ckeditor5_language) {
          // Set the weight for the translation file to be able to have the
          // translation files aggregated.
          $item['weight'] = $default_weight;
        }
        else {
          // Remove files that don't match the language requested.
          unset($javascript[$index]);
        }
      }
    }
  }

  /**
   * Implements hook_config_schema_info_alter().
   */
  #[Hook('config_schema_info_alter')]
  public function configSchemaInfoAlter(&$definitions): void {
    // In \Drupal\Tests\config\Functional\ConfigImportAllTest, this hook may be
    // called without ckeditor5.pair.schema.yml being active.
    if (!isset($definitions['ckeditor5_valid_pair__format_and_editor'])) {
      return;
    }
    // @see filter.format.*.filters
    $definitions['ckeditor5_valid_pair__format_and_editor']['mapping']['filters'] = $definitions['filter.format.*']['mapping']['filters'];
    // @see @see editor.editor.*.image_upload
    $definitions['ckeditor5_valid_pair__format_and_editor']['mapping']['image_upload'] = $definitions['editor.editor.*']['mapping']['image_upload'];
  }

}
