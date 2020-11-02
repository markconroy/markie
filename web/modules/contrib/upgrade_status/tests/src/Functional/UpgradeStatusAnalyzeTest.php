<?php

namespace Drupal\Tests\upgrade_status\Functional;

/**
 * Tests analysing sample projects.
 *
 * @group upgrade_status
 */
class UpgradeStatusAnalyzeTest extends UpgradeStatusTestBase {

  public function testAnalyzer() {
    $this->drupalLogin($this->drupalCreateUser(['administer software updates']));
    $this->runFullScan();

    /** @var \Drupal\Core\KeyValueStore\KeyValueStoreInterface $key_value */
    $key_value = \Drupal::service('keyvalue')->get('upgrade_status_scan_results');

    // Check if the project has scan result in the keyValueStorage.
    $this->assertTrue($key_value->has('upgrade_status_test_error'));
    $this->assertTrue($key_value->has('upgrade_status_test_no_error'));
    $this->assertTrue($key_value->has('upgrade_status_test_submodules'));
    $this->assertTrue($key_value->has('upgrade_status_test_submodules_with_error'));
    $this->assertTrue($key_value->has('upgrade_status_test_contrib_error'));
    $this->assertTrue($key_value->has('upgrade_status_test_contrib_no_error'));
    $this->assertTrue($key_value->has('upgrade_status_test_twig'));
    $this->assertTrue($key_value->has('upgrade_status_test_theme'));
    $this->assertTrue($key_value->has('upgrade_status_test_library'));

    // The project upgrade_status_test_submodules_a shouldn't have scan result,
    // because it's a submodule of 'upgrade_status_test_submodules',
    // and we always want to run the scan on root modules.
    $this->assertFalse($key_value->has('upgrade_status_test_submodules_a'));

    $project = $key_value->get('upgrade_status_test_error');
    $this->assertNotEmpty($project);
    $report = json_decode($project, TRUE);
    $this->assertEquals(4, $report['data']['totals']['file_errors']);
    $this->assertCount(4, $report['data']['files']);
    $file = reset($report['data']['files']);
    $message = $file['messages'][0];
    $this->assertEquals("Syntax error, unexpected T_STRING on line 3", $message['message']);
    $this->assertEquals(3, $message['line']);
    $file = next($report['data']['files']);
    $message = $file['messages'][0];
    $this->assertEquals("Call to deprecated function menu_cache_clear_all(). Deprecated in drupal:8.6.0 and is removed from drupal:9.0.0. Use \Drupal::cache('menu')->invalidateAll() instead.", $message['message']);
    $this->assertEquals(10, $message['line']);

    $project = $key_value->get('upgrade_status_test_no_error');
    $this->assertNotEmpty($project);
    $report = json_decode($project, TRUE);
    $this->assertEquals(0, $report['data']['totals']['file_errors']);
    $this->assertCount(0, $report['data']['files']);

    $project = $key_value->get('upgrade_status_test_contrib_error');
    $this->assertNotEmpty($project);
    $report = json_decode($project, TRUE);
    $this->assertEquals(2, $report['data']['totals']['file_errors']);
    $this->assertCount(2, $report['data']['files']);
    $file = reset($report['data']['files']);
    $message = $file['messages'][0];
    $this->assertEquals("Call to deprecated function drupal_set_message(). Deprecated in drupal:8.5.0 and is removed from drupal:9.0.0. Use Drupal\Core\Messenger\MessengerInterface::addMessage() instead.", $message['message']);
    $this->assertEquals(13, $message['line']);

    $project = $key_value->get('upgrade_status_test_twig');
    $this->assertNotEmpty($project);
    $report = json_decode($project, TRUE);
    $this->assertEquals(3, $report['data']['totals']['file_errors']);
    $this->assertCount(1, $report['data']['files']);
    $file = reset($report['data']['files']);
    $this->assertEquals('Twig Tag "raw" is deprecated since version 1.21. Use "verbatim" instead. See https://drupal.org/node/3071078.', $file['messages'][0]['message']);
    $this->assertEquals(3, $file['messages'][0]['line']);
    $this->assertEquals('Template is attaching a deprecated library. The "upgrade_status_test_library/deprecated_library" asset library is deprecated for testing.', $file['messages'][1]['message']);
    $this->assertEquals(1, $file['messages'][1]['line']);
    $this->assertEquals('Template is attaching a deprecated library. The "upgrade_status_test_twig/deprecated_library" asset library is deprecated for testing.', $file['messages'][2]['message']);
    $this->assertEquals(2, $file['messages'][2]['line']);

    $project = $key_value->get('upgrade_status_test_theme');
    $this->assertNotEmpty($project);
    $report = json_decode($project, TRUE);
    $this->assertEquals(4, $report['data']['totals']['file_errors']);
    $this->assertCount(3, $report['data']['files']);
    $file = reset($report['data']['files']);
    $message = $file['messages'][0];
    $this->assertEquals('Twig Tag "raw" is deprecated since version 1.21. Use "verbatim" instead. See https://drupal.org/node/3071078.', $message['message']);
    $this->assertEquals(1, $message['line']);
    $file = next($report['data']['files']);
    $this->assertEquals('Theme is overriding a deprecated library. The "upgrade_status_test_library/deprecated_library" asset library is deprecated for testing.', $file['messages'][0]['message']);
    $this->assertEquals(0, $file['messages'][0]['line']);
    $this->assertEquals('Theme is extending a deprecated library. The "upgrade_status_test_twig/deprecated_library" asset library is deprecated for testing.', $file['messages'][1]['message']);
    $this->assertEquals(0, $file['messages'][1]['line']);
    $file = next($report['data']['files']);
    $this->assertEquals('The theme is overriding the "upgrade_status_test_theme_function_theme_function_override" theme function. Theme functions are deprecated. For more info, see https://www.drupal.org/node/2575445.', $file['messages'][0]['message']);
    $this->assertEquals(6, $file['messages'][0]['line']);

    $project = $key_value->get('upgrade_status_test_theme_functions');
    $this->assertNotEmpty($project);
    $report = json_decode($project, TRUE);
    $this->assertEquals(3, $report['data']['totals']['file_errors']);
    $this->assertCount(1, $report['data']['files']);
    $file = reset($report['data']['files']);
    $this->assertEquals('The module is defining "upgrade_status_test_theme_function" theme function. Theme functions are deprecated. For more info, see https://www.drupal.org/node/2575445.', $file['messages'][0]['message']);
    $this->assertEquals(9, $file['messages'][0]['line']);
    $this->assertEquals('The module is defining "upgrade_status_test_theme_function" theme function. Theme functions are deprecated. For more info, see https://www.drupal.org/node/2575445.', $file['messages'][1]['message']);
    $this->assertEquals(20, $file['messages'][1]['line']);
    $this->assertEquals('The module is defining an unknown theme function. Theme functions are deprecated. For more info, see https://www.drupal.org/node/2575445.', $file['messages'][2]['message']);
    $this->assertEquals(21, $file['messages'][2]['line']);

    $project = $key_value->get('upgrade_status_test_library');
    $this->assertNotEmpty($project);
    $report = json_decode($project, TRUE);
    $this->assertEquals(4, $report['data']['totals']['file_errors']);
    $this->assertCount(2, $report['data']['files']);
    $file = reset($report['data']['files']);
    $this->assertEquals("The 'library' library is depending on a deprecated library. The \"upgrade_status_test_library/deprecated_library\" asset library is deprecated for testing.", $file['messages'][0]['message']);
    $this->assertEquals(0, $file['messages'][0]['line']);
    $this->assertEquals("The 'library' library is depending on a deprecated library. The \"upgrade_status_test_twig/deprecated_library\" asset library is deprecated for testing.", $file['messages'][1]['message']);
    $this->assertEquals(0, $file['messages'][1]['line']);
    $file = $report['data']['files'][array_keys($report['data']['files'])[1]];
    $this->assertEquals('The referenced library is deprecated. The "upgrade_status_test_library/deprecated_library" asset library is deprecated for testing.', $file['messages'][0]['message']);
    $this->assertEquals(8, $file['messages'][0]['line']);
    $this->assertEquals('The referenced library is deprecated. The "upgrade_status_test_twig/deprecated_library" asset library is deprecated for testing.', $file['messages'][1]['message']);
    $this->assertEquals(10, $file['messages'][1]['line']);

    $project = $key_value->get('upgrade_status_test_library_exception');
    $this->assertNotEmpty($project);
    $report = json_decode($project, TRUE);
    $this->assertEquals(1, $report['data']['totals']['file_errors']);
    $this->assertCount(1, $report['data']['files']);
    $file = reset($report['data']['files']);
    $this->assertEquals("Incomplete library definition for definition 'library_exception' in extension 'upgrade_status_test_library_exception'", $file['messages'][0]['message']);

    // Module upgrade_status_test_submodules_with_error_a shouldn't have scan
    // result, but its info.yml errors should appear in its parent scan.
    $this->assertFalse($key_value->has('upgrade_status_test_submodules_with_error_a'));
    $project = $key_value->get('upgrade_status_test_submodules_with_error');
    $this->assertNotEmpty($project);
    $report = json_decode($project, TRUE);
    $this->assertEquals(2, $report['data']['totals']['file_errors']);
    $this->assertCount(2, $report['data']['files']);
  }

}
