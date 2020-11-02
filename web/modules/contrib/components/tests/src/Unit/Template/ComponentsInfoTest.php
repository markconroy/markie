<?php

namespace Drupal\Tests\components\Unit\Template;

use Drupal\components\Template\ComponentsInfo;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\components\Template\ComponentsInfo
 * @group components
 */
class ComponentsInfoTest extends UnitTestCase {

  /**
   * The module extension list.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $moduleExtensionList;

  /**
   * The theme extension list.
   *
   * @var \Drupal\Core\Extension\ThemeExtensionList|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $themeExtensionList;

  /**
   * The system under test.
   *
   * @var \Drupal\components\Template\ComponentsInfo
   */
  protected $systemUnderTest;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    // Prevent test failures due to constants used in ModuleExtensionList.
    if (!defined('DRUPAL_MINIMUM_PHP')) {
      define('DRUPAL_MINIMUM_PHP', '7.0.8');
    }

    $this->moduleExtensionList = $this->createMock('\Drupal\Core\Extension\ModuleExtensionList');
    $this->themeExtensionList = $this->createMock('\Drupal\Core\Extension\ThemeExtensionList');
  }

  /**
   * Creates a ComponentsInfo service after the dependencies are set up.
   */
  public function newSystemUnderTest() {
    $this->systemUnderTest = new ComponentsInfo($this->moduleExtensionList, $this->themeExtensionList);
  }

  /**
   * Tests finding components info from extension .info.yml files.
   *
   * Since this is a protected method, we are testing it via the constructor,
   * getAllModuleInfo, and getProtectedNamespaces.
   *
   * @covers ::findComponentsInfo
   */
  public function testFindComponentsInfo() {
    $this->moduleExtensionList
      ->expects($this->exactly(1))
      ->method('getAllInstalledInfo')
      ->willReturn([
        // Does not have a components entry.
        'system' => [
          'no-components' => 'system-value',
        ],
        // Look for namespaces using 1.x API (backwards compatibility).
        'harriet_tubman' => [
          'component-libraries' => [
            'harriet_tubman' => [
              'paths' => ['deprecated'],
            ],
          ],
        ],
        'phillis_wheatley' => [
          'components' => [
            'namespaces' => [
              // Namespace path is a string.
              'phillis_wheatley' => 'templates',
              // Namespace path is an array.
              'wheatley' => ['components'],
            ],
          ],
          // If components.namespaces is set, ignore 1.x API.
          'component-libraries' => [
            'wheatley' => [
              'paths' => ['deprecated'],
            ],
          ],
        ],
        // No default namespace defined.
        'edna_lewis' => [
          'unrelatedKey' => 'should be ignored',
          'components' => [
            'includedKey' => 'included',
            'namespaces' => [
              'lewis' => ['templates', 'components'],
            ],
          ],
        ],
        // Manual opt-in.
        'components' => [
          'components' => [
            'allow_default_namespace_reuse' => TRUE,
          ],
        ],
      ]);
    $this->moduleExtensionList->expects($this->exactly(5))
      ->method('getPath')
      ->willReturn('/system', '/tubman', '/wheatley', '/lewis', '/components');

    $this->themeExtensionList
      ->method('getAllInstalledInfo')
      ->willReturn([]);

    $this->newSystemUnderTest();

    $expected = [
      'harriet_tubman' => [
        'namespaces' => [
          'harriet_tubman' => ['/tubman/deprecated'],
        ],
        'extensionPath' => '/tubman',
      ],
      'phillis_wheatley' => [
        'namespaces' => [
          'phillis_wheatley' => ['/wheatley/templates'],
          'wheatley' => ['/wheatley/components'],
        ],
        'extensionPath' => '/wheatley',
      ],
      'edna_lewis' => [
        'includedKey' => 'included',
        'namespaces' => [
          'lewis' => ['/lewis/templates', '/lewis/components'],
        ],
        'extensionPath' => '/lewis',
      ],
      'components' => [
        'allow_default_namespace_reuse' => TRUE,
        'extensionPath' => '/components',
      ],
    ];
    $result = $this->systemUnderTest->getAllModuleInfo();
    $this->assertEquals($expected, $result);

    $expected = ['system', 'edna_lewis'];
    $result = $this->systemUnderTest->getProtectedNamespaces();
    $this->assertEquals($expected, $result);
  }

  /**
   * Tests retrieving components info from a module.
   *
   * @covers ::getModuleInfo
   */
  public function testGetModuleInfo() {
    $this->moduleExtensionList
      ->expects($this->exactly(1))
      ->method('getAllInstalledInfo')
      ->willReturn([
        'foo' => [
          'components' => [
            'included' => 'foo',
          ],
        ],
        'bar' => [
          'components' => [
            'included' => 'bar',
          ],
        ],
      ]);
    $this->moduleExtensionList
      ->expects($this->exactly(2))
      ->method('getPath')
      ->willReturn('/foo', '/bar');

    $this->themeExtensionList
      ->method('getAllInstalledInfo')
      ->willReturn([]);

    $this->newSystemUnderTest();

    $expected = [
      'included' => 'bar',
      'extensionPath' => '/bar',
    ];
    $result = $this->systemUnderTest->getModuleInfo('bar');
    $this->assertEquals($expected, $result);
  }

  /**
   * Tests retrieving all components info from modules.
   *
   * @covers ::getAllModuleInfo
   */
  public function testGetAllModuleInfo() {
    $this->moduleExtensionList
      ->expects($this->exactly(1))
      ->method('getAllInstalledInfo')
      ->willReturn([
        'foo' => [
          'no-components' => 'ignored',
        ],
        'bar' => [
          'components' => [
            'included' => 'not-ignored',
          ],
        ],
      ]);
    $this->moduleExtensionList
      ->expects($this->exactly(2))
      ->method('getPath')
      ->willReturn('/foo', '/bar');

    $this->themeExtensionList
      ->method('getAllInstalledInfo')
      ->willReturn([]);

    $this->newSystemUnderTest();

    $expected = [
      'bar' => [
        'included' => 'not-ignored',
        'extensionPath' => '/bar',
      ],
    ];
    $result = $this->systemUnderTest->getAllModuleInfo();
    $this->assertEquals($expected, $result);
  }

  /**
   * Tests retrieving components info from a theme.
   *
   * @covers ::getThemeInfo
   */
  public function testGetThemeInfo() {
    $this->moduleExtensionList
      ->method('getAllInstalledInfo')
      ->willReturn([]);

    $this->themeExtensionList
      ->expects($this->exactly(1))
      ->method('getAllInstalledInfo')
      ->willReturn([
        'foo' => [
          'components' => [
            'included' => 'foo',
          ],
        ],
        'bar' => [
          'components' => [
            'included' => 'bar',
          ],
        ],
      ]);
    $this->themeExtensionList
      ->expects($this->exactly(2))
      ->method('getPath')
      ->willReturn('/foo', '/bar');

    $this->newSystemUnderTest();

    $expected = [
      'included' => 'bar',
      'extensionPath' => '/bar',
    ];
    $result = $this->systemUnderTest->getThemeInfo('bar');
    $this->assertEquals($expected, $result);
  }

  /**
   * Tests retrieving all components info from themes.
   *
   * @covers ::getAllThemeInfo
   */
  public function testGetAllThemeInfo() {
    $this->moduleExtensionList
      ->method('getAllInstalledInfo')
      ->willReturn([]);

    $this->themeExtensionList
      ->expects($this->exactly(1))
      ->method('getAllInstalledInfo')
      ->willReturn([
        'foo' => [
          'no-components' => 'ignored',
        ],
        'bar' => [
          'components' => [
            'included' => 'not-ignored',
          ],
        ],
      ]);
    $this->themeExtensionList
      ->expects($this->exactly(2))
      ->method('getPath')
      ->willReturn('/foo', '/bar');

    $this->newSystemUnderTest();

    $expected = [
      'bar' => [
        'included' => 'not-ignored',
        'extensionPath' => '/bar',
      ],
    ];
    $result = $this->systemUnderTest->getAllThemeInfo();
    $this->assertEquals($expected, $result);
  }

  /**
   * Tests retrieving protected namespaces.
   *
   * @covers ::getProtectedNamespaces
   */
  public function testGetProtectedNamespaces() {
    $this->moduleExtensionList
      ->expects($this->exactly(1))
      ->method('getAllInstalledInfo')
      ->willReturn([
        'foo' => [
          'no-components' => 'system-value',
        ],
        'bar' => [
          'no-components' => 'system-value',
        ],
        'baz' => [
          'no-components' => 'system-value',
        ],
        'bop' => [
          'no-components' => 'system-value',
        ],
        // Manual opt-in.
        'mmmbop' => [
          'components' => [
            'allow_default_namespace_reuse' => TRUE,
          ],
        ],
      ]);
    $this->moduleExtensionList->expects($this->exactly(5))
      ->method('getPath')
      ->willReturn('/some-path');

    $this->themeExtensionList
      ->method('getAllInstalledInfo')
      ->willReturn([]);

    $this->newSystemUnderTest();

    $expected = ['foo', 'bar', 'baz', 'bop'];
    $result = $this->systemUnderTest->getProtectedNamespaces();
    $this->assertEquals($expected, $result);
  }

}
