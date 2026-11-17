<?php

declare(strict_types=1);

namespace Drupal\key_deprecation_test\Plugin\KeyProvider;

use Drupal\key\KeyInterface;
use Drupal\key\Plugin\KeyProviderBase;

/**
 * Plugin with deprecated definition entries.
 *
 * It is defined with an annotation on purpose: attributes have no
 * 'storage_method' entry.
 *
 * @KeyProvider(
 *   id = "deprecated_defintion_entries",
 *   label = @Translation("Deprecated"),
 *   storage_method = "whatever",
 *   key_value = {
 *     "accepted" = FALSE,
 *     "required" = FALSE
 *   }
 * )
 */
final class DeprecatedDefinition extends KeyProviderBase {

  /**
   * {@inheritdoc}
   */
  public function getKeyValue(KeyInterface $key) {
    return "deprecatedkey";
  }

}
