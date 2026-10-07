<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_automators\Kernel\Traits;

use Drupal\ai_automators\Entity\AiAutomator;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;

/**
 * Tests AutomatorFieldWidgetActionTrait::isAvailable(), getAutomatorsOptions().
 *
 * Covers the regression where a disabled AiAutomator config entity still
 * made its Field Widget Action button appear: getAutomatorsOptions() must
 * only offer status=TRUE automators, and isAvailable() (which is driven
 * entirely by getAutomatorsOptions()) must reflect that.
 *
 * @group ai_automators
 */
class AutomatorFieldWidgetActionTraitTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'file',
    'node',
    'options',
    'text',
    'token',
    'filter',
    'key',
    'ai',
    'ai_automators',
    'field_widget_actions',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installConfig(['system', 'field', 'node', 'filter']);
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();

    FieldStorageConfig::create([
      'field_name' => 'field_string',
      'entity_type' => 'node',
      'type' => 'string',
      'cardinality' => 1,
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_string',
      'entity_type' => 'node',
      'bundle' => 'article',
      'label' => 'field_string',
    ])->save();
  }

  /**
   * Creates an automator_text plugin instance targeting field_string.
   */
  protected function plugin(): object {
    $plugin = \Drupal::service('plugin.manager.field_widget_actions')->createInstance('automator_text');
    $definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', 'article');
    $plugin->setFieldDefinition($definitions['field_string']);
    return $plugin;
  }

  /**
   * Creates an AiAutomator config entity for node.article.field_string.
   */
  protected function createAutomator(string $id, bool $status): AiAutomator {
    $automator = AiAutomator::create([
      'id' => $id,
      'label' => $id,
      'status' => $status,
      'rule' => 'llm_string',
      'input_mode' => 'base',
      'weight' => 100,
      'worker_type' => 'direct',
      'entity_type' => 'node',
      'bundle' => 'article',
      'field_name' => 'field_string',
      'edit_mode' => FALSE,
      'base_field' => 'field_string',
      'prompt' => 'x',
      'token' => '',
      'plugin_config' => [],
    ]);
    $automator->save();
    return $automator;
  }

  /**
   * Without a field definition (the FWA settings form context) it's TRUE.
   */
  public function testIsAvailableTrueWithoutFieldDefinition(): void {
    $plugin = \Drupal::service('plugin.manager.field_widget_actions')->createInstance('automator_text');
    $this->assertTrue($plugin->isAvailable());
  }

  /**
   * No automator at all configured for the field: unavailable.
   */
  public function testIsAvailableFalseWithNoAutomators(): void {
    $plugin = $this->plugin();
    $this->assertFalse($plugin->isAvailable());
    $this->assertSame([], $plugin->getAutomatorsOptions('node', 'article', 'field_string'));
  }

  /**
   * An enabled automator on the field makes the action available.
   */
  public function testIsAvailableTrueWithEnabledAutomator(): void {
    $automator = $this->createAutomator('node.article.field_string.enabled', TRUE);

    $plugin = $this->plugin();
    $this->assertTrue($plugin->isAvailable());
    $this->assertSame(
      [$automator->id() => $automator->label()],
      $plugin->getAutomatorsOptions('node', 'article', 'field_string')
    );
  }

  /**
   * A disabled automator must not make the action available.
   *
   * Regression guard: getAutomatorsOptions() used to loadMultiple() every
   * ai_automator config entity regardless of its status, so a disabled
   * automator still produced a button on the field widget.
   */
  public function testIsAvailableFalseWithDisabledAutomator(): void {
    $this->createAutomator('node.article.field_string.disabled', FALSE);

    $plugin = $this->plugin();
    $this->assertFalse($plugin->isAvailable());
    $this->assertSame([], $plugin->getAutomatorsOptions('node', 'article', 'field_string'));
  }

  /**
   * A disabled automator is excluded even when an enabled one also exists.
   */
  public function testGetAutomatorsOptionsExcludesOnlyDisabledOnes(): void {
    $enabled = $this->createAutomator('node.article.field_string.enabled', TRUE);
    $this->createAutomator('node.article.field_string.disabled', FALSE);

    $plugin = $this->plugin();
    $this->assertTrue($plugin->isAvailable());
    $this->assertSame(
      [$enabled->id() => $enabled->label()],
      $plugin->getAutomatorsOptions('node', 'article', 'field_string')
    );
  }

  /**
   * Re-enabling a disabled automator makes the action available again.
   */
  public function testIsAvailableReflectsStatusToggle(): void {
    $automator = $this->createAutomator('node.article.field_string.toggle', FALSE);
    $plugin = $this->plugin();
    $this->assertFalse($plugin->isAvailable());

    $automator->setStatus(TRUE)->save();
    $this->assertTrue($this->plugin()->isAvailable());
  }

  /**
   * An enabled automator on a different bundle does not leak into this one.
   *
   * Guards the entity_type/field_name loadByProperties() filter against
   * accidentally widening the match beyond what
   * automatorAppliesToFieldBundleContext() used to enforce alone.
   */
  public function testIsAvailableFalseWhenEnabledAutomatorTargetsOtherBundle(): void {
    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();
    FieldConfig::create([
      'field_name' => 'field_string',
      'entity_type' => 'node',
      'bundle' => 'page',
      'label' => 'field_string',
    ])->save();

    AiAutomator::create([
      'id' => 'node.page.field_string.enabled',
      'label' => 'Page automator',
      'status' => TRUE,
      'rule' => 'llm_string',
      'input_mode' => 'base',
      'weight' => 100,
      'worker_type' => 'direct',
      'entity_type' => 'node',
      'bundle' => 'page',
      'field_name' => 'field_string',
      'edit_mode' => FALSE,
      'base_field' => 'field_string',
      'prompt' => 'x',
      'token' => '',
      'plugin_config' => [],
    ])->save();

    $plugin = $this->plugin();
    $this->assertFalse($plugin->isAvailable());
    $this->assertSame([], $plugin->getAutomatorsOptions('node', 'article', 'field_string'));
  }

}
