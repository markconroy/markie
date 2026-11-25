<?php

declare(strict_types=1);

namespace Drupal\ai_content_suggestions;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\ai_content_suggestions\Annotation\AiContentSuggestions;
use Drupal\ai_content_suggestions\Attribute\AiContentSuggestions as AiContentSuggestionsAttribute;

/**
 * AiContentSuggestions plugin manager.
 */
final class AiContentSuggestionsPluginManager extends DefaultPluginManager {

  /**
   * Constructs the object.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/AiContentSuggestions', $namespaces, $module_handler, AiContentSuggestionsInterface::class, AiContentSuggestionsAttribute::class, AiContentSuggestions::class);
    $this->alterInfo('ai_content_suggestions_info');
    $this->setCacheBackend($cache_backend, 'ai_content_suggestions_plugins');
  }

}
