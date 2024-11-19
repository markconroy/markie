<?php

namespace Drupal\ai\OperationType\Chat;

/**
 * For streaming chat message.
 */
interface StreamedChatMessageIteratorInterface extends \IteratorAggregate {

  public function __construct(\IteratorAggregate $iterator);

}
