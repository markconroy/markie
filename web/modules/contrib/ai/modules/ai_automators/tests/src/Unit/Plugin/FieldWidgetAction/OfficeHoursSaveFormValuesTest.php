<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_automators\Unit\Plugin\FieldWidgetAction;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\ai_automators\Plugin\FieldWidgetAction\OfficeHours;
use Drupal\Tests\UnitTestCase;

/**
 * Tests how generated office hours are written into the rendered widget.
 *
 * Issue #3586716: the prompt asks the model for the open days only, so a
 * re-run returns nothing for a day that has since closed. Nothing cleared the
 * slots that were not returned, so hours from the previous run stayed on
 * screen and the widget showed a mix of two answers.
 *
 * The synthetic form arrays below mirror the office_hours widgets'
 * ['widget']['value'] layout, so this stays independent of the contrib
 * office_hours module.
 *
 * @group ai_automators
 * @coversDefaultClass \Drupal\ai_automators\Plugin\FieldWidgetAction\OfficeHours
 */
class OfficeHoursSaveFormValuesTest extends UnitTestCase {

  /**
   * A day the automator no longer returns is blanked, not left stale.
   */
  public function testDaysNotReturnedAreCleared(): void {
    // On screen: weekends 10:00 to 16:00, weekdays 08:00 to 18:00.
    $form = $this->form([
      0 => ['1000', '1600', 'weekend hours'],
      1 => ['0800', '1800', ''],
      6 => ['1000', '1600', 'weekend hours'],
    ]);

    // "8am to 6pm on weekdays and closed on weekends" returns Monday only.
    $this->save($form, [['day' => 1, 'starthours' => 800, 'endhours' => 1800, 'comment' => '']]);

    $slots = $form['field_office_hours']['widget']['value'];

    $this->assertSame('', $slots[0]['#value']['starthours'], 'Sunday is cleared.');
    $this->assertSame('', $slots[0]['#value']['endhours']);
    $this->assertSame('', $slots[0]['#value']['comment'], 'The stale comment is cleared too.');
    $this->assertSame('', $slots[0]['starthours']['time']['#value'], 'The rendered time input is cleared.');

    $this->assertSame('', $slots[2]['#value']['starthours'], 'Saturday is cleared.');
    $this->assertSame('', $slots[2]['comment']['#value']);

    $this->assertSame('0800', $slots[1]['#value']['starthours'], 'Monday keeps the generated hours.');
    $this->assertSame('1800', $slots[1]['#value']['endhours']);
    $this->assertSame('08:00', $slots[1]['starthours']['time']['#value']);
    $this->assertSame('18:00', $slots[1]['endhours']['time']['#value']);
  }

  /**
   * Hours shorter than four digits are padded before being written.
   */
  public function testHoursArePaddedAndFormatted(): void {
    $form = $this->form([1 => ['', '', '']]);

    $this->save($form, [['day' => 1, 'starthours' => 900, 'endhours' => 1700, 'comment' => 'by appointment']]);

    $slot = $form['field_office_hours']['widget']['value'][0];
    $this->assertSame('0900', $slot['#value']['starthours']);
    $this->assertSame('09:00', $slot['starthours']['time']['#value']);
    $this->assertSame('17:00', $slot['endhours']['time']['#value']);
    $this->assertSame('by appointment', $slot['#value']['comment']);
  }

  /**
   * Day 0 is a real day, not an absent one.
   */
  public function testSundayIsWritten(): void {
    $form = $this->form([0 => ['', '', '']]);

    $this->save($form, [['day' => 0, 'starthours' => 1000, 'endhours' => 1600, 'comment' => '']]);

    $this->assertSame('1000', $form['field_office_hours']['widget']['value'][0]['#value']['starthours']);
  }

  /**
   * A widget rendering a different layout logs instead of raising a TypeError.
   */
  public function testUnexpectedWidgetLayoutIsLogged(): void {
    $channel = $this->createMock(LoggerChannelInterface::class);
    $channel->expects($this->once())
      ->method('warning')
      ->with($this->stringContains('did not render the expected slot structure'), $this->anything());

    $factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $factory->method('get')->with('ai_automators')->willReturn($channel);

    // The office_hours_list widget renders per-delta elements instead.
    $form = ['field_office_hours' => ['widget' => [0 => ['value' => []]]]];

    $plugin = $this->plugin($factory);
    $method = new \ReflectionMethod(OfficeHours::class, 'saveFormValues');
    $method->setAccessible(TRUE);
    $method->invokeArgs($plugin, [&$form, 'field_office_hours', $this->entity([]), NULL]);
  }

  /**
   * Runs saveFormValues() with an entity holding the generated rows.
   *
   * @param array $form
   *   The synthetic form, by reference.
   * @param array $rows
   *   The generated office hours rows.
   */
  private function save(array &$form, array $rows): void {
    $method = new \ReflectionMethod(OfficeHours::class, 'saveFormValues');
    $method->setAccessible(TRUE);
    $method->invokeArgs($this->plugin(), [&$form, 'field_office_hours', $this->entity($rows), NULL]);
  }

  /**
   * Builds a synthetic widget render array, one slot per given day.
   *
   * @param array $days
   *   Day number => [starthours, endhours, comment].
   *
   * @return array
   *   The form array.
   */
  private function form(array $days): array {
    $slots = [];
    foreach ($days as $day => [$start, $end, $comment]) {
      $slots[] = [
        '#value' => [
          'day' => $day,
          'starthours' => $start,
          'endhours' => $end,
          'comment' => $comment,
        ],
        '#default_value' => [
          'day' => $day,
          'starthours' => $start,
          'endhours' => $end,
          'comment' => $comment,
        ],
        'starthours' => ['time' => ['#value' => $start, '#default_value' => $start]],
        'endhours' => ['time' => ['#value' => $end, '#default_value' => $end]],
        'comment' => ['#value' => $comment, '#default_value' => $comment],
      ];
    }
    // '#type' sits alongside the numeric slots, exactly as the widget renders.
    $slots['#type'] = 'office_hours_table';

    return ['field_office_hours' => ['widget' => ['value' => $slots]]];
  }

  /**
   * Builds an entity whose field yields the given office hours rows.
   *
   * @param array $rows
   *   Rows of day / starthours / endhours / comment.
   *
   * @return \Drupal\Tests\ai_automators\Unit\Plugin\FieldWidgetAction\OfficeHoursTestEntity
   *   The stand-in entity.
   */
  private function entity(array $rows): OfficeHoursTestEntity {
    return new OfficeHoursTestEntity($rows);
  }

  /**
   * Builds the plugin without invoking its constructor.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface|null $factory
   *   Optional logger factory, needed only for the bail-out path.
   *
   * @return \Drupal\ai_automators\Plugin\FieldWidgetAction\OfficeHours
   *   The plugin instance.
   */
  private function plugin(?LoggerChannelFactoryInterface $factory = NULL): OfficeHours {
    $plugin = (new \ReflectionClass(OfficeHours::class))->newInstanceWithoutConstructor();
    if ($factory) {
      $property = new \ReflectionProperty(OfficeHours::class, 'loggerFactory');
      $property->setAccessible(TRUE);
      $property->setValue($plugin, $factory);
    }
    return $plugin;
  }

}

/**
 * Minimal stand-in for the entity saveFormValues() reads its rows from.
 *
 * Only $entity->get($field) and get() on each item are used by
 * saveFormValues(), so a plain double keeps this test independent of the
 * contrib office_hours field type.
 */
final class OfficeHoursTestEntity {

  /**
   * The office hours rows.
   *
   * @var array
   */
  private array $rows;

  /**
   * Constructs the stand-in entity.
   *
   * @param array $rows
   *   Rows of day / starthours / endhours / comment.
   */
  public function __construct(array $rows) {
    $this->rows = $rows;
  }

  /**
   * Returns the field item list for any field name.
   *
   * @return \Drupal\Tests\ai_automators\Unit\Plugin\FieldWidgetAction\OfficeHoursTestItemList
   *   The item list.
   */
  public function get(string $field_name): OfficeHoursTestItemList {
    return new OfficeHoursTestItemList($this->rows);
  }

}

/**
 * Iterable stand-in for an office hours field item list.
 */
final class OfficeHoursTestItemList implements \IteratorAggregate {

  /**
   * The office hours rows.
   *
   * @var array
   */
  private array $rows;

  /**
   * Constructs the item list.
   *
   * @param array $rows
   *   Rows of day / starthours / endhours / comment.
   */
  public function __construct(array $rows) {
    $this->rows = $rows;
  }

  /**
   * {@inheritdoc}
   */
  public function getIterator(): \ArrayIterator {
    return new \ArrayIterator(array_map(
      static fn (array $row): OfficeHoursTestItem => new OfficeHoursTestItem($row),
      $this->rows,
    ));
  }

}

/**
 * Stand-in for one office hours field item.
 */
final class OfficeHoursTestItem {

  /**
   * One office hours row.
   *
   * @var array
   */
  private array $row;

  /**
   * Constructs the item.
   *
   * @param array $row
   *   One row of day / starthours / endhours / comment.
   */
  public function __construct(array $row) {
    $this->row = $row;
  }

  /**
   * Returns the named property.
   *
   * @param string $property
   *   The property name.
   *
   * @return \Drupal\Tests\ai_automators\Unit\Plugin\FieldWidgetAction\OfficeHoursTestProperty
   *   The property wrapper.
   */
  public function get(string $property): OfficeHoursTestProperty {
    return new OfficeHoursTestProperty($this->row[$property] ?? NULL);
  }

}

/**
 * Stand-in for a typed data property holding one scalar.
 */
final class OfficeHoursTestProperty {

  /**
   * The property value.
   *
   * @var mixed
   */
  private mixed $value;

  /**
   * Constructs the property wrapper.
   *
   * @param mixed $value
   *   The value.
   */
  public function __construct(mixed $value) {
    $this->value = $value;
  }

  /**
   * Returns the value.
   *
   * @return mixed
   *   The value.
   */
  public function getValue(): mixed {
    return $this->value;
  }

}
