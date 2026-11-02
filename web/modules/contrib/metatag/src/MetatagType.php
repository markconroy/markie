<?php

namespace Drupal\metatag;

/**
 * Enum for MetatagTag::type value.
 */
enum MetatagType: string {
  case Date = 'date';
  case Image = 'image';
  case Integer = 'integer';
  case Label = 'label';
  case String = 'string';
  case Uri = 'uri';
  case Video = 'video';
}
