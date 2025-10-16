<?php

declare(strict_types=1);

namespace Drupal\ai_chatbot\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for contextual.
 */
class ChatbotHooks {

  use StringTranslationTrait;

  /**
   * Constructs the ChatbotHooks object.
   */
  public function __construct(
    protected AccountProxyInterface $currentUser,
  ) {
  }

  /**
   * Implements hook_toolbar().
   */
  #[Hook('toolbar')]
  public function toolbar() {
    $items = [];
    $items['contextual'] = ['#cache' => ['contexts' => ['user.permissions']]];

    if (!$this->currentUser->hasPermission('access deepchat api')) {
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
      '#attached' => [
        'library' => [
          'ai_chatbot/toolbar',
        ],
      ],
    ];

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

}
