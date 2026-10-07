<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_validations\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\ai\AiProviderInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field_validation\Entity\FieldValidationRuleSet;
use Drupal\user\UserInterface;

/**
 * Tests the AI Provider Configuration element on the three rule plugins.
 *
 * Verifies that the FieldValidationRule plugins switched to use the
 * ai_provider_configuration form element render correctly, persist the
 * selected provider/model as a "provider__model" string, surface the
 * "Default" option when a default provider is configured for the
 * underlying operation type, and reload the saved selection when the
 * rule is edited.
 *
 * @group ai_validations
 * @group 3586397
 */
#[RunTestsInSeparateProcesses]
class AiValidationsProviderConfigurationFormElementTest extends WebDriverTestBase {

  use ContentTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'claro';

  /**
   * {@inheritdoc}
   *
   * The minimal profile pre-installs node in the normal order, which avoids
   * the action-plugin discovery race we hit when node is installed alongside
   * ai_test from $modules (node_make_sticky_action wasn't yet discovered when
   * node's default config tried to instantiate it).
   */
  protected $profile = 'minimal';

  /**
   * {@inheritdoc}
   *
   * Config schema for field_validation.rule_set.* does not cover the 'data'
   * sub-keys of individual rules; disable strict schema validation to avoid
   * false positives until the upstream module ships a complete schema.
   */
  // phpcs:ignore
  protected $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'text',
    'file',
    'image',
    'field_validation',
    'ai',
    'ai_test',
    'ai_validations',
  ];

  /**
   * Admin user with permissions to manage validation rule sets.
   */
  protected UserInterface $admin;

  /**
   * The rule set entity used by the tests.
   */
  protected FieldValidationRuleSet $ruleSet;

  /**
   * The provider plugin id offered by the ai_test module.
   */
  protected const TEST_PROVIDER_ID = 'echoai';

  /**
   * A model id exposed by the test provider.
   */
  protected const TEST_MODEL_ID = 'gpt-test';

  /**
   * Combined provider__model string persisted by the rule plugins.
   */
  protected const TEST_PROVIDER_MODEL = self::TEST_PROVIDER_ID . '__' . self::TEST_MODEL_ID;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->createContentType(['type' => 'article', 'name' => 'Article']);
    $this->createImageField('field_test_image', 'node', 'article');
    $this->createFileField('field_test_audio', 'node', 'article');

    $this->ruleSet = FieldValidationRuleSet::create([
      'name' => 'node_article',
      'label' => 'Node article validation',
      'entity_type' => 'node',
      'bundle' => 'article',
    ]);
    $this->ruleSet->save();

    $this->admin = $this->drupalCreateUser([
      'administer field validation rule set',
      'administer ai',
      'create article content',
      'edit own article content',
      'access content',
    ]);
    $this->drupalLogin($this->admin);
  }

  /**
   * Asserts the AI text prompt rule persists the chosen provider/model.
   */
  public function testTextRulePersistsSelectedProvider(): void {
    $this->visitAddRule('ai_text_prompt_constraint_rule', 'body');

    $this->assertProviderElementRenders('data[provider][provider_model]');

    $this->primeRuleFormFields('AI Text Prompt', 'value');
    $this->selectProviderOption('data[provider][provider_model]', self::TEST_PROVIDER_MODEL);
    $this->getSession()->getPage()->pressButton('Add Rule');
    $this->assertSession()->pageTextContains('The rule was successfully applied.');

    $rule = $this->loadSavedRule('ai_text_prompt_constraint_rule');
    $this->assertSame(self::TEST_PROVIDER_MODEL, $rule['data']['provider']);
  }

  /**
   * Asserts the AI image prompt rule persists the chosen provider/model.
   */
  public function testImageRulePersistsSelectedProvider(): void {
    $this->visitAddRule('ai_image_constraint_rule', 'field_test_image');

    $this->assertProviderElementRenders('data[provider][provider_model]');

    $this->primeRuleFormFields('AI Image Prompt', 'target_id');
    $this->selectProviderOption('data[provider][provider_model]', self::TEST_PROVIDER_MODEL);
    $this->getSession()->getPage()->pressButton('Add Rule');
    $this->assertSession()->pageTextContains('The rule was successfully applied.');

    $rule = $this->loadSavedRule('ai_image_constraint_rule');
    $this->assertSame(self::TEST_PROVIDER_MODEL, $rule['data']['provider']);
  }

  /**
   * Asserts the AI image classification rule persists the chosen model.
   *
   * The image classification plugin keeps the legacy "model" config key
   * rather than "provider"; this guards against accidental renames.
   */
  public function testImageClassificationRulePersistsSelectedModel(): void {
    $this->visitAddRule('ai_image_classification%20constraint_rule', 'field_test_image');

    $this->assertProviderElementRenders('data[model][provider_model]');

    $this->primeRuleFormFields('AI Image Classification', 'target_id');
    $this->getSession()->getPage()->fillField('data[tag]', 'cat');
    $this->selectProviderOption('data[model][provider_model]', self::TEST_PROVIDER_MODEL);
    $this->getSession()->getPage()->pressButton('Add Rule');
    $this->assertSession()->pageTextContains('The rule was successfully applied.');

    $rule = $this->loadSavedRule('ai_image_classification constraint_rule');
    $this->assertSame(self::TEST_PROVIDER_MODEL, $rule['data']['model']);
  }

  /**
   * Asserts the AI moderation rule persists the chosen provider/model.
   */
  public function testModerationRulePersistsSelectedProvider(): void {
    $this->visitAddRule('ai_moderation_constraint_rule', 'body');

    $this->assertProviderElementRenders('data[provider][provider_model]');

    $this->primeRuleFormFields('AI Moderation', 'value');
    $this->selectProviderOption('data[provider][provider_model]', self::TEST_PROVIDER_MODEL);
    $this->getSession()->getPage()->pressButton('Add Rule');
    $this->assertSession()->pageTextContains('The rule was successfully applied.');

    $rule = $this->loadSavedRule('ai_moderation_constraint_rule');
    $this->assertSame(self::TEST_PROVIDER_MODEL, $rule['data']['provider']);
  }

  /**
   * Asserts the AI text classification rule persists the chosen model.
   *
   * The text classification plugin uses the "model" config key, mirroring
   * the image classification plugin rather than the prompt-based rules.
   */
  public function testTextClassificationRulePersistsSelectedModel(): void {
    $this->visitAddRule('ai_text_classification_constraint_rule', 'body');

    $this->assertProviderElementRenders('data[model][provider_model]');

    $this->primeRuleFormFields('AI Text Classification', 'value');
    $this->getSession()->getPage()->fillField('data[tag]', 'positive');
    $this->selectProviderOption('data[model][provider_model]', self::TEST_PROVIDER_MODEL);
    $this->getSession()->getPage()->pressButton('Add Rule');
    $this->assertSession()->pageTextContains('The rule was successfully applied.');

    $rule = $this->loadSavedRule('ai_text_classification_constraint_rule');
    $this->assertSame(self::TEST_PROVIDER_MODEL, $rule['data']['model']);
  }

  /**
   * Asserts a configured default provider surfaces and persists as Default.
   */
  public function testDefaultProviderOptionIsAvailableAndPersists(): void {
    $this->setDefaultProvider('chat', self::TEST_PROVIDER_ID, self::TEST_MODEL_ID);

    $this->visitAddRule('ai_text_prompt_constraint_rule', 'body');

    $select = $this->getSession()->getPage()->findField('data[provider][provider_model]');
    $this->assertNotNull($select);
    $option_values = array_map(
      static fn($option) => $option->getValue(),
      $select->findAll('css', 'option')
    );
    $this->assertContains(AiProviderInterface::DEFAULT_MODEL_VALUE, $option_values, 'Default option should be present when a default provider is configured.');

    $this->primeRuleFormFields('AI Text Prompt default', 'value');
    $this->selectProviderOption('data[provider][provider_model]', AiProviderInterface::DEFAULT_MODEL_VALUE);
    $this->getSession()->getPage()->pressButton('Add Rule');
    $this->assertSession()->pageTextContains('The rule was successfully applied.');

    $rule = $this->loadSavedRule('ai_text_prompt_constraint_rule');
    $this->assertSame('', $rule['data']['provider'], 'Selecting Default must not persist a provider__model string.');
  }

  /**
   * Asserts the edit form preloads a previously saved provider/model.
   */
  public function testEditFormPreloadsSavedProvider(): void {
    $this->ruleSet->addFieldValidationRule([
      'id' => 'ai_text_prompt_constraint_rule',
      'title' => 'Pre-saved rule',
      'field_name' => 'body',
      'column' => 'value',
      'weight' => 0,
      'data' => [
        'prompt' => 'Answer XTRUE if mentions Drupal otherwise XFALSE.',
        'message' => 'Invalid value.',
        'provider' => self::TEST_PROVIDER_MODEL,
      ],
    ]);
    $this->ruleSet->save();

    $rules = $this->ruleSet->getFieldValidationRules();
    $uuid = '';
    foreach ($rules as $rule) {
      $uuid = $rule->getUuid();
      break;
    }
    $this->assertNotEmpty($uuid);

    $this->drupalGet('admin/structure/field_validation/manage/' . $this->ruleSet->id() . '/rules/' . $uuid);

    $select = $this->getSession()->getPage()->findField('data[provider][provider_model]');
    $this->assertNotNull($select);
    $this->assertSame(self::TEST_PROVIDER_MODEL, $select->getValue());
  }

  /**
   * Visits the add-rule form for the given plugin id and field.
   *
   * The base form uses the "field_name" query string to seed the (disabled)
   * field select and the dependent column dropdown, so it must be supplied
   * up front.
   *
   * @param string $rule_plugin_id
   *   The FieldValidationRule plugin id, url-encoded for routes that contain
   *   spaces (e.g. image classification's id).
   * @param string $field_name
   *   The field name to attach the rule to.
   */
  protected function visitAddRule(string $rule_plugin_id, string $field_name): void {
    $this->drupalGet(
      'admin/structure/field_validation/manage/' . $this->ruleSet->id() . '/add/' . $rule_plugin_id,
      ['query' => ['field_name' => $field_name]]
    );
  }

  /**
   * Asserts the provider/model select rendered by the new element exists.
   */
  protected function assertProviderElementRenders(string $select_name): void {
    $page = $this->getSession()->getPage();
    $select = $page->findField($select_name);
    $this->assertNotNull($select, sprintf('Provider/model select %s should be rendered by the ai_provider_configuration element.', $select_name));
    $options = array_map(
      static fn($option) => $option->getValue(),
      $select->findAll('css', 'option')
    );
    $this->assertContains(self::TEST_PROVIDER_MODEL, $options, 'Provider/model dropdown should expose the test provider model.');
  }

  /**
   * Selects an option on a provider/model select.
   */
  protected function selectProviderOption(string $select_name, string $value): void {
    $select = $this->getSession()->getPage()->findField($select_name);
    $this->assertNotNull($select);
    if ($select->getValue() !== $value) {
      $select->setValue($value);
      $this->assertSession()->assertWaitOnAjaxRequest();
    }
  }

  /**
   * Populates the required title and column fields of the rule add form.
   *
   * Must be called BEFORE selectProviderOption(), because selecting the
   * provider element fires an AJAX rebuild that wipes any field state not
   * already round-tripped through the form state.
   */
  protected function primeRuleFormFields(string $title, string $column): void {
    $page = $this->getSession()->getPage();
    $page->fillField('title', $title);
    $page->selectFieldOption('column', $column);
  }

  /**
   * Returns the most recently added rule of a given plugin id as an array.
   */
  protected function loadSavedRule(string $plugin_id): array {
    $config = $this->container->get('config.factory')
      ->getEditable('field_validation.rule_set.' . $this->ruleSet->id())
      ->getRawData();
    $matching = [];
    foreach (($config['field_validation_rules'] ?? []) as $rule) {
      if (($rule['id'] ?? '') === $plugin_id) {
        $matching[] = $rule;
      }
    }
    $this->assertNotEmpty($matching, sprintf('At least one %s rule should have been persisted.', $plugin_id));
    return end($matching);
  }

  /**
   * Configures a default provider/model for an operation type.
   */
  protected function setDefaultProvider(string $operation_type, string $provider_id, string $model_id): void {
    $config = $this->container->get('config.factory')->getEditable('ai.settings');
    $defaults = $config->get('default_providers') ?? [];
    $defaults[$operation_type] = [
      'provider_id' => $provider_id,
      'model_id' => $model_id,
    ];
    $config->set('default_providers', $defaults)->save();
  }

  /**
   * Asserts the AI audio constraint rule persists both provider/model configs.
   */
  public function testAudioRulePersistsSelectedProviders(): void {
    $this->visitAddRule('ai_audio_constraint_rule', 'field_test_audio');

    $this->assertProviderElementRenders('data[provider][provider_model]');
    $this->assertProviderElementRenders('data[chat_provider][provider_model]');

    $this->primeRuleFormFields('AI Audio Prompt', 'target_id');
    $this->selectProviderOption('data[provider][provider_model]', self::TEST_PROVIDER_MODEL);
    $this->selectProviderOption('data[chat_provider][provider_model]', self::TEST_PROVIDER_MODEL);
    $this->getSession()->getPage()->pressButton('Add Rule');
    $this->assertSession()->pageTextContains('The rule was successfully applied.');

    $rule = $this->loadSavedRule('ai_audio_constraint_rule');
    $this->assertSame(self::TEST_PROVIDER_MODEL, $rule['data']['provider']);
    $this->assertSame(self::TEST_PROVIDER_MODEL, $rule['data']['chat_provider']);
  }

  /**
   * Creates an image field on a content type and exposes it on the edit form.
   */
  protected function createImageField(string $name, string $entity_type, string $bundle): void {
    FieldStorageConfig::create([
      'field_name' => $name,
      'entity_type' => $entity_type,
      'type' => 'image',
      'cardinality' => 1,
    ])->save();
    FieldConfig::create([
      'field_name' => $name,
      'entity_type' => $entity_type,
      'bundle' => $bundle,
      'label' => 'Test image',
    ])->save();
    \Drupal::service('entity_display.repository')
      ->getFormDisplay($entity_type, $bundle, 'default')
      ->setComponent($name, ['type' => 'image_image'])
      ->save();
  }

  /**
   * Creates a file field on a content type and exposes it on the edit form.
   */
  protected function createFileField(string $name, string $entity_type, string $bundle): void {
    FieldStorageConfig::create([
      'field_name' => $name,
      'entity_type' => $entity_type,
      'type' => 'file',
      'cardinality' => 1,
    ])->save();
    FieldConfig::create([
      'field_name' => $name,
      'entity_type' => $entity_type,
      'bundle' => $bundle,
      'label' => 'Test audio',
    ])->save();
    \Drupal::service('entity_display.repository')
      ->getFormDisplay($entity_type, $bundle, 'default')
      ->setComponent($name, ['type' => 'file_generic'])
      ->save();
  }

}
