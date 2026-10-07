<?php

declare(strict_types=1);

namespace Drupal\Tests\field_widget_actions\Kernel;

use PHPUnit\Framework\Attributes\Group;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormState;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for the refinement configuration and container-level behavior.
 *
 * @group field_widget_actions
 */
#[RunTestsInSeparateProcesses]
#[Group('field_widget_actions')]
class FieldWidgetActionRefinementTest extends KernelTestBase {

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
   * The refinement third-party settings conform to the config schema.
   *
   * KernelTestBase validates config against its schema on save, so a malformed
   * schema for enable_refinement / refinement_modal_title (including the
   * nullable case) would throw here.
   */
  public function testRefinementConfigMatchesSchema(): void {
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
          'ec6795f3-3956-4df2-bd64-980e5002129d' => [
            'enabled' => TRUE,
            'automatic' => FALSE,
            'button_label' => 'Refine',
            'multiple' => FALSE,
            'weight' => 0,
            'plugin_id' => 'refinable_texts_for_textfield',
            'enable_refinement' => TRUE,
            'refinement_modal_title' => 'Custom title',
          ],
        ],
      ],
    ])->save();

    // Reload and assert the values persisted through the schema-checked save.
    $stored = EntityFormDisplay::load('node.article.default')
      ->getComponent('field_test')['third_party_settings']['field_widget_actions']['ec6795f3-3956-4df2-bd64-980e5002129d'];
    $this->assertTrue($stored['enable_refinement']);
    $this->assertSame('Custom title', $stored['refinement_modal_title']);

    // The nullable title must also validate.
    $display = EntityFormDisplay::load('node.article.default');
    $component = $display->getComponent('field_test');
    $component['third_party_settings']['field_widget_actions']['ec6795f3-3956-4df2-bd64-980e5002129d']['refinement_modal_title'] = NULL;
    $display->setComponent('field_test', $component)->save();
    $this->assertNull(
      EntityFormDisplay::load('node.article.default')
        ->getComponent('field_test')['third_party_settings']['field_widget_actions']['ec6795f3-3956-4df2-bd64-980e5002129d']['refinement_modal_title']
    );
  }

  /**
   * Refinable plugins default refinement off and round-trip configuration.
   */
  public function testDefaultConfigurationAndRoundTrip(): void {
    $default = $this->plugin('refinable_texts_for_textfield')->getConfiguration();
    $this->assertArrayHasKey('enable_refinement', $default);
    $this->assertFalse($default['enable_refinement']);
    $this->assertArrayHasKey('refinement_modal_title', $default);
    $this->assertNull($default['refinement_modal_title']);

    $configured = $this->plugin('refinable_texts_for_textfield', [
      'enable_refinement' => TRUE,
      'refinement_modal_title' => 'My title',
    ])->getConfiguration();
    $this->assertTrue($configured['enable_refinement']);
    $this->assertSame('My title', $configured['refinement_modal_title']);
  }

  /**
   * The isRefinementEnabled() helper reflects the configuration.
   */
  public function testIsRefinementEnabled(): void {
    $this->assertFalse($this->plugin('refinable_texts_for_textfield')->isRefinementEnabled());
    $this->assertTrue($this->plugin('refinable_texts_for_textfield', ['enable_refinement' => TRUE])->isRefinementEnabled());
  }

  /**
   * Refinement options appear only for refinement-aware plugins.
   *
   * Locks the design decision that the refinement settings live on the
   * refinement-aware base, so they cannot give the false impression that they
   * apply to plugins that do not support refinement.
   */
  public function testBuildConfigurationFormShowsRefinementOnlyForRefinableActions(): void {
    $field_definition = FieldConfig::loadByName('node', 'article', 'field_test');

    $refinable = $this->plugin('refinable_texts_for_textfield');
    $refinable->setFieldDefinition($field_definition);
    $element = $refinable->buildConfigurationForm([], new FormState(), 'uuid');
    $this->assertArrayHasKey('enable_refinement', $element);
    $this->assertArrayHasKey('refinement_modal_title', $element);

    $plain = $this->plugin('suggest_texts_for_textfield');
    $plain->setFieldDefinition($field_definition);
    $plain_element = $plain->buildConfigurationForm([], new FormState(), 'uuid');
    $this->assertArrayNotHasKey('enable_refinement', $plain_element);
    $this->assertArrayNotHasKey('refinement_modal_title', $plain_element);
  }

  /**
   * The action button is a modal button or a direct-fill button by config.
   */
  public function testActionButtonModalVersusDirectFill(): void {
    $node = Node::create(['type' => 'article', 'title' => 'Test']);
    $context = [
      'items' => $node->get('field_test'),
      'action_id' => 'uuid',
      'delta' => NULL,
    ];

    // Refinement enabled → modal button (opens the dialog, no save).
    $modal = $this->plugin('refinable_texts_for_textfield', [
      'enable_refinement' => TRUE,
      'multiple' => FALSE,
      'button_label' => 'Go',
    ]);
    $form = ['widget' => [0 => []]];
    $modal->completeFormAlter($form, new FormState(), $context);
    $button = $form['uuid'];
    $this->assertSame([$modal, 'openModalCallback'], $button['#ajax']['callback']);
    $this->assertContains('use-ajax', $button['#attributes']['class']);
    $this->assertSame([[$modal, 'suppressSave']], $button['#submit']);

    // Refinement disabled → direct-fill button (own AJAX callback, no modal).
    $direct = $this->plugin('refinable_texts_for_textfield', [
      'enable_refinement' => FALSE,
      'multiple' => FALSE,
      'button_label' => 'Go',
    ]);
    $form = ['widget' => [0 => []]];
    $direct->completeFormAlter($form, new FormState(), $context);
    $button = $form['uuid'];
    // Direct-fill buttons route through the base class wrapper, which invokes
    // the plugin's own callback and then reports the outcome to the author.
    $this->assertSame([$direct, 'reportResultAjax'], $button['#ajax']['callback']);
    $this->assertSame('fillDirectly', $direct->getAjaxCallback());
    $this->assertSame(['::validateForm', [$direct, 'clearErrorsForAction']], $button['#validate']);
    $this->assertArrayNotHasKey('#submit', $button);
    $this->assertNotContains('use-ajax', $button['#attributes']['class']);
  }

  /**
   * The buildEntity() helper returns NULL for a non-content-entity form.
   */
  public function testBuildEntityReturnsNullForNonContentEntityForm(): void {
    $form_state = new FormState();
    $form_state->setFormObject($this->createMock(FormInterface::class));
    $this->assertNull($this->plugin('refinable_texts_for_textfield')->buildEntity([], $form_state));
  }

}
