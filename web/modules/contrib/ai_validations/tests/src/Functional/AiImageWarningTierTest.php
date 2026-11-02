<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_validations\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\ai_validations_test\Plugin\AiProvider\ClassifierTestProvider;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the warning tier end-to-end on an entity form with an image upload.
 *
 * An ai_image_constraint_rule with a warning message configured switches to
 * the ternary XSAFE/XWARN/XBLOCK protocol: XWARN must accept the node and
 * show a warning, XBLOCK must reject the submission with a form error, and
 * XSAFE must accept silently.
 */
#[Group('ai_validations')]
#[Group('3606131')]
class AiImageWarningTierTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'field',
    'file',
    'image',
    'key',
    'ai',
    'ai_test',
    'field_validation',
    'ai_validations',
    'ai_validations_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The image field the validation rule is attached to.
   */
  protected const FIELD_NAME = 'field_image_test';

  /**
   * The configured non-blocking warning message.
   */
  protected const WARNING_TEXT = 'This image was flagged as moderate.';

  /**
   * The configured blocking violation message.
   */
  protected const BLOCK_TEXT = 'Blocked by AI image validation.';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);

    FieldStorageConfig::create([
      'field_name' => self::FIELD_NAME,
      'entity_type' => 'node',
      'type' => 'image',
    ])->save();
    FieldConfig::create([
      'field_storage' => FieldStorageConfig::loadByName('node', self::FIELD_NAME),
      'bundle' => 'article',
      'label' => 'Test image',
      'settings' => [
        'alt_field' => 0,
        'alt_field_required' => 0,
      ],
    ])->save();
    \Drupal::service('entity_display.repository')
      ->getFormDisplay('node', 'article')
      ->setComponent(self::FIELD_NAME, ['type' => 'image_image'])
      ->save();

    // The rule set name must be "{entity_type}_{bundle}" so field_validation
    // attaches its umbrella constraint to the bundle's field definitions.
    $rule_set = \Drupal::entityTypeManager()
      ->getStorage('field_validation_rule_set')
      ->create([
        'name' => 'node_article',
        'label' => 'Article AI image validation',
        'entity_type' => 'node',
        'bundle' => 'article',
      ]);
    $rule_set->addFieldValidationRule([
      'id' => 'ai_image_constraint_rule',
      'title' => 'AI image warning tier',
      'weight' => 1,
      'field_name' => self::FIELD_NAME,
      'column' => 'target_id',
      'error_message' => '',
      'data' => [
        'validate_mode' => 'default',
        'prompt' => 'Respond with exactly one token: XSAFE, XWARN or XBLOCK.',
        'message' => self::BLOCK_TEXT,
        'warningMessage' => self::WARNING_TEXT,
        'provider' => 'classifier_test__gpt-test',
      ],
    ]);
    $rule_set->save();
    \Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();

    $this->drupalLogin($this->drupalCreateUser([
      'access content',
      'create article content',
    ]));
  }

  /**
   * Sets the literal chat response text the test provider should return.
   */
  protected function setChatResponse(string $text): void {
    \Drupal::state()->set(ClassifierTestProvider::STATE_CHAT_RESPONSE, $text);
  }

  /**
   * Submits a new article with an uploaded test image.
   */
  protected function submitArticleWithImage(string $title): void {
    $this->drupalGet('node/add/article');
    $this->submitForm([
      'title[0][value]' => $title,
      'files[' . self::FIELD_NAME . '_0]' => \Drupal::root() . '/core/tests/fixtures/files/image-test.png',
    ], 'Save');
  }

  /**
   * An XWARN verdict saves the node and shows the warning message.
   */
  public function testWarnVerdictSavesWithWarning(): void {
    $this->setChatResponse('XWARN');
    $this->submitArticleWithImage('Warn article');

    $this->assertSession()->pageTextContains(self::WARNING_TEXT);
    $this->assertSession()->pageTextNotContains(self::BLOCK_TEXT);
    $this->assertNotEmpty($this->getNodeByTitle('Warn article'));
  }

  /**
   * An XBLOCK verdict rejects the submission with a form error.
   */
  public function testBlockVerdictPreventsSave(): void {
    $this->setChatResponse('XBLOCK');
    $this->submitArticleWithImage('Block article');

    $this->assertSession()->pageTextContains(self::BLOCK_TEXT);
    $this->assertSession()->pageTextNotContains(self::WARNING_TEXT);
    $this->assertEmpty($this->getNodeByTitle('Block article'));
  }

  /**
   * An XSAFE verdict saves the node with no messages.
   */
  public function testSafeVerdictSavesSilently(): void {
    $this->setChatResponse('XSAFE');
    $this->submitArticleWithImage('Safe article');

    $this->assertSession()->pageTextNotContains(self::WARNING_TEXT);
    $this->assertSession()->pageTextNotContains(self::BLOCK_TEXT);
    $this->assertNotEmpty($this->getNodeByTitle('Safe article'));
  }

}
