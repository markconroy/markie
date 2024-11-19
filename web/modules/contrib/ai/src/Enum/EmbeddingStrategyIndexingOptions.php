<?php

declare(strict_types=1);

namespace Drupal\ai\Enum;

/**
 * The different indexing options available for Embedding Strategies to use.
 */
enum EmbeddingStrategyIndexingOptions: string {
  case MAIN_CONTENT = 'main_content';
  case CONTEXTUAL_CONTENT = 'contextual_content';
  case ATTRIBUTES = 'attributes';
  case IGNORE = 'ignore';

  /**
   * Return the option key.
   *
   * @return string
   *   The key.
   */
  public function getKey(): string {
    return match($this) {
      self::MAIN_CONTENT => 'main_content',
      self::CONTEXTUAL_CONTENT => 'contextual_content',
      self::ATTRIBUTES => 'attributes',
      self::IGNORE => 'ignore',
    };
  }

  /**
   * Return the option label.
   *
   * @return string
   *   The label.
   */
  public function getLabel(): string {
    return match($this) {
      self::MAIN_CONTENT => t('Main content')->__toString(),
      self::CONTEXTUAL_CONTENT => t('Contextual content')->__toString(),
      self::ATTRIBUTES => t('Filterable attributes')->__toString(),
      self::IGNORE => t('Ignore')->__toString(),
    };
  }

  /**
   * Return the option description.
   *
   * @return string
   *   The description.
   */
  public function getDescription(): string {
    return match($this) {
      self::MAIN_CONTENT => t('This is the main body content. It is typically longer and needs to be broken into chunks. Queries by end-users are performed on this content. Usually only one field should be main content and a more advanced Embedding Strategy may be needed to support multiple main contents.')->__toString(),
      self::CONTEXTUAL_CONTENT => t("The main body on its own, when chunked, can often miss its overall context. Repeating contextual details into each chunk, such as title, summary, or authors can help provide better results for queries. This content is therefore appended to each chunk of 'Main content' and therefore queries by end-users also use this context to provide better results. For entity reference fields, ensure you select 'fulltext' or 'string' for the 'Type' to vectorize the label instead of the ID.")->__toString(),
      self::ATTRIBUTES => t("This content is attached to the record to allow the Vector Database to pre-filter results (such as only search within a specific date range, or on a specific taxonomy term) before searching for relevant content. This allows the end-users' query to be run against a sub-set of the Vector Database only. For entity reference fields, stick to integers for filters.")->__toString(),
      self::IGNORE => t('Skip this option when indexing for now. This is effectively the same as not adding the field at all. It is only really useful if you are experimenting with different combinations of fields.')->__toString(),
    };
  }

}
