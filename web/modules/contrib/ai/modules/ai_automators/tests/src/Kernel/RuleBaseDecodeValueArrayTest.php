<?php

namespace Drupal\Tests\ai_automators\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ai_automators\PluginBaseClasses\RuleBase;

/**
 * Regression tests for RuleBase::decodeValueArray().
 *
 * @group ai_automators
 */
class RuleBaseDecodeValueArrayTest extends KernelTestBase {

  /**
   * Modules to enable before running the tests.
   *
   * @var array
   */
  protected static $modules = ['system', 'file', 'user', 'ai', 'token', 'ai_automators'];

  /**
   * Ensures fallback array decoding returns all entries.
   */
  public function testDecodeValueArrayReturnsAllFallbackValues() {
    $rule = $this->container
      ->get('plugin.manager.ai_automator')
      ->createInstance('llm_list_string');
    $this->assertInstanceOf(RuleBase::class, $rule);

    $this->assertSame(
      ['alpha', 'beta', 'gamma'],
      $rule->decodeValueArray([
        ['first' => 'alpha'],
        ['second' => 'beta'],
        ['third' => 'gamma'],
      ])
    );
  }

  /**
   * Array-valued "value" key decodes identically to one object per scalar.
   *
   * Models occasionally nest all values inside a single array-valued "value"
   * key. Without flattening, the result is [["A","B"]] — a list-of-lists that
   * verifyValue() cannot match against scalar term names, leaving the field
   * empty even though every term is valid.
   */
  public function testDecodeValueArrayFlattensArrayValue() {
    $rule = $this->container
      ->get('plugin.manager.ai_automator')
      ->createInstance('llm_list_string');

    $nested = $rule->decodeValueArray([
      ['value' => ['alpha', 'beta', 'gamma']],
    ]);
    $flat = $rule->decodeValueArray([
      ['value' => 'alpha'],
      ['value' => 'beta'],
      ['value' => 'gamma'],
    ]);

    $this->assertSame(
      ['alpha', 'beta', 'gamma'],
      $nested,
      'Array-valued "value" key should be flattened to a list of scalars.',
    );
    $this->assertSame($flat, $nested, 'Nested and flat shapes must decode identically.');
  }

  /**
   * A record-shaped "value" must survive decoding intact.
   *
   * Issue #3586716: flattening was applied to every array-valued "value" key,
   * which shredded the one-object-per-value shape several rules ask the model
   * for into its loose property values. Office hours then crashed with a
   * TypeError, and the FAQ, metatag and moderation state rules rejected every
   * generated value, so their field widget action buttons did nothing at all.
   *
   * @dataProvider providerRecordShapes
   */
  public function testDecodeValueArrayKeepsRecordsIntact(array $record): void {
    $rule = $this->container
      ->get('plugin.manager.ai_automator')
      ->createInstance('llm_list_string');

    $this->assertSame(
      [$record],
      $rule->decodeValueArray([['value' => $record]]),
      'A keyed record must be returned as one value, not split into its properties.',
    );
  }

  /**
   * The record shapes the affected rules prompt the model for.
   *
   * @return array
   *   Test cases, each holding one record.
   */
  public static function providerRecordShapes(): array {
    return [
      'office hours' => [
        ['day' => '1', 'starthours' => '0900', 'endhours' => '1700', 'comment' => ''],
      ],
      'faq pair' => [
        ['question' => 'What is Drupal?', 'answer' => 'A content management system.'],
      ],
      'metatag set' => [
        ['abstract' => 'A short abstract.', 'description' => 'A description.'],
      ],
      'moderation state' => [
        ['state' => 'published'],
      ],
    ];
  }

  /**
   * Several records in one response stay one value each.
   */
  public function testDecodeValueArrayKeepsEveryRecordSeparate(): void {
    $rule = $this->container
      ->get('plugin.manager.ai_automator')
      ->createInstance('llm_list_string');

    $rows = [
      ['day' => '1', 'starthours' => '0900', 'endhours' => '1700', 'comment' => ''],
      ['day' => '3', 'starthours' => '1200', 'endhours' => '2000', 'comment' => ''],
    ];

    $this->assertSame(
      $rows,
      $rule->decodeValueArray([
        ['value' => $rows[0]],
        ['value' => $rows[1]],
      ]),
    );
  }

  /**
   * The wrong-key fallback path must preserve records too.
   */
  public function testDecodeValueArrayKeepsRecordsOnWrongKeyPath(): void {
    $rule = $this->container
      ->get('plugin.manager.ai_automator')
      ->createInstance('llm_list_string');

    $record = ['question' => 'What is Drupal?', 'answer' => 'A CMS.'];

    $this->assertSame(
      [$record],
      $rule->decodeValueArray([['faq' => $record]]),
    );
  }

}
