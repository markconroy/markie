<?php

declare(strict_types=1);

namespace Drupal\Tests\field_widget_actions\Kernel;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Form\FormState;
use Drupal\Core\Render\Element;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field_widget_actions\FieldWidgetActionBase;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the target element lookup against real widget form shapes.
 *
 * FieldWidgetActionBase::getTargetElement() must return the actual input
 * element the action button is attached to. Widgets differ in where that
 * input lives: options widgets are the input themselves (and this module
 * wraps them in a container), autocomplete and link widgets keep it under the
 * field's main property, text widgets under 'value', and an action may target
 * another property altogether. Every shape is built through the real entity
 * form so the assertions cover what the AJAX callbacks actually receive.
 *
 * @group field_widget_actions
 * @coversDefaultClass \Drupal\field_widget_actions\FieldWidgetActionBase
 */
#[RunTestsInSeparateProcesses]
#[Group('field_widget_actions')]
class FieldWidgetActionsTargetElementTest extends KernelTestBase {

  /**
   * The field name used for every shape.
   */
  const FIELD_NAME = 'field_test';

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
    'taxonomy',
    'options',
    'link',
    'file',
    'image',
    'datetime',
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
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('file');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('file', ['file_usage']);
    $this->installConfig(['system', 'field', 'node', 'filter', 'taxonomy']);

    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();

    Vocabulary::create(['vid' => 'tags', 'name' => 'Tags'])->save();
    for ($i = 1; $i <= 5; $i++) {
      Term::create([
        'name' => "Term $i",
        'vid' => 'tags',
      ])->save();
    }
  }

  /**
   * Provides the widget shapes the lookup has to resolve.
   *
   * Each case builds one field with one widget and one action button in a
   * given mode, and names the element the lookup must return. The '#name' is
   * what the fill commands key on; '#attributes' name and '#multiple' are the
   * details a select plugin needs to build a selector for a multiple select.
   *
   * @return array[]
   *   The test cases.
   */
  public static function widgetShapeProvider(): array {
    $tags_storage = [
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'taxonomy_term'],
    ];
    $tags_field = [
      'settings' => [
        'handler' => 'default:taxonomy_term',
        'handler_settings' => ['target_bundles' => ['tags' => 'tags']],
      ],
    ];
    $list_storage = [
      'type' => 'list_string',
      'settings' => [
        'allowed_values' => ['one' => 'One', 'two' => 'Two', 'three' => 'Three'],
      ],
    ];
    // Without display_summary the summary is a hidden 'value' element.
    $summary_field = ['settings' => ['display_summary' => TRUE]];
    $resolve = 'resolve_target_test_action';

    return [
      // Options select: the element is the input; per-item buttons get the
      // module's container wrapper, whole-field buttons sit beside the select.
      'options_select, entity reference, unlimited, per-item' => [
        $tags_storage, $tags_field, -1, 'options_select', $resolve, TRUE,
        [
          '#type' => 'select',
          '#name' => 'field_test[widget]',
          'name' => 'field_test[widget][]',
          '#multiple' => TRUE,
          'selector' => 'edit-field-test-widget',
        ],
      ],
      'options_select, entity reference, unlimited, whole-field' => [
        $tags_storage, $tags_field, -1, 'options_select', $resolve, FALSE,
        [
          '#type' => 'select',
          '#name' => 'field_test',
          'name' => 'field_test[]',
          '#multiple' => TRUE,
          'selector' => 'edit-field-test',
        ],
      ],
      'options_select, list string, single, per-item' => [
        $list_storage, [], 1, 'options_select', $resolve, TRUE,
        [
          '#type' => 'select',
          '#name' => 'field_test[widget]',
          'name' => 'field_test[widget]',
          '#multiple' => FALSE,
          'selector' => 'edit-field-test-widget',
        ],
      ],
      'options_select, list string, single, whole-field' => [
        $list_storage, [], 1, 'options_select', $resolve, FALSE,
        [
          '#type' => 'select',
          '#name' => 'field_test',
          'name' => 'field_test',
          '#multiple' => FALSE,
          'selector' => 'edit-field-test',
        ],
      ],
      // Options buttons are not wrapped; the checkboxes/radios element is the
      // input in both modes.
      'options_buttons, entity reference, unlimited, per-item' => [
        $tags_storage, $tags_field, -1, 'options_buttons', $resolve, TRUE,
        ['#type' => 'checkboxes', '#name' => 'field_test'],
      ],
      'options_buttons, entity reference, unlimited, whole-field' => [
        $tags_storage, $tags_field, -1, 'options_buttons', $resolve, FALSE,
        ['#type' => 'checkboxes', '#name' => 'field_test'],
      ],
      'options_buttons, entity reference, single, per-item' => [
        $tags_storage, $tags_field, 1, 'options_buttons', $resolve, TRUE,
        ['#type' => 'radios', '#name' => 'field_test'],
      ],
      'options_buttons, entity reference, single, whole-field' => [
        $tags_storage, $tags_field, 1, 'options_buttons', $resolve, FALSE,
        ['#type' => 'radios', '#name' => 'field_test'],
      ],
      // Autocomplete widgets keep the input under the main property.
      'autocomplete tags, entity reference, unlimited, per-item' => [
        $tags_storage, $tags_field, -1, 'entity_reference_autocomplete_tags', $resolve, TRUE,
        ['#type' => 'entity_autocomplete', '#name' => 'field_test[target_id]'],
      ],
      'autocomplete tags, entity reference, unlimited, whole-field' => [
        $tags_storage, $tags_field, -1, 'entity_reference_autocomplete_tags', $resolve, FALSE,
        ['#type' => 'entity_autocomplete', '#name' => 'field_test[target_id]'],
      ],
      'autocomplete, entity reference, unlimited, per-item' => [
        $tags_storage, $tags_field, -1, 'entity_reference_autocomplete', $resolve, TRUE,
        ['#type' => 'entity_autocomplete', '#name' => 'field_test[0][target_id]'],
      ],
      'autocomplete, entity reference, unlimited, whole-field' => [
        $tags_storage, $tags_field, -1, 'entity_reference_autocomplete', $resolve, FALSE,
        ['#type' => 'entity_autocomplete', '#name' => 'field_test[0][target_id]'],
      ],
      // Plain text widgets: the 'value' child of the first delta.
      'textfield, string, single, per-item' => [
        ['type' => 'string'], [], 1, 'string_textfield', $resolve, TRUE,
        ['#type' => 'textfield', '#name' => 'field_test[0][value]'],
      ],
      'textfield, string, single, whole-field' => [
        ['type' => 'string'], [], 1, 'string_textfield', $resolve, FALSE,
        ['#type' => 'textfield', '#name' => 'field_test[0][value]'],
      ],
      // Link: the main property is 'uri', not 'value'.
      'link, single, per-item' => [
        ['type' => 'link'], [], 1, 'link_default', $resolve, TRUE,
        ['#name' => 'field_test[0][uri]'],
      ],
      // The date widget keeps its input under 'value' as well.
      'datetime, single, per-item' => [
        ['type' => 'datetime'], [], 1, 'datetime_default', $resolve, TRUE,
        ['#type' => 'datetime', '#name' => 'field_test[0][value]'],
      ],
      // A plugin overriding FORM_ELEMENT_PROPERTY targets that property child.
      'summary override, text with summary, per-item' => [
        ['type' => 'text_with_summary'], $summary_field, 1, 'text_textarea_with_summary', 'refinable_summary_for_textfield', TRUE,
        ['#type' => 'textarea', '#name' => 'field_test[0][summary]'],
      ],
      'summary override, text with summary, whole-field' => [
        ['type' => 'text_with_summary'], $summary_field, 1, 'text_textarea_with_summary', 'refinable_summary_for_textfield', FALSE,
        ['#type' => 'textarea', '#name' => 'field_test[0][summary]'],
      ],
      // The image widget element is itself a managed_file input; the override
      // must still reach its 'alt' child.
      'alt override, image, per-item' => [
        ['type' => 'image'], [], 1, 'image_image', 'fill_image_alt_test_action', TRUE,
        ['#type' => 'textfield', '#name' => 'field_test[0][alt]'],
      ],
      'alt override, image, whole-field' => [
        ['type' => 'image'], [], 1, 'image_image', 'fill_image_alt_test_action', FALSE,
        ['#type' => 'textfield', '#name' => 'field_test[0][alt]'],
      ],
      // Without an override the managed_file element is the target itself.
      'no override, image, per-item' => [
        ['type' => 'image'], [], 1, 'image_image', $resolve, TRUE,
        ['#type' => 'managed_file', '#name' => 'field_test[0]'],
      ],
    ];
  }

  /**
   * The lookup resolves the input element for every widget shape.
   *
   * @param array $storage
   *   Field storage values (type and settings).
   * @param array $field
   *   Additional field config values.
   * @param int $cardinality
   *   The field cardinality.
   * @param string $widget_type
   *   The widget plugin id.
   * @param string $plugin_id
   *   The action plugin id to attach.
   * @param bool $multiple
   *   Whether the action is per-item (TRUE) or whole-field (FALSE).
   * @param array $expected
   *   Expected '#type', '#name', and optionally rendered 'name', '#multiple'
   *   and data-drupal-'selector' of the resolved element.
   *
   * @covers ::getTargetElement
   * @covers ::isTargetElementInput
   * @covers ::getTargetElementProperty
   */
  #[DataProvider('widgetShapeProvider')]
  public function testTargetElementResolution(array $storage, array $field, int $cardinality, string $widget_type, string $plugin_id, bool $multiple, array $expected): void {
    $this->createField($storage, $field, $cardinality);
    $this->configureDisplay($widget_type, $plugin_id, $multiple);

    $target = $this->resolveTargetElement($plugin_id);

    $this->assertNotEmpty($target, 'The lookup must resolve an element.');
    if (isset($expected['#type'])) {
      $this->assertSame($expected['#type'], $target['#type'] ?? NULL);
    }
    $this->assertSame($expected['#name'], $target['#name'] ?? NULL, 'The fill commands key on #name.');
    if (isset($expected['name'])) {
      $this->assertSame($expected['name'], $target['#attributes']['name'] ?? $target['#name'], 'The rendered name must match what a selector can find.');
    }
    if (array_key_exists('#multiple', $expected)) {
      $this->assertSame($expected['#multiple'], !empty($target['#multiple']));
    }
    if (isset($expected['selector'])) {
      $this->assertSame($expected['selector'], $target['#attributes']['data-drupal-selector'] ?? NULL, 'getSuggestionsTarget() keys on data-drupal-selector.');
    }
    $this->assertFalse($this->isActionButton($target), 'The action button itself is never the target.');
  }

  /**
   * Creates the test field on the article bundle.
   *
   * @param array $storage
   *   Field storage values (type and settings).
   * @param array $field
   *   Additional field config values.
   * @param int $cardinality
   *   The field cardinality.
   */
  protected function createField(array $storage, array $field, int $cardinality): void {
    FieldStorageConfig::create($storage + [
      'field_name' => static::FIELD_NAME,
      'entity_type' => 'node',
      'cardinality' => $cardinality,
    ])->save();
    FieldConfig::create($field + [
      'field_storage' => FieldStorageConfig::loadByName('node', static::FIELD_NAME),
      'bundle' => 'article',
      'label' => 'Test field',
    ])->save();
  }

  /**
   * Configures the form display with the widget and one action button.
   *
   * @param string $widget_type
   *   The widget plugin id.
   * @param string $plugin_id
   *   The action plugin id.
   * @param bool $multiple
   *   Whether the action is per-item (TRUE) or whole-field (FALSE).
   */
  protected function configureDisplay(string $widget_type, string $plugin_id, bool $multiple): void {
    $display = EntityFormDisplay::load('node.article.default')
      ?? EntityFormDisplay::create([
        'targetEntityType' => 'node',
        'bundle' => 'article',
        'mode' => 'default',
        'status' => TRUE,
      ]);
    $display->setComponent('title', ['type' => 'string_textfield', 'weight' => -5]);
    $display->setComponent(static::FIELD_NAME, [
      'type' => $widget_type,
      'region' => 'content',
      'third_party_settings' => [
        'field_widget_actions' => [
          'target-uuid-1234' => [
            'enabled' => TRUE,
            'button_label' => 'Fill',
            'multiple' => $multiple,
            'plugin_id' => $plugin_id,
          ],
        ],
      ],
    ])->save();
  }

  /**
   * Determines whether an element is a field widget action button.
   *
   * @param array $element
   *   The element.
   *
   * @return bool
   *   TRUE for an action button.
   */
  protected function isActionButton(array $element): bool {
    return ($element['#field_widget_action_field_name'] ?? NULL) === static::FIELD_NAME;
  }

  /**
   * Finds the first element matching the predicate, recursively.
   *
   * @param array $element
   *   The render array to search.
   * @param callable $matcher
   *   A predicate receiving each element and returning bool.
   *
   * @return array|null
   *   The first matching element, or NULL.
   */
  protected function findElement(array $element, callable $matcher): ?array {
    if ($matcher($element)) {
      return $element;
    }
    foreach (Element::children($element) as $key) {
      if (is_array($element[$key])) {
        $found = $this->findElement($element[$key], $matcher);
        if ($found !== NULL) {
          return $found;
        }
      }
    }
    return NULL;
  }

  /**
   * Builds the node form and resolves the target element for the action.
   *
   * @param string $plugin_id
   *   The action plugin id whose lookup is exercised.
   *
   * @return array
   *   The target element returned by getTargetElement().
   */
  protected function resolveTargetElement(string $plugin_id): array {
    $node = Node::create(['type' => 'article']);
    $form = $this->container->get('entity.form_builder')->getForm($node, 'default');

    $button = $this->findElement($form, [$this, 'isActionButton']);
    $this->assertNotNull($button, 'The action button must be present in the built form.');

    $form_state = new FormState();
    $form_state->setTriggeringElement($button);

    $plugin = $this->container
      ->get('plugin.manager.field_widget_actions')
      ->createInstance($plugin_id);
    $plugin->setFieldDefinition(FieldConfig::loadByName('node', 'article', static::FIELD_NAME));

    $reflection = new \ReflectionMethod(FieldWidgetActionBase::class, 'getTargetElement');
    $args = [&$form, $form_state];
    return $reflection->invokeArgs($plugin, $args);
  }

}
