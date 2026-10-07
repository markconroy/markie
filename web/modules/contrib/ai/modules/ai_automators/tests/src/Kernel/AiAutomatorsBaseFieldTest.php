<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_automators\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\ai_automators\Traits\AiAutomatorCreationTrait;
use Drupal\node\Entity\NodeType;

/**
 * Tests that AI Automators work with fields declared as base fields.
 *
 * @see \Drupal\Core\Field\BaseFieldDefinition
 *
 * @group ai_automators
 */
final class AiAutomatorsBaseFieldTest extends KernelTestBase {

  use AiAutomatorCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'file',
    'node',
    'token',
    'ai',
    'ai_automators',
    'field_widget_actions',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
  }

  /**
   * Tests that ::isAvailable() returns TRUE if a base field has an automator.
   *
   * @covers \Drupal\ai_automators\Plugin\FieldWidgetAction\AutomatorBaseAction::isAvailable
   */
  public function testIsAvailableWithBaseFieldDefinitionAndBundleScopedAutomator(): void {
    $this->createAiAutomator([
      'id' => 'node.article.title.widget_test',
      'field_name' => 'title',
    ]);
    $title_definition = $this->container->get('entity_field.manager')->getBaseFieldDefinitions('node')['title'];
    // This is making sure we are testing using a bundleless base field.
    $this->assertNull($title_definition->getTargetBundle(), 'A base field doesn\'t have a target bundle.');

    /** @var \Drupal\field_widget_actions\FieldWidgetActionManagerInterface $manager */
    $manager = $this->container->get('plugin.manager.field_widget_actions');
    $plugin = $manager->createInstance('automator_text', []);
    $plugin->setFieldDefinition($title_definition);
    $this->assertTrue($plugin->isAvailable());
  }

  /**
   * Tests that ::isAvailable() returns FALSE if a base field has no automator.
   *
   * @covers \Drupal\ai_automators\Plugin\FieldWidgetAction\AutomatorBaseAction::isAvailable
   */
  public function testIsAvailableWithBaseFieldDefinitionAndNoAutomators(): void {
    $title_definition = $this->container->get('entity_field.manager')->getBaseFieldDefinitions('node')['title'];

    /** @var \Drupal\field_widget_actions\FieldWidgetActionManagerInterface $manager */
    $manager = $this->container->get('plugin.manager.field_widget_actions');
    $plugin = $manager->createInstance('automator_text', []);
    $plugin->setFieldDefinition($title_definition);
    $this->assertFalse($plugin->isAvailable());
  }

}
