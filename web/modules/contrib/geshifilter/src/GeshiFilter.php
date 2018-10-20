<?php

namespace Drupal\geshifilter;

use Drupal\Core\Url;

/**
 * Contains constantas and some helper functions.
 */
class GeshiFilter {
  /**
   * Default for sintax highting, format as plain text.
   */
  const DEFAULT_PLAINTEXT = 'GESHIFILTER_DEFAULT_PLAINTEXT';

  /**
   * Default for sintax highting, do nothing.
   */
  const DEFAULT_DONOTHING = 'GESHIFILTER_DEFAULT_DONOTHING';

  /**
   * CSS modes, inline.
   */
  const CSS_INLINE = 1;

  /**
   * Usage of CSS classes and an automatically managaged external stylesheet.
   */
  const CSS_CLASSES_AUTOMATIC = 2;

  /**
   * Only add CSS classes to markup.
   *
   * Admin/themer is responsible for defining the CSS rules.
   */
  const CSS_CLASSES_ONLY = 3;

  /**
   * Attributes valid to set language, by example, [code language="php"].
   */
  const ATTRIBUTES_LANGUAGE = 'type lang language class';

  /**
   * Attributes valid to set line numbering.
   */
  const ATTRIBUTE_LINE_NUMBERING = 'linenumbers';

  /**
   * Attributes valid to set line start.
   */
  const ATTRIBUTE_LINE_NUMBERING_START = 'start';

  /**
   * Attributes valid to set the interval of fancy lines.
   */
  const ATTRIBUTE_FANCY_N = 'fancy';

  /**
   * Attributes valid to set title.
   */
  const ATTRIBUTE_TITLE = 'title';

  /**
   * Attributes valid to set special lines(lines to highlight).
   */
  const ATTRIBUTE_SPECIAL_LINES = 'special';

  /**
   * Parse code with tags inside <>, example, <code>.
   */
  const BRACKETS_ANGLE = 1;

  /**
   * Parse code with tags inside [], example, [code].
   */
  const BRACKETS_SQUARE = 2;

  /**
   * Deprecated, only used in upgrade path.
   */
  const BRACKETS_BOTH = 3;

  /**
   * Parse code with tags inside [[]], example, [[code]].
   */
  const BRACKETS_DOUBLESQUARE = 4;

  /**
   * Parse code with tags inside <?php ?>, example, <?php echo('hi'); ?>.
   */
  const BRACKETS_PHPBLOCK = 8;

  /**
   * Parse code inside Markdown (```) blocks.
   */
  const BRACKETS_MARKDOWNBLOCK = 16;

  /**
   * No line numbers.
   */
  const LINE_NUMBERS_DEFAULT_NONE = 0;

  /**
   * Normal line numbers.
   */
  const LINE_NUMBERS_DEFAULT_NORMAL = 1;

  /**
   * Fancy line numbers, each 5.
   */
  const LINE_NUMBERS_DEFAULT_FANCY5 = 5;

  /**
   * Fancy line numbers, each 10.
   */
  const LINE_NUMBERS_DEFAULT_FANCY10 = 10;

  /**
   * Fancy line numbers, each 20.
   */
  const LINE_NUMBERS_DEFAULT_FANCY20 = 20;

  /**
   * Helper function for splitting a string on white spaces.
   *
   * Using explode(' ', $string) is not enough because it returns empty elements
   * if $string contains consecutive spaces.
   *
   * @param string $string
   *   The string to split by spaces.
   *
   * @return array
   *   Return the string split by white spaces.
   */
  public static function whitespaceExplode($string) {
    return preg_split('/\s+/', $string, -1, PREG_SPLIT_NO_EMPTY);
  }

  /**
   * Split a string with tags to an array.
   *
   * @param string $string
   *   The tags to split.
   *
   * @return array
   *   The tag split.
   */
  public static function tagSplit($string) {
    return preg_split('/\s+|<|>|\[|\]/', $string, -1, PREG_SPLIT_NO_EMPTY);
  }

  /**
   * List of available languages.
   *
   * @return array
   *   An array mapping language code to array with the language path and
   *   full language name.
   */
  public static function getAvailableLanguages() {
    // Try to get it from cache (database actually).
    $cache = \Drupal::cache();
    $available_languages = $cache->get('geshifilter_available_languages_cache');
    if (!$available_languages) {
      // Not in cache: build the array of available_languages.
      $geshi_library = GeshiFilter::loadGeshi();
      $available_languages = [];
      if ($geshi_library['loaded']) {
        $dirs = [
          $geshi_library['library path'] . '/geshi',
          drupal_get_path('module', 'geshifilter') . '/geshi-extra',
        ];
        foreach ($dirs as $dir) {
          foreach (file_scan_directory($dir, '/.[pP][hH][pP]$/i') as $filename => $fileinfo) {
            // Short name.
            $name = $fileinfo->name;
            // Get full name.
            $geshi = new \GeSHi('', $name);
            $geshi->set_language_path($dir);
            $fullname = $geshi->get_language_name();
            unset($geshi);
            // Store.
            $available_languages[$name] = ['language_path' => $dir, 'fullname' => $fullname];
          }
        }
        ksort($available_languages);
        // Save array to database.
        $cache->set('geshifilter_available_languages_cache', $available_languages);
      }
    }
    else {
      $available_languages = $available_languages->data;
    }
    return $available_languages;
  }

  /**
   * List of enabled languages(with caching).
   *
   * @return array
   *   Array with enabled languages mapping language code to full name.
   */
  public static function getEnabledLanguages() {
    $config = \Drupal::config('geshifilter.settings');
    static $enabled_languages = NULL;
    if ($enabled_languages === NULL) {
      $enabled_languages = [];
      $languages = self::getAvailableLanguages();
      foreach ($languages as $language => $language_data) {
        if ($config->get('language.' . $language . ".enabled")) {
          $enabled_languages[$language] = $language_data['fullname'];
        }
      }
    }
    return $enabled_languages;
  }

  /**
   * Load geshi library.
   *
   * If the geshi library is installed with composer, we use it, if not, we
   * try to use it with libraries module(same way as drupal 7).
   *
   * @return array
   *   Return an array with the same keys(the ones we use) from
   *   libraries_load().
   */
  public static function loadGeshi() {
    $library = [];
    // Try include geshi from composer.
    if (class_exists('GeSHi')) {
      $library['loaded'] = TRUE;
      $library['library path'] = GESHI_ROOT;
    }
    // Try include geshi using libraries module.
    elseif (\Drupal::moduleHandler()->moduleExists('libraries')) {
      $library = libraries_load('geshi');
    }
    // Geshi is not available from composer and libraries module is not
    // available, so we return the same as libraries when the geshi do not
    // exist.
    else {
      $library['loaded'] = FALSE;
      $library['library path'] = '';
      $library['error message'] = t('The GeSHi filter requires the GeSHi library (which needs to be @downloaded and installed seperately). Please review the install instruction at @readme.', [
        '@downloaded' => \Drupal::l(t('downloaded'), Url::fromUri('http://qbnz.com/highlighter/')),
        '@readme' => \Drupal::l(t('README.TXT'), Url::fromUri('http://cgit.drupalcode.org/geshifilter/tree/README.txt?h=8.x-1.x')),
      ]);
    }
    return $library;
  }

}
