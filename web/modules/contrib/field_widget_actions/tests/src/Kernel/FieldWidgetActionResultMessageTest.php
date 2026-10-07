<?php

declare(strict_types=1);

namespace Drupal\Tests\field_widget_actions\Kernel;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for the configurable action result message.
 *
 * @group field_widget_actions
 */
#[RunTestsInSeparateProcesses]
class FieldWidgetActionResultMessageTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'filter',
    'node',
    'field_widget_actions',
    'field_widget_actions_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installConfig(['field', 'node', 'filter']);

    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();

    FieldStorageConfig::create([
      'field_name' => 'field_test',
      'entity_type' => 'node',
      'type' => 'string',
      'cardinality' => 1,
    ])->save();
    FieldConfig::create([
      'field_storage' => FieldStorageConfig::loadByName('node', 'field_test'),
      'bundle' => 'article',
      'label' => 'Test',
    ])->save();
  }

  /**
   * Creates a configured action plugin instance.
   *
   * @param string $id
   *   The plugin id.
   * @param array $configuration
   *   The plugin configuration.
   *
   * @return \Drupal\field_widget_actions\FieldWidgetActionInterface
   *   The plugin instance.
   */
  protected function plugin(string $id, array $configuration = []) {
    return $this->container
      ->get('plugin.manager.field_widget_actions')
      ->createInstance($id, $configuration);
  }

  /**
   * Result messaging is off by default.
   */
  public function testMessagingIsOffByDefault(): void {
    $default = $this->plugin('suggest_texts_for_textfield')->getConfiguration();
    $this->assertFalse($default['show_result_message']);
  }

  /**
   * The message third-party settings conform to the config schema.
   *
   * KernelTestBase validates config against its schema on save, so a malformed
   * schema for the three new keys — including the nullable message text —
   * would throw here.
   */
  public function testConfigMatchesSchema(): void {
    $uuid = 'ec6795f3-3956-4df2-bd64-980e5002129d';
    $display = EntityFormDisplay::load('node.article.default')
      ?? EntityFormDisplay::create([
        'targetEntityType' => 'node',
        'bundle' => 'article',
        'mode' => 'default',
        'status' => TRUE,
      ]);
    $display->setComponent('field_test', [
      'type' => 'string_textfield',
      'region' => 'content',
      'third_party_settings' => [
        'field_widget_actions' => [
          $uuid => [
            'enabled' => TRUE,
            'automatic' => FALSE,
            'button_label' => 'Suggest',
            'multiple' => FALSE,
            'weight' => 0,
            'plugin_id' => 'suggest_texts_for_textfield',
            'show_result_message' => TRUE,
            'message_success' => 'Got something for @field.',
            'message_empty' => 'Nothing for @field.',
          ],
        ],
      ],
    ])->save();

    $stored = EntityFormDisplay::load('node.article.default')
      ->getComponent('field_test')['third_party_settings']['field_widget_actions'][$uuid];
    $this->assertTrue($stored['show_result_message']);
    $this->assertSame('Got something for @field.', $stored['message_success']);

    // The nullable message text must also validate.
    $display = EntityFormDisplay::load('node.article.default');
    $component = $display->getComponent('field_test');
    $component['third_party_settings']['field_widget_actions'][$uuid]['message_success'] = NULL;
    $component['third_party_settings']['field_widget_actions'][$uuid]['message_empty'] = NULL;
    $display->setComponent('field_test', $component)->save();
    $reloaded = EntityFormDisplay::load('node.article.default')
      ->getComponent('field_test')['third_party_settings']['field_widget_actions'][$uuid];
    $this->assertNull($reloaded['message_success']);
    $this->assertNull($reloaded['message_empty']);
  }

  /**
   * The rebuild-based fixture wires a submit handler onto its button.
   *
   * A '#type' => 'button' element does not run submit handlers unless
   * '#executes_submit_callback' is set, and the whole rebuild path depends on
   * the value being written during the submit phase. Pin both so a change to
   * actionButton() cannot silently strip them.
   */
  public function testRebuildActionWiresSubmitHandler(): void {
    $node = \Drupal::entityTypeManager()->getStorage('node')->create([
      'type' => 'article',
      'title' => 'Test',
    ]);
    $plugin = $this->plugin('rebuild_fill', [
      'produce_value' => TRUE,
      'multiple' => FALSE,
      'enabled' => TRUE,
    ]);
    $form = ['widget' => [0 => []]];
    $plugin->completeFormAlter($form, new FormState(), [
      'items' => $node->get('field_test'),
      'action_id' => 'uuid',
      'delta' => NULL,
    ]);

    $button = $form['uuid'];
    $this->assertTrue($button['#executes_submit_callback'], 'A #type button needs this or its submit handler never runs.');
    $this->assertContains([$plugin, 'writeValueSubmit'], $button['#submit']);
    // The wrapper still owns the AJAX callback.
    $this->assertSame([$plugin, 'reportResultAjax'], $button['#ajax']['callback']);
    $this->assertSame('field_test', $button['#field_widget_action_field_name']);
  }

}
