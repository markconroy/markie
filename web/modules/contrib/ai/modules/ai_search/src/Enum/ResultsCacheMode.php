<?php

declare(strict_types=1);

namespace Drupal\ai_search\Enum;

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * How cached search results are shared between users of an AI Search index.
 *
 * The AI Search backend checks access to each result for the current user
 * after the vector database responds, which the Search API results cache does
 * not know about. This setting decides which users may share cached results.
 */
enum ResultsCacheMode: string {
  case None = 'none';
  case PerUser = 'per_user';
  case PerRole = 'per_role';
  case Shared = 'shared';

  /**
   * Returns the mode used when an index has not been configured yet.
   *
   * @return self
   *   The default mode.
   */
  public static function default(): self {
    return self::PerRole;
  }

  /**
   * Returns the mode stored for an index, falling back to the default.
   *
   * @param string|null $value
   *   The stored value, if any.
   *
   * @return self
   *   The mode.
   */
  public static function fromStoredValue(?string $value): self {
    return ($value !== NULL ? self::tryFrom($value) : NULL) ?? self::default();
  }

  /**
   * Returns the option label.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The label.
   */
  public function getLabel(): TranslatableMarkup {
    return match($this) {
      self::None => new TranslatableMarkup('Do not cache'),
      self::PerUser => new TranslatableMarkup('Cache per user'),
      self::PerRole => new TranslatableMarkup('Cache per role'),
      self::Shared => new TranslatableMarkup('Single cache for all'),
    };
  }

  /**
   * Returns the option description.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The description.
   */
  public function getDescription(): TranslatableMarkup {
    return match($this) {
      self::None => new TranslatableMarkup('Search results and view output are not cached, so every search queries the embedding provider and vector database. Use this if results must always reflect the latest access rules.'),
      self::PerUser => new TranslatableMarkup('Each user gets their own cached results. Use this if access varies between users who share the same roles, for example authors who can view their own unpublished content, node access grants, or group membership. Uses the most cache storage.'),
      self::PerRole => new TranslatableMarkup('Users with the same roles share cached results. Use this if access is decided by role permissions only. Not safe if access varies between users who share the same roles, for example "View own unpublished content", node access grants, or group membership.'),
      self::Shared => new TranslatableMarkup('All users share the same cached results. Only safe if every indexed item can be viewed by everyone who can reach the search, for example an index of published, public content only.'),
    };
  }

  /**
   * Returns all modes as form options.
   *
   * @return array<string, \Drupal\Core\StringTranslation\TranslatableMarkup>
   *   Labels keyed by stored value.
   */
  public static function getOptions(): array {
    $options = [];
    foreach (self::cases() as $mode) {
      $options[$mode->value] = $mode->getLabel();
    }
    return $options;
  }

}
