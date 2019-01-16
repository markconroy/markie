<?php

namespace Drupal\jsonapi\ForwardCompatibility\Normalizer;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\TypedData\Plugin\DataType\DateTimeIso8601;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItem;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;

/**
 * Converts values for the DateTimeIso8601 data type to RFC3339.
 *
 * @internal
 * @see \Drupal\serialization\Normalizer\DateTimeIso8601Normalizer
 * @todo Remove when JSON:API requires Drupal 8.7.
 */
class DateTimeIso8601Normalizer extends DateTimeNormalizer {

  /**
   * {@inheritdoc}
   */
  protected $allowedFormats = [
    'RFC 3339' => \DateTime::RFC3339,
    'ISO 8601' => \DateTime::ISO8601,
    // @todo Remove this in https://www.drupal.org/project/drupal/issues/2958416.
    // RFC3339 only covers combined date and time representations. For date-only
    // representations, we need to use ISO 8601. There isn't a constant on the
    // \DateTime class that we can use, so we have to hardcode the format.
    // @see https://en.wikipedia.org/wiki/ISO_8601#Calendar_dates
    // @see \Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface::DATE_STORAGE_FORMAT
    'date-only' => 'Y-m-d',
  ];

  /**
   * {@inheritdoc}
   */
  protected $supportedInterfaceOrClass = DateTimeIso8601::class;

  /**
   * {@inheritdoc}
   */
  public function normalize($datetime, $format = NULL, array $context = []) {
    $field_item = $datetime->getParent();
    // @todo Remove this in https://www.drupal.org/project/drupal/issues/2958416.
    if ($field_item instanceof DateTimeItem && $field_item->getFieldDefinition()->getFieldStorageDefinition()->getSetting('datetime_type') === DateTimeItem::DATETIME_TYPE_DATE) {
      // @todo Remove when JSON:API only supports Drupal >=8.7, which fixed this in https://www.drupal.org/project/drupal/issues/3002164.
      $drupal_date_time = floatval(floatval(\Drupal::VERSION) >= 8.7)
        ? $datetime->getDateTime()
        : ($datetime->getValue() ? new DrupalDateTime($datetime->getValue(), 'UTC') : NULL);
      if ($drupal_date_time === NULL) {
        return $drupal_date_time;
      }
      return $drupal_date_time->format($this->allowedFormats['date-only']);
    }
    return parent::normalize($datetime, $format, $context);
  }

  /**
   * {@inheritdoc}
   */
  public function denormalize($data, $class, $format = NULL, array $context = []) {
    // @todo Move the date-only handling out of here in https://www.drupal.org/project/drupal/issues/2958416.
    $field_definition = isset($context['target_instance'])
      ? $context['target_instance']->getFieldDefinition()
      : (isset($context['field_definition']) ? $context['field_definition'] : NULL);
    $datetime_type = $field_definition->getSetting('datetime_type');
    $is_date_only = $datetime_type === DateTimeItem::DATETIME_TYPE_DATE;

    if ($is_date_only) {
      $context['datetime_allowed_formats'] = array_intersect_key($this->allowedFormats, ['date-only' => TRUE]);
      $datetime = parent::denormalize($data, $class, $format, $context);
      unset($context['datetime_allowed_formats']);
      if (!$datetime instanceof \DateTime) {
        return $datetime;
      }
      return $datetime->format(DateTimeItemInterface::DATE_STORAGE_FORMAT);
    }
    else {
      $context['datetime_allowed_formats'] = array_diff_key($this->allowedFormats, ['date-only' => TRUE]);
      try {
        $datetime = parent::denormalize($data, $class, $format, $context);
      }
      catch (\UnexpectedValueException $e) {
        // If denormalization didn't work using any of the actively supported
        // formats, try again with the BC format too. Explicitly label it as
        // being deprecated and trigger a deprecation error.
        $using_deprecated_format = TRUE;
        $context['datetime_allowed_formats']['backward compatibility — deprecated'] = DateTimeItemInterface::DATETIME_STORAGE_FORMAT;
        $datetime = parent::denormalize($data, $class, $format, $context);
      }
      unset($context['datetime_allowed_formats']);
      if (!$datetime instanceof \DateTime) {
        return $datetime;
      }
      if (isset($using_deprecated_format)) {
        @trigger_error('The provided datetime string format (Y-m-d\\TH:i:s) is deprecated and will be removed before Drupal 9.0.0. Use the RFC3339 format instead (Y-m-d\\TH:i:sP).', E_USER_DEPRECATED);
      }
      $datetime->setTimezone(new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE));
      return $datetime->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);
    }
  }

}
