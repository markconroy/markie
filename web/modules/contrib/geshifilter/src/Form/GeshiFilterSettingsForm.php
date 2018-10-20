<?php

namespace Drupal\geshifilter\Form;

// Need this for base class of the form.
use Drupal\Core\Form\ConfigFormBase;

use Drupal\Core\Form\FormStateInterface;

use Drupal\geshifilter\GeshiFilterCss;

// Necessary for URL.
use Drupal\Core\Url;

use Drupal\Core\Cache\Cache;

// Necessary for SafeMarkup::checkPlain().
use Drupal\geshifilter\GeshiFilter;

/**
 * Form with the settings for the module.
 */
class GeshiFilterSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'geshifilter_admin_settings';
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
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('geshifilter.settings');

    // Try to load GeSHi library and get version if successful.
    $geshi_library = GeshiFilter::loadGeshi();

    // GeSHi library settings (constant GESHI_VERSION is defined in GeSHi
    // library).
    $form['library'] = [
      '#type' => 'fieldset',
      '#title' => defined('GESHI_VERSION') ? $this->t('GeSHi library version @version detected', ['@version' => GESHI_VERSION]) : $this->t('GeSHi library'),
      '#description' => $this->t('The GeSHi filter requires the GeSHi library (which needs to be @downloaded and installed seperately). Please review the install instruction at @readme.', [
        '@downloaded' => $this->l($this->t('downloaded'), Url::fromUri('http://qbnz.com/highlighter/')),
        '@readme' => $this->l($this->t('README.TXT'), Url::fromUri('http://cgit.drupalcode.org/geshifilter/tree/README.txt?h=8.x-1.x')),
      ]),
      '#collapsible' => TRUE,
      '#collapsed' => $geshi_library['loaded'],
    ];

    // If the GeSHi library is loaded, show all the options and settings.
    if ($geshi_library['loaded']) {
      // Option for flushing the GeSHi language definition cache.
      $form['library']['language_definition_caching'] = [
        '#type' => 'item',
        '#title' => $this->t('GeSHi language definition caching'),
        '#description' => $this->t('The GeSHi library uses languages definition files to define the properties and highlight rules of the supported languages. In most scenarios these language definition files do not change and a lot of derivative data, such as the list of available languages or the CSS style sheet, can be cached for efficiency reasons. Sometimes however, this cache needs to be flushed and the languages definition files need to be reparsed, for example after an upgrade of the GeSHi library or after adding/editing some language definition files manually.'),
      ];
      // Non-submitting button for flushing the GeSHi language definition file
      // cache.
      $form['library']['language_definition_caching']['flush_language_definition_cache'] = [
        '#type' => 'button',
        '#value' => $this->t("Flush the GeSHi language definition cache"),
        '#executes_submit_callback' => TRUE,
        '#submit' => ['::flushLanguageDefinitionCache'],
      ];

      // GeSHi filter tags and delimiters options.
      $form['tag_options'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('GeSHi filter tags and delimiters'),
        '#collapsible' => TRUE,
      ];
      // Usage of format specific options.
      $form['tag_options']['use_format_specific_options'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Use text format specific tag settings.'),
        '#default_value' => $config->get('use_format_specific_options', FALSE),
        '#description' => $this->t('Enable seperate tag settings of the GeSHi filter for each @text-format instead of global tag settings.', [
          '@text-format' => \Drupal::l($this->t('text format'), Url::fromRoute('filter.admin_overview')),
        ]),
      ];
      // Generic tags settings.
      // @todo must validate the tag styles.
      if (!$config->get('use_format_specific_options', FALSE)) {
        $form['tag_options']['general_tags'] = $this->generalHighlightTagsSettings();
        // $form['#validate'][] = '_geshifilter_tag_styles_validate';.
      }

      // GeSHi filter highlighting options.
      $form['highlighting_options'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Syntax highlighting options'),
        '#collapsible' => TRUE,
      ];
      // Default language.
      $languages = GeshiFilter::getEnabledLanguages();
      $form['highlighting_options']['default_highlighting'] = [
        '#type' => 'select',
        '#title' => $this->t('Default highlighting mode'),
        '#default_value' => $config->get('default_highlighting'),
        '#options' => [
          (string) $this->t('No highlighting') => [
            GeshiFilter::DEFAULT_DONOTHING => $this->t('Do nothing'),
            GeshiFilter::DEFAULT_PLAINTEXT => $this->t('As plain text'),
          ],
          (string) $this->t('Languages') => $languages,
        ],
        '#description' => $this->t('Select the default highlighting mode to use when no language is defined with a language attribute in the tag.'),
      ];
      // Default line numbering scheme.
      $form['highlighting_options']['default_line_numbering'] = [
        '#type' => 'select',
        '#title' => $this->t('Default line numbering'),
        '#default_value' => $config->get('default_line_numbering'),
        '#options' => [
          GeshiFilter::LINE_NUMBERS_DEFAULT_NONE => $this->t('no line numbers'),
          GeshiFilter::LINE_NUMBERS_DEFAULT_NORMAL => $this->t('normal line numbers'),
          GeshiFilter::LINE_NUMBERS_DEFAULT_FANCY5 => $this->t('fancy line numbers (every @n lines)', ['@n' => GeshiFilter::LINE_NUMBERS_DEFAULT_FANCY5]),
          GeshiFilter::LINE_NUMBERS_DEFAULT_FANCY10 => $this->t('fancy line numbers (every @n lines)', ['@n' => GeshiFilter::LINE_NUMBERS_DEFAULT_FANCY10]),
          GeshiFilter::LINE_NUMBERS_DEFAULT_FANCY20 => $this->t('fancy line numbers (every @n lines)', ['@n' => GeshiFilter::LINE_NUMBERS_DEFAULT_FANCY20]),
        ],
        '#description' => $this->t('Select the default line numbering scheme: no line numbers, normal line numbers or fancy line numbers. With fancy line numbers every n<sup>th</sup> line number is highlighted. (GeSHi documentation: @line-numbers).', [
          '@line-numbers' => \Drupal::l($this->t('Line numbers'), Url::fromUri('http://qbnz.com/highlighter/geshi-doc.html#line-numbers')),
        ]),
      ];
      
      // Generic tags.
      $form['highlighting_options']["tab_width"] = [
        '#type' => 'textfield',
        '#title' => $this->t('Tab Width'),
        '#default_value' => $config->get('tab_width'),
        '#description' => $this->t('How many spaces to use when replacing tabs.'),
      ];
      
      // Highlight_string usage option.
      $form['highlighting_options']['use_highlight_string_for_php'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Use built-in PHP function <code>highlight_string()</code> for PHP source code.'),
        '#description' => $this->t('When enabled, PHP source code will be syntax highlighted with the built-in PHP function <code>@highlight-string</code> instead of with the GeSHi library. GeSHi features, like line numbering and usage of an external CSS stylesheet for example, are not available.', [
          '@highlight-string' => \Drupal::l('highlight_string()', Url::fromUri('http://php.net/manual/en/function.highlight-string.php')),
        ]),
        '#default_value' => $config->get('use_highlight_string_for_php'),
      ];
      // Option to disable Keyword URL's.
      $form['highlighting_options']['enable_keyword_urls'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Enable GeSHi keyword URLs'),
        '#description' => $this->t('For some languages GeSHi can link language keywords (e.g. standard library functions) to their online documentation. (GeSHi documentation: @keyword-urls).', [
          '@keyword-urls' => \Drupal::l($this->t('Keyword URLs'), Url::fromUri('http://qbnz.com/highlighter/geshi-doc.html#keyword-urls')),
        ]),
        '#default_value' => $config->get('enable_keyword_urls'),
      ];

      // Styling, layout and CSS.
      $form['styling'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Styling, layout and CSS'),
        '#collapsible' => TRUE,
      ];

      // CSS mode.
      $form['styling']['css_mode'] = [
        '#type' => 'radios',
        '#title' => $this->t('CSS mode for syntax highlighting'),
        '#options' => [
          GeshiFilter::CSS_INLINE => $this->t('Inline CSS style attributes.'),
          GeshiFilter::CSS_CLASSES_AUTOMATIC => $this->t('Use CSS classes and an automatically managed external CSS style sheet.'),
          GeshiFilter::CSS_CLASSES_ONLY => $this->t('Only add CSS classes to the markup.'),
        ],
        '#default_value' => $config->get('css_mode', GeshiFilter::CSS_INLINE),
        '#description' => $this->t('Inline CSS is easier to set up, does not depend on
          an external style sheets and is consequently more robust to copy/paste
          operations like content aggregation. However, usage of CSS classes and
          an external stylesheet requires much less markup code and bandwidth.
          The external style sheet can be managed automatically by the GeSHi
          filter module, but this feature requires the public
          @download-method. If the GeSHi filter is
          configured to only add the CSS classes to the markup, the
          administrator or themer is responsible for adding the appropriate CSS
          rules to the pages (e.g. based on @css-defaults).
          (GeSHi documentation: @css-classes).',
          [
            '@css-classes' => \Drupal::l($this->t('Using CSS Classes'),
              Url::fromUri('http://qbnz.com/highlighter/geshi-doc.html#using-css-classes')
            ),
            '@download-method' => \Drupal::l($this->t('download method'), Url::fromRoute('system.file_system_settings')),
            '@css-defaults' => \Drupal::l($this->t('these defaults'), Url::fromRoute('geshifilter.generate_css')),
          ]
        ),
      ];

      // Code container.
      $container_options = [
        GESHI_HEADER_PRE => $this->t('%val: uses a @cnt wrapper, efficient whitespace coding, no automatic line wrapping, generates invalid HTML with line numbering.', [
          '%val' => 'GESHI_HEADER_PRE',
          '@cnt' => '<pre>',
        ]),
        GESHI_HEADER_DIV => $this->t('%val: uses a @cnt wrapper, enables automatic line wrapping.', [
          '%val' => 'GESHI_HEADER_DIV',
          '@cnt' => '<div>',
        ]),
      ];
      if (version_compare(GESHI_VERSION, '1.0.8', '>=')) {
        $container_options[GESHI_HEADER_PRE_VALID] = $this->t('%val: uses @pre
          wrappers, ensures valid HTML with line numbering, but generates more
          markup.',
          [
            '%val' => 'GESHI_HEADER_PRE_VALID',
            '@pre' => '<pre>',
            '@li' => '<li>',
          ]
        );
        $container_options[GESHI_HEADER_PRE_TABLE] = $this->t('%val: uses a @table construction for adding line numbers which avoids selection/copy/paste problems.', [
          '%val' => 'GESHI_HEADER_PRE_TABLE',
          '@table' => '<table>',
        ]);
      }
      if (version_compare(GESHI_VERSION, '1.0.7.2', '>=')) {
        $container_options[GESHI_HEADER_NONE] = $this->t('%val: uses no wrapper.', ['%val' => 'GESHI_HEADER_NONE']);
      }

      $form['styling']['code_container'] = [
        '#type' => 'radios',
        '#title' => $this->t('Code container, wrapping technique'),
        '#description' => $this->t('Define the wrapping technique to use for code blocks. (GeSHi documentation: @code-container).',
          ['@code-container' => \Drupal::l($this->t('The Code Container'), Url::fromUri('http://qbnz.com/highlighter/geshi-doc.html#the-code-container'))]
        ),
        '#options' => $container_options,
        '#default_value' => $config->get('code_container'),
      ];
    }
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Check if automatically managed style sheet is posible.
    if ($form_state->hasValue('css_mode') &&
      $form_state->getValue('css_mode') == GeshiFilter::CSS_CLASSES_AUTOMATIC &&
      !GeshiFilterCss::managedExternalStylesheetPossible()
    ) {
      $form_state->setErrorByName('css_mode', $this->t('GeSHi filter can not
        automatically manage an external CSS style sheet when the download method
        is private.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $errors = $form_state->getErrors();
    if (count($errors) == 0) {
      $config = $this->config('geshifilter.settings');
      $config->set('use_format_specific_options', $form_state->getValue('use_format_specific_options'))
        ->set('default_highlighting', $form_state->getValue('default_highlighting'))
        ->set('default_line_numbering', $form_state->getValue('default_line_numbering'))
        ->set('tab_width', $form_state->getValue('tab_width'))
        ->set('use_highlight_string_for_php', $form_state->getValue('use_highlight_string_for_php'))
        ->set('enable_keyword_urls', $form_state->getValue('enable_keyword_urls'))
        ->set('css_mode', $form_state->getValue('css_mode'))
        ->set('code_container', $form_state->getValue('code_container'));
      // These values are not always set, so this prevents a warning.
      if ($form_state->hasValue('tags')) {
        $config->set('tags', $form_state->getValue('tags'));
        $config->set('tag_styles', $form_state->getValue('tag_styles'));
        $config->set('decode_entities', $form_state->getValue('decode_entities'));
      }
      $config->save();

      // Regenerate language css.
      if ($config->get('css_mode') == GeshiFilter::CSS_CLASSES_AUTOMATIC) {
        GeshiFilterCss::generateLanguagesCssFile();
      }
      // Always clear the filter cache.
      Cache::invalidateTags(['geshifilter']);
      parent::submitForm($form, $form_state);
    }
  }

  /**
   * Helper function for flushing the GeSHi language definition cache.
   */
  public function flushLanguageDefinitionCache() {
    $config = \Drupal::config('geshifilter.settings');
    if (GeshiFilter::CSS_CLASSES_AUTOMATIC == $config->get('css_mode')) {
      // Forced regeneration of the CSS file.
      GeshiFilterCss::generateLanguagesCssFile(TRUE);
    }
    $cache = \Drupal::cache();
    $cache->delete('geshifilter_available_languages_cache');
    drupal_set_message($this->t('Flushed the GeSHi language definition cache.'));
  }

  /**
   * Helper function for some settings form fields usable as general/specific.
   *
   * @return array
   *   The form elements to choose tag settings.
   */
  private function generalHighlightTagsSettings() {
    $form = [];

    // Generic tags.
    $form["tags"] = [
      '#type' => 'textfield',
      '#title' => $this->t('Generic syntax highlighting tags'),
      '#default_value' => $this->tags(),
      '#description' => $this->t('Tags that should activate the GeSHi syntax highlighting. Specify a space-separated list of tagnames.'),
    ];

    // Container tag styles.
    $form["tag_styles"] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Container tag style'),
      '#options' => [
        GeshiFilter::BRACKETS_ANGLE => '<code>' . htmlentities('<foo> ... </foo>') . '</code>',
        GeshiFilter::BRACKETS_SQUARE => '<code>[foo] ... [/foo]</code>',
        GeshiFilter::BRACKETS_DOUBLESQUARE => '<code>[[foo]] ... [[/foo]]</code>',
        GeshiFilter::BRACKETS_PHPBLOCK => $this->t('PHP style source code blocks: <code>@php</code> and <code>@percent</code>', [
          '@php' => '<?php ... ?>',
          '@percent' => '<% ... %>',
        ]),
        GeshiFilter::BRACKETS_MARKDOWNBLOCK => '<code>' . htmlentities('```foo ... ```') . '</code>',
      ],
      '#default_value' => $this->tagStyles(),
      '#description' => $this->t('Select the container tag styles that should trigger GeSHi syntax highlighting.'),
    ];

    // Setting to decode entities, see https://www.drupal.org/node/2047021.
    $config = $this->config('geshifilter.settings');
    $form["decode_entities"] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Decode entities'),
      '#default_value' => $config->get('decode_entities'),
      '#description' => $this->t('Decode entities, for example, if the code has been typed in a WYSIWYG editor.'),
    ];
    return $form;
  }

  /**
   * Get the global common tags.
   *
   * Return the generic tags configured, as example, code blockcode.
   *
   * @return string
   *   Return the generic tags.
   */
  private function tags() {
    $config = $this->config('geshifilter.settings');
    return $config->get('tags');
  }

  /**
   * Get the global tag styles.
   *
   * @return array
   *   The global tag styles.
   */
  protected function tagStyles() {
    $config = \Drupal::config('geshifilter.settings');
    $tags = $config->get('tag_styles');
    if ($tags) {
      return $tags;
    }
    else {
      return [];
    }
  }

}
