<?php

namespace Drupal\backup_migrate\Core\Translation;

/**
 * Defines the translatable interface interface.
 *
 * @package Drupal\backup_migrate\Core\Translation
 */
interface TranslatableInterface {

  /**
   * Translate a string.
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
  public function t($string, $replacements = [], $context = []);

}
