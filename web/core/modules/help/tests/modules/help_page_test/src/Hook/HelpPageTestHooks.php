<?php

declare(strict_types=1);

namespace Drupal\help_page_test\Hook;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for help_page_test.
 */
class HelpPageTestHooks {

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, RouteMatchInterface $route_match): string|array {
    switch ($route_name) {
      case 'help.page.help_page_test':
        // Make the help text conform to core standards. See
        // \Drupal\system\Tests\Functional\GenericModuleTestBase::assertHookHelp().
        return 'Read the <a href="http://www.example.com">online documentation for the Help Page Test module</a>.';

      case 'help_page_test.has_help':
        return 'I have help!';

      case 'help_page_test.test_array':
        return ['#markup' => 'Help text from help_page_test_help module.'];
    }
    // Ensure that hook_help() can return an empty string and not cause the
    // block to display.
    return '';
  }

}
