<?php

namespace Drupal\token;

use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Loads taxonomy term parent names for term tokens.
 *
 * The service lives for the whole request, so the parent names it loads are
 * kept until the request ends, or until resetParents() is called.
 *
 * @internal
 */
class TokenTermHelper {

  /**
   * The taxonomy term parent names, keyed by language code and term ID.
   *
   * @var array
   */
  protected array $parents = [];

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityRepositoryInterface $entityRepository,
  ) {
  }

  /**
   * Loads the names of all the parents of a taxonomy term.
   *
   * @param int $tid
   *   The term ID.
   * @param string $langcode
   *   The language code.
   *
   * @return string[]
   *   The parent names, keyed by term ID, from the root down.
   */
  public function loadParents(int $tid, string $langcode): array {
    if (!isset($this->parents[$langcode][$tid])) {
      $this->parents[$langcode][$tid] = [];
      /** @var \Drupal\taxonomy\TermStorageInterface $term_storage */
      $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
      $terms = $term_storage->loadAllParents($tid);
      // Remove this term from the array.
      array_shift($terms);
      foreach (array_reverse($terms) as $term) {
        $translation = $this->entityRepository->getTranslationFromContext($term, $langcode);
        $this->parents[$langcode][$tid][$term->id()] = $translation->label();
      }
    }

    return $this->parents[$langcode][$tid];
  }

  /**
   * Forgets the taxonomy term parent names, so they are loaded again.
   */
  public function resetParents(): void {
    $this->parents = [];
  }

}
