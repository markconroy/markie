<?php

namespace Drupal\ai_translate;

use Drupal\Core\Language\LanguageInterface;

/**
 * Defines text translator interface.
 */
interface TextTranslatorInterface {

  /**
   * Get the text translated by AI API call.
   *
   * @param string $input_text
   *   Input prompt for the LLm.
   * @param \Drupal\Core\Language\LanguageInterface $langTo
   *   Destination language.
   * @param \Drupal\Core\Language\LanguageInterface|null $langFrom
   *   Optional source language.
   * @param array $context
   *   Optional context, passed through to the provider. This module reads
   *   the entity_context key (entity_type, bundle, id), built by
   *   AiTranslateEntityContext::toContext(), so Context Control Center
   *   entity scopes can match; callers with no entity omit it. Other
   *   modules may add their own keys for their own providers, as
   *   ai_translate_plus does.
   *
   * @return string
   *   Translated content.
   *
   * @throws \Drupal\ai_translate\TranslationException
   */
  public function translateContent(
    string $input_text,
    LanguageInterface $langTo,
    ?LanguageInterface $langFrom = NULL,
    array $context = [],
  ) : string;

}
