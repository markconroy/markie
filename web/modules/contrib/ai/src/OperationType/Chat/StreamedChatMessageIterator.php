<?php

namespace Drupal\ai\OperationType\Chat;

/**
 * Streamed chat message iterator interface.
 */
abstract class StreamedChatMessageIterator implements StreamedChatMessageIteratorInterface {

  /**
   * The iterator.
   *
   * @var \Traversable
   */
  protected $iterator;

  /**
   * Constructor.
   */
  public function __construct(\Traversable $iterator) {
    $this->iterator = $iterator;
  }

}
