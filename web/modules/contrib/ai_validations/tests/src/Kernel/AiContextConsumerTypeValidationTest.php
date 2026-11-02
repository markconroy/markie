<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_validations\Kernel;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai_context\Entity\AiContextItem;
use Drupal\ai_context\Model\AiContextConsumerRouteResult;
use Drupal\ai_context\Model\AiContextProviderRequestContext;
use Drupal\ai_context\Plugin\AiContextConsumerType\AiContextConsumerTypeBase;
use Drupal\ai_context\Service\AiContextScopeIndexService;
use Drupal\ai_validations\AiValidationsEntityContext;
use Drupal\ai_validations\AiValidationsRequestTags;
use Drupal\ai_validations\Plugin\AiContextConsumerType\AiContextConsumerTypeValidation;
use Drupal\ai_validations\Plugin\Validation\Constraint\AiAudioConstraint;
use Drupal\ai_validations\Plugin\Validation\Constraint\AiAudioConstraintValidator;
use Drupal\ai_validations\Plugin\Validation\Constraint\AiImageConstraint;
use Drupal\ai_validations\Plugin\Validation\Constraint\AiImageConstraintValidator;
use Drupal\ai_validations\Plugin\Validation\Constraint\AiTextConstraint;
use Drupal\ai_validations\Plugin\Validation\Constraint\AiTextConstraintValidator;
use Drupal\ai_validations_test\Plugin\AiProvider\ClassifierTestProvider;
use Drupal\field_validation\Entity\FieldValidationRuleSet;
use Drupal\file\Entity\File;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Tests the AI validation consumer type for AI Context.
 *
 * @see https://git.drupalcode.org/project/ai_validations/-/work_items/3606133
 */
#[Group('ai_validations')]
#[Group('3606133')]
#[CoversClass(AiContextConsumerTypeValidation::class)]
#[RunTestsInSeparateProcesses]
final class AiContextConsumerTypeValidationTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'file',
    'image',
    'text',
    'filter',
    'options',
    'taxonomy',
    'views',
    'workflows',
    'content_moderation',
    'key',
    'ai',
    'ai_test',
    'ai_agents',
    'ai_context',
    'field_validation',
    'ai_validations',
    'ai_validations_test',
  ];

  /**
   * AI Validations ships no schema for its field validation rule data.
   *
   * @var bool
   */
  // phpcs:ignore DrupalPractice.Objects.StrictSchemaDisabled.StrictConfigSchema
  protected $strictConfigSchema = FALSE;

  /**
   * A resolved "provider__model" option pointing at the test provider.
   */
  private const OPTION = 'classifier_test__gpt-test';

  /**
   * The validation prompt used by the text rule.
   */
  private const PROMPT = 'Answer XTRUE or XFALSE.';

  /**
   * Marker for the always-include context item.
   */
  private const CONTEXT_MARKER = 'VALIDATION-CONTEXT-MARKER-5170';

  /**
   * The reply-format reminder the boolean protocol appends.
   */
  private const BOOLEAN_REMINDER = 'begin your reply with XTRUE or XFALSE.';

  /**
   * The reply-format reminder the ternary protocol appends.
   */
  private const TERNARY_REMINDER = 'begin your reply with XSAFE, XWARN or XBLOCK.';

  /**
   * A 1x1 GIF used as a managed file for image and audio validators.
   */
  private const VALID_GIF = "GIF89a\x01\x00\x01\x00\x80\x00\x00\xff\xff\xff\x00\x00\x00\x21\xf9\x04\x01\x00\x00\x00\x00\x2c\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x01\x44\x00\x3b";

  /**
   * The consumer type plugin.
   */
  private AiContextConsumerTypeValidation $plugin;

  /**
   * UUID of the AI text prompt rule.
   */
  private string $textRuleUuid;

  /**
   * UUID of the AI image prompt rule.
   */
  private string $imageRuleUuid;

  /**
   * UUID of the AI audio prompt rule.
   */
  private string $audioRuleUuid;

  /**
   * UUID of the AI moderation rule, which does not run a chat prompt.
   */
  private string $moderationRuleUuid;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    // The ai_validations namespace exists in two locations: this git clone
    // and the bundled copy inside ai/modules/ai_validations. Prepend our own
    // PSR-4 loader so it wins. See AiTextConstraintValidatorTest.
    // @todo Remove this workaround once ai_validations is no longer bundled
    //   inside the ai package. See
    //   https://git.drupalcode.org/project/ai/-/work_items/3586538
    $module_root = dirname(__DIR__, 3);
    spl_autoload_register(static function (string $class) use ($module_root): void {
      $prefix = 'Drupal\\ai_validations\\';
      if (str_starts_with($class, $prefix)) {
        $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
        $file = $module_root . '/src/' . $relative . '.php';
        if (file_exists($file)) {
          require_once $file;
        }
      }
    }, TRUE, TRUE);

    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('ai_context_item');
    $this->installEntitySchema('ai_context_usage');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('content_moderation_state');
    $this->installSchema('file', ['file_usage']);
    $this->installSchema('ai_context', [AiContextScopeIndexService::TABLE]);
    $this->installConfig(['system', 'ai_context']);

    $role = Role::create([
      'id' => 'context_viewer',
      'label' => 'Context viewer',
    ]);
    $role->grantPermission('view ai context items');
    $role->grantPermission('access published ai context');
    $role->save();
    $user = User::create([
      'name' => 'validation-context',
      'status' => 1,
    ]);
    $user->addRole('context_viewer');
    $user->save();
    $this->container->get('current_user')->setAccount($user);

    $rule_set = FieldValidationRuleSet::create([
      'name' => 'user_user',
      'label' => 'User rules',
      'entity_type' => 'user',
      'bundle' => 'user',
    ]);
    $this->textRuleUuid = $rule_set->addFieldValidationRule([
      'id' => 'ai_text_prompt_constraint_rule',
      'title' => 'Name mentions Drupal',
      'weight' => 0,
      'field_name' => 'name',
      'column' => 'value',
      'error_message' => '',
      'data' => [
        'prompt' => self::PROMPT,
        'provider' => self::OPTION,
        'message' => 'Not valid.',
      ],
    ]);
    $this->imageRuleUuid = $rule_set->addFieldValidationRule([
      'id' => 'ai_image_constraint_rule',
      'title' => '',
      'weight' => 1,
      'field_name' => 'user_picture',
      'column' => 'target_id',
      'error_message' => '',
      'data' => [],
    ]);
    $this->audioRuleUuid = $rule_set->addFieldValidationRule([
      'id' => 'ai_audio_constraint_rule',
      'title' => 'Voice mentions Drupal',
      'weight' => 2,
      'field_name' => 'name',
      'column' => 'value',
      'error_message' => '',
      'data' => [],
    ]);
    $this->moderationRuleUuid = $rule_set->addFieldValidationRule([
      'id' => 'ai_moderation_constraint_rule',
      'title' => 'Moderated name',
      'weight' => 3,
      'field_name' => 'name',
      'column' => 'value',
      'error_message' => '',
      'data' => [],
    ]);
    $rule_set->save();

    $this->plugin = $this->container
      ->get('plugin.manager.ai_context_consumer_type')
      ->createInstance(AiContextConsumerTypeValidation::PLUGIN_ID);
  }

  /**
   * Tests defaults and routing tags.
   *
   * The type has no availability gate of its own: it is only discovered
   * once ai_context, which ships the consumer type API, is enabled.
   */
  public function testAvailabilityDefaultsAndRoutingTags(): void {
    $this->assertSame('Validation', $this->plugin->getLabel());
    $this->assertTrue($this->plugin->isAvailable());
    $this->assertTrue($this->plugin->isEnabled());
    // Validation does not override the base defaults. Push is off. The
    // selection_mode key comes from the AI Context base class: absent
    // on 1.0.0-beta5, inherit (Site default) from #3586535 on. Either
    // way an unsaved row follows the site setting at runtime.
    $defaults = $this->plugin->getInstanceDefaults();
    $this->assertFalse($defaults['push_enabled']);
    if (array_key_exists('selection_mode', $defaults)) {
      $this->assertSame('inherit', $defaults['selection_mode']);
    }
    $declaring = (new \ReflectionMethod($this->plugin, 'getInstanceDefaults'))
      ->getDeclaringClass()
      ->getName();
    $this->assertSame(AiContextConsumerTypeBase::class, $declaring);
    $this->assertSame(
      [AiValidationsRequestTags::ROUTING],
      $this->plugin->getRoutingRequestTags(),
    );
    $this->assertSame(
      ['ai_validations', 'ai_validations:abc'],
      AiValidationsRequestTags::forRule('abc'),
    );
    $this->assertSame(
      ['ai_validations'],
      AiValidationsRequestTags::forRule(NULL),
    );
  }

  /**
   * Tests only chat prompt rules are listed, with labels and links.
   */
  public function testInstancesListChatPromptRules(): void {
    $collection = $this->plugin->getInstances();

    $text = $collection->get('validation:' . $this->textRuleUuid);
    $this->assertNotNull($text);
    $this->assertSame('Name mentions Drupal', $text->getLabel());
    $this->assertSame(
      'AI text prompt constraint on the name field in the "User rules" rule set.',
      $text->getDescription(),
    );

    // An untitled rule falls back to the plugin label.
    $image = $collection->get('validation:' . $this->imageRuleUuid);
    $this->assertNotNull($image);
    $this->assertSame('Ai image constraint', $image->getLabel());

    $audio = $collection->get('validation:' . $this->audioRuleUuid);
    $this->assertNotNull($audio);
    $this->assertSame('Voice mentions Drupal', $audio->getLabel());
    $this->assertSame(
      'AI audio constraint on the name field in the "User rules" rule set.',
      $audio->getDescription(),
    );

    $this->assertFalse(
      $collection->has('validation:' . $this->moderationRuleUuid),
    );
    $this->assertContains('config:core.extension', $collection->getCacheTags());
    $this->assertContains(
      'config:field_validation_rule_set_list',
      $collection->getCacheTags(),
    );

    $this->assertSame([
      'route_name' => 'entity.field_validation_rule_set.edit_form',
      'route_parameters' => ['field_validation_rule_set' => 'user_user'],
    ], $this->plugin->getLabelRoute($this->textRuleUuid));
    $this->assertNull(
      $this->plugin->getLabelRoute($this->moderationRuleUuid),
    );
  }

  /**
   * Tests chat prompt rules pass their UUID to the constraint.
   */
  public function testRuleUuidIsPassedToConstraint(): void {
    $rules = FieldValidationRuleSet::load('user_user')
      ->getFieldValidationRules();

    $text_options = $rules->get($this->textRuleUuid)->getConstraintOptions();
    $this->assertSame($this->textRuleUuid, $text_options['ruleUuid'] ?? NULL);

    $image_options = $rules->get($this->imageRuleUuid)->getConstraintOptions();
    $this->assertSame($this->imageRuleUuid, $image_options['ruleUuid'] ?? NULL);

    $audio_options = $rules->get($this->audioRuleUuid)->getConstraintOptions();
    $this->assertSame($this->audioRuleUuid, $audio_options['ruleUuid'] ?? NULL);

    $moderation_options = $rules->get($this->moderationRuleUuid)
      ->getConstraintOptions();
    $this->assertArrayNotHasKey('ruleUuid', $moderation_options);

    // The option must be accepted by the constraints.
    $constraint = new AiTextConstraint($text_options);
    $this->assertSame($this->textRuleUuid, $constraint->ruleUuid);
    $image_constraint = new AiImageConstraint($image_options);
    $this->assertSame($this->imageRuleUuid, $image_constraint->ruleUuid);
    $audio_constraint = new AiAudioConstraint($audio_options);
    $this->assertSame($this->audioRuleUuid, $audio_constraint->ruleUuid);
  }

  /**
   * Tests request tags resolve only a known, unique rule.
   */
  public function testResolveConsumerId(): void {
    $this->assertSame(
      'validation:' . $this->textRuleUuid,
      $this->resolve(AiValidationsRequestTags::forRule($this->textRuleUuid)),
    );
    $this->assertNull($this->resolve(['ai_validations']));
    $this->assertNull(
      $this->resolve(AiValidationsRequestTags::forRule('missing-rule')),
    );
    $this->assertNull(
      $this->resolve(AiValidationsRequestTags::forRule($this->moderationRuleUuid)),
    );
    $this->assertNull($this->resolve([
      'ai_validations',
      'ai_validations:' . $this->textRuleUuid,
      'ai_validations:' . $this->imageRuleUuid,
    ]));
  }

  /**
   * Tests a new rule set is found after the UUID map cache is built.
   */
  public function testResolveFindsRuleAddedAfterMapCache(): void {
    $this->assertSame(
      'validation:' . $this->textRuleUuid,
      $this->resolve(AiValidationsRequestTags::forRule($this->textRuleUuid)),
    );

    $rule_set = FieldValidationRuleSet::create([
      'name' => 'user_user_extra',
      'label' => 'Extra user rules',
      'entity_type' => 'user',
      'bundle' => 'user',
    ]);
    $extra_uuid = $rule_set->addFieldValidationRule([
      'id' => 'ai_text_prompt_constraint_rule',
      'title' => 'Extra name rule',
      'weight' => 0,
      'field_name' => 'name',
      'column' => 'value',
      'error_message' => '',
      'data' => [
        'prompt' => self::PROMPT,
        'provider' => self::OPTION,
        'message' => 'Not valid.',
      ],
    ]);
    $rule_set->save();

    $this->plugin = $this->container
      ->get('plugin.manager.ai_context_consumer_type')
      ->createInstance(AiContextConsumerTypeValidation::PLUGIN_ID);

    $this->assertSame(
      'validation:' . $extra_uuid,
      $this->resolve(AiValidationsRequestTags::forRule($extra_uuid)),
    );
    $this->assertSame(
      'validation:' . $this->textRuleUuid,
      $this->resolve(AiValidationsRequestTags::forRule($this->textRuleUuid)),
    );
    $this->assertSame([
      'route_name' => 'entity.field_validation_rule_set.edit_form',
      'route_parameters' => [
        'field_validation_rule_set' => 'user_user_extra',
      ],
    ], $this->plugin->getLabelRoute($extra_uuid));
  }

  /**
   * Tests the router keeps agent and assistant requests off this type.
   *
   * The plugin has no excluded-tag check of its own; the CCC router
   * returns no routing types for those tags, so resolveConsumerId() is
   * never asked about them.
   */
  public function testRouterNeverRoutesAgentAndAssistantTagsHere(): void {
    $router = $this->container->get('ai_context.consumer_router');
    $rule_tags = AiValidationsRequestTags::forRule($this->textRuleUuid);

    foreach ([
      ['ai_agents'],
      ['ai_assistant_api'],
      array_merge(['ai_agents'], $rule_tags),
      array_merge(['ai_assistant_api'], $rule_tags),
    ] as $tags) {
      $result = $router->routeRequest(new AiContextProviderRequestContext(
        $tags,
        'chat',
        'classifier_test',
      ));
      $this->assertNull($result->getConsumerId(), implode(',', $tags));
      $this->assertNotSame(
        AiContextConsumerRouteResult::RESOLVED,
        $result->getStatus(),
        implode(',', $tags),
      );
    }

    $result = $router->routeRequest(new AiContextProviderRequestContext(
      $rule_tags,
      'chat',
      'classifier_test',
    ));
    $this->assertSame(AiContextConsumerRouteResult::RESOLVED, $result->getStatus());
    $this->assertSame(
      'validation:' . $this->textRuleUuid,
      $result->getConsumerId()?->toString(),
    );
  }

  /**
   * Tests pushed context reaches the validation system prompt.
   */
  public function testContextIsPushedIntoValidationPrompt(): void {
    $this->enablePush($this->textRuleUuid);
    $last = $this->validateText($this->textRuleUuid);
    $this->assertPromptHasPushedContext($last, $this->textRuleUuid);
    // Context is appended after the prompt's format instruction, so the
    // reminder in the user message is the last instruction the model reads.
    $this->assertStringStartsWith('some text', (string) $last['user_text']);
    $this->assertStringEndsWith(self::BOOLEAN_REMINDER, (string) $last['user_text']);
  }

  /**
   * Tests pushed context reaches the image validation system prompt.
   */
  public function testContextIsPushedIntoImageValidationPrompt(): void {
    $this->enablePush($this->imageRuleUuid);
    $this->assertPromptHasPushedContext(
      $this->validateImage($this->imageRuleUuid),
      $this->imageRuleUuid,
    );
  }

  /**
   * Tests context, tags and entity_context reach the image warning tier.
   *
   * With a warning message set, the image validator uses the ternary
   * XSAFE/XWARN/XBLOCK protocol, which shares the request construction with
   * the boolean protocol.
   */
  public function testContextIsPushedIntoTernaryImageValidationPrompt(): void {
    $this->enablePush($this->imageRuleUuid);
    $user = User::load($this->container->get('current_user')->id());
    $this->assertInstanceOf(User::class, $user);

    $last = $this->validateImage($this->imageRuleUuid, 'Borderline.', $user);
    $this->assertPromptHasPushedContext($last, $this->imageRuleUuid);
    // The ternary path sends only the prompt, not the violation message.
    $this->assertStringNotContainsString('Not valid.', $last['system_prompt']);
    $this->assertStringEndsWith(self::TERNARY_REMINDER, (string) $last['user_text']);

    $probe = new ChatInput([new ChatMessage('user', 'metadata-probe')]);
    if (AiValidationsEntityContext::inputSupportsRequestMetadata($probe)) {
      $this->assertSame([
        'entity_type' => 'user',
        'bundle' => 'user',
        'id' => (string) $user->id(),
      ], $last['entity_context']);
    }
  }

  /**
   * Tests pushed context reaches the audio validation system prompt.
   */
  public function testContextIsPushedIntoAudioValidationPrompt(): void {
    $this->enablePush($this->audioRuleUuid);
    $this->assertPromptHasPushedContext(
      $this->validateAudio($this->audioRuleUuid),
      $this->audioRuleUuid,
    );
  }

  /**
   * Tests push stays off until an admin turns it on.
   */
  public function testNoContextByDefault(): void {
    $last = $this->validateText($this->textRuleUuid);
    $this->assertContains(
      'ai_validations:' . $this->textRuleUuid,
      $last['tags'],
    );
    $this->assertSame(
      self::PROMPT . PHP_EOL . 'Not valid.',
      $last['system_prompt'],
    );
    $this->assertNull($last['entity_context']);
  }

  /**
   * Tests a saved host entity is copied onto the chat input.
   */
  public function testSavedEntityContextIsAttachedToChat(): void {
    $this->skipIfChatInputLacksRequestMetadata();
    $user = User::load($this->container->get('current_user')->id());
    $this->assertInstanceOf(User::class, $user);
    $last = $this->validateText($this->textRuleUuid, $user);
    $this->assertSame([
      'entity_type' => 'user',
      'bundle' => 'user',
      'id' => (string) $user->id(),
    ], $last['entity_context']);
  }

  /**
   * Tests an unsaved host entity sends an empty id.
   */
  public function testUnsavedEntityContextHasEmptyId(): void {
    $this->skipIfChatInputLacksRequestMetadata();
    $user = User::create([
      'name' => 'unsaved-validation',
      'status' => 1,
    ]);
    $last = $this->validateText($this->textRuleUuid, $user);
    $this->assertSame([
      'entity_type' => 'user',
      'bundle' => 'user',
      'id' => '',
    ], $last['entity_context']);
  }

  /**
   * Tests Entity Types matching works without Always include.
   */
  public function testEntityTypeScopeMatchesWithEntityContext(): void {
    $this->skipIfChatInputLacksRequestMetadata();
    $user = User::load($this->container->get('current_user')->id());
    $this->assertInstanceOf(User::class, $user);
    $this->enablePush($this->textRuleUuid, [
      'scope' => [
        [
          'scope_id' => 'entity_type',
          'value' => 'user:user',
        ],
      ],
    ], [
      'always_include' => [],
      'selection_mode' => 'relevant',
    ]);

    $without = $this->validateText($this->textRuleUuid);
    $this->assertStringNotContainsString(
      self::CONTEXT_MARKER,
      (string) $without['system_prompt'],
    );
    $this->assertNull($without['entity_context']);

    $with = $this->validateText($this->textRuleUuid, $user);
    $this->assertPromptHasPushedContext($with, $this->textRuleUuid);
    $this->assertSame('user', $with['entity_context']['entity_type']);
    $this->assertSame((string) $user->id(), $with['entity_context']['id']);
  }

  /**
   * Skips tests that need ChatInput request metadata.
   *
   * AI 1.2-1.4 and some 2.x snapshots do not implement
   * get/setRequestMetadataValue(). Entity-scoped CCC matching is a
   * no-op on those versions; Always include still works.
   */
  private function skipIfChatInputLacksRequestMetadata(): void {
    $input = new ChatInput([
      new ChatMessage('user', 'metadata-probe'),
    ]);
    if (!AiValidationsEntityContext::inputSupportsRequestMetadata($input)) {
      $this->markTestSkipped(
        'This AI version does not store request metadata on ChatInput.',
      );
    }
  }

  /**
   * Resolves a consumer ID for the given request tags.
   *
   * @param string[] $tags
   *   Provider request tags.
   *
   * @return string|null
   *   The canonical consumer ID, or NULL when declined.
   */
  private function resolve(array $tags): ?string {
    $context = new AiContextProviderRequestContext(
      $tags,
      'chat',
      'classifier_test',
    );
    return $this->plugin->resolveConsumerId($context)?->toString();
  }

  /**
   * Runs the text validator for a rule and returns the recorded chat call.
   *
   * @param string $ruleUuid
   *   The rule UUID to put on the constraint.
   * @param \Drupal\Core\Entity\ContentEntityInterface|null $entity
   *   Optional host entity for entity_context. NULL leaves it unset.
   *
   * @return array{system_prompt: string, user_text: string, tags: string[], entity_context: mixed}
   *   The recorded chat call.
   */
  private function validateText(
    string $ruleUuid,
    ?ContentEntityInterface $entity = NULL,
  ): array {
    $constraint = new AiTextConstraint();
    $constraint->provider = self::OPTION;
    $constraint->prompt = self::PROMPT;
    $constraint->message = 'Not valid.';
    $constraint->ruleUuid = $ruleUuid;
    return $this->runValidator(
      AiTextConstraintValidator::class,
      $constraint,
      'some text',
      $entity,
    );
  }

  /**
   * Runs the image validator for a rule and returns the recorded chat call.
   *
   * @param string $ruleUuid
   *   The rule UUID to put on the constraint.
   * @param string $warningMessage
   *   A warning message. Non-empty switches to the ternary protocol.
   * @param \Drupal\Core\Entity\ContentEntityInterface|null $entity
   *   Optional host entity for entity_context. NULL leaves it unset.
   *
   * @return array{system_prompt: string, user_text: string, tags: string[], entity_context: mixed}
   *   The recorded chat call.
   */
  private function validateImage(
    string $ruleUuid,
    string $warningMessage = '',
    ?ContentEntityInterface $entity = NULL,
  ): array {
    $constraint = new AiImageConstraint();
    $constraint->provider = self::OPTION;
    $constraint->prompt = self::PROMPT;
    $constraint->message = 'Not valid.';
    $constraint->warningMessage = $warningMessage;
    $constraint->ruleUuid = $ruleUuid;
    return $this->runValidator(
      AiImageConstraintValidator::class,
      $constraint,
      $this->createManagedFile('validation-image.gif'),
      $entity,
    );
  }

  /**
   * Runs the audio validator for a rule and returns the recorded chat call.
   *
   * @param string $ruleUuid
   *   The rule UUID to put on the constraint.
   *
   * @return array{system_prompt: string, user_text: string, tags: string[], entity_context: mixed}
   *   The recorded chat call.
   */
  private function validateAudio(string $ruleUuid): array {
    $constraint = new AiAudioConstraint();
    $constraint->provider = self::OPTION;
    $constraint->chat_provider = self::OPTION;
    $constraint->prompt = self::PROMPT;
    $constraint->message = 'Not valid.';
    $constraint->ruleUuid = $ruleUuid;
    return $this->runValidator(
      AiAudioConstraintValidator::class,
      $constraint,
      $this->createManagedFile('validation-audio.gif'),
    );
  }

  /**
   * Runs a constraint validator and returns the recorded chat call.
   *
   * @param string $validatorClass
   *   The validator class.
   * @param \Symfony\Component\Validator\Constraint $constraint
   *   The constraint.
   * @param mixed $data
   *   The value to validate.
   * @param \Drupal\Core\Entity\ContentEntityInterface|null $entity
   *   Optional host entity exposed as the validator root.
   *
   * @return array{system_prompt: string, user_text: string, tags: string[], entity_context: mixed}
   *   The recorded chat call.
   */
  private function runValidator(
    string $validatorClass,
    Constraint $constraint,
    mixed $data,
    ?ContentEntityInterface $entity = NULL,
  ): array {
    $this->container->get('state')
      ->set(ClassifierTestProvider::STATE_CHAT_RESPONSE, 'XTRUE');
    $this->container->get('state')
      ->delete(ClassifierTestProvider::STATE_LAST_CHAT);

    $context = $this->createMock(ExecutionContextInterface::class);
    $context->expects($this->never())->method('addViolation');
    $context->method('getRoot')->willReturn($entity);
    $context->method('getObject')->willReturn(NULL);
    $validator = $validatorClass::create($this->container);
    $validator->initialize($context);
    $validator->validate($data, $constraint);

    $last = $this->container->get('state')
      ->get(ClassifierTestProvider::STATE_LAST_CHAT);
    $this->assertIsArray($last);
    return $last;
  }

  /**
   * Creates a published context item and turns push on for a rule.
   *
   * @param string $ruleUuid
   *   The rule UUID.
   * @param array<string, mixed> $itemValues
   *   Extra context item values, such as scope.
   * @param array<string, mixed> $consumerValues
   *   Extra consumer row values. Use always_include => [] to rely on
   *   scope matching instead of Always include.
   */
  private function enablePush(
    string $ruleUuid,
    array $itemValues = [],
    array $consumerValues = [],
  ): void {
    $item = AiContextItem::create(array_merge([
      'uid' => $this->container->get('current_user')->id(),
      'label' => 'Validation marker',
      'status' => 1,
      'moderation_state' => 'published',
      'content' => self::CONTEXT_MARKER,
    ], $itemValues));
    $item->save();
    $this->writeConsumers([
      array_merge([
        'consumer_id' => 'validation:' . $ruleUuid,
        'type' => AiContextConsumerTypeValidation::PLUGIN_ID,
        'push_enabled' => TRUE,
        'always_include' => [(string) $item->id()],
        'selection_mode' => 'minimal',
      ], $consumerValues),
    ]);
  }

  /**
   * Asserts the last chat call was tagged and received pushed context.
   *
   * @param array{system_prompt: string, user_text: string, tags: string[], entity_context: mixed} $last
   *   The recorded chat call.
   * @param string $ruleUuid
   *   The rule UUID expected on the instance tag.
   */
  private function assertPromptHasPushedContext(array $last, string $ruleUuid): void {
    $this->assertContains('ai_validations:' . $ruleUuid, $last['tags']);
    $this->assertStringStartsWith(self::PROMPT, $last['system_prompt']);
    $this->assertStringContainsString(
      self::CONTEXT_MARKER,
      $last['system_prompt'],
    );
  }

  /**
   * Creates a managed file with a tiny GIF payload.
   *
   * @param string $filename
   *   The public:// filename.
   *
   * @return int
   *   The file entity ID.
   */
  private function createManagedFile(string $filename): int {
    $uri = 'public://' . $filename;
    file_put_contents($uri, self::VALID_GIF);
    $file = File::create([
      'uri' => $uri,
      'filename' => $filename,
      'status' => 1,
    ]);
    $file->save();
    return (int) $file->id();
  }

  /**
   * Writes consumer override rows.
   *
   * @param array<int, array<string, mixed>> $rows
   *   Consumer rows.
   */
  private function writeConsumers(array $rows): void {
    $this->container->get('config.storage')->write(
      'ai_context.consumers',
      ['consumers' => $rows],
    );
    $this->container->get('config.factory')->reset('ai_context.consumers');
  }

}
