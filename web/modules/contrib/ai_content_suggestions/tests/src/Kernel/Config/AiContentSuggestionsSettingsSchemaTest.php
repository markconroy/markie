<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_content_suggestions\Kernel\Config;

use Drupal\ai_content_suggestions\AiContentSuggestionsInterface;
use Drupal\ai_content_suggestions\Form\SettingsForm;
use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\SchemaCheckTestTrait;

/**
 * Tests ai_content_suggestions.settings config schema coverage.
 *
 * @coversDefaultClass \Drupal\ai_content_suggestions\Form\SettingsForm
 *
 * @group ai_content_suggestions
 */
class AiContentSuggestionsSettingsSchemaTest extends KernelTestBase {

  use SchemaCheckTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ai',
    'ai_content_suggestions',
    'ai_test',
    'key',
    'system',
    'user',
  ];

  /**
   * The settings form instance.
   */
  protected SettingsForm $settingsForm;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['ai', 'ai_content_suggestions']);
    $this->installEntitySchema('ai_mock_provider_result');
    \Drupal::configFactory()
      ->getEditable('ai.settings')
      ->set('default_providers.chat', [
        'provider_id' => 'echoai',
        'model_id' => 'gpt-test',
      ])
      ->save();
    $this->settingsForm = SettingsForm::create(\Drupal::getContainer());
  }

  /**
   * Tests schema is valid for default installed config.
   */
  public function testSchemaWithDefaultConfig(): void {
    $this->assertConfigSchemaByName('ai_content_suggestions.settings');
  }

  /**
   * Tests schema after submitting a non-empty field widget prompt.
   */
  public function testSchemaWithFieldWidgetPrompt(): void {
    $this->submitSettingsForm([
      'field_settings' => [
        'field_widget_prompt' => 'Provide content suggestions based on the following input.',
      ],
    ]);

    $this->assertConfigSchemaByName('ai_content_suggestions.settings');
  }

  /**
   * Tests schema after submitting an empty field widget prompt.
   */
  public function testSchemaWithEmptyFieldWidgetPrompt(): void {
    $this->submitSettingsForm([
      'field_settings' => [
        'field_widget_prompt' => '',
      ],
    ]);

    $this->assertConfigSchemaByName('ai_content_suggestions.settings');
  }

  /**
   * Tests schema with plugin configurations for all base plugins.
   *
   * Covers summarise, readability, title_suggest, tone, and moderate plugins
   * which use ai_content_suggestions.plugin_config_base (with optional extra
   * fields) and carry no ConfigExists constraints.
   */
  public function testSchemaWithPopulatedPluginValues(): void {
    $this->submitSettingsForm([
      'field_settings' => [
        'field_widget_prompt' => 'Provide content suggestions.',
      ],
      'plugins' => [
        'summarise' => [
          'enabled' => TRUE,
          'model' => 'provider__model',
          'prompt' => 'Create a summary of the following text:',
        ],
        'readability' => [
          'enabled' => TRUE,
          'model' => 'provider__model',
          'prompt' => 'Analyse the readability of:',
        ],
        'title_suggest' => [
          'enabled' => TRUE,
          'model' => 'provider__model',
          'prompt' => 'Suggest a title for:',
        ],
        'tone' => [
          'enabled' => TRUE,
          'model' => 'provider__model',
          'prompt' => 'Analyse the tone of:',
          'taxonomy' => '',
          'taxonomy_enabled' => FALSE,
        ],
        'moderate' => [
          'enabled' => TRUE,
          'model' => 'provider__model',
        ],
      ],
    ]);

    $this->assertConfigSchemaByName('ai_content_suggestions.settings');
  }

  /**
   * Tests schema when plugins are submitted as an empty set.
   */
  public function testSchemaWithEmptyPlugins(): void {
    $this->submitSettingsForm([
      'field_settings' => [
        'field_widget_prompt' => 'Some prompt.',
      ],
      'plugins' => [],
    ]);

    $this->assertSame([], $this->config('ai_content_suggestions.settings')->get('plugins'));
    $this->assertConfigSchemaByName('ai_content_suggestions.settings');
  }

  /**
   * Tests schema with disabled plugin configurations.
   */
  public function testSchemaWithDisabledPluginValues(): void {
    $this->submitSettingsForm([
      'field_settings' => [
        'field_widget_prompt' => '',
      ],
      'plugins' => [
        'summarise' => [
          'enabled' => FALSE,
          'model' => '',
          'prompt' => '',
        ],
        'tone' => [
          'enabled' => FALSE,
          'model' => '',
          'prompt' => '',
          'taxonomy' => '',
          'taxonomy_enabled' => FALSE,
        ],
      ],
    ]);

    $this->assertConfigSchemaByName('ai_content_suggestions.settings');
  }

  /**
   * Tests taxonomy_suggest plugin schema with installed ai_prompt configs.
   *
   * The taxonomy_suggest plugin schema enforces ConfigExists constraints on
   * prompt_open and prompt_from_voc. This test uses the ai_prompt configs
   * shipped with ai_content_suggestions in config/install to satisfy them.
   */
  public function testSchemaWithTaxonomySuggestPlugin(): void {
    $this->submitSettingsForm([
      'field_settings' => [
        'field_widget_prompt' => 'Field prompt.',
      ],
      'plugins' => [
        'taxonomy_suggest' => [
          'enabled' => TRUE,
          'model' => 'provider__model',
          'prompt_open' => 'suggest_tags__suggest_tags_default',
          'prompt_from_voc' => 'suggest_vocabulary__suggest_vocabulary_default',
        ],
      ],
    ]);

    $this->assertConfigSchemaByName('ai_content_suggestions.settings');
  }

  /**
   * Tests schema with entity type configurations for the enable mode.
   *
   * Covers entity type entries with enable mode and selected bundles.
   */
  public function testSchemaWithPopulatedEntityTypeValues(): void {
    $this->submitSettingsForm([
      'field_settings' => [
        'field_widget_prompt' => 'Field prompt.',
      ],
      'entity_type_settings' => [
        'entity_types' => [
          'user' => [
            'mode' => 'enable',
            'bundles' => ['user' => 'user'],
          ],
        ],
      ],
    ]);

    $this->assertConfigSchemaByName('ai_content_suggestions.settings');
  }

  /**
   * Tests schema when entity types are submitted as an empty set.
   */
  public function testSchemaWithEmptyEntityTypes(): void {
    $this->submitSettingsForm([
      'field_settings' => [
        'field_widget_prompt' => 'Some prompt.',
      ],
      'entity_type_settings' => [
        'entity_types' => [],
      ],
    ]);

    $this->assertSame([], $this->config('ai_content_suggestions.settings')->get('entity_types'));
    $this->assertConfigSchemaByName('ai_content_suggestions.settings');
  }

  /**
   * Tests schema with entity type in disable mode and no selected bundles.
   */
  public function testSchemaWithDisableEntityTypeMode(): void {
    $this->submitSettingsForm([
      'field_settings' => [
        'field_widget_prompt' => '',
      ],
      'entity_type_settings' => [
        'entity_types' => [
          'user' => [
            'mode' => 'disable',
            'bundles' => [],
          ],
        ],
      ],
    ]);

    $this->assertConfigSchemaByName('ai_content_suggestions.settings');
  }

  /**
   * Tests that entity type data is persisted to config on form submit.
   */
  public function testEntityTypeConfigIsSaved(): void {
    $this->submitSettingsForm([
      'field_settings' => [
        'field_widget_prompt' => '',
      ],
      'entity_type_settings' => [
        'entity_types' => [
          'user' => [
            'mode' => 'enable',
            'bundles' => ['user' => 'user'],
          ],
        ],
      ],
    ]);

    $entity_types = $this->config('ai_content_suggestions.settings')->get('entity_types');
    $this->assertArrayHasKey('user', $entity_types);
    $this->assertSame('enable', $entity_types['user']['mode']);
    $this->assertSame(['user' => 'user'], $entity_types['user']['bundles']);
    $this->assertConfigSchemaByName('ai_content_suggestions.settings');
  }

  /**
   * Tests that field_widget_prompt value is persisted correctly.
   */
  public function testFieldWidgetPromptIsSaved(): void {
    $expected = 'Custom system prompt for field widget.';
    $this->submitSettingsForm([
      'field_settings' => [
        'field_widget_prompt' => $expected,
      ],
    ]);

    $this->assertSame(
      $expected,
      $this->config('ai_content_suggestions.settings')->get('field_widget_prompt')
    );
  }

  /**
   * Tests that plugin data is persisted to config on form submit.
   */
  public function testPluginConfigIsSaved(): void {
    $this->submitSettingsForm([
      'field_settings' => [
        'field_widget_prompt' => '',
      ],
      'plugins' => [
        'summarise' => [
          'enabled' => TRUE,
          'model' => 'provider__model',
          'prompt' => 'Summarise:',
        ],
      ],
    ]);

    $plugins = $this->config('ai_content_suggestions.settings')->get('plugins');
    $this->assertArrayHasKey('summarise', $plugins);
    $this->assertTrue($plugins['summarise']['enabled']);
    $this->assertSame('provider__model', $plugins['summarise']['model']);
    $this->assertConfigSchemaByName('ai_content_suggestions.settings');
  }

  /**
   * Tests post update hook 15001 migrates plugin config from old to new format.
   *
   * Old format: plugins stored as plugin_id => model_string, with prompts in
   * ai_content_suggestions.prompts and tone settings in
   * ai_content_suggestions.tone.
   *
   * New format: each plugin is a full mapping stored under
   * ai_content_suggestions.settings plugins.
   */
  public function testPostUpdateHook15001MigratesPluginConfig(): void {
    // Write old-format settings (plugin_id => model string) directly to raw
    // storage to bypass ConfigSchemaChecker, which rejects the string values
    // because the current schema requires a mapping for each plugin. After
    // writing, reset the ConfigFactory's PHP-level object cache so the update
    // hook reads fresh data from storage rather than the cached new-format
    // object left by setUp()'s installConfig() call.
    $storage = \Drupal::service('config.storage');
    $storage->write('ai_content_suggestions.settings', [
      'field_widget_prompt' => 'Existing field prompt.',
      'plugins' => [
        'summarise' => 'provider__summarise_model',
        'readability' => 'provider__readability_model',
        'title_suggest' => 'provider__title_model',
        'tone' => 'provider__tone_model',
        'taxonomy_suggest' => 'provider__taxonomy_model',
        'moderate' => 'provider__moderate_model',
      ],
    ]);
    \Drupal::configFactory()->reset('ai_content_suggestions.settings');

    // Write ai_content_suggestions.prompts and .tone directly to storage.
    // These configs have no schema definition so the event system must be
    // bypassed. They are not in the ConfigFactory cache, so the update hook's
    // getEditable() calls will read the written data from storage.
    $storage->write('ai_content_suggestions.prompts', [
      'summarise' => 'Summarise the text:',
      'readability' => 'Analyse readability:',
      'title_suggest' => 'Suggest a title:',
      'tone' => 'Analyse tone:',
      'taxonomy_suggest_open' => 'suggest_tags__suggest_tags_default',
      'taxonomy_suggest_from_voc' => 'suggest_vocabulary__suggest_vocabulary_default',
    ]);
    $storage->write('ai_content_suggestions.tone', [
      'tone_taxonomy' => 'tone_terms',
      'tone_taxonomy_enabled' => TRUE,
    ]);

    // Run the update hook.
    \Drupal::moduleHandler()->loadInclude('ai_content_suggestions', 'php', 'ai_content_suggestions.post_update');
    $sandbox = [];
    $args = [&$sandbox];
    call_user_func_array('ai_content_suggestions_post_update_15001', $args);

    $settings = $this->config('ai_content_suggestions.settings');
    $plugins = $settings->get('plugins');

    // Unchanged top-level value must be preserved.
    $this->assertSame('Existing field prompt.', $settings->get('field_widget_prompt'));

    // Basic plugins: string model expanded to enabled+model+prompt.
    $this->assertSame([
      'enabled' => TRUE,
      'model' => 'provider__summarise_model',
      'prompt' => 'Summarise the text:',
    ], $plugins['summarise']);

    $this->assertSame([
      'enabled' => TRUE,
      'model' => 'provider__readability_model',
      'prompt' => 'Analyse readability:',
    ], $plugins['readability']);

    $this->assertSame([
      'enabled' => TRUE,
      'model' => 'provider__title_model',
      'prompt' => 'Suggest a title:',
    ], $plugins['title_suggest']);

    // Tone plugin: prompt from prompts config + taxonomy from tone config.
    $this->assertSame([
      'enabled' => TRUE,
      'model' => 'provider__tone_model',
      'prompt' => 'Analyse tone:',
      'taxonomy' => 'tone_terms',
      'taxonomy_enabled' => TRUE,
    ], $plugins['tone']);

    // Taxonomy suggest: prompt references merged from prompts config.
    $this->assertSame([
      'enabled' => TRUE,
      'model' => 'provider__taxonomy_model',
      'prompt_open' => 'suggest_tags__suggest_tags_default',
      'prompt_from_voc' => 'suggest_vocabulary__suggest_vocabulary_default',
    ], $plugins['taxonomy_suggest']);

    // Moderate: no matching prompt entry, only base fields.
    $this->assertSame([
      'enabled' => TRUE,
      'model' => 'provider__moderate_model',
    ], $plugins['moderate']);

    // Old config objects must have been deleted.
    $this->assertTrue(
      \Drupal::configFactory()->get('ai_content_suggestions.prompts')->isNew(),
      'ai_content_suggestions.prompts was not deleted by the update hook.'
    );
    $this->assertTrue(
      \Drupal::configFactory()->get('ai_content_suggestions.tone')->isNew(),
      'ai_content_suggestions.tone was not deleted by the update hook.'
    );

    $this->assertConfigSchemaByName('ai_content_suggestions.settings');
  }

  /**
   * Tests post update hook 15001 preserves plugins already in array format.
   *
   * Plugins that were already migrated before the hook runs must not be
   * overwritten, even if a prompt exists for them in the old prompts config.
   */
  public function testPostUpdateHook15001SkipsAlreadyMigratedPlugins(): void {
    $storage = \Drupal::service('config.storage');
    $storage->write('ai_content_suggestions.settings', [
      'field_widget_prompt' => '',
      'plugins' => [
        'summarise' => [
          'enabled' => TRUE,
          'model' => 'provider__model',
          'prompt' => 'Already migrated prompt.',
        ],
      ],
    ]);
    \Drupal::configFactory()->reset('ai_content_suggestions.settings');

    // Even though a different prompt exists in the prompts config, the already-
    // array plugin must be left untouched.
    $storage->write('ai_content_suggestions.prompts', [
      'summarise' => 'Stale prompt that must be ignored.',
    ]);

    \Drupal::moduleHandler()->loadInclude('ai_content_suggestions', 'php', 'ai_content_suggestions.post_update');
    $sandbox = [];
    $args = [&$sandbox];
    call_user_func_array('ai_content_suggestions_post_update_15001', $args);

    $plugins = $this->config('ai_content_suggestions.settings')->get('plugins');

    $this->assertSame([
      'enabled' => TRUE,
      'model' => 'provider__model',
      'prompt' => 'Already migrated prompt.',
    ], $plugins['summarise']);
  }

  /**
   * Tests post update hook 15001 when no prompts or tone config objects exist.
   *
   * The hook must complete without errors and produce valid plugin config even
   * when the legacy ai_content_suggestions.prompts and
   * ai_content_suggestions.tone objects are absent.
   */
  public function testPostUpdateHook15001WithMissingLegacyConfigs(): void {
    $storage = \Drupal::service('config.storage');
    $storage->write('ai_content_suggestions.settings', [
      'field_widget_prompt' => '',
      'plugins' => [
        'summarise' => 'provider__model',
      ],
    ]);
    \Drupal::configFactory()->reset('ai_content_suggestions.settings');

    // Intentionally do not write ai_content_suggestions.prompts or .tone.
    \Drupal::moduleHandler()->loadInclude('ai_content_suggestions', 'php', 'ai_content_suggestions.post_update');
    $sandbox = [];
    $args = [&$sandbox];
    call_user_func_array('ai_content_suggestions_post_update_15001', $args);

    $plugins = $this->config('ai_content_suggestions.settings')->get('plugins');

    // Without a prompts entry, only enabled and model must be present.
    $this->assertSame([
      'enabled' => TRUE,
      'model' => 'provider__model',
    ], $plugins['summarise']);

    $this->assertConfigSchemaByName('ai_content_suggestions.settings');
  }

  /**
   * Tests isEnabled() returns TRUE when the plugin is configured as enabled.
   */
  public function testIsEnabledReturnsTrueWhenEnabled(): void {
    $plugin = $this->createPlugin('summarise', ['enabled' => TRUE, 'model' => '']);
    $this->assertTrue($plugin->isEnabled());
  }

  /**
   * Tests isEnabled() returns FALSE when the plugin is configured as disabled.
   */
  public function testIsEnabledReturnsFalseWhenDisabled(): void {
    $plugin = $this->createPlugin('summarise', ['enabled' => FALSE, 'model' => '']);
    $this->assertFalse($plugin->isEnabled());
  }

  /**
   * Tests isEnabled() returns FALSE when no configuration is provided.
   */
  public function testIsEnabledReturnsFalseByDefault(): void {
    $plugin = $this->createPlugin('summarise', []);
    $this->assertFalse($plugin->isEnabled());
  }

  /**
   * Tests getFormFieldValue() returns the value stored under the plugin key.
   */
  public function testGetFormFieldValueReturnsValue(): void {
    $plugin = $this->createPlugin('summarise', []);
    $form_state = new FormState();
    $form_state->setValue('summarise', ['prompt' => 'My custom prompt.']);
    $this->assertSame('My custom prompt.', $plugin->getFormFieldValue('prompt', $form_state));
  }

  /**
   * Tests getFormFieldValue() returns [] when the plugin key is absent.
   */
  public function testGetFormFieldValueReturnsEmptyArrayWhenPluginKeyMissing(): void {
    $plugin = $this->createPlugin('summarise', []);
    $form_state = new FormState();
    $this->assertIsArray($plugin->getFormFieldValue('prompt', $form_state));
    $this->assertEmpty($plugin->getFormFieldValue('prompt', $form_state));
  }

  /**
   * Tests getFormFieldValue() returns [] when the field key is absent.
   */
  public function testGetFormFieldValueReturnsEmptyArrayWhenFieldKeyMissing(): void {
    $plugin = $this->createPlugin('summarise', []);
    $form_state = new FormState();
    $form_state->setValue('summarise', ['other_field' => 'value']);
    $this->assertIsArray($plugin->getFormFieldValue('prompt', $form_state));
    $this->assertEmpty($plugin->getFormFieldValue('prompt', $form_state));
  }

  /**
   * Tests getDefaultModel() returns the model set in configuration.
   */
  public function testGetDefaultModelReturnsConfiguredModel(): void {
    $plugin = $this->createPlugin('summarise', ['model' => 'provider__specific_model']);
    $this->assertSame('provider__specific_model', $plugin->getDefaultModel());
  }

  /**
   * Tests getDefaultModel() falls back to the default provider when empty.
   *
   * When the plugin's model config is an empty string, getDefaultModel() must
   * fall through to getDefaultProviderForOperationType() and return the
   * concatenated provider and model from ai.settings.
   */
  public function testGetDefaultModelUsesDefaultProviderWhenModelEmpty(): void {
    $plugin = $this->createPlugin('summarise', ['model' => '']);
    $this->assertSame('echoai__gpt-test', $plugin->getDefaultModel());
  }

  /**
   * Tests getDefaultModel() falls back to the default provider when absent.
   *
   * When the plugin has no model key in its configuration at all,
   * getDefaultModel() must fall through to getDefaultProviderForOperationType()
   * and return the concatenated provider and model from ai.settings.
   */
  public function testGetDefaultModelUsesDefaultProviderWhenModelAbsent(): void {
    $plugin = $this->createPlugin('summarise', []);
    $this->assertSame('echoai__gpt-test', $plugin->getDefaultModel());
  }

  /**
   * Submits the settings form with the given values.
   *
   * @param array $values
   *   Form state values keyed by form element name.
   */
  protected function submitSettingsForm(array $values): void {
    $form_state = new FormState();
    $form_state->setValues($values);
    $form = [];
    $this->settingsForm->submitForm($form, $form_state);
  }

  /**
   * Creates a plugin instance via the plugin manager.
   *
   * @param string $plugin_id
   *   The plugin ID (e.g. 'summarise', 'tone').
   * @param array $config
   *   Configuration to pass to the plugin.
   *
   * @return \Drupal\ai_content_suggestions\AiContentSuggestionsInterface
   *   The instantiated plugin.
   */
  protected function createPlugin(string $plugin_id, array $config): AiContentSuggestionsInterface {
    /** @var \Drupal\ai_content_suggestions\AiContentSuggestionsPluginManager $manager */
    $manager = $this->container->get('plugin.manager.ai_content_suggestions');
    return $manager->createInstance($plugin_id, $config);
  }

}
