<?php

declare(strict_types=1);

namespace Drupal\Tests\field_widget_actions\Unit;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\field_widget_actions\FieldWidgetActionBase;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Unit tests for the pure logic in FieldWidgetActionBase.
 *
 * @group field_widget_actions
 *
 * @coversDefaultClass \Drupal\field_widget_actions\FieldWidgetActionBase
 */
#[Group('field_widget_actions')]
class FieldWidgetActionBaseTest extends UnitTestCase {

  /**
   * Builds a concrete plugin instance for testing the abstract base.
   *
   * @return \Drupal\field_widget_actions\FieldWidgetActionBase
   *   A test double exposing the protected helpers under test.
   */
  protected function createPlugin(): FieldWidgetActionBase {
    return new class([], 'test_action', ['label' => 'Test Action', 'id' => 'test_action'], $this->createMock(MessengerInterface::class)) extends FieldWidgetActionBase {

      /**
       * {@inheritdoc}
       */
      public function getAjaxCallback(): ?string {
        return NULL;
      }

      /**
       * Exposes isUnfilledRequiredElement() for testing.
       */
      public function callIsUnfilledRequiredElement(array $element): bool {
        return $this->isUnfilledRequiredElement($element);
      }

      /**
       * Exposes collectRequiredButEmptyKeys() for testing.
       */
      public function callCollectRequiredButEmptyKeys(array $form): array {
        $keys = [];
        $this->collectRequiredButEmptyKeys($form, $keys);
        return $keys;
      }

      /**
       * Exposes getActionButtonWidgetId() for testing.
       */
      public function callGetActionButtonWidgetId(string $field_name, array $context): string {
        return $this->getActionButtonWidgetId($field_name, $context);
      }

      /**
       * Exposes getTargetElement() for testing.
       */
      public function callGetTargetElement(array &$form, FormStateInterface $form_state): array {
        return $this->getTargetElement($form, $form_state);
      }

    };
  }

  /**
   * Builds a plugin overriding FORM_ELEMENT_PROPERTY to 'summary'.
   *
   * Mirrors the RefinableSummaryTestAction contract: an action targeting the
   * plain 'summary' property of a text_with_summary field instead of the
   * formatted 'value' property.
   *
   * @return \Drupal\field_widget_actions\FieldWidgetActionBase
   *   A test double whose fill target property is 'summary'.
   */
  protected function createSummaryPlugin(): FieldWidgetActionBase {
    return new class([], 'test_summary_action', ['label' => 'Test Summary Action', 'id' => 'test_summary_action'], $this->createMock(MessengerInterface::class)) extends FieldWidgetActionBase {

      /**
       * The summary property, not the formatted main value.
       */
      const FORM_ELEMENT_PROPERTY = 'summary';

      /**
       * {@inheritdoc}
       */
      public function getAjaxCallback(): ?string {
        return NULL;
      }

      /**
       * Exposes getTargetElement() for testing.
       */
      public function callGetTargetElement(array &$form, FormStateInterface $form_state): array {
        return $this->getTargetElement($form, $form_state);
      }

    };
  }

  /**
   * @covers ::isUnfilledRequiredElement
   * @dataProvider providerIsUnfilledRequiredElement
   */
  #[DataProvider('providerIsUnfilledRequiredElement')]
  public function testIsUnfilledRequiredElement(array $element, bool $expected): void {
    $this->assertSame($expected, $this->createPlugin()->callIsUnfilledRequiredElement($element));
  }

  /**
   * Data provider for testIsUnfilledRequiredElement().
   */
  public static function providerIsUnfilledRequiredElement(): array {
    return [
      'core required-but-empty flag' => [['#required_but_empty' => TRUE], TRUE],
      'required single select unselected' => [['#required' => TRUE, '#value' => '_none'], TRUE],
      'required multi select with only _none' => [['#required' => TRUE, '#value' => ['_none']], TRUE],
      'required with real value preserved' => [['#required' => TRUE, '#value' => 'apple'], FALSE],
      'required multi select with values preserved' => [['#required' => TRUE, '#value' => ['1', '3']], FALSE],
      'not required even if _none' => [['#required' => FALSE, '#value' => '_none'], FALSE],
      'required without a submitted value' => [['#required' => TRUE], FALSE],
      'plain element' => [[], FALSE],
    ];
  }

  /**
   * @covers ::collectRequiredButEmptyKeys
   */
  public function testCollectRequiredButEmptyKeys(): void {
    // A node-style form tree: a required-but-empty title (nested value child),
    // an unselected required select, and a filled field that must be ignored.
    $form = [
      'title' => [
        '#parents' => ['title'],
        'widget' => [
          '#parents' => ['title'],
          0 => [
            '#parents' => ['title', 0],
            'value' => [
              '#parents' => ['title', 0, 'value'],
              '#required_but_empty' => TRUE,
            ],
          ],
        ],
      ],
      'field_choice' => [
        '#parents' => ['field_choice'],
        '#required' => TRUE,
        '#value' => '_none',
      ],
      'field_filled' => [
        '#parents' => ['field_filled'],
        '#required' => TRUE,
        '#value' => 'something',
      ],
    ];

    $keys = $this->createPlugin()->callCollectRequiredButEmptyKeys($form);
    sort($keys);
    $this->assertSame(['field_choice', 'title][0][value'], $keys);
  }

  /**
   * @covers ::getActionButtonWidgetId
   * @dataProvider providerGetActionButtonWidgetId
   */
  #[DataProvider('providerGetActionButtonWidgetId')]
  public function testGetActionButtonWidgetId(string $field_name, array $context, string $expected): void {
    $this->assertSame($expected, $this->createPlugin()->callGetActionButtonWidgetId($field_name, $context));
  }

  /**
   * Data provider for testGetActionButtonWidgetId().
   */
  public static function providerGetActionButtonWidgetId(): array {
    return [
      'derived from field and plugin id' => [
        'field_body',
        [],
        'field_body_field_widget_action_test_action',
      ],
      'explicit action id wins' => [
        'field_body',
        ['action_id' => 'my-uuid'],
        'my-uuid',
      ],
      'delta is appended' => [
        'field_body',
        ['delta' => 2],
        'field_body_field_widget_action_test_action_2',
      ],
      'action id with delta' => [
        'field_body',
        ['action_id' => 'my-uuid', 'delta' => 3],
        'my-uuid_3',
      ],
    ];
  }

  /**
   * Builds a plugin overriding FORM_ELEMENT_PROPERTY to 'alt'.
   *
   * Mirrors an action targeting the alternative text of an image widget. The
   * image widget element is itself a managed_file input, and 'alt' is a text
   * field child of it.
   *
   * @return \Drupal\field_widget_actions\FieldWidgetActionBase
   *   A test double whose fill target property is 'alt'.
   */
  protected function createAltPlugin(): FieldWidgetActionBase {
    return new class([], 'test_alt_action', ['label' => 'Test Alt Action', 'id' => 'test_alt_action'], $this->createMock(MessengerInterface::class)) extends FieldWidgetActionBase {

      /**
       * The alternative text property of an image item.
       */
      const FORM_ELEMENT_PROPERTY = 'alt';

      /**
       * {@inheritdoc}
       */
      public function getAjaxCallback(): ?string {
        return NULL;
      }

      /**
       * Exposes getTargetElement() for testing.
       */
      public function callGetTargetElement(array &$form, FormStateInterface $form_state): array {
        return $this->getTargetElement($form, $form_state);
      }

    };
  }

  /**
   * Builds a form state with the given triggering element shape.
   *
   * @param array $array_parents
   *   The #array_parents of the triggering element.
   * @param int|null $delta
   *   The #field_widget_action_field_delta of the triggering element.
   *
   * @return \Drupal\Core\Form\FormStateInterface
   *   The form state.
   */
  protected function createFormState(array $array_parents, $delta): FormStateInterface {
    $form_state = new FormState();
    $form_state->setTriggeringElement([
      '#array_parents' => $array_parents,
      '#field_widget_action_field_name' => 'field_test',
      '#field_widget_action_field_delta' => $delta,
    ]);
    return $form_state;
  }

  /**
   * Builds a plugin whose field storage reports the given main property.
   *
   * @param string $main_property
   *   The main property name to report.
   *
   * @return \Drupal\field_widget_actions\FieldWidgetActionBase
   *   The plugin with a mocked field definition attached.
   */
  protected function pluginWithMainProperty(string $main_property): FieldWidgetActionBase {
    $field_storage = $this->createMock(FieldStorageDefinitionInterface::class);
    $field_storage->method('getMainPropertyName')->willReturn($main_property);
    $field_definition = $this->createMock(FieldDefinitionInterface::class);
    $field_definition->method('getFieldStorageDefinition')->willReturn($field_storage);

    $plugin = $this->createPlugin();
    $plugin->setFieldDefinition($field_definition);
    return $plugin;
  }

  /**
   * A select widget placed directly under [field]['widget'] is returned as-is.
   *
   * This is the whole-field (multiple: 0) options_select shape: the module
   * wraps the select in the complete-form container, and the select is the
   * 'widget' child of that container. There is no 'value' child.
   *
   * @covers ::getTargetElement
   */
  public function testSelectTargetElementAtWidget(): void {
    $form = [
      'field_test' => [
        '#type' => 'container',
        'widget' => [
          '#type' => 'select',
          '#key_column' => 'value',
          '#name' => 'field_test',
        ],
        'btn' => ['#field_widget_action_field_name' => 'field_test'],
      ],
    ];

    $target = $this->createPlugin()->callGetTargetElement(
      $form,
      $this->createFormState(['field_test', 'btn'], NULL),
    );

    $this->assertSame('select', $target['#type']);
  }

  /**
   * A select widget wrapped in an extra container is still found.
   *
   * Per-item (multiple: 1) options_select actions are wrapped by the module's
   * single-element alter, so the select sits two 'widget' levels deep.
   *
   * @covers ::getTargetElement
   */
  public function testWrappedSelectTargetElement(): void {
    $form = [
      'field_test' => [
        '#type' => 'container',
        'widget' => [
          '#type' => 'container',
          'widget' => [
            '#type' => 'select',
            '#key_column' => 'value',
            '#input' => TRUE,
            '#name' => 'field_test[widget]',
          ],
        ],
        'btn' => ['#field_widget_action_field_name' => 'field_test'],
      ],
    ];

    $target = $this->createPlugin()->callGetTargetElement(
      $form,
      $this->createFormState(['field_test', 'btn'], 0),
    );

    $this->assertSame('select', $target['#type']);
  }

  /**
   * A standard text widget resolves to its 'value' child.
   *
   * Per-item (multiple: 1) text widgets are keyed by numeric delta, and the
   * input is the 'value' property child of the delta element.
   *
   * @covers ::getTargetElement
   */
  public function testTextValueChildTargetElement(): void {
    $form = [
      'field_test' => [
        'widget' => [
          0 => [
            'value' => [
              '#type' => 'textfield',
              '#input' => TRUE,
              '#name' => 'field_test[0][value]',
            ],
            'btn' => ['#field_widget_action_field_name' => 'field_test'],
          ],
        ],
      ],
    ];

    $target = $this->createPlugin()->callGetTargetElement(
      $form,
      $this->createFormState(['field_test', 'widget', 0, 'btn'], 0),
    );

    $this->assertSame('textfield', $target['#type']);
    $this->assertSame('field_test[0][value]', $target['#name']);
  }

  /**
   * A whole-field text action navigates the numeric delta wrapper.
   *
   * For a whole-field (multiple: 0) text action the button sits next to the
   * multi-value wrapper, so the lookup must descend into the first delta before
   * resolving the 'value' child.
   *
   * @covers ::getTargetElement
   */
  public function testWholeFieldTextTargetElement(): void {
    $form = [
      'field_test' => [
        '#type' => 'container',
        'widget' => [
          0 => [
            'value' => [
              '#type' => 'textfield',
              '#input' => TRUE,
              '#name' => 'field_test[0][value]',
            ],
          ],
        ],
        'btn' => ['#field_widget_action_field_name' => 'field_test'],
      ],
    ];

    $target = $this->createPlugin()->callGetTargetElement(
      $form,
      $this->createFormState(['field_test', 'btn'], NULL),
    );

    $this->assertSame('textfield', $target['#type']);
    $this->assertSame('field_test[0][value]', $target['#name']);
  }

  /**
   * An entity reference resolves to its 'target_id' main property child.
   *
   * The field storage's main property name drives the lookup, so autocomplete
   * widgets (whose input is the 'target_id' child) are found without relying on
   * a hardcoded 'value'.
   *
   * @covers ::getTargetElement
   */
  public function testEntityReferenceTargetIdTargetElement(): void {
    $form = [
      'field_test' => [
        'widget' => [
          0 => [
            'target_id' => [
              '#type' => 'entity_autocomplete',
              '#input' => TRUE,
              '#name' => 'field_test[0][target_id]',
            ],
            'btn' => ['#field_widget_action_field_name' => 'field_test'],
          ],
        ],
      ],
    ];

    $target = $this->pluginWithMainProperty('target_id')->callGetTargetElement(
      $form,
      $this->createFormState(['field_test', 'widget', 0, 'btn'], 0),
    );

    $this->assertSame('entity_autocomplete', $target['#type']);
    $this->assertSame('field_test[0][target_id]', $target['#name']);
  }

  /**
   * A link field resolves to its 'uri' main property child.
   *
   * @covers ::getTargetElement
   */
  public function testLinkUriTargetElement(): void {
    $form = [
      'field_test' => [
        'widget' => [
          0 => [
            'uri' => [
              '#type' => 'textfield',
              '#input' => TRUE,
              '#name' => 'field_test[0][uri]',
            ],
            'title' => [
              '#type' => 'textfield',
              '#input' => TRUE,
              '#name' => 'field_test[0][title]',
            ],
            'btn' => ['#field_widget_action_field_name' => 'field_test'],
          ],
        ],
      ],
    ];

    $target = $this->pluginWithMainProperty('uri')->callGetTargetElement(
      $form,
      $this->createFormState(['field_test', 'widget', 0, 'btn'], 0),
    );

    $this->assertSame('textfield', $target['#type']);
    $this->assertSame('field_test[0][uri]', $target['#name']);
  }

  /**
   * A subclass override of FORM_ELEMENT_PROPERTY wins over the main property.
   *
   * A text_with_summary field stores its formatted text under the 'value' main
   * property (rendered as a CKEditor), but the summary is a plain textarea. An
   * action overriding FORM_ELEMENT_PROPERTY = 'summary' must resolve to the
   * 'summary' child, not the formatted 'value' child.
   *
   * @covers ::getTargetElement
   */
  public function testSummaryOverrideTargetElement(): void {
    $form = [
      'field_test' => [
        'widget' => [
          0 => [
            'value' => [
              '#type' => 'textarea',
              '#base_type' => 'textarea',
              '#name' => 'field_test[0][value]',
            ],
            'summary' => [
              '#type' => 'textarea',
              '#name' => 'field_test[0][summary]',
            ],
            'btn' => ['#field_widget_action_field_name' => 'field_test'],
          ],
        ],
      ],
    ];

    $target = $this->createSummaryPlugin()->callGetTargetElement(
      $form,
      $this->createFormState(['field_test', 'widget', 0, 'btn'], 0),
    );

    $this->assertSame('textarea', $target['#type']);
    $this->assertSame('field_test[0][summary]', $target['#name']);
  }

  /**
   * The summary override also wins for a whole-field action.
   *
   * For a whole-field (multiple: 0) action the button sits next to the
   * multi-value wrapper, so the lookup descends into the first delta before
   * resolving the target. The 'summary' child must still be returned.
   *
   * @covers ::getTargetElement
   */
  public function testWholeFieldSummaryOverrideTargetElement(): void {
    $form = [
      'field_test' => [
        '#type' => 'container',
        'widget' => [
          0 => [
            'value' => [
              '#type' => 'textarea',
              '#base_type' => 'textarea',
              '#name' => 'field_test[0][value]',
            ],
            'summary' => [
              '#type' => 'textarea',
              '#name' => 'field_test[0][summary]',
            ],
          ],
        ],
        'btn' => ['#field_widget_action_field_name' => 'field_test'],
      ],
    ];

    $target = $this->createSummaryPlugin()->callGetTargetElement(
      $form,
      $this->createFormState(['field_test', 'btn'], NULL),
    );

    $this->assertSame('textarea', $target['#type']);
    $this->assertSame('field_test[0][summary]', $target['#name']);
  }

  /**
   * Builds the image widget shape: a managed_file input with an 'alt' child.
   *
   * @param string $name
   *   The #name of the managed_file element.
   *
   * @return array
   *   The delta element of an image widget.
   */
  protected function imageDeltaElement(string $name): array {
    return [
      '#type' => 'managed_file',
      '#input' => TRUE,
      '#name' => $name,
      'alt' => [
        '#type' => 'textfield',
        '#input' => TRUE,
        '#name' => $name . '[alt]',
      ],
      'title' => [
        '#type' => 'textfield',
        '#input' => TRUE,
        '#name' => $name . '[title]',
      ],
    ];
  }

  /**
   * An explicit property override wins even when the parent is an input.
   *
   * The image widget element is itself a managed_file input, so the input
   * short-circuit must not return it when the action targets its 'alt' child.
   *
   * @covers ::getTargetElement
   */
  public function testAltOverrideWinsOverInputParent(): void {
    $form = [
      'field_test' => [
        'widget' => [
          0 => $this->imageDeltaElement('field_test[0]') + [
            'btn' => ['#field_widget_action_field_name' => 'field_test'],
          ],
        ],
      ],
    ];

    $target = $this->createAltPlugin()->callGetTargetElement(
      $form,
      $this->createFormState(['field_test', 'widget', 0, 'btn'], 0),
    );

    $this->assertSame('textfield', $target['#type']);
    $this->assertSame('field_test[0][alt]', $target['#name']);
  }

  /**
   * The alt override also wins for a whole-field action.
   *
   * @covers ::getTargetElement
   */
  public function testWholeFieldAltOverrideWinsOverInputParent(): void {
    $form = [
      'field_test' => [
        '#type' => 'container',
        'widget' => [
          0 => $this->imageDeltaElement('field_test[0]'),
        ],
        'btn' => ['#field_widget_action_field_name' => 'field_test'],
      ],
    ];

    $target = $this->createAltPlugin()->callGetTargetElement(
      $form,
      $this->createFormState(['field_test', 'btn'], NULL),
    );

    $this->assertSame('textfield', $target['#type']);
    $this->assertSame('field_test[0][alt]', $target['#name']);
  }

  /**
   * Without an override an input parent is returned as-is.
   *
   * The default 'value' property does not exist on a managed_file element, so
   * the element itself is the target, exactly as for a select.
   *
   * @covers ::getTargetElement
   */
  public function testInputParentWithoutOverride(): void {
    $form = [
      'field_test' => [
        'widget' => [
          0 => $this->imageDeltaElement('field_test[0]') + [
            'btn' => ['#field_widget_action_field_name' => 'field_test'],
          ],
        ],
      ],
    ];

    $target = $this->pluginWithMainProperty('target_id')->callGetTargetElement(
      $form,
      $this->createFormState(['field_test', 'widget', 0, 'btn'], 0),
    );

    $this->assertSame('managed_file', $target['#type']);
    $this->assertSame('field_test[0]', $target['#name']);
  }

}
