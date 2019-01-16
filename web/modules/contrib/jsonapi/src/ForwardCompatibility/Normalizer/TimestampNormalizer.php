<?php

namespace Drupal\jsonapi\ForwardCompatibility\Normalizer;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\TypedData\Plugin\DataType\Timestamp;

/**
 * Converts values for the Timestamp data type to and from common formats.
 *
 * @internal
 * @see \Drupal\serialization\Normalizer\TimestampNormalizer
 * @todo Remove when JSON:API requires Drupal 8.7.
 */
class TimestampNormalizer extends DateTimeNormalizer {

  /**
   * {@inheritdoc}
   */
  protected $allowedFormats = [
    'UNIX timestamp' => 'U',
    'ISO 8601' => \DateTime::ISO8601,
    'RFC 3339' => \DateTime::RFC3339,
  ];

  /**
   * {@inheritdoc}
   */
  protected $supportedInterfaceOrClass = Timestamp::class;

  /**
   * {@inheritdoc}
   */
  public function normalize($datetime, $format = NULL, array $context = []) {
    return DrupalDateTime::createFromTimestamp($datetime->getValue())
      ->setTimezone($this->getNormalizationTimezone())
      ->format(\DateTime::RFC3339);
  }

  /**
   * {@inheritdoc}
   */
  protected function getNormalizationTimezone() {
    return new \DateTimeZone('UTC');
  }

  /**
   * {@inheritdoc}
   */
  public function denormalize($data, $class, $format = NULL, array $context = []) {
    $denormalized = parent::denormalize($data, $class, $format, $context);
    return $denormalized->getTimestamp();
  }

}
