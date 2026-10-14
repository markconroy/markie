<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_automators\FunctionalJavascript\Plugin\FieldWidgetAction;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field_widget_actions\FieldWidgetActionBase;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\ai\FunctionalJavascriptTests\BaseClassFunctionalJavascriptTests;
use Symfony\Component\Yaml\Yaml;

/**
 * Tests the result message an automator action reports to the author.
 *
 * Uses mocked LLM responses: "Monkeys are cool animals" always returns the tag
 * "monkeys", and "Nothing matches here" returns an empty JSON list.
 *
 * @group ai_automators
 * @group 3585987
 */
class AutomatorResultMessageTest extends BaseClassFunctionalJavascriptTests {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ai',
    'ai_test',
    'node',
    'file',
    'ai_automators',
    'field',
    'user',
    'text',
    'field_ui',
    'field_widget_actions',
    'taxonomy',
  ];

  /**
   * {@inheritdoc}
   */
  protected bool $videoRecording = TRUE;

  /**
   * {@inheritdoc}
   */
  protected string $screenshotModuleName = 'ai_automators';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Result messaging ships in Field Widget Actions 1.5.0.
    // @todo Remove once ai requires drupal/field_widget_actions ^1.5.
    if (!method_exists(FieldWidgetActionBase::class, 'reportResultAjax')) {
      $this->markTestSkipped('Requires Field Widget Actions with result messaging (>=1.5.0).');
    }

    Vocabulary::create(['name' => 'Tags', 'vid' => 'tags'])->save();
    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);

    FieldStorageConfig::create([
      'field_name' => 'field_tags',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'taxonomy_term'],
      'cardinality' => -1,
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_tags',
      'entity_type' => 'node',
      'bundle' => 'article',
      'label' => 'Tags',
      'settings' => [
        'handler' => 'default',
        'handler_settings' => [
          'auto_create' => TRUE,
          'target_bundles' => ['tags' => 'tags'],
        ],
      ],
    ])->save();

    $config_path = __DIR__ . '/../../../../config/autocomplete_tags_taxonomy_test/';
    $storage = \Drupal::entityTypeManager()->getStorage('ai_automator');
    $storage->create(Yaml::parseFile($config_path . 'ai_automators.ai_automator.node.article.field_tags.default.yml'))->save();

    $data = Yaml::parseFile($config_path . 'core.entity_form_display.node.article.default.yml');
    $data['content']['field_tags']['third_party_settings']['field_widget_actions']['4e9e20fc-8a39-4498-b70c-26b29751fab9']['show_result_message'] = TRUE;
    $display_storage = \Drupal::entityTypeManager()->getStorage('entity_form_display');
    $display_storage->load('node.article.default')?->delete();
    $display_storage->create($data)->save();

    $this->drupalLogin($this->drupalCreateUser([
      'access content',
      'create article content',
      'edit any article content',
    ]));
  }

  /**
   * Clicks the tags action for a mocked body text and waits for the result.
   *
   * @param string $body
   *   The body text, which selects the mocked response.
   */
  protected function runTagsAction(string $body = 'Monkeys are cool animals'): void {
    $page = $this->getSession()->getPage();
    $page->fillField('title[0][value]', 'Test article');
    $page->fillField('body[0][value]', $body);
    $this->click('.field-widget-action-automator_autocomplete_tags_on_taxonomy');
    $this->assertSession()->assertWaitOnAjaxRequest();
  }

  /**
   * Returns the ID of a tag, as the automator may have created it.
   *
   * @param string $name
   *   The tag name.
   *
   * @return int|string|null
   *   The term ID, or NULL if no such tag exists.
   */
  protected function tagId(string $name): int|string|null {
    $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')
      ->loadByProperties(['vid' => 'tags', 'name' => $name]);
    return array_key_first($terms);
  }

  /**
   * A tag added to an empty field reports success.
   */
  public function testNewValueReportsSuccess(): void {
    $this->drupalGet('/node/add/article');
    $this->runTagsAction();

    $assert = $this->assertSession();
    $assert->fieldValueEquals('field_tags[target_id]', 'monkeys (' . $this->tagId('monkeys') . ')');
    $assert->pageTextContains('A suggestion was added to Tags.');
    $assert->pageTextNotContains('No suggestion was returned for Tags.');
  }

  /**
   * The AI returning the tag already in the field reports nothing new.
   *
   * The field is not empty after the run, but the automator changed nothing,
   * so a success message would be a false positive.
   */
  public function testSameValueWarns(): void {
    $term = Term::create(['vid' => 'tags', 'name' => 'monkeys']);
    $term->save();

    $this->drupalGet('/node/add/article');
    $this->getSession()->getPage()->fillField('field_tags[target_id]', 'monkeys (' . $term->id() . ')');
    $this->runTagsAction();

    $assert = $this->assertSession();
    $assert->fieldValueEquals('field_tags[target_id]', 'monkeys (' . $term->id() . ')');
    $assert->pageTextContains('No suggestion was returned for Tags.');
    $assert->pageTextNotContains('A suggestion was added to Tags.');
  }

  /**
   * A new tag replacing the author's own tag reports success.
   *
   * The field holds a value before and after the run, so the check must see
   * the different tag as a change, the counterpart of testSameValueWarns().
   */
  public function testChangedValueReportsSuccess(): void {
    $term = Term::create(['vid' => 'tags', 'name' => 'cats']);
    $term->save();

    $this->drupalGet('/node/add/article');
    $this->getSession()->getPage()->fillField('field_tags[target_id]', 'cats (' . $term->id() . ')');
    $this->runTagsAction();

    $assert = $this->assertSession();
    // Only the suggested tag is checked: whether the author's tag is kept
    // alongside it is the automator's business, not the message's.
    $this->assertStringContainsString('monkeys (' . $this->tagId('monkeys') . ')', $this->getSession()->getPage()->findField('field_tags[target_id]')->getValue());
    $assert->pageTextContains('A suggestion was added to Tags.');
    $assert->pageTextNotContains('No suggestion was returned for Tags.');
  }

  /**
   * An empty JSON list from the AI reports that nothing was suggested.
   *
   * The AI answers "[]" when no term fits. That is a valid response, not a
   * JSON error, so the author gets the "no suggestion" warning.
   */
  public function testEmptyListWarns(): void {
    $this->drupalGet('/node/add/article');
    $this->runTagsAction('Nothing matches here');

    $assert = $this->assertSession();
    $assert->fieldValueEquals('field_tags[target_id]', '');
    $assert->pageTextContains('No suggestion was returned for Tags.');
    $assert->pageTextNotContains('The response was not a valid JSON response.');
    $assert->pageTextNotContains('A suggestion was added to Tags.');
    $this->takeScreenshot('empty_list_warns');
  }

}
