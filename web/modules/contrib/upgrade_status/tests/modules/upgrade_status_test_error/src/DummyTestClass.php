<?php

namespace Drupal\upgrade_status_test_error;

use Drupal\simpletest\WebTestBase;

/**
 * A DummyTestClass to test deprecation of WebTestBase.
 *
 * @group upgrade_status_test_error
 */
class DummyTestClass extends WebTestBase {

  /**
   * No-op test method to make testbot happy.
   */
  public function testNoop() {

  }

}
