<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_automators\FunctionalJavascript\Plugin\FieldWidgetAction;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\ai\FunctionalJavascriptTests\BaseClassFunctionalJavascriptTests;
use Symfony\Component\Yaml\Yaml;

/**
 * Tests the field widget action for the Tagify autocomplete widget.
 *
 * Tagify is an optional integration, so it is installed at runtime and the
 * test skips where the module is not in the codebase.
 *
 * @group ai_automators
 * @group 3536912
 */
class TagifyTaxonomyTest extends BaseClassFunctionalJavascriptTests {

  /**
   * {@inheritdoc}
   */
  protected bool $videoRecording = TRUE;

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
  protected string $screenshotModuleName = 'ai_automators';

  /**
   * The name of the tagify widget under test.
   */
  protected string $fixtureDirectory = 'tagify_taxonomy_test';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Tagify cannot go in $modules — that would fatal before we get a chance
    // to skip on codebases where it is not installed.
    if (!isset(\Drupal::service('extension.list.module')->getList()['tagify'])) {
      $this->markTestSkipped('The tagify module is not present in this codebase.');
    }
    \Drupal::service('module_installer')->install(['tagify']);
    $this->rebuildContainer();

    // Create a vocabulary for testing.
    $vocabulary = Vocabulary::create([
      'name' => 'Tags',
      'vid' => 'tags',
      'description' => 'Vocabulary for testing tags.',
    ]);
    $vocabulary->save();

    // Create a content type for testing.
    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);

    // Create a field for the vocabulary.
    FieldStorageConfig::create([
      'field_name' => 'field_tags',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'settings' => [
        'target_type' => 'taxonomy_term',
      ],
      'cardinality' => -1,
    ])->save();

    // Field instance (on the article bundle).
    FieldConfig::create([
      'field_name' => 'field_tags',
      'entity_type' => 'node',
      'bundle' => 'article',
      'label' => 'Tags',
      'settings' => [
        'handler' => 'default',
        'handler_settings' => [
          'auto_create' => TRUE,
          'target_bundles' => [
            'tags' => 'tags',
          ],
        ],
      ],
    ])->save();

    $this->installFixtures();
  }

  /**
   * Imports the automator and the form display carrying the widget action.
   */
  protected function installFixtures(): void {
    $config_path = __DIR__ . '/../../../../config/' . $this->fixtureDirectory . '/';

    $data = Yaml::parseFile($config_path . 'ai_automators.ai_automator.node.article.field_tags.default.yml');
    \Drupal::entityTypeManager()
      ->getStorage('ai_automator')
      ->create($data)
      ->save();

    $data = Yaml::parseFile($config_path . 'core.entity_form_display.node.article.default.yml');
    $storage = \Drupal::entityTypeManager()->getStorage('entity_form_display');
    // Remove the display first, as we will create it from YAML.
    if ($display = $storage->load('node.article.default')) {
      $display->delete();
    }
    $storage->create($data)->save();
  }

  /**
   * Returns a user allowed to author articles and manage tags.
   */
  protected function createArticleAuthor() {
    return $this->drupalCreateUser([
      'administer site configuration',
      'administer nodes',
      'administer taxonomy',
      'access content',
      'create article content',
      'edit any article content',
    ]);
  }

  /**
   * Tests suggesting tags into the Tagify widget.
   */
  public function testCreateTagsFieldWidgetAction(): void {
    $this->drupalLogin($this->createArticleAuthor());
    $this->drupalGet('/node/add/article');
    $this->takeScreenshot('1_initial_form');

    $page = $this->getSession()->getPage();
    $page->fillField('title[0][value]', 'Test Article with Tags');
    $page->fillField('body[0][value]', 'Monkeys are cool animals');
    $this->takeScreenshot('2_filled_form');

    // The action button only exists if the plugin was offered for the Tagify
    // widget at all — the regression this issue is about.
    $this->click('.field-widget-action-automator_tagify_taxonomy');
    $this->takeScreenshot('3_after_add_tags_button');

    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->takeScreenshot('4_after_ajax_call');

    // Assert on the widget's own input rather than the rendered chips: the
    // Tagify library is loaded from a CDN, so the chips are not guaranteed
    // to exist, while the input value is what the field actually submits.
    $value = $this->assertSession()->fieldExists('field_tags')->getValue();
    $this->assertNotSame('', $value, 'The Tagify input was filled by the automator.');

    $tags = json_decode($value, TRUE);
    $this->assertIsArray($tags, 'The Tagify input holds a JSON payload.');
    $this->assertCount(1, $tags);
    $this->assertSame('monkeys', $tags[0]['label']);
    $this->assertNotEmpty($tags[0]['entity_id'], 'The suggested tag references a real term.');
  }

}
