<?php

namespace Drupal\Tests\ai_ckeditor\Kernel;

use Drupal\ai\Entity\AiPrompt;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests enabling ai_ckeditor and its dependencies.
 *
 * @group ai_ckeditor
 */
class InstallAiCKEditorTest extends KernelTestBase {

  /**
   * Modules to enable before running the tests.
   *
   * @var array
   */
  protected static $modules = ['system', 'user', 'filter', 'editor', 'ckeditor5', 'ai'];

  /**
   * Tests if the module installs successfully.
   */
  public function testModuleCanBeEnabled() {
    try {
      // Try to enable the module.
      \Drupal::service('module_installer')->install(['ai_ckeditor']);
      $this->assertTrue(\Drupal::service('module_handler')->moduleExists('ai_ckeditor'), 'The module is successfully installed.');
    }
    catch (\Exception $e) {
      $this->fail('The module could not be enabled: ' . $e->getMessage());
    }
  }

  /**
   * Tests that uninstalling the module cleans up stale editor config.
   *
   * @see ai_ckeditor_module_preuninstall()
   */
  public function testUninstallRemovesStaleToolbarItems(): void {
    // Uninstalling a module clears its entries from the user.data service,
    // which requires the users_data table to exist.
    $this->installSchema('user', ['users_data']);
    \Drupal::service('module_installer')->install(['ai_ckeditor']);

    $entity_type_manager = \Drupal::entityTypeManager();
    $filter_format_storage = $entity_type_manager->getStorage('filter_format');
    $editor_storage = $entity_type_manager->getStorage('editor');

    // The editor entities validate that their format still exists, so the
    // text formats they are assigned to must be created first.
    $filter_format_storage->create([
      'format' => 'test_format',
      'name' => 'Test format',
    ])->save();
    $filter_format_storage->create([
      'format' => 'other_format',
      'name' => 'Other format',
    ])->save();

    // An editor that uses the AI toolbar items and plugin settings provided
    // by ai_ckeditor.
    $editor_storage->create([
      'format' => 'test_format',
      'editor' => 'ckeditor5',
      'settings' => [
        'toolbar' => [
          'items' => ['bold', 'italic', 'aickeditor', 'ai_balloon_menu'],
        ],
        'plugins' => [
          'ai_ckeditor_ai' => [
            'plugins' => [
              'ai_ckeditor_help' => ['enabled' => TRUE],
            ],
          ],
        ],
      ],
    ])->save();

    // An unrelated editor that does not reference ai_ckeditor at all, used
    // to confirm the uninstall hook leaves untouched configs alone.
    $editor_storage->create([
      'format' => 'other_format',
      'editor' => 'ckeditor5',
      'settings' => [
        'toolbar' => [
          'items' => ['bold', 'italic'],
        ],
      ],
    ])->save();

    \Drupal::service('module_installer')->uninstall(['ai_ckeditor']);

    // loadUnchanged() bypasses the static entity cache so the entities
    // ai_ckeditor_module_preuninstall() modified out-of-band are re-read.
    $updated = $editor_storage->loadUnchanged('test_format');
    $this->assertSame(
      ['bold', 'italic'],
      $updated->getSettings()['toolbar']['items'] ?? NULL,
      'Stale AI CKEditor toolbar items are removed on uninstall.'
    );
    $this->assertNull(
      $updated->getSettings()['plugins']['ai_ckeditor_ai'] ?? NULL,
      'Stale AI CKEditor plugin settings are removed on uninstall.'
    );

    $untouched = $editor_storage->loadUnchanged('other_format');
    $this->assertSame(
      ['bold', 'italic'],
      $untouched->getSettings()['toolbar']['items'] ?? NULL,
      'Editor configs without AI CKEditor items are left unchanged on uninstall.'
    );
  }

  /**
   * Tests that uninstalling removes the module's prompts so it can reinstall.
   *
   * @see ai_ckeditor_module_preuninstall()
   */
  public function testUninstallRemovesPromptsAndAllowsReinstall(): void {
    $this->installSchema('user', ['users_data']);
    $module_installer = \Drupal::service('module_installer');
    $module_installer->install(['ai_ckeditor']);
    // Kernel tests do not import default configuration on module install.
    $this->installConfig(['ai_ckeditor']);

    $entity_type_manager = \Drupal::entityTypeManager();
    $prompt_storage = $entity_type_manager->getStorage('ai_prompt');
    $this->assertNotNull($entity_type_manager->getStorage('ai_prompt_type')->load('ai_ckeditor_tone'));
    $this->assertNotNull($prompt_storage->load('ai_ckeditor_tone__default'));

    // A prompt without the enforced module dependency, as created by older
    // versions of the module, is not removed by the config dependency system.
    $legacy_prompt = AiPrompt::create([
      'id' => 'legacy',
      'type' => 'ai_ckeditor_tone',
      'label' => 'Legacy tone prompt',
      'prompt' => 'Change the tone of {inputText} to {tone}.',
    ]);
    $legacy_prompt->save();
    $this->assertSame('ai_ckeditor_tone__legacy', $legacy_prompt->id());
    $this->assertEmpty($legacy_prompt->getDependencies());

    $module_installer->uninstall(['ai_ckeditor']);

    // The container is rebuilt on uninstall, so use fresh services.
    $entity_type_manager = \Drupal::entityTypeManager();
    $prompt_storage = $entity_type_manager->getStorage('ai_prompt');
    $this->assertNull($prompt_storage->load('ai_ckeditor_tone__default'));
    $this->assertNull($prompt_storage->load('ai_ckeditor_tone__legacy'));
    $this->assertNull($entity_type_manager->getStorage('ai_prompt_type')->load('ai_ckeditor_tone'));

    // Reinstalling must not fail with a PreExistingConfigException.
    $module_installer->install(['ai_ckeditor']);
    $this->installConfig(['ai_ckeditor']);
    $this->assertTrue(\Drupal::service('module_handler')->moduleExists('ai_ckeditor'));
    $prompt_storage = \Drupal::entityTypeManager()->getStorage('ai_prompt');
    $this->assertNotNull($prompt_storage->load('ai_ckeditor_tone__default'));
  }

}
