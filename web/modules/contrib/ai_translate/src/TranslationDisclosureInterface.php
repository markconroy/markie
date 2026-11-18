<?php

declare(strict_types=1);

namespace Drupal\ai_translate;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Language\LanguageInterface;

/**
 * Defines an interface for recording AI disclosures on AI translations.
 */
interface TranslationDisclosureInterface {

  /**
   * Whether AI disclosure is available and switched on.
   */
  public function isEnabled(): bool;

  /**
   * Discloses that a translation was produced by AI.
   *
   * Call this on the translation, after its translated values are set and
   * before it is saved: the caller's own save persists the change.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $translation
   *   The translation that holds the AI generated text.
   * @param \Drupal\Core\Language\LanguageInterface|null $langFrom
   *   The language the text was translated from, if known.
   * @param \Drupal\Core\Entity\ContentEntityInterface|null $source
   *   The entity a NEW translation was made from, so that the copy of its
   *   disclosure can be replaced. Leave NULL for an existing translation,
   *   whose own disclosure is kept.
   */
  public function apply(ContentEntityInterface $translation, ?LanguageInterface $langFrom = NULL, ?ContentEntityInterface $source = NULL): void;

}
