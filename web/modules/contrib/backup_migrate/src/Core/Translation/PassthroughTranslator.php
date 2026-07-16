<?php

namespace Drupal\backup_migrate\Core\Translation;

/**
 * Passes through the us-english strings with the replacement tokens.
 *
 * @package Drupal\backup_migrate\Core\Service
 */
class PassthroughTranslator implements TranslatorInterface {

  /**
   * Translates a string without localization.
   *
   * @param string $string
   *   The string.
   *   The string to be translated.
   * @param mixed $replacements
   *   The replacements.
   *   Any untranslatable variables to be replaced into the string.
   * @param mixed $context
   *   The context.
   *   Extra context to help translators distinguish ambiguous strings.
   *
   * @return mixed
   *   The return value.
   */
  public function translate($string, $replacements = [], $context = []) {
    // Provide Drupal-like escaping of replacement values.
    foreach ($replacements as $key => $value) {
      switch (substr($key, 0, 1)) {
        case '@':
        case '%':
          $replacements[$key] = strip_tags($value);
          break;
      }
    }

    return strtr($string, $replacements);
  }

}
