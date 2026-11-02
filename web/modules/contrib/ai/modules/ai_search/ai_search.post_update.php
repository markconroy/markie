<?php

/**
 * @file
 * Contains post_update hooks for ai_search module.
 */

use Drupal\Core\Cache\Cache;
use Drupal\ai_search\Enum\ResultsCacheMode;

/**
 * Set how cached search results are shared between users on AI Search indexes.
 */
function ai_search_post_update_results_cache_mode(&$sandbox = NULL): string {
  $config_factory = \Drupal::configFactory();
  $updated = 0;
  $per_role_without_access_processor = [];

  foreach ($config_factory->listAll('search_api.index.') as $config_name) {
    $index_config = $config_factory->get($config_name)->getRawData();
    if (
      empty($index_config['id'])
      || empty($index_config['server'])
      || $config_factory->get('search_api.server.' . $index_config['server'])->get('backend') !== 'search_api_ai_search'
    ) {
      continue;
    }

    $ai_search_config = $config_factory->getEditable('ai_search.index.' . $index_config['id']);
    if ($ai_search_config->get('results_cache_mode') !== NULL) {
      continue;
    }

    // Content access depends on the individual user (own unpublished content
    // and node access grants), Role-based access only on their roles.
    $processors = $index_config['processor_settings'] ?? [];
    if (isset($processors['content_access'])) {
      $mode = ResultsCacheMode::PerUser;
    }
    else {
      $mode = ResultsCacheMode::PerRole;
      if (!isset($processors['role_access'])) {
        $per_role_without_access_processor[] = $index_config['name'] ?? $index_config['id'];
      }
    }

    if ($ai_search_config->isNew()) {
      $ai_search_config->set('index_id', $index_config['id']);
    }
    $ai_search_config->set('results_cache_mode', $mode->value);
    $ai_search_config->save();

    // Results cached before the mode was set may have been shared between
    // users who should not see them.
    Cache::invalidateTags(['search_api_list:' . $index_config['id']]);
    $updated++;
  }

  $message = (string) t('Set the results caching mode on @count AI Search index(es).', ['@count' => $updated]);
  if ($per_role_without_access_processor) {
    $message .= ' ' . t('Warning: the following indexes now share cached search results between users with the same roles: @indexes. If access to indexed items varies between users who share the same roles (for example "View own unpublished content", node access grants, or group membership), change "AI Search results caching" on the index edit form to "Cache per user" or "Do not cache".', [
      '@indexes' => implode(', ', $per_role_without_access_processor),
    ]);
  }
  return $message;
}
