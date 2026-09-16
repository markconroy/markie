<?php

declare(strict_types=1);

namespace Drupal\ai_chatbot\Hook;

use Drupal\block\BlockInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Theme\ThemeManagerInterface;

/**
 * Hook implementations for contextual.
 */
class ChatbotHooks {

  use StringTranslationTrait;

  private const CHATBOT_BLOCK_PLUGIN_ID = 'ai_deepchat_block';

  /**
   * Constructs the ChatbotHooks object.
   */
  public function __construct(
    protected AccountProxyInterface $currentUser,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ThemeManagerInterface $themeManager,
  ) {
  }

  /**
   * Implements hook_preprocess_top_bar().
   */
  #[Hook('preprocess_top_bar')]
  public function topbar(array &$variables): void {
    $cacheability = new CacheableMetadata();
    $block = $this->getToolbarDeepChatBlock($cacheability);
    $this->mergeCacheability($variables, $cacheability);

    if ($block === NULL) {
      return;
    }

    $ai_chatbot = [
      '#type' => 'html_tag',
      '#tag' => 'button',
      '#attributes' => [
        'class' => ['hidden', 'button--ai-chatbot'],
        'aria-label' => $this->t('Open AI assistant'),
      ],
      '#weight' => -9999,
    ];

    $variables['tools'][] = $ai_chatbot;
  }

  /**
   * Implements hook_page_attachments().
   */
  #[Hook('page_attachments')]
  public function pageAttachments(array &$attachments): void {
    // The early script must load in the initial page head: the chatbot block
    // itself can arrive in a late BigPipe chunk, after the first paint, so
    // attaching through the block would defer the script past the paint it
    // has to run before. The gates below depend on permissions and block
    // config only, mirrored in the cacheability metadata.
    $cacheability = new CacheableMetadata();
    $block = $this->getToolbarDeepChatBlock($cacheability);
    $this->mergeCacheability($attachments, $cacheability);

    if ($block === NULL) {
      return;
    }

    // Marks the restored open state on <html> before rendering starts.
    $attachments['#attached']['library'][] = 'ai_chatbot/toolbar-chatbot-early';
    $attachments['#attached']['library'][] = 'ai_chatbot/toolbar-chatbot';
  }

  /**
   * Implements hook_toolbar().
   */
  #[Hook('toolbar')]
  public function toolbar() {
    $cacheability = new CacheableMetadata();
    $block = $this->getToolbarDeepChatBlock($cacheability);

    $items = [];

    if ($block === NULL) {
      // Return a cache-only entry so the negative decision is cached with
      // the same conditions that produced it and is invalidated with them.
      $items['ai_chatbot'] = [];
      $cacheability->applyTo($items['ai_chatbot']);
      return $items;
    }

    $items['ai_chatbot'] = [
      '#type' => 'toolbar_item',
      'tab' => [
        '#type' => 'html_tag',
        '#tag' => 'button',
        '#value' => $this->t('Assistant'),
        '#attributes' => [
          'class' => [
            'hidden',
            'toolbar-icon',
            'toolbar-icon-ai-chatbot',
            'open-chat',
            'button--ai-chatbot',
          ],
          'aria-pressed' => 'false',
          'type' => 'button',
        ],
      ],
      '#wrapper_attributes' => [
        'class' => [
          'ai-chatbot-toolbar-tab',
        ],
      ],
    ];
    $cacheability->applyTo($items['ai_chatbot']);

    return $items;
  }

  /**
   * Implements hook_theme_suggestions_HOOK_alter().
   */
  #[Hook('theme_suggestions_ai_deepchat_alter')]
  public function themeSuggestionsAiDeepchatAlter(array &$suggestions, array $variables): void {
    if (!empty($variables['settings']['placement'])) {
      $placement = strtr($variables['settings']['placement'], '-', '_');
      $suggestions[] = 'ai_deepchat__' . $placement;
    }
  }

  /**
   * Finds the first accessible toolbar-placed chatbot block, if any.
   *
   * Only a block that is enabled in the active theme, configured with the
   * toolbar placement, and accessible to the current user (which includes
   * the block's visibility conditions, the 'access deepchat api' permission
   * and the plugin's own assistant validation) counts. Everything the
   * decision depends on is recorded in the passed cacheability object: the
   * block config list tag covers blocks being added, removed or
   * reconfigured, the theme and permissions contexts cover the lookup
   * inputs, and each inspected block and access result contributes its own
   * metadata.
   *
   * @param \Drupal\Core\Cache\CacheableMetadata $cacheability
   *   Accumulates every cacheable condition the decision was based on.
   *
   * @return \Drupal\block\BlockInterface|null
   *   The toolbar chatbot block, or NULL if none applies.
   */
  protected function getToolbarDeepChatBlock(CacheableMetadata $cacheability): ?BlockInterface {
    $cacheability->addCacheContexts(['theme', 'user.permissions']);

    // The chat API behind the toolbar button requires this permission, and
    // the block's own access is role-based rather than permission-based, so
    // gate on it explicitly before looking any further.
    if (!$this->currentUser->hasPermission('access deepchat api')) {
      return NULL;
    }

    try {
      $cacheability->addCacheTags($this->entityTypeManager->getDefinition('block')->getListCacheTags());
      $theme = $this->themeManager->getActiveTheme()->getName();
      $blocks = $this->entityTypeManager->getStorage('block')->loadByProperties([
        'theme' => $theme,
        'plugin' => self::CHATBOT_BLOCK_PLUGIN_ID,
        'status' => TRUE,
      ]);

      foreach ($blocks as $block) {
        /** @var \Drupal\block\BlockInterface $block */
        $cacheability->addCacheableDependency($block);
        $settings = $block->get('settings');
        if (($settings['placement'] ?? '') !== 'toolbar') {
          continue;
        }
        $access = $block->access('view', NULL, TRUE);
        $cacheability->addCacheableDependency($access);
        if ($access->isAllowed()) {
          return $block;
        }
      }
    }
    catch (\Exception $e) {
      // If something goes wrong, fail gracefully.
      return NULL;
    }

    return NULL;
  }

  /**
   * Merges collected cacheability into a preprocess/attachments array.
   *
   * @param array $element
   *   The variables or attachments array carrying a #cache key.
   * @param \Drupal\Core\Cache\CacheableMetadata $cacheability
   *   The metadata to merge in.
   */
  protected function mergeCacheability(array &$element, CacheableMetadata $cacheability): void {
    $element['#cache']['contexts'] = Cache::mergeContexts($element['#cache']['contexts'] ?? [], $cacheability->getCacheContexts());
    $element['#cache']['tags'] = Cache::mergeTags($element['#cache']['tags'] ?? [], $cacheability->getCacheTags());
    $element['#cache']['max-age'] = Cache::mergeMaxAges($element['#cache']['max-age'] ?? Cache::PERMANENT, $cacheability->getCacheMaxAge());
  }

}
