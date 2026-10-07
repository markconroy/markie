<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_content_suggestions\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ai_content_suggestions\Plugin\FieldWidgetAction\PromptContentSuggestion;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that prompts for a translation are built from the translated content.
 *
 * @group ai_content_suggestions
 */
#[Group('ai_content_suggestions')]
#[RunTestsInSeparateProcesses]
class PromptContentSuggestionLanguageTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'key',
    'ai',
    'field_widget_actions',
    'ai_content_suggestions',
    'node',
    'field',
    'text',
    'filter',
    'language',
  ];

  /**
   * The English original with a French translation.
   */
  protected NodeInterface $node;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['system', 'filter', 'node', 'language']);
    ConfigurableLanguage::createFromLangcode('fr')->save();

    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();
    // Drupal 10 ships the body field storage with the node module.
    if (!FieldStorageConfig::loadByName('node', 'body')) {
      FieldStorageConfig::create([
        'field_name' => 'body',
        'entity_type' => 'node',
        'type' => 'text_with_summary',
      ])->save();
    }
    FieldConfig::create([
      'field_name' => 'body',
      'entity_type' => 'node',
      'bundle' => 'page',
      'label' => 'Body',
      'translatable' => TRUE,
    ])->save();
    \Drupal::service('entity_display.repository')
      ->getViewDisplay('node', 'page', 'full')
      ->setComponent('body', ['type' => 'text_default'])
      ->save();

    $this->node = Node::create([
      'type' => 'page',
      'langcode' => 'en',
      'title' => 'Bees in the city',
      'body' => ['value' => 'English body text', 'format' => 'plain_text'],
    ]);
    $this->node->addTranslation('fr', [
      'title' => 'Les abeilles en ville',
      'body' => ['value' => 'Texte du corps en français', 'format' => 'plain_text'],
    ]);
    $this->node->save();
  }

  /**
   * Tests the prompt when it uses entity tokens.
   */
  public function testPromptWithEntityTokens(): void {
    $prompt = $this->getPlugin()->buildPrompt('Summarize: [node:body]', $this->node->getTranslation('fr'));

    $this->assertStringContainsString('Texte du corps en français', $prompt);
    $this->assertStringNotContainsString('English body text', $prompt);
  }

  /**
   * Tests the prompt when the rendered entity is attached.
   */
  public function testPromptWithRenderedEntity(): void {
    $prompt = $this->getPlugin()->buildPrompt('Summarize:', $this->node->getTranslation('fr'));

    $this->assertStringContainsString('Texte du corps en français', $prompt);
    $this->assertStringNotContainsString('English body text', $prompt);
  }

  /**
   * Returns the prompt content suggestion field widget action plugin.
   */
  protected function getPlugin(): PromptContentSuggestion {
    return \Drupal::service('plugin.manager.field_widget_actions')
      ->createInstance('prompt_content_suggestion');
  }

}
