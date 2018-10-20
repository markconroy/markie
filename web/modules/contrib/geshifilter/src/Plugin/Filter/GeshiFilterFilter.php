<?php

namespace Drupal\geshifilter\Plugin\Filter;

// Base class for filters.
use Drupal\filter\Plugin\FilterBase;

// Necessary for SafeMarkup::checkPlain().
use Drupal\Component\Utility\SafeMarkup;

// Necessary for passing HTML into t().
use Drupal\Core\Render\Markup;

// Necessary for Html::decodeEntities().
use Drupal\Component\Utility\Html;

// Necessary for forms.
use Drupal\Core\Form\FormStateInterface;

// Necessary for result of process().
use Drupal\filter\FilterProcessResult;

// Necessary for URL.
use Drupal\Core\Url;

use Drupal\geshifilter\GeshiFilter;
use Drupal\geshifilter\GeshiFilterProcess;

/**
 * Provides a base filter for Geshi Filter.
 *
 * @Filter(
 *   id = "filter_geshifilter",
 *   module = "geshifilter",
 *   title = @Translation("GeSHi filter"),
 *   description = @Translation("Enables syntax highlighting of inline/block
 *     source code using the GeSHi engine"),
 *   type = \Drupal\filter\Plugin\FilterInterface::TYPE_TRANSFORM_IRREVERSIBLE,
 *   cache = FALSE,
 *   settings = {
 *     "general_tags" = {},
 *     "per_language_settings" = {}
 *   },
 *   weight = 0
 * )
 */
class GeshiFilterFilter extends FilterBase {

  /**
   * Object with configuration for geshifilter.
   *
   * @var object
   */
  protected $config;

  /**
   * Object with configuration for geshifilter, where we need editable..
   *
   * @var object
   */
  protected $configEditable;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->config = \Drupal::config('geshifilter.settings');
    $this->configEditable = \Drupal::configFactory()->getEditable('geshifilter.settings');
  }

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) {

    $result = new FilterProcessResult($text);

    try {
      // Load GeSHi library (if not already).
      $geshi_library = GeshiFilter::loadGeshi();
      if (!$geshi_library['loaded']) {
        throw new \Exception($geshi_library['error message']);
      }

      // Get the available tags.
      list($generic_code_tags, $language_tags, $tag_to_lang) = $this->getTags();
      if (in_array(GeshiFilter::BRACKETS_PHPBLOCK, array_filter($this->tagStyles()))) {
        $language_tags[] = 'questionmarkphp';
        $tag_to_lang['questionmarkphp'] = 'php';
      }
      $tags = array_merge($generic_code_tags, $language_tags);
      // Escape special (regular expression) characters in tags (for tags like
      // 'c++' and 'c#').
      $tags = preg_replace('#(\\+|\\#)#', '\\\\$1', $tags);

      $tags_string = implode('|', $tags);
      // Pattern for matching the prepared "<code>...</code>" stuff.
      $pattern = '#\\[geshifilter-(' . $tags_string . ')([^\\]]*)\\](.*?)(\\[/geshifilter-\1\\])#s';
      $text = preg_replace_callback($pattern, [
        $this,
        'replaceCallback',
      ], $text);

      // Create the object with result.
      $result = new FilterProcessResult($text);

      // Add the css file when necessary.
      if (in_array($this->config->get('css_mode'), [GeshiFilter::CSS_CLASSES_AUTOMATIC, GeshiFilter::CSS_INLINE])) {
        $result->setAttachments([
          'library' => [
            'geshifilter/geshifilter',
          ],
        ]);
      }

      // Add cache tags, so we can re-create the node when some geshifilter
      // settings change.
      $cache_tags = ['geshifilter'];
      $result->addCacheTags($cache_tags);
    }
    catch (\Exception $e) {
      watchdog_exception('geshifilter', $e);
      drupal_set_message($geshi_library['error message'], 'error');
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function prepare($text, $langcode) {
    // Get the available tags.
    list($generic_code_tags, $language_tags, $tag_to_lang) = $this->getTags();
    $tags = array_merge($generic_code_tags, $language_tags);

    // Escape special (regular expression) characters in tags (for tags like
    // 'c++' and 'c#').
    $tags = preg_replace('#(\\+|\\#)#', '\\\\$1', $tags);
    $tags_string = implode('|', $tags);
    // Pattern for matching "<code>...</code>" like stuff
    // Also matches "<code>...$"  where "$" refers to end of string, not end of
    // line (because PCRE_MULTILINE (modifier 'm') is not enabled), so matching
    // still works when teaser view trims inside the source code.
    // Replace the code container tag brackets
    // and prepare the container content (newline and angle bracket protection).
    // @todo: make sure that these replacements can be done in series.
    $tag_styles = array_filter($this->tagStyles());
    if (in_array(GeshiFilter::BRACKETS_ANGLE, $tag_styles)) {
      // Prepare <foo>..</foo> blocks.
      $pattern = '#(<)(' . $tags_string . ')((\s+[^>]*)*)(>)(.*?)(</\2\s*>|$)#s';
      $text = preg_replace_callback($pattern, [$this, 'prepareCallback'], $text);
    }
    if (in_array(GeshiFilter::BRACKETS_SQUARE, $tag_styles)) {
      // Prepare [foo]..[/foo] blocks.
      $pattern = '#((?<!\[)\[)(' . $tags_string . ')((\s+[^\]]*)*)(\])(.*?)((?<!\[)\[/\2\s*\]|$)#s';
      $text = preg_replace_callback($pattern, [$this, 'prepareCallback'], $text);
    }
    if (in_array(GeshiFilter::BRACKETS_DOUBLESQUARE, $tag_styles)) {
      // Prepare [[foo]]..[[/foo]] blocks.
      $pattern = '#(\[\[)(' . $tags_string . ')((\s+[^\]]*)*)(\]\])(.*?)(\[\[/\2\s*\]\]|$)#s';
      $text = preg_replace_callback($pattern, [$this, 'prepareCallback'], $text);
    }
    if (in_array(GeshiFilter::BRACKETS_PHPBLOCK, $tag_styles)) {
      // Prepare < ?php ... ? > blocks.
      $pattern = '#[\[<](\?php|\?PHP|%)(.+?)((\?|%)[\]>]|$)#s';
      $text = preg_replace_callback($pattern, [$this, 'preparePhpCallback'], $text);
    }
    if (in_array(GeshiFilter::BRACKETS_MARKDOWNBLOCK, $tag_styles)) {
      // Prepare ```php ``` blocks(markdown).
      $pattern = '#(```([a-z]*)\n([\s\S]*?)\n```)#s';
      $text = preg_replace_callback($pattern, [$this, 'prepareMarkdownCallback'], $text);
    }
    return $text;
  }

  /**
   * Get the tips for the filter.
   *
   * @param bool $long
   *   If get the long or short tip.
   *
   * @return string
   *   The tip to show for the user.
   */
  public function tips($long = FALSE) {
    // Get the supported tag styles.
    $tag_styles = array_filter($this->tagStyles());
    $tag_style_examples = [];
    $bracket_open = NULL;
    $bracket_close = NULL;
    if (in_array(GeshiFilter::BRACKETS_ANGLE, $tag_styles)) {
      if (!$bracket_open) {
        $bracket_open = SafeMarkup::checkPlain('<');
        $bracket_close = SafeMarkup::checkPlain('>');
      }
      $tag_style_examples[] = '<code>' . SafeMarkup::checkPlain('<foo>') . '</code>';
    }
    if (in_array(GeshiFilter::BRACKETS_SQUARE, $tag_styles)) {
      if (!$bracket_open) {
        $bracket_open = SafeMarkup::checkPlain('[');
        $bracket_close = SafeMarkup::checkPlain(']');
      }
      $tag_style_examples[] = '<code>' . SafeMarkup::checkPlain('[foo]') . '</code>';
    }
    if (in_array(GeshiFilter::BRACKETS_DOUBLESQUARE, $tag_styles)) {
      if (!$bracket_open) {
        $bracket_open = SafeMarkup::checkPlain('[[');
        $bracket_close = SafeMarkup::checkPlain(']]');
      }
      $tag_style_examples[] = '<code>' . SafeMarkup::checkPlain('[[foo]]') . '</code>';
    }
    if (in_array(GeshiFilter::BRACKETS_MARKDOWNBLOCK, $tag_styles)) {
      if (!$bracket_open) {
        $bracket_open = SafeMarkup::checkPlain('```');
        $bracket_close = SafeMarkup::checkPlain('```');
      }
      $tag_style_examples[] = '<code>' . SafeMarkup::checkPlain('```foo ```') . '</code>';
    }
    if (!$bracket_open) {
      drupal_set_message($this->t('Could not determine a valid tag style for GeSHi filtering.'), 'error');
      $bracket_open = SafeMarkup::checkPlain('<');
      $bracket_close = SafeMarkup::checkPlain('>');
    }

    if ($long) {
      // Get the available tags.
      list($generic_code_tags, $language_tags, $tag_to_lang) = $this->getTags();
      // Get the available languages.
      $languages = GeshiFilter::getEnabledLanguages();
      $lang_attributes = GeshiFilter::whitespaceExplode(GeshiFilter::ATTRIBUTES_LANGUAGE);

      // Syntax highlighting tags.
      $output = '<p>' . $this->t('Syntax highlighting of source code can be enabled with the following tags:') . '</p>';
      $items = [];
      // Seneric tags.
      $tags = [];
      foreach ($generic_code_tags as $tag) {
        $tags[] = $bracket_open . $tag . $bracket_close;
      }
      $items[] = $this->t('Generic syntax highlighting tags: <code>@tags</code>.', ['@tags' => Markup::create(implode(', ', $tags))]);
      // Language tags.
      $tags = [];
      foreach ($language_tags as $tag) {
        $tags[] = $this->t('<code>@tag</code> for @lang source code', [
          '@tag' => Markup::create($bracket_open . $tag . $bracket_close),
          '@lang' => $languages[$tag_to_lang[$tag]],
        ]);
      }
      $items[] = '<li>' . $this->t('Language specific syntax highlighting tags:') . implode(', ', $tags) . '</li>';
      // PHP specific delimiters.
      if (in_array(GeshiFilter::BRACKETS_PHPBLOCK, $tag_styles)) {
        $items[] = $this->t('PHP source code can also be enclosed in &lt;?php ... ?&gt; or &lt;% ... %&gt;, but additional options like line numbering are not possible here.');
      }

      $output .= '<ul>' . implode('', $items) . '</ul>';

      // Options and tips.
      $output .= '<p>' . $this->t('Options and tips:') . '</p>';
      $items = [];

      // Info about language attribute to language mapping.
      $att_to_full = [];
      foreach ($languages as $langcode => $fullname) {
        $att_to_full[$langcode] = $fullname;
      }
      foreach ($tag_to_lang as $tag => $lang) {
        $att_to_full[$tag] = $languages[$lang];
      }
      ksort($att_to_full);
      $att_for_full = [];
      foreach ($att_to_full as $att => $fullname) {
        $att_for_full[] = $this->t('"<code>@langcode</code>" (for @fullname)', ['@langcode' => $att, '@fullname' => $fullname]);
      }
      $items[] = $this->t('The language for the generic syntax highlighting tags can be
        specified with one of the attribute(s): %attributes. The possible values
        are: @languages.', [
          '%attributes' => implode(', ', $lang_attributes),
          '@languages' => Markup::create(implode(', ', $att_for_full)),
        ]
      );

      // Tag style options.
      if (count($tag_style_examples) > 1) {
        $items[] = $this->t('The supported tag styles are: @tag_styles.', ['@tag_styles' => Markup::create(implode(', ', $tag_style_examples))]);
      }

      // Line numbering options.
      $items[] = $this->t('<em>Line numbering</em> can be enabled/disabled with the
        attribute "%linenumbers". Possible values are: "%off" for no line
        numbers, "%normal" for normal line numbers and "%fancy" for fancy line
        numbers (every n<sup>th</sup> line number highlighted). The start line
        number can be specified with the attribute "%start", which implicitly
        enables normal line numbering. For fancy line numbering the interval
        for the highlighted line numbers can be specified with the attribute
        "%interval", which implicitly enables fancy line numbering.', [
          '%linenumbers' => GeshiFilter::ATTRIBUTE_LINE_NUMBERING,
          '%off' => 'off',
          '%normal' => 'normal',
          '%fancy' => 'fancy',
          '%start' => GeshiFilter::ATTRIBUTE_LINE_NUMBERING_START,
          '%interval' => GeshiFilter::ATTRIBUTE_FANCY_N,
        ]
      );

      // Block versus inline.
      $items[] = $this->t('If the source code between the tags contains a newline (e.g.
        immediatly after the opening tag), the highlighted source code will be
        displayed as a code block. Otherwise it will be displayed inline.');

      // Code block title.
      $items[] = $this->t('A title can be added to a code block with the attribute "%title".', [
        '%title' => GeshiFilter::ATTRIBUTE_TITLE,
      ]);

      $render = [
        '#theme' => 'item_list',
        '#items' => $items,
        '#type' => 'ul',
      ];
      $output .= render($render);

      // Defaults.
      $output .= '<p>' . $this->t('Defaults:') . '</p>';
      $items = [];
      $default_highlighting = $this->config->get('default_highlighting');
      switch ($default_highlighting) {
        case GeshiFilter::DEFAULT_DONOTHING:
          $description = $this->t("when no language attribute is specified the code
            block won't be processed by the GeSHi filter");
          break;

        case GeshiFilter::DEFAULT_PLAINTEXT:
          $description = $this->t('when no language attribute is specified, no syntax
           highlighting will be done');
          break;

        default:
          $description = $this->t('the default language used for syntax highlighting is
            "%default_lang"', ['%default_lang' => $default_highlighting]);
          break;
      }
      $items[] = $this->t('Default highlighting mode for generic syntax highlighting
        tags: @description.', ['@description' => $description]);
      $default_line_numbering = $this->config->get('default_line_numbering');
      switch ($default_line_numbering) {
        case GeshiFilter::LINE_NUMBERS_DEFAULT_NONE:
          $description = $this->t('no line numbers');
          break;

        case GeshiFilter::LINE_NUMBERS_DEFAULT_NORMAL:
          $description = $this->t('normal line numbers');
          break;

        default:
          $description = $this->t('fancy line numbers (every @n lines)', ['@n' => $default_line_numbering]);
          break;
      }
      $items[] = $this->t('Default line numbering: @description.', ['@description' => $description]);
      $render = [
        '#theme' => 'item_list',
        '#items' => $items,
        '#type' => 'ul',
      ];
      $output .= render($render);
    }
    else {
      // Get the available tags.
      list($generic_code_tags, $language_tags, $tag_to_lang) = $this->getTags();
      $tags = [];
      foreach ($generic_code_tags as $tag) {
        $tags[] = '<code>' . $bracket_open . SafeMarkup::checkPlain($tag) . $bracket_close . '</code>';
      }
      foreach ($language_tags as $tag) {
        $tags[] = '<code>' . $bracket_open . SafeMarkup::checkPlain($tag) . $bracket_close . '</code>';
      }
      $output = $this->t('You can enable syntax highlighting of source code with the following tags: @tags.', ['@tags' => Markup::create(implode(', ', $tags))]);
      // Tag style options.
      if (count($tag_style_examples) > 1) {
        $output .= ' ' . $this->t('The supported tag styles are: @tag_styles.', ['@tag_styles' => Markup::create(implode(', ', $tag_style_examples))]);
      }
      if (in_array(GeshiFilter::BRACKETS_PHPBLOCK, $tag_styles)) {
        $output .= ' ' . $this->t('PHP source code can also be enclosed in &lt;?php ... ?&gt; or &lt;% ... %&gt;.');
      }
    }
    return $output;
  }

  /**
   * Create the settings form for the filter.
   *
   * @param array $form
   *   A minimally prepopulated form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The state of the (entire) configuration form.
   *
   * @return array
   *   The $form array with additional form elements for the settings of
   *   this filter. The submitted form values should match $this->settings.
   *
   * @todo Add validation of submited form values, it already exists for
   *       drupal 7, must update it only.
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    if ($this->configEditable->get('use_format_specific_options')) {
      // Tags and attributes.
      $form['general_tags'] = $this->generalHighlightTagsSettings();
      // Per language tags.
      $form['per_language_settings'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Per language tags'),
        '#collapsible' => TRUE,
        'table' => $this->perLanguageSettings('enabled', FALSE, TRUE),
      ];
      // Validate the tags
      // $form['#validate'][] = '::validateForm';.
    }
    else {
      $form['info'] = [
        '#markup' => '<p>' . $this->t('GeSHi filter is configured to use global tag
          settings. For separate settings per text format, enable this option in
          the <a href=":geshi_admin_url">general GeSHi filter settings</a>.', [
            ':geshi_admin_url' => Url::fromRoute('geshifilter.settings')->toString(),
          ]
        ) . '</p>',
      ];
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  /*public function validateForm(array &$form, FormStateInterface $form_state) {
  // Language tags should differ from each other.
  $languages = GeshiFilter::getAvailableLanguages();

  $values = $form_state->getValue('language');
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
  $generics = GeshiFilter::tagSplit($this->config->get('tags'));
  $tags2 = array_merge($tags2, $generics);

  // And now we can check tags1 against tags2.
  foreach ($tags1 as $tag1) {
  foreach ($tags2 as $tag2) {
  if ($tag1 == $tag2) {
  $name = "language[{$language2}][tags]";
  $form_state->setErrorByName($name, t('The language tags should differ between
  languages and from the generic tags.'));
  }
  }
  }
  }
  }
  }*/

  /**
   * Get the tags for this filter.
   *
   * @return string
   *   A string with the tags for this filter.
   */
  protected function tags() {
    if (!$this->config->get('use_format_specific_options')) {
      // We do not want per filter tags, so get the global tags.
      return $this->config->get('tags');
    }
    else {
      if (isset($this->settings['general_tags']['tags'])) {
        // Tags are set for this format.
        return $this->settings['general_tags']['tags'];
      }
      else {
        // Tags are not set for this format, so use the global ones.
        return $this->config->get('tags');
      }
    }
  }

  /**
   * Helper function for gettings the tags.
   *
   * Old: _geshifilter_get_tags.
   *
   * @todo: recreate a cache for this function.
   */
  protected function getTags() {
    $generic_code_tags = GeshiFilter::tagSplit($this->tags());
    $language_tags = [];
    $tag_to_lang = [];
    $enabled_languages = GeshiFilter::getEnabledLanguages();
    foreach ($enabled_languages as $language => $fullname) {
      $lang_tags = GeshiFilter::tagSplit($this->languageTags($language));
      foreach ($lang_tags as $lang_tag) {
        $language_tags[] = $lang_tag;
        $tag_to_lang[$lang_tag] = $language;
      }
    }

    return [
      $generic_code_tags,
      $language_tags,
      $tag_to_lang,
    ];
  }

  /**
   * Helper function for some settings form fields.
   */
  protected function generalHighlightTagsSettings() {
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
        GeshiFilter::BRACKETS_SQUARE => '<code>' . htmlentities('[foo] ... [/foo]') . '</code>',
        GeshiFilter::BRACKETS_DOUBLESQUARE => '<code>' . htmlentities('[[foo]] ... [[/foo]]') . '</code>',
        GeshiFilter::BRACKETS_PHPBLOCK => $this->t('PHP style source code blocks: <code>@php</code> and <code>@percent</code>', [
          '@php' => '<?php ... ?>',
          '@percent' => '<% ... %>',
        ]),
        GeshiFilter::BRACKETS_MARKDOWNBLOCK => '<code>' . htmlentities('```foo ... ```') . '</code>',
      ],
      '#default_value' => $this->tagStyles(),
      '#description' => $this->t('Select the container tag styles that should trigger GeSHi syntax highlighting.'),
    ];
    // Decode entities.
    $form["decode_entities"] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Decode entities'),
      '#default_value' => $this->settings['general_tags']['decode_entities'],
      '#description' => $this->t('Decode entities, for example, if the code has been typed in a WYSIWYG editor.'),
    ];
    return $form;
  }

  /**
   * Function for generating a form table for per language settings.
   *
   * @param string $view
   *   Which languages to show:
   *   - enabled Show only enabled languages.
   *   - disabled Show only disabled languages.
   *   - all Show all languages.
   * @param bool $add_checkbox
   *   When add(TRUE) or not a  checkbox to enable languages.
   * @param bool $add_tag_option
   *   When add(TRUE) or not a textbox to set the tags for a language.
   *
   * @return array
   *   An array with form elements for languages.
   */
  protected function perLanguageSettings($view, $add_checkbox, $add_tag_option) {
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
      $enabled = $this->config->get("language.{$language}.enabled", FALSE);
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
          '#default_value' => $this->settings['per_language_settings']['table']['language'][$language]['tags'],
          '#size' => 20,
        ];
      }
    }
    return $form;
  }

  /**
   * Get the tags for a language.
   *
   * @param string $language
   *   The language to get the tags(ex: php, html, ...).
   *
   * @return string
   *   The tags for the language(ex: [php],[php5],...).
   */
  private function languageTags($language) {
    if (!$this->config->get('use_format_specific_options')) {
      return $this->config->get("language.{$language}.tags");
    }
    else {
      $settings = $this->settings["per_language_settings"]['table']['language'];
      if (isset($settings[$language]["tags"])) {
        // Tags are set for this language.
        return $settings[$language]["tags"];
      }
      else {
        // Tags are not set for this language, so use the global ones.
        return $this->config->get("language.{$language}.tags");
      }
    }
  }

  /**
   * Get the tag style.
   *
   * @return array
   *   Where to use [], <>, or both for tags.
   */
  protected function tagStyles() {
    if ($this->config->get('use_format_specific_options') == FALSE) {
      // Get global tag styles.
      $styles = $this->config->get('tag_styles');
    }
    else {
      if (isset($this->settings['general_tags']["tag_styles"])) {
        // Tags are set for this language.
        $styles = $this->settings['general_tags']["tag_styles"];
      }
      else {
        // Tags are not set for this language, so use the global ones.
        $styles = $this->config->get('tag_styles');
      }
    }
    return $styles;
  }

  /**
   * Callback for preg_replace_callback.
   *
   * Old: _geshifilter_replace_callback($match, $format).
   *
   * @param array $match
   *   Elements from array:
   *   - 0: complete matched string.
   *   - 1: tag name.
   *   - 2: tag attributes.
   *   - 3: tag content.
   *
   * @return string
   *   Return the string processed by geshi library.
   */
  protected function replaceCallback(array $match) {
    $complete_match = $match[0];
    $tag_name = $match[1];
    $tag_attributes = $match[2];
    $source_code = $match[3];

    // Undo linebreak and escaping from preparation phase.
    $source_code = Html::decodeEntities($source_code);

    // Initialize to default settings.
    $lang = $this->config->get('default_highlighting');
    $line_numbering = $this->config->get('default_line_numbering');
    $linenumbers_start = 1;
    $title = NULL;

    // Determine language based on tag name if possible.
    list($generic_code_tags, $language_tags, $tag_to_lang) = $this->getTags();
    if (in_array(GeshiFilter::BRACKETS_PHPBLOCK, array_filter($this->tagStyles()))) {
      $language_tags[] = 'questionmarkphp';
      $tag_to_lang['questionmarkphp'] = 'php';
    }
    if (isset($tag_to_lang[$tag_name])) {
      $lang = $tag_to_lang[$tag_name];
    }

    // Get additional settings from the tag attributes.
    $settings = $this->parseAttributes($tag_attributes);
    if (isset($settings['language'])) {
      $lang = $settings['language'];
    }
    if (isset($settings['line_numbering'])) {
      $line_numbering = $settings['line_numbering'];
    }
    if (isset($settings['linenumbers_start'])) {
      $linenumbers_start = $settings['linenumbers_start'];
    }
    if (isset($settings['title'])) {
      $title = $settings['title'];
    }
    if (isset($settings['special_lines'])) {
      $special_lines = $settings['special_lines'];
    }

    if ($lang == GeshiFilter::DEFAULT_DONOTHING) {
      // Do nothing, and return the original.
      return $complete_match;
    }
    if ($lang == GeshiFilter::DEFAULT_PLAINTEXT) {
      // Use plain text 'highlighting'.
      $lang = 'text';
    }
    $inline_mode = (strpos($source_code, "\n") === FALSE);
    // Process and return.
    return GeshiFilterProcess::processSourceCode($source_code, $lang, $line_numbering, $linenumbers_start, $inline_mode, $title, $special_lines);
  }

  /**
   * Helper function for parsing the attributes of GeSHi code tags.
   *
   * Get the settings for language, line numbers, etc.
   *
   * @param string $attributes
   *   String with the attributes.
   *
   * @return array
   *   An array of settings with fields 'language', 'line_numbering',
   *   'linenumbers_start' and 'title'.
   */
  public function parseAttributes($attributes) {
    // Initial values.
    $lang = NULL;
    $line_numbering = NULL;
    $linenumbers_start = NULL;
    $title = NULL;
    $special_lines = [];
    $class_to_lang = NULL;

    // Get the possible tags and languages.
    list($generic_code_tags, $language_tags, $tag_to_lang) = $this->getTags();

    $language_attributes = GeshiFilter::whitespaceExplode(GeshiFilter::ATTRIBUTES_LANGUAGE);
    $attributes_preg_string = implode('|', array_merge(
      $language_attributes, [
        GeshiFilter::ATTRIBUTE_LINE_NUMBERING,
        GeshiFilter::ATTRIBUTE_LINE_NUMBERING_START,
        GeshiFilter::ATTRIBUTE_FANCY_N,
        GeshiFilter::ATTRIBUTE_TITLE,
        GeshiFilter::ATTRIBUTE_SPECIAL_LINES,
      ]
    ));
    $enabled_languages = GeshiFilter::getEnabledLanguages();

    // Parse $attributes to an array $attribute_matches with:
    // $attribute_matches[0][xx] fully matched string, e.g. 'language="python"'
    // $attribute_matches[1][xx] param name, e.g. 'language'
    // $attribute_matches[2][xx] param value, e.g. 'python'.
    preg_match_all('#(' . $attributes_preg_string . ')="?([^"]*)"?#', $attributes, $attribute_matches);

    foreach ($attribute_matches[1] as $a_key => $att_name) {
      // Get attribute value.
      $att_value = $attribute_matches[2][$a_key];

      // Check for the language attributes.
      $class_to_lang = str_replace('language-', '', $att_value);

      if (in_array($att_name, $language_attributes)) {
        // Try first to map the attribute value to geshi language code.
        if (in_array($att_value, $language_tags)) {
          $att_value = $tag_to_lang[$att_value];
        }
        // Set language if extracted language is an enabled language.
        if (array_key_exists($att_value, $enabled_languages)) {
          $lang = $att_value;
        }
        // class_to_lang hotfix: language never filled cause ckeditor plugin uses classes instead of tags.
        elseif($att_name == 'class' && array_key_exists($class_to_lang, $enabled_languages)) {
          $lang = $class_to_lang;
        }
      }

      // Check for line numbering related attributes.
      // $line_numbering defines the line numbering mode:
      // 0: no line numbering.
      // 1: normal line numbering.
      // n>= 2: fancy line numbering every nth line.
      elseif ($att_name == GeshiFilter::ATTRIBUTE_LINE_NUMBERING) {
        switch (strtolower($att_value)) {
          case "off":
            $line_numbering = 0;
            break;

          case "normal":
            $line_numbering = 1;
            break;

          case "fancy":
            $line_numbering = 5;
            break;
        }
      }
      elseif ($att_name == GeshiFilter::ATTRIBUTE_FANCY_N) {
        $att_value = (int) ($att_value);
        if ($att_value >= 2) {
          $line_numbering = $att_value;
        }
      }
      elseif ($att_name == GeshiFilter::ATTRIBUTE_LINE_NUMBERING_START) {
        if ($line_numbering < 1) {
          $line_numbering = 1;
        }
        $linenumbers_start = (int) ($att_value);
      }
      elseif ($att_name == GeshiFilter::ATTRIBUTE_TITLE) {
        $title = $att_value;
      }
      elseif ($att_name == GeshiFilter::ATTRIBUTE_SPECIAL_LINES) {
        $special_lines = explode(',', $att_value);
      }
    }

    // Return parsed results.
    return [
      'language' => $lang,
      'line_numbering' => $line_numbering,
      'linenumbers_start' => $linenumbers_start,
      'title' => $title,
      'special_lines' => $special_lines,
    ];
  }

  /**
   * Callback_geshifilter_prepare for preparing input text.
   *
   * Replaces the code tags brackets with geshifilter specific ones to prevent
   * possible messing up by other filters, e.g.
   *   '[python]foo[/python]' to '[geshifilter-python]foo[/geshifilter-python]'.
   * Replaces newlines with "&#10;" to prevent issues with the line break filter
   * Escapes the tricky characters like angle brackets with
   * SafeMarkup::checkPlain() to prevent messing up by other filters like the
   * HTML filter.
   *
   * @param array $match
   *   An array with the pieces from matched string.
   *   - 0: complete matched string.
   *   - 1: opening bracket ('<' or '[').
   *   - 2: tag.
   *   - 3: and.
   *   - 4: attributes.
   *   - 5: closing bracket.
   *   - 6: source code.
   *   - 7: closing tag.
   *
   * @return string
   *   Return escaped code block.
   */
  public function prepareCallback(array $match) {
    $tag_name = $match[2];
    $tag_attributes = $match[3];
    $content = $match[6];

    // Get the default highlighting mode.
    $lang = $this->config->get('default_highlighting');
    if ($lang == GeshiFilter::DEFAULT_DONOTHING) {
      // If the default highlighting mode is GeshiFilter::DEFAULT_DONOTHING
      // and there is no language set (with language tag or language attribute),
      // we should not do any escaping in this prepare phase,
      // so that other filters can do their thing.
      $enabled_languages = GeshiFilter::getEnabledLanguages();
      // Usage of language tag?
      list($generic_code_tags, $language_tags, $tag_to_lang) = $this->getTags();
      if (isset($tag_to_lang[$tag_name]) && isset($enabled_languages[$tag_to_lang[$tag_name]])) {
        $lang = $tag_to_lang[$tag_name];
      }
      // Usage of language attribute?
      else {
        // Get additional settings from the tag attributes.
        $settings = $this->parseAttributes($tag_attributes);
        if ($settings['language'] && isset($enabled_languages[$settings['language']])) {
          $lang = $settings['language'];
        }
      }
      // If no language was set: prevent escaping and return original string.
      if ($lang == GeshiFilter::DEFAULT_DONOTHING) {
        return $match[0];
      }
    }
    if ($this->decodeEntities()) {
      $content = $this->unencode($content);
    }
    // Return escaped code block.
    return '[geshifilter-' . $tag_name . $tag_attributes . ']'
      . str_replace(["\r", "\n"], ['', '&#10;'], SafeMarkup::checkPlain($content))
      . '[/geshifilter-' . $tag_name . ']';
  }

  /**
   * Callback for _geshifilter_prepare for < ?php ... ? > blocks.
   *
   * @param array $match
   *   An array with the pieces from matched string.
   */
  public function preparePhpCallback(array $match) {
    if ($this->decodeEntities()) {
      $match[2] = $this->unencode($match[2]);
    }
    return '[geshifilter-questionmarkphp]'
    . str_replace(["\r", "\n"], ['', '&#10;'], SafeMarkup::checkPlain($match[2]))
    . '[/geshifilter-questionmarkphp]';
  }

  /**
   * Callback for preparing input text from markdown(```) tags.
   *
   * Replaces the code tags brackets with geshifilter specific ones to prevent
   * possible messing up by other filters, e.g.
   *   '[python]foo[/python]' to '[geshifilter-python]foo[/geshifilter-python]'.
   * Replaces newlines with "&#10;" to prevent issues with the line break filter
   * Escapes the tricky characters like angle brackets with
   * SafeMarkup::checkPlain() to prevent messing up by other filters like the
   * HTML filter.
   *
   * @param array $match
   *   An array with the pieces from matched string.
   *
   * @return string
   *   Return escaped code block.
   */
  public function prepareMarkdownCallback(array $match) {
    $tag_name = $match[2];
    $tag_attributes = '';
    $content = $match[3];

    // Get the default highlighting mode.
    $lang = $this->config->get('default_highlighting');
    if ($lang == GeshiFilter::DEFAULT_DONOTHING) {
      // If the default highlighting mode is GeshiFilter::DEFAULT_DONOTHING
      // and there is no language set (with language tag or language attribute),
      // we should not do any escaping in this prepare phase,
      // so that other filters can do their thing.
      $enabled_languages = GeshiFilter::getEnabledLanguages();
      // Usage of language tag?
      list($generic_code_tags, $language_tags, $tag_to_lang) = $this->getTags();
      if (isset($tag_to_lang[$tag_name]) && isset($enabled_languages[$tag_to_lang[$tag_name]])) {
        $lang = $tag_to_lang[$tag_name];
      }

      // If no language was set: prevent escaping and return original string.
      if ($lang == GeshiFilter::DEFAULT_DONOTHING) {
        return $match[0];
      }
    }
    if ($this->decodeEntities()) {
      $content = $this->unencode($content);
    }
    // Return escaped code block.
    return '[geshifilter-' . $tag_name . $tag_attributes . ']'
      . str_replace(["\r", "\n"], ['', '&#10;'], SafeMarkup::checkPlain($content))
      . '[/geshifilter-' . $tag_name . ']';
  }

  /**
   * Return the string with some html entities unencoded.
   *
   * Text editors like ckeditor encodes some strings, like " to &amp&quote;,
   * we must undo this on code passed to geshifilter.
   *
   * @param string $text
   *   The original text.
   *
   * @return string
   *   The text unencoded.
   */
  public function unencode($text) {
    $text = html_entity_decode($text, ENT_QUOTES);
    return $text;
  }

  /**
   * Return when we need to decode html entities for this filter.
   *
   * @return bool
   *   Return TRUE if we need to decode the entities.
   */
  protected function decodeEntities() {
    if (!$this->config->get('use_format_specific_options')) {
      // Return global value.
      return $this->config->get('decode_entities');
    }
    // Return value for this filter.
    return $this->settings['general_tags']['decode_entities'];
  }

}
