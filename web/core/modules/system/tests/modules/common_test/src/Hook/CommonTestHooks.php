<?php

declare(strict_types=1);

namespace Drupal\common_test\Hook;

use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Asset\AttachedAssetsInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for common_test.
 */
class CommonTestHooks {

  /**
   * Implements hook_TYPE_alter().
   */
  #[Hook('drupal_alter_alter')]
  public function drupalAlterAlter(&$data, &$arg2 = NULL, &$arg3 = NULL): void {
    // Alter first argument.
    if (is_array($data)) {
      $data['foo'] = 'Drupal';
    }
    elseif (is_object($data)) {
      $data->foo = 'Drupal';
    }
    // Alter second argument, if present.
    if (isset($arg2)) {
      if (is_array($arg2)) {
        $arg2['foo'] = 'Drupal';
      }
      elseif (is_object($arg2)) {
        $arg2->foo = 'Drupal';
      }
    }
    // Try to alter third argument, if present.
    if (isset($arg3)) {
      if (is_array($arg3)) {
        $arg3['foo'] = 'Drupal';
      }
      elseif (is_object($arg3)) {
        $arg3->foo = 'Drupal';
      }
    }
  }

  /**
   * Implements hook_TYPE_alter().
   *
   * This is to verify that
   * \Drupal::moduleHandler()->alter([TYPE1, TYPE2], ...) allows
   * hook_module_implements_alter() to affect the order in which module
   * implementations are executed.
   */
  #[Hook('drupal_alter_foo_alter', module: 'block')]
  public function blockDrupalAlterFooAlter(&$data, &$arg2 = NULL, &$arg3 = NULL): void {
    $data['foo'] .= ' block';
  }

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme() : array {
    return [
      'common_test_foo' => [
        'variables' => [
          'foo' => 'foo',
          'bar' => 'bar',
        ],
      ],
      'common_test_render_element' => [
        'render element' => 'foo',
      ],
    ];
  }

  /**
   * Implements hook_library_info_build().
   */
  #[Hook('library_info_build')]
  public function libraryInfoBuild(): array {
    $libraries = [];
    if (\Drupal::state()->get('common_test.library_info_build_test')) {
      $libraries['dynamic_library'] = ['version' => '1.0', 'css' => ['base' => ['common_test.css' => []]]];
    }
    return $libraries;
  }

  /**
   * Implements hook_library_info_alter().
   */
  #[Hook('library_info_alter')]
  public function libraryInfoAlter(&$libraries, $module): void {
    if ($module === 'core' && isset($libraries['loadjs'])) {
      // Change the version of loadjs to 0.0.
      $libraries['loadjs']['version'] = '0.0';
      // Make loadjs depend on jQuery Form to test library dependencies.
      $libraries['loadjs']['dependencies'][] = 'core/internal.jquery.form';
    }
    // Alter the dynamically registered library definition.
    if ($module === 'common_test' && isset($libraries['dynamic_library'])) {
      $libraries['dynamic_library']['dependencies'] = ['core/jquery'];
    }
  }

  /**
   * Implements hook_cron().
   *
   * System module should handle if a module does not catch an exception and
   * keep cron going.
   *
   * @see common_test_cron_helper()
   */
  #[Hook('cron')]
  public function cron(): void {
    throw new \Exception('Uncaught exception');
  }

  /**
   * Implements hook_page_attachments().
   *
   * @see \Drupal\system\Tests\Common\PageRenderTest::assertPageRenderHookExceptions()
   */
  #[Hook('page_attachments')]
  public function pageAttachments(array &$page): void {
    $page['#attached']['library'][] = 'core/foo';
    $page['#attached']['library'][] = 'core/bar';
    $page['#cache']['tags'] = ['example'];
    $page['#cache']['contexts'] = ['user.permissions'];
    if (\Drupal::state()->get('common_test.hook_page_attachments.descendant_attached', FALSE)) {
      $page['content']['#attached']['library'][] = 'core/jquery';
    }
    if (\Drupal::state()->get('common_test.hook_page_attachments.render_array', FALSE)) {
      $page['something'] = ['#markup' => 'test'];
    }
    if (\Drupal::state()->get('common_test.hook_page_attachments.early_rendering', FALSE)) {
      // Do some early rendering.
      $element = ['#markup' => '123'];
      \Drupal::service('renderer')->render($element);
    }
  }

  /**
   * Implements hook_page_attachments_alter().
   *
   * @see \Drupal\system\Tests\Common\PageRenderTest::assertPageRenderHookExceptions()
   */
  #[Hook('page_attachments_alter')]
  public function pageAttachmentsAlter(array &$page): void {
    // Remove a library that was added in common_test_page_attachments(), to
    // test that this hook can do what it claims to do.
    if (isset($page['#attached']['library']) && ($index = array_search('core/bar', $page['#attached']['library'])) && $index !== FALSE) {
      unset($page['#attached']['library'][$index]);
    }
    $page['#attached']['library'][] = 'core/baz';
    $page['#cache']['tags'] = ['example'];
    $page['#cache']['contexts'] = ['user.permissions'];
    if (\Drupal::state()->get('common_test.hook_page_attachments_alter.descendant_attached', FALSE)) {
      $page['content']['#attached']['library'][] = 'core/jquery';
    }
    if (\Drupal::state()->get('common_test.hook_page_attachments_alter.render_array', FALSE)) {
      $page['something'] = ['#markup' => 'test'];
    }
  }

  /**
   * Implements hook_js_alter().
   *
   * @see \Drupal\KernelTests\Core\Asset\AttachedAssetsTest::testAlter()
   */
  #[Hook('js_alter')]
  public function jsAlter(&$javascript, AttachedAssetsInterface $assets, LanguageInterface $language): void {
    // Attach alter.js above tableselect.js.
    $alter_js = \Drupal::service('extension.list.module')->getPath('common_test') . '/alter.js';
    if (array_key_exists($alter_js, $javascript) && array_key_exists('core/misc/tableselect.js', $javascript)) {
      $javascript[$alter_js]['weight'] = $javascript['core/misc/tableselect.js']['weight'] - 1;
    }
  }

  /**
   * Implements hook_js_settings_alter().
   *
   * @see \Drupal\system\Tests\Common\JavaScriptTest::testHeaderSetting()
   */
  #[Hook('js_settings_alter')]
  public function jsSettingsAlter(&$settings, AttachedAssetsInterface $assets): void {
    // Modify an existing setting.
    if (array_key_exists('pluralDelimiter', $settings)) {
      $settings['pluralDelimiter'] = '☃';
    }
    // Add a setting.
    $settings['foo'] = 'bar';
  }

}
