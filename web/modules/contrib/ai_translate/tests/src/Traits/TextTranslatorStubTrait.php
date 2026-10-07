<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_translate\Traits;

use Drupal\Core\Language\LanguageInterface;
use Drupal\ai_translate\TextTranslatorInterface;

/**
 * Replaces the translator service with a deterministic stub.
 *
 * For tests that exercise the translation pipeline rather than the provider
 * layer: the whole path from extraction to saving a translation runs, without
 * contacting a provider and without the non-determinism of a real one. Tests
 * that cover the translator itself, such as the caching tests, should use the
 * real service instead.
 *
 * A trait rather than a base class, because this is useful across suites —
 * kernel and functional tests both need it, and each has its own base class.
 */
trait TextTranslatorStubTrait {

  /**
   * Replaces 'ai_translate.text_translator' with the stub.
   *
   * Call after the container is available, so from setUp() or later.
   */
  protected function stubTextTranslator(): void {
    $this->container->set('ai_translate.text_translator', new class implements TextTranslatorInterface {

      /**
       * {@inheritdoc}
       */
      public function translateContent(
        string $input_text,
        LanguageInterface $langTo,
        ?LanguageInterface $langFrom = NULL,
        array $context = [],
      ): string {
        return '[' . $langTo->getId() . '] ' . $input_text;
      }

    });
  }

  /**
   * Returns what the stub translates a given string into.
   *
   * Keeps assertions from hardcoding the stub's format, so the expectation and
   * the stub cannot drift apart.
   *
   * @param string $text
   *   The source text.
   * @param string $langcode
   *   The target langcode.
   *
   * @return string
   *   The translation the stub produces.
   */
  protected function stubTranslationOf(string $text, string $langcode): string {
    return '[' . $langcode . '] ' . $text;
  }

}
