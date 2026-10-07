<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Functional\Plugin\AiGuardrail;

use Drupal\ai\Entity\AiGuardrail;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the RestrictToTopic guardrail admin form.
 *
 * Verifies that the new `matching_mode` select and `similarity_threshold`
 * number fields appear in the guardrail edit form and are saved correctly
 * through the entity form.
 *
 * @group ai
 * @covers \Drupal\ai\Plugin\AiGuardrail\RestrictToTopic
 */
class RestrictToTopicFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ai',
    'ai_test',
    'key',
    'file',
    'system',
    'user',
    'block',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Admin user with guardrail management permissions.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->adminUser = $this->drupalCreateUser([
      'administer guardrails',
      'administer ai',
      'access administration pages',
    ]);
    $this->drupalLogin($this->adminUser);
  }

  /**
   * The guardrail edit form renders the matching_mode and threshold fields.
   */
  public function testFormRendersNewFields(): void {
    $guardrail = AiGuardrail::create([
      'id' => 'test_restrict_to_topic_form',
      'label' => 'Test Restrict to Topic',
      'description' => 'Functional test guardrail.',
      'guardrail' => 'restrict_to_topic',
      'guardrail_settings' => [
        'valid_topics' => 'banana',
        'invalid_topics' => '',
        'invalid_topics_present_message' => 'Blocked.',
        'valid_topics_missing_message' => 'Off-topic.',
        'matching_mode' => 'exact',
        'similarity_threshold' => 0.75,
      ],
    ]);
    $guardrail->save();

    $this->drupalGet('/admin/config/ai/guardrails/test_restrict_to_topic_form');
    $this->assertSession()->statusCodeEquals(200);

    // The matching_mode select element must be present.
    $this->assertSession()->fieldExists('guardrail_settings[matching_mode]');

    // The similarity_threshold number field must be present.
    $this->assertSession()->fieldExists('guardrail_settings[similarity_threshold]');

    // The matching_mode select must have both options.
    $this->assertSession()->optionExists(
      'guardrail_settings[matching_mode]',
      'exact'
    );
    $this->assertSession()->optionExists(
      'guardrail_settings[matching_mode]',
      'semantic'
    );
  }

  /**
   * Default exact mode is pre-selected when rendering the edit form.
   */
  public function testExactModeIsDefaultSelected(): void {
    $guardrail = AiGuardrail::create([
      'id' => 'test_restrict_exact_default',
      'label' => 'Test Restrict to Topic Exact Default',
      'description' => 'Default mode test.',
      'guardrail' => 'restrict_to_topic',
      'guardrail_settings' => [
        'valid_topics' => 'engineering',
        'invalid_topics' => '',
        'invalid_topics_present_message' => 'Blocked.',
        'valid_topics_missing_message' => 'Off-topic.',
        'matching_mode' => 'exact',
        'similarity_threshold' => 0.75,
      ],
    ]);
    $guardrail->save();

    $this->drupalGet('/admin/config/ai/guardrails/test_restrict_exact_default');
    $this->assertSession()->statusCodeEquals(200);

    $select = $this->assertSession()->selectExists('guardrail_settings[matching_mode]');
    $this->assertSame('exact', $select->getValue());
  }

  /**
   * Semantic mode value is correctly pre-populated on the edit form.
   */
  public function testSemanticModePrePopulated(): void {
    $guardrail = AiGuardrail::create([
      'id' => 'test_restrict_semantic',
      'label' => 'Test Restrict to Topic Semantic',
      'description' => 'Semantic mode test.',
      'guardrail' => 'restrict_to_topic',
      'guardrail_settings' => [
        'valid_topics' => 'engineering',
        'invalid_topics' => '',
        'invalid_topics_present_message' => 'Blocked.',
        'valid_topics_missing_message' => 'Off-topic.',
        'matching_mode' => 'semantic',
        'similarity_threshold' => 0.6,
      ],
    ]);
    $guardrail->save();

    $this->drupalGet('/admin/config/ai/guardrails/test_restrict_semantic');
    $this->assertSession()->statusCodeEquals(200);

    $select = $this->assertSession()->selectExists('guardrail_settings[matching_mode]');
    $this->assertSame('semantic', $select->getValue());

    $threshold = $this->assertSession()->fieldExists('guardrail_settings[similarity_threshold]');
    $this->assertSame('0.6', $threshold->getValue());
  }

}
