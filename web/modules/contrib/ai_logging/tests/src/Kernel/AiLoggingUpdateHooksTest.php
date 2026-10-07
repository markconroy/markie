<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_logging\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the ai_logging update hooks.
 *
 * @group ai_logging
 */
#[RunTestsInSeparateProcesses]
class AiLoggingUpdateHooksTest extends KernelTestBase {

  /**
   * Field names installed by ai_logging_update_10307().
   */
  protected const TOKEN_FIELDS = [
    'tokens_input',
    'tokens_output',
    'tokens_total',
    'tokens_reasoning',
    'tokens_cached',
  ];

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'views',
    'ai',
    'ai_logging',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('ai_log');
    $this->installConfig(['ai_logging']);
    $module_path = \Drupal::service('extension.list.module')->getPath('ai_logging');
    require_once $module_path . '/ai_logging.install';
    require_once $module_path . '/ai_logging.post_update.php';
  }

  /**
   * @covers ::ai_logging_update_10307
   */
  public function testUpdate10307InstallsTokenFields(): void {
    $update_manager = \Drupal::entityDefinitionUpdateManager();

    // A fresh install already ships with these fields. Uninstall them to
    // simulate a site that predates this change.
    foreach (self::TOKEN_FIELDS as $field_name) {
      $storage_definition = $update_manager->getFieldStorageDefinition($field_name, 'ai_log');
      $this->assertNotNull($storage_definition, "$field_name should exist on a fresh install.");
      $update_manager->uninstallFieldStorageDefinition($storage_definition);
    }
    foreach (self::TOKEN_FIELDS as $field_name) {
      $this->assertNull($update_manager->getFieldStorageDefinition($field_name, 'ai_log'));
    }

    ai_logging_update_10307();

    foreach (self::TOKEN_FIELDS as $field_name) {
      $storage_definition = $update_manager->getFieldStorageDefinition($field_name, 'ai_log');
      $this->assertNotNull($storage_definition, "$field_name should be installed by the update.");
      $this->assertEquals('integer', $storage_definition->getType());
    }

    // Running it again on a site that already has the fields must not error.
    ai_logging_update_10307();
  }

  /**
   * @covers ::ai_logging_update_10308
   */
  public function testUpdate10308AddsTokensColumnWhenViewUnmodified(): void {
    $view_config = \Drupal::configFactory()->getEditable('views.view.ai_logs');
    $fields = $view_config->get('display.default.display_options.fields');
    $this->assertArrayHasKey('tokens_total', $fields, 'Fresh install ships with the column already.');

    // Simulate the pre-update state by removing the column.
    unset($fields['tokens_total']);
    $view_config->set('display.default.display_options.fields', $fields)->save();

    $result = ai_logging_update_10308();

    $updated_fields = \Drupal::configFactory()
      ->getEditable('views.view.ai_logs')
      ->get('display.default.display_options.fields');
    $this->assertArrayHasKey('tokens_total', $updated_fields);
    $keys = array_keys($updated_fields);
    $model_position = array_search('model', $keys, TRUE);
    $this->assertSame('tokens_total', $keys[$model_position + 1], 'Column is inserted right after "model".');
    $this->assertStringContainsString('Added a Total Tokens column', (string) $result);
  }

  /**
   * @covers ::ai_logging_update_10308
   */
  public function testUpdate10308SkipsCustomizedView(): void {
    $view_config = \Drupal::configFactory()->getEditable('views.view.ai_logs');
    $fields = $view_config->get('display.default.display_options.fields');
    unset($fields['tokens_total']);
    // Simulate a site customization made before this update ships.
    unset($fields['extra_data']);
    $view_config->set('display.default.display_options.fields', $fields)->save();

    $result = ai_logging_update_10308();

    $updated_fields = \Drupal::configFactory()
      ->getEditable('views.view.ai_logs')
      ->get('display.default.display_options.fields');
    $this->assertArrayNotHasKey('tokens_total', $updated_fields, 'A customized view must not be modified.');
    $this->assertStringContainsString('customized', (string) $result);
  }

  /**
   * @covers ::ai_logging_update_10308
   */
  public function testUpdate10308SkipsIfColumnAlreadyPresent(): void {
    // A fresh install already ships with the column.
    $result = ai_logging_update_10308();
    $this->assertStringContainsString('already has a Total Tokens column', (string) $result);
  }

  /**
   * @covers ::ai_logging_update_10309
   */
  public function testUpdate10309ChangesViewSortToId(): void {
    $view_config = \Drupal::configFactory()->getEditable('views.view.ai_logs');
    $view_config->set('display.default.display_options.sorts', [
      'created' => [
        'id' => 'created',
        'table' => 'ai_log',
        'field' => 'created',
        'relationship' => 'none',
        'group_type' => 'group',
        'admin_label' => '',
        'entity_type' => 'ai_log',
        'entity_field' => 'created',
        'plugin_id' => 'date',
        'order' => 'DESC',
        'expose' => [
          'label' => '',
          'field_identifier' => '',
        ],
        'exposed' => FALSE,
        'granularity' => 'second',
      ],
    ])->save();

    ai_logging_update_10309();

    $sorts = \Drupal::configFactory()
      ->getEditable('views.view.ai_logs')
      ->get('display.default.display_options.sorts');
    $this->assertArrayNotHasKey('created', $sorts);
    $this->assertArrayHasKey('id', $sorts);
    $this->assertSame('id', $sorts['id']['field']);
    $this->assertSame('standard', $sorts['id']['plugin_id']);
    $this->assertSame('DESC', $sorts['id']['order']);
  }

  /**
   * @covers ::ai_logging_post_update_add_excluded_tags_setting
   */
  public function testPostUpdateAddsExcludedTagsSettingWhenMissing(): void {
    // A fresh install ships with the key. Remove it to simulate a site that
    // predates this setting.
    $this->config('ai_logging.settings')->clear('prompt_logging_excluded_tags')->save();
    $this->assertNull($this->config('ai_logging.settings')->get('prompt_logging_excluded_tags'));

    $sandbox = [];
    $result = ai_logging_post_update_add_excluded_tags_setting($sandbox);

    $this->assertSame('', $this->config('ai_logging.settings')->get('prompt_logging_excluded_tags'));
    $this->assertStringContainsString('Added', (string) $result);
  }

  /**
   * @covers ::ai_logging_post_update_add_excluded_tags_setting
   */
  public function testPostUpdateKeepsExistingExcludedTagsSetting(): void {
    // Sites upgraded from the AI Logging submodule bundled with AI core
    // 1.3.x/1.4.x already carry a value that must survive.
    $this->config('ai_logging.settings')->set('prompt_logging_excluded_tags', 'ai_api_explorer')->save();

    $sandbox = [];
    $result = ai_logging_post_update_add_excluded_tags_setting($sandbox);

    $this->assertSame('ai_api_explorer', $this->config('ai_logging.settings')->get('prompt_logging_excluded_tags'));
    $this->assertStringContainsString('already present', (string) $result);
  }

  /**
   * @covers ::ai_logging_update_10310
   */
  public function testUpdate10310InstallsResponseTextField(): void {
    $update_manager = \Drupal::entityDefinitionUpdateManager();

    // A fresh install already ships with the field. Uninstall it to simulate
    // a site that predates this change.
    $update_manager->uninstallFieldStorageDefinition($update_manager->getFieldStorageDefinition('response_text', 'ai_log'));
    $this->assertNull($update_manager->getFieldStorageDefinition('response_text', 'ai_log'));

    $result = ai_logging_update_10310();
    $storage_definition = $update_manager->getFieldStorageDefinition('response_text', 'ai_log');
    $this->assertNotNull($storage_definition);
    $this->assertSame('string_long', $storage_definition->getType());
    $this->assertStringContainsString('Installed the AI Log fields: response_text', (string) $result);
    $this->assertArrayNotHasKey('ai_log', $update_manager->getChangeSummary(), 'The installed field matches a fresh install.');

    // Running it again on a site that already has the field must not error.
    $result = ai_logging_update_10310();
    $this->assertStringContainsString('already exist', (string) $result);
  }

  /**
   * Tests that fields recorded as installed but missing a column are fixed.
   *
   * Before ai_logging_update_10306() was fixed, a site moving from the AI
   * Logging submodule bundled with AI core had every current field recorded
   * as installed without adding its column.
   *
   * @covers ::ai_logging_update_10310
   */
  public function testUpdate10310RepairsFieldsMissingColumns(): void {
    $database_schema = \Drupal::database()->schema();
    foreach (['tokens_total', 'response_text'] as $field_name) {
      $database_schema->dropField('ai_log', $field_name);
    }
    $update_manager = \Drupal::entityDefinitionUpdateManager();
    $this->assertNotNull($update_manager->getFieldStorageDefinition('tokens_total', 'ai_log'));

    $result = ai_logging_update_10310();

    $this->assertTrue($database_schema->fieldExists('ai_log', 'tokens_total'));
    $this->assertTrue($database_schema->fieldExists('ai_log', 'response_text'));
    $this->assertStringContainsString('response_text, tokens_total', (string) $result);
    $this->assertArrayNotHasKey('ai_log', $update_manager->getChangeSummary());

    // The repaired fields can store values.
    $log = \Drupal::entityTypeManager()->getStorage('ai_log')->create([
      'bundle' => 'generic',
      'tokens_total' => 42,
      'response_text' => 'Hello',
    ]);
    $log->save();
    $this->assertSame(1, (int) \Drupal::database()->query('SELECT COUNT(*) FROM {ai_log} WHERE tokens_total = 42 AND response_text = :text', [':text' => 'Hello'])->fetchField());
  }

  /**
   * Tests that an existing AI Log entity type is left alone.
   *
   * @covers ::ai_logging_update_10306
   */
  public function testUpdate10306SkipsInstalledEntityTypes(): void {
    $update_manager = \Drupal::entityDefinitionUpdateManager();
    // Simulate a site on the bundled submodule, which predates this field.
    $update_manager->uninstallFieldStorageDefinition($update_manager->getFieldStorageDefinition('tokens_total', 'ai_log'));

    ai_logging_update_10306();

    $this->assertNull(
      $update_manager->getFieldStorageDefinition('tokens_total', 'ai_log'),
      'The field is not recorded as installed without its column.',
    );
    ai_logging_update_10307();
    $this->assertTrue(\Drupal::database()->schema()->fieldExists('ai_log', 'tokens_total'));
  }

  /**
   * @covers ::ai_logging_post_update_add_thread_settings
   */
  public function testPostUpdateAddsThreadSettingsWhenMissing(): void {
    $this->config('ai_logging.settings')
      ->clear('thread_tag_prefixes')
      ->clear('thread_primary_tag_pattern')
      ->save();

    $sandbox = [];
    ai_logging_post_update_add_thread_settings($sandbox);

    $config = $this->config('ai_logging.settings');
    $this->assertSame(['ai_agents_thread_', 'ai_assistant_thread_'], $config->get('thread_tag_prefixes'));
    $this->assertSame('ai_agents_prompt_%', $config->get('thread_primary_tag_pattern'));
  }

  /**
   * @covers ::ai_logging_post_update_add_thread_settings
   */
  public function testPostUpdateKeepsExistingThreadSettings(): void {
    $this->config('ai_logging.settings')
      ->set('thread_tag_prefixes', [])
      ->set('thread_primary_tag_pattern', '')
      ->save();

    $sandbox = [];
    ai_logging_post_update_add_thread_settings($sandbox);

    $config = $this->config('ai_logging.settings');
    $this->assertSame([], $config->get('thread_tag_prefixes'));
    $this->assertSame('', $config->get('thread_primary_tag_pattern'));
  }

}
