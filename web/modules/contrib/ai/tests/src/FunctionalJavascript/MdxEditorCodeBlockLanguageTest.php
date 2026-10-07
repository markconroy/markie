<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\FunctionalJavascript;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\ai\FunctionalJavascriptTests\BaseClassFunctionalJavascriptTests;

/**
 * Tests that YAML is a selectable MDXEditor code-block language.
 *
 * @group ai
 * @group 3586585
 */
class MdxEditorCodeBlockLanguageTest extends BaseClassFunctionalJavascriptTests {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ai',
    'node',
    'field',
    'field_ui',
    'user',
    'system',
  ];

  /**
   * {@inheritdoc}
   */
  protected bool $videoRecording = TRUE;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->drupalCreateContentType([
      'name' => 'MDX Test',
      'type' => 'mdx_test',
    ]);

    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_textarea',
      'entity_type' => 'node',
      'type' => 'string_long',
    ]);
    $field_storage->save();

    FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'mdx_test',
      'label' => 'Textarea',
    ])->save();

    $form_display = EntityFormDisplay::load('node.mdx_test.default');
    $form_display->setComponent('field_textarea', [
      'type' => 'string_textarea',
      'region' => 'content',
      'settings' => [
        'rows' => 9,
        'placeholder' => '',
      ],
      'third_party_settings' => [
        'ai' => [
          'use_mdx_editor' => TRUE,
        ],
      ],
    ]);
    $form_display->save();

    $user = $this->drupalCreateUser([
      'create mdx_test content',
      'access content',
    ]);
    $this->drupalLogin($user);
  }

  /**
   * Tests that a YAML fenced code block shows "YAML" as its language.
   *
   * Before YAML was added to codeBlockLanguages (Editor.jsx), the block's
   * language selector fell back to its unresolved placeholder instead.
   */
  public function testYamlCodeBlockLanguageIsSelectable(): void {
    $this->drupalGet('node/add/mdx_test');

    $this->assertSession()->waitForElementVisible('css', '.mdxeditor-rich-text-editor [contenteditable="true"]');

    // The underlying textarea is hidden once the rich text editor mounts.
    $this->fillMdxEditorField('field_textarea[0][value]', "```yaml\nkey: value\n```");

    // aria-label is stable; the toolbar's CSS module classes are hashed.
    $language_trigger = $this->assertSession()->waitForElementVisible('css', '.mdxeditor-rich-text-editor [aria-label="Language"]');
    $this->assertNotEmpty($language_trigger);
    $this->takeScreenshot('yaml_code_block_language_trigger');

    $this->assertSession()->waitForText('YAML');
    $this->assertEquals('YAML', trim($language_trigger->getText()));
  }

  /**
   * Tests that YAML and Python code blocks actually mount a CodeMirror editor.
   *
   * Regression test: these languages used to render a code block that
   * silently accepted no keystrokes because CodeMirror's EditorState.create()
   * threw. Their chunks imported shared code back from "./main.js", which
   * Drupal serves with a cache-busting query string, so the entry was
   * evaluated twice and @codemirror/state existed in two copies. The entry
   * is now an empty facade that nothing imports from — see the chunking
   * notes in ui/mdxeditor/vite.config.js. JavaScript/CSS were unaffected
   * because they are inlined into the shared chunk.
   *
   * @dataProvider providerCodeBlockLanguages
   */
  public function testCodeBlockMountsEditor(string $language, string $code): void {
    $this->drupalGet('node/add/mdx_test');

    $this->assertSession()->waitForElementVisible('css', '.mdxeditor-rich-text-editor [contenteditable="true"]');
    $this->fillMdxEditorField('field_textarea[0][value]', "```{$language}\n{$code}\n```");

    // Times out if EditorState.create() threw and .cm-content never mounted.
    $code_content = $this->assertSession()->waitForElementVisible('css', '.cm-content');
    $this->assertNotEmpty($code_content);
    $this->takeScreenshot("{$language}_code_block_mounted");
    $this->assertStringContainsString($code, $code_content->getText());
  }

  /**
   * Data provider for testCodeBlockMountsEditor().
   */
  public static function providerCodeBlockLanguages(): array {
    return [
      'yaml' => ['yaml', 'key: value'],
      'python' => ['python', 'x = 1'],
    ];
  }

}
