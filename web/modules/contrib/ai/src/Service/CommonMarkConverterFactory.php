<?php

declare(strict_types=1);

namespace Drupal\ai\Service;

use Drupal\Component\Utility\NestedArray;
use League\CommonMark\CommonMarkConverter;

/**
 * Default implementation of the CommonMark converter factory.
 */
class CommonMarkConverterFactory implements CommonMarkConverterFactoryInterface {

  /**
   * {@inheritdoc}
   */
  public function fromOptions(array $options = []): CommonMarkConverter {
    return new CommonMarkConverter(NestedArray::mergeDeep(self::DEFAULT_OPTIONS, $options));
  }

}
