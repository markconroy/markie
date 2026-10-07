<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\FunctionalJavascript;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\ai\FunctionalJavascriptTests\BaseClassFunctionalJavascriptTests;

/**
 * Tests MDXEditor presence on field widget based on the use_mdx_editor setting.
 *
 * @group ai
 * @group 3586451
 */
class MdxEditorFieldWidgetTest extends BaseClassFunctionalJavascriptTests {

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

    // Create the "MDX Test" content type.
    $this->drupalCreateContentType([
      'name' => 'MDX Test',
      'type' => 'mdx_test',
    ]);

    // Create a Text (plain, long) field named "Textarea".
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

    // Configure the default form display with the MDX editor enabled.
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

    // Create and log in a user who can create MDX Test nodes.
    $user = $this->drupalCreateUser([
      'create mdx_test content',
      'access content',
    ]);
    $this->drupalLogin($user);
  }

  /**
   * Tests MDXEditor appears and disappears based on the use_mdx_editor setting.
   *
   * With use_mdx_editor TRUE the MDX rich-text editor must be present on the
   * node add form. After switching the setting to FALSE the editor must no
   * longer appear.
   */
  public function testMdxEditorToggleWithThirdPartySetting(): void {
    // Visit the node add form with MDX editor enabled.
    $this->drupalGet('node/add/mdx_test');

    // Confirm MDX editor is rendered when use_mdx_editor is TRUE.
    $editor = $this->assertSession()->waitForElementVisible('css', '.mdxeditor-rich-text-editor [contenteditable="true"]');
    $this->assertNotEmpty($editor);

    // Disable the MDX editor in the form display third-party settings.
    \Drupal::entityTypeManager()->getStorage('entity_form_display')->resetCache();
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
          'use_mdx_editor' => FALSE,
        ],
      ],
    ]);
    $form_display->save();

    // Visit the node add form again with MDX editor disabled.
    $this->drupalGet('node/add/mdx_test');

    // Confirm MDX editor is not rendered when use_mdx_editor is FALSE.
    $this->assertSession()->elementNotExists('css', '.mdxeditor-rich-text-editor [contenteditable="true"]');
  }

}
