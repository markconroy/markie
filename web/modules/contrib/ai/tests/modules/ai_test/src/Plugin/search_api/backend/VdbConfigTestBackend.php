<?php

declare(strict_types=1);

namespace Drupal\ai_test\Plugin\search_api\backend;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\search_api\Attribute\SearchApiBackend;
use Drupal\search_api_test\Plugin\search_api\backend\TestBackend;

/**
 * Provides a test backend that accepts VDB configuration.
 */
#[SearchApiBackend(
  id: 'ai_test_vdb_config',
  label: new TranslatableMarkup('AI VDB configuration test backend'),
  description: new TranslatableMarkup('Stores VDB configuration for config action tests.'),
)]
class VdbConfigTestBackend extends TestBackend {

}
