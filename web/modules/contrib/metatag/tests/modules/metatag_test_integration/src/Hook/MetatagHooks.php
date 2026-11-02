<?php

namespace Drupal\metatag_test_integration\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Utility\Token;

/**
 * Metatag hook implementations for metatag_test_integration.
 */
class MetatagHooks {

  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected readonly Token $token;

  /**
   * Constructs a new MetatagHooks object.
   *
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   */
  public function __construct(Token $token) {
    $this->token = $token;
  }

  /**
   * Implements hook_metatags_attachments_alter().
   */
  #[Hook('metatags_attachments_alter')]
  public function metatagsAttachmentsAlter(array &$attachments) {
    $title = "This is the title I want | [site:name] | Yeah!";
    _metatag_test_integration_replace_tag('title', $this->token->replace($title), $attachments);
  }

}
