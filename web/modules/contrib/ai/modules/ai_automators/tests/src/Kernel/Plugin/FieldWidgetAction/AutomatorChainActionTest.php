<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_automators\Kernel\Plugin\FieldWidgetAction;

use Drupal\ai_automators\Entity\AiAutomator;
use Drupal\ai_automators\Entity\AutomatorChainType;
use Drupal\ai_automators\Plugin\FieldWidgetAction\AutomatorChainBaseAction;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormState;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Tests the automator chain field widget action plugins.
 *
 * Covers the AutomatorChainBaseAction contract via the concrete
 * automator_chain_text plugin:
 * - Config form: chain select options, per-chain input mapping selects
 *   filtered by main-property compatibility with expected-type
 *   descriptions, output field selects, #states selectors keyed by the
 *   passed action ID, and the chain settings pruning element validator.
 * - isAvailable() / getChainOptions() filter chains by output field types
 *   compatible with the plugin's field types.
 * - populateAutomatorValues() runs the chain through the
 *   ai_automator.automate service (deterministic echoai provider) and
 *   writes the output into $form_state->getUserInput().
 * - Value massaging strips properties unknown to the receiving field and
 *   respects its cardinality — for chain outputs and mapped inputs alike.
 * - A deleted chain type or a non-entity form degrades gracefully instead
 *   of causing a fatal error or a wasted provider call.
 * - The 'use automator chain widget actions' permission gates both the
 *   button rendering and the chain execution.
 *
 * @group ai_automators
 */
class AutomatorChainActionTest extends KernelTestBase {

  use UserCreationTrait;

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
    'ai_test',
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
    $this->installEntitySchema('file');
    $this->installEntitySchema('automator_chain');
    $this->installEntitySchema('ai_mock_provider_result');
    $this->installConfig(['system', 'field', 'node', 'filter', 'ai', 'ai_test']);

    // Burn uid 1 (which bypasses all permission checks) so the permission
    // gating below is real, then run as a user holding the permission so
    // the run-path tests pass under the gate.
    $this->createUser();
    $this->setUpCurrentUser([], ['use automator chain widget actions']);

    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    $this->createField('node', 'article', 'field_source', 'string');
    $this->createField('node', 'article', 'field_target', 'string');
    // A file field: main property target_id, incompatible with a text
    // chain input (main property value), so it must be filtered out of
    // the source options.
    $this->createField('node', 'article', 'field_attachment', 'file');

    // A chain with a required text input and an automated string output,
    // run by a real llm_string rule against the deterministic echoai
    // provider. The prompt leads with the JSON array the rule expects, so
    // the echoed prompt decodes to the rendered {{ context }} value.
    AutomatorChainType::create(['id' => 'text_chain', 'label' => 'Text chain'])->save();
    $this->createField('automator_chain', 'text_chain', 'field_chain_input', 'text_long', TRUE);
    $this->createField('automator_chain', 'text_chain', 'field_chain_output', 'string');
    // The runtime automator config is assembled from the automator_
    // prefixed plugin_config keys only (see
    // AiAutomatorEntityModifier::entityHasConfig()).
    AiAutomator::create([
      'id' => 'automator_chain.text_chain.field_chain_output.default',
      'label' => 'Chain output',
      'rule' => 'llm_string',
      'input_mode' => 'base',
      'weight' => 100,
      'worker_type' => 'direct',
      'entity_type' => 'automator_chain',
      'bundle' => 'text_chain',
      'field_name' => 'field_chain_output',
      'edit_mode' => FALSE,
      'base_field' => 'field_chain_input',
      'prompt' => '[{"value": "{{ context }}"}]',
      'token' => '',
      'plugin_config' => [
        'automator_enabled' => 1,
        'automator_rule' => 'llm_string',
        'automator_mode' => 'base',
        'automator_base_field' => 'field_chain_input',
        'automator_prompt' => '[{"value": "{{ context }}"}]',
        'automator_token' => '',
        'automator_edit_mode' => 0,
        'automator_label' => 'Chain output',
        'automator_weight' => '100',
        'automator_worker_type' => 'direct',
        'automator_ai_provider' => 'echoai',
        'automator_ai_model' => 'default',
      ],
    ])->save();
  }

  /**
   * Creates a field storage + instance.
   */
  protected function createField(string $entity_type, string $bundle, string $name, string $type, bool $required = FALSE, int $cardinality = 1): void {
    FieldStorageConfig::create([
      'field_name' => $name,
      'entity_type' => $entity_type,
      'type' => $type,
      'cardinality' => $cardinality,
    ])->save();
    FieldConfig::create([
      'field_name' => $name,
      'entity_type' => $entity_type,
      'bundle' => $bundle,
      'label' => $name,
      'required' => $required,
    ])->save();
  }

  /**
   * Creates an automator_chain_text plugin instance for field_target.
   */
  protected function plugin(array $configuration = []): AutomatorChainBaseAction {
    $plugin = $this->container->get('plugin.manager.field_widget_actions')
      ->createInstance('automator_chain_text', $configuration);
    $definitions = $this->container->get('entity_field.manager')
      ->getFieldDefinitions('node', 'article');
    $plugin->setFieldDefinition($definitions['field_target']);
    return $plugin;
  }

  /**
   * The default plugin settings pointing at the text_chain fixture.
   */
  protected function chainConfiguration(): array {
    return [
      'settings' => [
        'automator_chain_type' => 'text_chain',
        'chain_settings' => [
          'text_chain' => [
            'input_mapping' => [
              'field_chain_input' => 'field_source',
            ],
            'output_field' => 'field_chain_output',
          ],
        ],
      ],
    ];
  }

  /**
   * Builds a form + form state carrying a real node form object.
   */
  protected function createNodeFormState(Node $node): array {
    $display = EntityFormDisplay::create([
      'targetEntityType' => 'node',
      'bundle' => 'article',
      'mode' => 'default',
      'status' => TRUE,
    ]);
    $display->setComponent('field_target', ['type' => 'string_textfield'])->save();
    $form_object = $this->container->get('entity_type.manager')->getFormObject('node', 'default');
    $form_object->setEntity($node);
    $form_state = new FormState();
    $form_state->setFormObject($form_object);
    $form_state->set('form_display', $display);
    $form = [
      '#parents' => [],
      'field_target' => ['widget' => ['#field_parents' => []]],
    ];
    return [$form, $form_state];
  }

  /**
   * The config form offers the chain, its inputs and compatible outputs.
   */
  public function testConfigFormOptions(): void {
    $element = $this->plugin()->buildConfigurationForm([], new FormState(), 'test-uuid-123');

    $this->assertArrayHasKey('text_chain', $element['settings']['automator_chain_type']['#options']);

    $chain_form = $element['settings']['chain_settings']['text_chain'];
    $this->assertArrayHasKey('field_source', $chain_form['input_mapping']['field_chain_input']['#options']);
    $this->assertSame(['field_chain_output'], array_keys($chain_form['output_field']['#options']));

    // Source options are filtered by main-property compatibility: the
    // file field (target_id) cannot feed the text_long input (value).
    $this->assertArrayNotHasKey('field_attachment', $chain_form['input_mapping']['field_chain_input']['#options']);
    // The description states the chain input's expected field type.
    $this->assertStringContainsString('text_long', (string) $chain_form['input_mapping']['field_chain_input']['#description']);

    // The subform is keyed by the action ID (a UUID for actions added
    // through the UI), so #states selectors must embed it.
    $enabled_selector = array_key_first($element['settings']['#states']['visible']);
    $this->assertStringContainsString('[test-uuid-123][enabled]', $enabled_selector);
    $chain_selector = array_key_first($chain_form['#states']['visible']);
    $this->assertStringContainsString('[test-uuid-123][settings][automator_chain_type]', $chain_selector);

    // The pruning element validator is wired on the settings element.
    $validator = $element['settings']['#element_validate'][0] ?? NULL;
    $this->assertIsCallable($validator);
    $this->assertSame('validateChainSettings', $validator[1]);
  }

  /**
   * Only the selected chain's settings survive the element validator.
   */
  public function testChainSettingsPruningOnValidate(): void {
    $element = ['#parents' => ['settings']];
    $form_state = new FormState();
    $form_state->setValues([
      'settings' => [
        'automator_chain_type' => 'text_chain',
        'chain_settings' => [
          'text_chain' => [
            'input_mapping' => ['field_chain_input' => 'field_source'],
            'output_field' => 'field_chain_output',
          ],
          'other_chain' => [
            'input_mapping' => ['field_other_input' => 'field_source'],
            'output_field' => 'field_other_output',
          ],
        ],
      ],
    ]);

    AutomatorChainBaseAction::validateChainSettings($element, $form_state);

    $this->assertSame(['text_chain'], array_keys($form_state->getValue(['settings', 'chain_settings'])));
  }

  /**
   * Chains without a text-compatible output are filtered out.
   */
  public function testIsAvailableFiltersByOutputType(): void {
    // A chain whose only automated field is an email field — not in the
    // text plugin's field types.
    AutomatorChainType::create(['id' => 'email_chain', 'label' => 'Email chain'])->save();
    $this->createField('automator_chain', 'email_chain', 'field_email_output', 'email');
    AiAutomator::create([
      'id' => 'automator_chain.email_chain.field_email_output.default',
      'label' => 'Email output',
      'rule' => 'llm_string',
      'input_mode' => 'base',
      'entity_type' => 'automator_chain',
      'bundle' => 'email_chain',
      'field_name' => 'field_email_output',
      'base_field' => 'field_email_output',
      'prompt' => 'x',
      'token' => '',
      'weight' => 100,
      'worker_type' => 'direct',
      'edit_mode' => FALSE,
      'plugin_config' => [],
    ])->save();

    $plugin = $this->plugin();
    $this->assertTrue($plugin->isAvailable());
    $element = $plugin->buildConfigurationForm([], new FormState(), 'uuid');
    $this->assertSame(['text_chain'], array_keys($element['settings']['automator_chain_type']['#options']));

    // With no compatible chain left, the action is unavailable.
    AutomatorChainType::load('text_chain')->delete();
    $this->assertFalse($this->plugin()->isAvailable());
  }

  /**
   * Running the chain writes the output into the widget's user input.
   */
  public function testChainRunPopulatesUserInput(): void {
    $node = Node::create([
      'type' => 'article',
      'title' => 'T',
      'field_source' => 'hello world',
    ]);
    [$form, $form_state] = $this->createNodeFormState($node);

    $plugin = $this->plugin($this->chainConfiguration());
    $plugin->populateAutomatorValues($form, $form_state, 'field_target');

    $input = $form_state->getUserInput();
    $this->assertArrayHasKey('field_target', $input);
    $this->assertStringContainsString('hello world', $input['field_target'][0]['value']);
    $this->assertTrue($form_state->isRebuilding());
    // No automator_chain entities are left behind.
    $this->assertEmpty($this->container->get('entity_type.manager')->getStorage('automator_chain')->loadMultiple());
  }

  /**
   * Value massaging strips unknown properties and respects cardinality.
   */
  public function testMassageValuesForField(): void {
    $plugin = $this->plugin();
    $definitions = $this->container->get('entity_field.manager')
      ->getFieldDefinitions('node', 'article');

    $method = new \ReflectionMethod($plugin, 'massageValuesForField');
    $method->setAccessible(TRUE);
    $values = $method->invoke($plugin, [
      ['value' => 'first', 'format' => 'basic_html'],
      ['value' => 'second'],
    ], $definitions['field_target']);

    // The format key of a text_long chain output must not leak into the
    // string target field, and a single-value field gets a single item.
    $this->assertSame([['value' => 'first']], $values);
  }

  /**
   * Mapped source values are massaged into the chain input's shape.
   */
  public function testInputValuesAreMassaged(): void {
    // A chain whose required input is a plain string field, fed from a
    // rich text host field — the format property must be stripped before
    // the value reaches the chain entity's setValue().
    AutomatorChainType::create(['id' => 'string_chain', 'label' => 'String chain'])->save();
    $this->createField('automator_chain', 'string_chain', 'field_string_input', 'string', TRUE);
    $this->createField('node', 'article', 'field_rich', 'text_long');

    $node = Node::create([
      'type' => 'article',
      'title' => 'T',
      'field_rich' => ['value' => 'rich text', 'format' => 'basic_html'],
    ]);

    $plugin = $this->plugin();
    $method = new \ReflectionMethod($plugin, 'buildChainInputs');
    $method->setAccessible(TRUE);
    $inputs = $method->invoke($plugin, $node, 'string_chain', ['field_string_input' => 'field_rich']);

    $this->assertSame([['value' => 'rich text']], $inputs['field_string_input']);
  }

  /**
   * A deleted chain type degrades gracefully.
   */
  public function testMissingChainIsGraceful(): void {
    $node = Node::create([
      'type' => 'article',
      'title' => 'T',
      'field_source' => 'hello world',
    ]);
    [$form, $form_state] = $this->createNodeFormState($node);
    $form_state->setUserInput(['sentinel' => 'unchanged']);

    $configuration = $this->chainConfiguration();
    $configuration['settings']['automator_chain_type'] = 'deleted_chain';
    $plugin = $this->plugin($configuration);
    $plugin->populateAutomatorValues($form, $form_state, 'field_target');

    $this->assertSame(['sentinel' => 'unchanged'], $form_state->getUserInput());
    $this->assertFalse($form_state->isRebuilding());
    $messages = $this->container->get('messenger')->messagesByType('error');
    $this->assertNotEmpty($messages);
  }

  /**
   * A form without a content entity form object degrades gracefully.
   */
  public function testNonEntityFormIsGraceful(): void {
    // A form object that is not a content entity form — buildEntity()
    // returns NULL.
    $form_state = new FormState();
    $form_state->setFormObject($this->createMock(FormInterface::class));
    $form_state->setUserInput(['sentinel' => 'unchanged']);
    $form = [
      '#parents' => [],
      'field_target' => ['widget' => ['#field_parents' => []]],
    ];

    $plugin = $this->plugin($this->chainConfiguration());
    $plugin->populateAutomatorValues($form, $form_state, 'field_target');

    $this->assertSame(['sentinel' => 'unchanged'], $form_state->getUserInput());
    $this->assertFalse($form_state->isRebuilding());
    $this->assertNotEmpty($this->container->get('messenger')->messagesByType('error'));
    // The chain never ran — no wasted provider call.
    $this->assertEmpty($this->container->get('entity_type.manager')->getStorage('ai_mock_provider_result')->loadMultiple());
  }

  /**
   * The permission gates both the button rendering and the execution.
   */
  public function testPermissionGating(): void {
    $node = Node::create([
      'type' => 'article',
      'title' => 'T',
      'field_source' => 'hello world',
    ]);
    $context = [
      'items' => $node->get('field_target'),
      'action_id' => 'test-uuid-123',
    ];

    // The privileged user from setUp() gets the button.
    $plugin = $this->plugin($this->chainConfiguration());
    $method = new \ReflectionMethod($plugin, 'actionButton');
    $method->setAccessible(TRUE);
    $form = [];
    $method->invokeArgs($plugin, [&$form, new FormState(), $context]);
    $this->assertArrayHasKey('test-uuid-123', $form);

    // An unprivileged user gets no button and cannot execute.
    $this->setUpCurrentUser();
    $plugin = $this->plugin($this->chainConfiguration());
    $form = [];
    $method->invokeArgs($plugin, [&$form, new FormState(), $context]);
    $this->assertSame([], $form);

    [$run_form, $form_state] = $this->createNodeFormState($node);
    $form_state->setUserInput(['sentinel' => 'unchanged']);
    $plugin->populateAutomatorValues($run_form, $form_state, 'field_target');
    $this->assertSame(['sentinel' => 'unchanged'], $form_state->getUserInput());
    $this->assertFalse($form_state->isRebuilding());
    $this->assertNotEmpty($this->container->get('messenger')->messagesByType('error'));
    $this->assertEmpty($this->container->get('entity_type.manager')->getStorage('ai_mock_provider_result')->loadMultiple());
  }

}
