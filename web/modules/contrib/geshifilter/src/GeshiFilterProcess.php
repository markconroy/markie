<?php

namespace Drupal\geshifilter;

// Necessary for SafeMarkup::checkPlain().
use Drupal\Component\Utility\SafeMarkup;

/**
 * Helpers functions related to processing the source code with geshi.
 */
class GeshiFilterProcess {

  /**
   * Geshifilter wrapper for highlight_string() processing of PHP.
   *
   * @param string $source_code
   *   The source code.
   * @param bool $inline_mode
   *   When to use inline styles(TRUE) or a css.
   *
   * @return string
   *   The source code after being processed.
   */
  public static function highlightStringProcess($source_code, $inline_mode) {
    // Make sure that the source code starts with < ?php and ends with ? >.
    $text = trim($source_code);
    if (substr($text, 0, 5) != '<?php') {
      $source_code = '<?php' . $source_code;
    }
    if (substr($text, -2) != '?>') {
      $source_code = $source_code . '?>';
    }
    // Use the right container.
    $container = $inline_mode ? 'span' : 'div';
    // Process with highlight_string()
    $text = '<' . $container . ' class="codeblock geshifilter">' . highlight_string($source_code, TRUE) . '</' . $container . '>';
    // Remove newlines (added by highlight_string()) to avoid issues with the
    // linebreak filter.
    $text = str_replace("\n", '', $text);
    return $text;
  }

  /**
   * Geshifilter wrapper for GeSHi processing.
   *
   * @param string $source_code
   *   Source code to process.
   * @param string $lang
   *   Language from sourcecode.
   * @param int $line_numbering
   *   The line numbering mode, one of LINE_NUMBERS_* from GeshiFilter class.
   * @param int $linenumbers_start
   *   The line number to start from.
   * @param bool $inline_mode
   *   When to write all styles inline or from a css.
   * @param string $title
   *   The title to use in code.
   * @param array $special_lines
   *   Special lines to highlight.
   *
   * @return string
   *   The sourcecode after process by Geshi.
   */
  public static function geshiProcess($source_code, $lang, $line_numbering = 0, $linenumbers_start = 1, $inline_mode = FALSE, $title = NULL, array $special_lines = []) {
    $config = \Drupal::config('geshifilter.settings');
    // Load GeSHi library (if not already).
    $geshi_library = GeshiFilter::loadGeshi();
    if (!$geshi_library['loaded']) {
      drupal_set_message($geshi_library['error message'], 'error');
      return $source_code;
    }

    $source_code = trim($source_code, "\n\r");
    // Create GeSHi object.
    $geshi = self::geshiFactory($source_code, $lang);

    // CSS mode.
    $ccs_mode = $config->get('css_mode');
    if ($ccs_mode == GeshiFilter::CSS_CLASSES_AUTOMATIC || $ccs_mode == GeshiFilter::CSS_CLASSES_ONLY) {
      $geshi->enable_classes(TRUE);
    }
    self::overrideGeshiDefaults($geshi, $lang);
    // Some more GeSHi settings and parsing.
    if ($inline_mode) {
      // Inline source code mode.
      $geshi->set_header_type(GESHI_HEADER_NONE);
      // To make highlighting work we have to manually set a class on the code
      // element we will wrap the code in.
      // To counter a change between GeSHi version 1.0.7.22 and 1.0.8 (svn
      // commit 1610), we use both the language and overall_class for the class,
      // to mimic the 1.0.8 behavior, which is backward compatible.
      // $language and $overall_class are protected with $geshi, with no get
      // functions, recreate them manually.
      $overall_class = 'geshifilter-' . $lang;
      $code_class = "{$lang} {$overall_class}";
      $source_code = '<span class="geshifilter"'
        . (isset($title) ? ' title="' . SafeMarkup::checkPlain($title) . '"' : '')
        . '><code class="' . $code_class . '">' . $geshi->parse_code() . '</code></span>';
    }
    else {
      $geshi->highlight_lines_extra($special_lines);
      // How many spaces to use for tabs.
      $geshi->set_tab_width($config->get('tab_width'));

      // Block source code mode.
      $geshi->set_header_type((int) $config->get('code_container', GESHI_HEADER_PRE));
      if ($line_numbering == 1) {
        $geshi->enable_line_numbers(GESHI_NORMAL_LINE_NUMBERS);
        $geshi->start_line_numbers_at($linenumbers_start);
      }
      elseif ($line_numbering >= 2) {
        $geshi->enable_line_numbers(GESHI_FANCY_LINE_NUMBERS, $line_numbering);
        $geshi->start_line_numbers_at($linenumbers_start);
      }
      if (isset($title)) {
        $source_code = '<div class="geshifilter-title">' . SafeMarkup::checkPlain($title) . '</div>';
      }
      else {
        $source_code = '';
      }
      $source_code .= '<div class="geshifilter">' . $geshi->parse_code() . '</div>';
    }

    return $source_code;
  }

  /**
   * Helper function for overriding some GeSHi defaults.
   *
   * @param \Geshi $geshi
   *   Geshi object.
   * @param string $langcode
   *   The language.
   */
  public static function overrideGeshiDefaults(\Geshi &$geshi, $langcode) {
    $config = \Drupal::config('geshifilter.settings');
    // Override the some default GeSHi styles (e.g. GeSHi uses Courier by
    // default, which is ugly).
    $geshi->set_line_style('font-family: monospace; font-weight: normal;', 'font-family: monospace; font-weight: bold; font-style: italic;');
    $geshi->set_code_style('font-family: monospace; font-weight: normal; font-style: normal');
    // Overall class needed for CSS.
    $geshi->set_overall_class('geshifilter-' . $langcode);
    // Set keyword linking.
    $geshi->enable_keyword_links($config->get('enable_keyword_urls', TRUE));
  }

  /**
   * General geshifilter processing function for a chunk of source code.
   *
   * @param string $source_code
   *   Source code to process.
   * @param string $lang
   *   Language from sourcecode.
   * @param int $line_numbering
   *   The line numbering mode, one of LINE_NUMBERS_* from GeshiFilter class.
   * @param int $linenumbers_start
   *   The line number to start from.
   * @param bool $inline_mode
   *   When to write all styles inline or from a css.
   * @param string $title
   *   The title to use in code.
   * @param array $special_lines
   *   An array with the number of lines to highlight.
   *
   * @paran array $special_lines
   *   Lines to highlight.
   *
   * @return string
   *   The sourcecode after process by Geshi.
   */
  public static function processSourceCode($source_code, $lang, $line_numbering = 0, $linenumbers_start = 1, $inline_mode = FALSE, $title = NULL, array $special_lines = []) {
    $config = \Drupal::config('geshifilter.settings');
    // Process.
    if ($lang == 'php' && $config->get('use_highlight_string_for_php', FALSE)) {
      return self::highlightStringProcess($source_code, $inline_mode);
    }
    else {
      // Process with GeSHi.
      return self::geshiProcess($source_code, $lang, $line_numbering, $linenumbers_start, $inline_mode, $title, $special_lines);
    }
  }

  /**
   * Helper function for generating a GeSHi object.
   *
   * @param string $source_code
   *   The source code to process.
   * @param string $language
   *   The language to generate a GeSHi object for.
   *
   * @return \GeSHi
   *   Return a Geshi class object.
   */
  public static function geshiFactory($source_code, $language) {
    $available_languages = GeshiFilter::getAvailableLanguages();
    $geshi = new \GeSHi($source_code, $language);
    $geshi->set_language_path($available_languages[$language]['language_path']);
    return $geshi;
  }

}
