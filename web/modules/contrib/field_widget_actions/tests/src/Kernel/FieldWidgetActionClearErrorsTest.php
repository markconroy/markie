<?php

declare(strict_types=1);

namespace Drupal\Tests\field_widget_actions\Kernel;

use PHPUnit\Framework\Attributes\Group;
use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that the action #validate handler only suppresses required errors.
 *
 * FieldWidgetActionBase::clearErrorsForAction() runs when an action (Generate)
 * button triggers the form. It must drop "field is required" violations for
 * fields the user has not filled in, but preserve every other validation error
 * so genuinely invalid data is neither hidden from the user nor passed on to
 * the submit handlers.
 *
 * @group field_widget_actions
 *
 * @covers \Drupal\field_widget_actions\FieldWidgetActionBase::clearErrorsForAction
 */
#[RunTestsInSeparateProcesses]
#[Group('field_widget_actions')]
class FieldWidgetActionClearErrorsTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field_widget_actions',
    'field_widget_actions_test',
  ];

  /**
   * Required-field errors are dropped while genuine errors are preserved.
   */
  public function testClearErrorsForActionPreservesGenuineErrors(): void {
    // A form with two flagged fields: one required-but-empty (the kind core
    // sets when a required field is left blank) and one carrying an ordinary
    // validation error (e.g. an out-of-range value).
    $form = [
      'required_field' => [
        '#parents' => ['required_field'],
        '#required_but_empty' => TRUE,
      ],
      'invalid_field' => [
        '#parents' => ['invalid_field'],
      ],
    ];

    $form_state = new FormState();
    $form_state->setErrorByName('required_field', 'Required field field is required.');
    $form_state->setErrorByName('invalid_field', 'Invalid field must be lower than or equal to 10.');

    // Sanity check: both errors are present before the handler runs.
    $this->assertCount(2, $form_state->getErrors());

    /** @var \Drupal\field_widget_actions\FieldWidgetActionBase $plugin */
    $plugin = $this->container
      ->get('plugin.manager.field_widget_actions')
      ->createInstance('suggest_texts_for_textfield', []);
    $plugin->clearErrorsForAction($form, $form_state);

    // Only the genuine (non-required) error survives.
    $this->assertSame(
      ['invalid_field' => 'Invalid field must be lower than or equal to 10.'],
      $form_state->getErrors(),
    );
  }

  /**
   * Nested required-but-empty errors (field widget deltas) are dropped too.
   */
  public function testClearErrorsForActionHandlesNestedParents(): void {
    // Field widgets nest their value under [field_name, delta, value]; the
    // required flag lives on the deepest element.
    $form = [
      'field_test' => [
        '#parents' => ['field_test'],
        'widget' => [
          '#parents' => ['field_test'],
          0 => [
            '#parents' => ['field_test', 0],
            'value' => [
              '#parents' => ['field_test', 0, 'value'],
              '#required_but_empty' => TRUE,
            ],
          ],
        ],
      ],
    ];

    $form_state = new FormState();
    $form_state->setErrorByName('field_test][0][value', 'Field test field is required.');

    /** @var \Drupal\field_widget_actions\FieldWidgetActionBase $plugin */
    $plugin = $this->container
      ->get('plugin.manager.field_widget_actions')
      ->createInstance('suggest_texts_for_textfield', []);
    $plugin->clearErrorsForAction($form, $form_state);

    $this->assertSame([], $form_state->getErrors());
  }

  /**
   * Unselected required options widgets ('_none') are suppressed.
   *
   * Options widgets (single select, radios) run their own required check and
   * set the error without core's #required_but_empty flag; an unselected value
   * is the '_none' marker. The handler must still recognize these as unfilled
   * required fields, while a select that DOES hold a value keeps its error.
   */
  public function testClearErrorsForActionHandlesOptionsWidgets(): void {
    $form = [
      // Unselected required select — should be suppressed.
      'field_choice' => [
        '#parents' => ['field_choice'],
        '#required' => TRUE,
        '#value' => '_none',
      ],
      // A required select that holds a value but failed a real constraint —
      // should be preserved.
      'field_other' => [
        '#parents' => ['field_other'],
        '#required' => TRUE,
        '#value' => 'invalid_choice',
      ],
    ];

    $form_state = new FormState();
    $form_state->setErrorByName('field_choice', 'Choice field is required.');
    $form_state->setErrorByName('field_other', 'The value you selected is not a valid choice.');

    /** @var \Drupal\field_widget_actions\FieldWidgetActionBase $plugin */
    $plugin = $this->container
      ->get('plugin.manager.field_widget_actions')
      ->createInstance('suggest_texts_for_textfield', []);
    $plugin->clearErrorsForAction($form, $form_state);

    $this->assertSame(
      ['field_other' => 'The value you selected is not a valid choice.'],
      $form_state->getErrors(),
    );
  }

}
