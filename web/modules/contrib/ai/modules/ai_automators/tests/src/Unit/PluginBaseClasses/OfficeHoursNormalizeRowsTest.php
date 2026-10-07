<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_automators\Unit\PluginBaseClasses;

use Drupal\ai_automators\PluginBaseClasses\OfficeHours;
use Drupal\Tests\UnitTestCase;

/**
 * Tests office hours row normalization.
 *
 * Issue #3586716: clicking "Automator Office Hours" always failed with "The
 * AI automator failed to run." A row that was not a {day, starthours,
 * endhours} record made generate() read a property off a string, which raises
 * a TypeError in PHP 8 and aborted the run before any value was stored.
 *
 * @group ai_automators
 * @coversDefaultClass \Drupal\ai_automators\PluginBaseClasses\OfficeHours
 */
class OfficeHoursNormalizeRowsTest extends UnitTestCase {

  /**
   * A non-record row is dropped rather than crashing the run.
   */
  public function testNonRecordRowsAreDropped(): void {
    $rows = $this->normalize([
      '1',
      '0900',
      ['day' => '2', 'starthours' => '0900', 'endhours' => '1700'],
      NULL,
    ]);

    $this->assertSame(
      [['day' => '2', 'starthours' => '0900', 'endhours' => '1700']],
      $rows,
      'Only record-shaped rows survive, and a malformed row does not abort the run.',
    );
  }

  /**
   * A row missing an hour property no longer raises an error.
   */
  public function testRowMissingHoursIsLeftForVerification(): void {
    $rows = $this->normalize([['day' => '4']]);

    $this->assertSame([['day' => '4']], $rows);
    $this->assertFalse(
      $this->rule()->verifyValue(
        $this->createMock('Drupal\Core\Entity\ContentEntityInterface'),
        $rows[0],
        $this->createMock('Drupal\Core\Field\FieldDefinitionInterface'),
        [],
      ),
      'A row without hours is rejected by verifyValue(), not by a fatal error.',
    );
  }

  /**
   * The gpt-5.2 three-character hour quirk is still repaired.
   */
  public function testThreeCharacterHoursGetTheirTrailingZero(): void {
    $rows = $this->normalize([
      ['day' => '1', 'starthours' => '090', 'endhours' => '170'],
    ]);

    $this->assertSame('0900', $rows[0]['starthours']);
    $this->assertSame('1700', $rows[0]['endhours']);
  }

  /**
   * An absent or falsy day means Sunday.
   */
  public function testMissingDayBecomesSunday(): void {
    $rows = $this->normalize([['starthours' => '0900', 'endhours' => '1700']]);

    $this->assertSame('0', $rows[0]['day']);
  }

  /**
   * Runs normalizeRows() on a rule instance built without its collaborators.
   *
   * @param array $rows
   *   The decoded rows to normalize.
   *
   * @return array
   *   The normalized rows.
   */
  private function normalize(array $rows): array {
    $method = new \ReflectionMethod(OfficeHours::class, 'normalizeRows');
    $method->setAccessible(TRUE);
    return $method->invoke($this->rule(), $rows);
  }

  /**
   * Builds an OfficeHours rule without invoking its constructor.
   *
   * AiProviderPluginManager and AiProviderFormHelper are final and cannot be
   * mocked; the methods under test do not touch them.
   *
   * @return \Drupal\ai_automators\PluginBaseClasses\OfficeHours
   *   The rule instance.
   */
  private function rule(): OfficeHours {
    return (new \ReflectionClass(OfficeHours::class))->newInstanceWithoutConstructor();
  }

}
