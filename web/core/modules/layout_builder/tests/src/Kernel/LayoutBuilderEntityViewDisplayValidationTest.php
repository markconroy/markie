<?php

namespace Drupal\Tests\layout_builder\Kernel;

use Drupal\Core\Entity\Entity\EntityViewMode;
use Drupal\KernelTests\Core\Config\ConfigEntityValidationTestBase;
use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay;

/**
 * Tests validation of Layout Builder's entity_view_display entities.
 *
 * @group layout_builder
 * @group #slow
 */
class LayoutBuilderEntityViewDisplayValidationTest extends ConfigEntityValidationTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['layout_builder', 'user'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    EntityViewMode::create([
      'id' => 'user.layout',
      'label' => 'Layout',
      'targetEntityType' => 'user',
    ])->save();

    $this->entity = LayoutBuilderEntityViewDisplay::create([
      'mode' => 'layout',
      'label' => 'Layout',
      'targetEntityType' => 'user',
      'bundle' => 'user',
    ]);
    $this->entity->save();
  }

  /**
   * {@inheritdoc}
   */
  public function testLabelValidation(): void {
    // @todo Remove this override in https://www.drupal.org/i/2939931. The label of Layout Builder's EntityViewDisplay override is computed dynamically, that issue will change this.
    // @see \Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay::label()
    $this->markTestSkipped();
  }

}
