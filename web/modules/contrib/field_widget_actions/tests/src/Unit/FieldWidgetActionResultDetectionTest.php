<?php

declare(strict_types=1);

namespace Drupal\Tests\field_widget_actions\Unit;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\field_widget_actions\Ajax\FillCheckboxesOrRadiosCommand;
use Drupal\field_widget_actions\Ajax\FillSelectCommand;
use Drupal\field_widget_actions\Ajax\FillSimpleFieldCommand;
use Drupal\field_widget_actions\FieldWidgetActionBase;
use Drupal\Tests\UnitTestCase;

/**
 * Unit tests for detecting whether an action produced a value.
 *
 * The responseCarriesValue() helper reads nothing but the rendered command
 * arrays, so it needs no container, entity or form display. Keeping it out of
 * a kernel test means these run without a Drupal bootstrap.
 *
 * @group field_widget_actions
 *
 * @coversDefaultClass \Drupal\field_widget_actions\FieldWidgetActionBase
 */
class FieldWidgetActionResultDetectionTest extends UnitTestCase {

  /**
   * Builds a concrete plugin instance exposing the detection helper.
   *
   * @return object
   *   A test double extending the abstract base.
   */
  protected function createPlugin(): object {
    return new class([], 'test_action', ['label' => 'Test Action', 'id' => 'test_action'], $this->createMock(MessengerInterface::class)) extends FieldWidgetActionBase {

      /**
       * {@inheritdoc}
       */
      public function getAjaxCallback(): ?string {
        return NULL;
      }

      /**
       * Exposes responseCarriesValue() for testing.
       */
      public function callResponseCarriesValue(AjaxResponse $response): ?bool {
        return $this->responseCarriesValue($response);
      }

    };
  }

  /**
   * @covers ::responseCarriesValue
   * @dataProvider providerResponseCarriesValue
   */
  public function testResponseCarriesValue(AjaxResponse $response, ?bool $expected): void {
    $this->assertSame($expected, $this->createPlugin()->callResponseCarriesValue($response));
  }

  /**
   * Data provider for testResponseCarriesValue().
   *
   * Two cases record a known asymmetry between the fill commands rather than
   * compensating for it: FillCheckboxesOrRadiosCommand::render() applies
   * array_filter() to its values, so a falsy entry is gone by the time the
   * command is rendered, while FillSelectCommand::render() applies only
   * array_values() and keeps it. Detection reads what the rendered command
   * actually carries, so a change to either command is a visible decision here
   * rather than a silent behavior shift.
   *
   * @see \Drupal\field_widget_actions\Ajax\FillCheckboxesOrRadiosCommand::render()
   * @see \Drupal\field_widget_actions\Ajax\FillSelectCommand::render()
   */
  public static function providerResponseCarriesValue(): array {
    $with = static function (array $commands): AjaxResponse {
      $response = new AjaxResponse();
      foreach ($commands as $command) {
        $response->addCommand($command);
      }
      return $response;
    };

    return [
      'fill command with text' => [
        $with([new FillSimpleFieldCommand('[name="field_test[0][value]"]', 'Some text')]),
        TRUE,
      ],
      // The reported bug: a well-formed response that produced nothing.
      'fill command with empty string' => [
        $with([new FillSimpleFieldCommand('[name="field_test[0][value]"]', '')]),
        FALSE,
      ],
      // '0' is a value the author asked for, not an empty result.
      'fill command with the string zero' => [
        $with([new FillSimpleFieldCommand('[name="field_test[0][value]"]', '0')]),
        TRUE,
      ],
      'checkboxes command with no values' => [
        $with([new FillCheckboxesOrRadiosCommand('field_topics[widget]', [])]),
        FALSE,
      ],
      'checkboxes command with a falsy value filtered upstream' => [
        $with([new FillCheckboxesOrRadiosCommand('field_topics[widget]', [0])]),
        FALSE,
      ],
      'select command keeps a falsy value' => [
        $with([new FillSelectCommand('[name="field_test"]', [0])]),
        TRUE,
      ],
      // No fill command at all: the plugin is doing something this check does
      // not model, such as opening a dialog. Staying undetermined is what lets
      // returnSuggestions() keep its own empty-case dialog without a second
      // message appearing behind it.
      'no fill command' => [
        $with([new MessageCommand('Something happened.')]),
        NULL,
      ],
      'no commands at all' => [$with([]), NULL],
      // One populated command is enough, whatever else rode along.
      'empty and populated fill commands together' => [
        $with([
          new FillSimpleFieldCommand('[name="field_test[0][value]"]', ''),
          new FillSimpleFieldCommand('[name="field_other[0][value]"]', 'Text'),
        ]),
        TRUE,
      ],
    ];
  }

}
