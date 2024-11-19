<?php

namespace Drupal\Tests\ai\Mock;

use Drupal\ai\OperationType\Chat\StreamedChatMessage;
use Drupal\ai\OperationType\Chat\StreamedChatMessageIteratorInterface;

/**
 * Mock chat iterator for testing.
 */
class MockStreamedChatIterator implements StreamedChatMessageIteratorInterface {

  /**
   * The iterator.
   *
   * @var \IteratorAggregate
   */
  private $iterator;

  /**
   * {@inheritdoc}
   */
  public function __construct(\IteratorAggregate $iterator) {
    $this->iterator = $iterator;
  }

  /**
   * Get the iterator.
   *
   * @return \Traversable
   *   The iterator.
   */
  public function getIterator(): \Traversable {
    foreach ($this->iterator as $data) {
      yield new StreamedChatMessage(
        'assistant',
        $data . "\n",
        []
      );
    }
  }

}
