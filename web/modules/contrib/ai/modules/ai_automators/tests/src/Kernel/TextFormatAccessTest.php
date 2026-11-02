<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_automators\Kernel;

use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\ai_automators\AiAutomatorRuleRunner;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\filter\Entity\FilterFormat;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Log\AbstractLogger;

/**
 * Tests that text automators respect access to the configured text format.
 */
#[Group('ai_automators')]
#[RunTestsInSeparateProcesses]
class TextFormatAccessTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'file',
    'image',
    'media',
    'node',
    'options',
    'text',
    'token',
    'filter',
    'key',
    'ai',
    'ai_automators',
    'field_widget_actions',
  ];

  /**
   * The logged warnings.
   */
  protected array $warnings = [];

  /**
   * The test node.
   */
  protected Node $node;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installConfig(['system', 'field', 'node', 'filter']);

    FilterFormat::create(['format' => 'basic_html', 'name' => 'Basic HTML'])->save();
    FilterFormat::create(['format' => 'full_html', 'name' => 'Full HTML'])->save();

    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    FieldStorageConfig::create([
      'field_name' => 'field_output',
      'entity_type' => 'node',
      'type' => 'text_long',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_output',
      'entity_type' => 'node',
      'bundle' => 'article',
    ])->save();
    $this->node = Node::create(['type' => 'article', 'title' => 'Test']);

    $warnings = &$this->warnings;
    $this->container->get('logger.factory')->addLogger(new class($warnings) extends AbstractLogger {

      public function __construct(protected array &$warnings) {}

      /**
       * {@inheritdoc}
       */
      public function log($level, string|\Stringable $message, array $context = []): void {
        if ($level === RfcLogLevel::WARNING && in_array($context['channel'] ?? '', ['ai_automators', 'ai_automator'], TRUE)) {
          $this->warnings[] = strtr((string) $message, array_filter($context, 'is_scalar'));
        }
      }

    });

    // Reserve uid 1 so the test users are not the super user.
    $this->createUser();
  }

  /**
   * Provides the automator plugins with a text format setting.
   */
  public static function pluginProvider(): array {
    return [
      ['llm_text'],
      ['llm_text_long'],
      ['llm_text_with_summary'],
      ['llm_simple_text_long'],
      ['llm_simple_text_with_summary'],
      ['summarize_to_text_long'],
      ['llm_video_to_text_long'],
    ];
  }

  /**
   * Tests that video to HTML stops without access to the field's format.
   */
  public function testVideoToHtmlSkippedWithoutFormatAccess(): void {
    $this->setAllowedFormats(['full_html']);
    $this->setUpCurrentUser([], ['use text format basic_html']);
    $plugin = $this->container->get('plugin.manager.ai_automator')->createInstance('llm_video_to_image');

    // No base field or provider is configured, so going on would fail.
    $values = $plugin->generate($this->node, $this->node->getFieldDefinition('field_output'), []);

    $this->assertSame([], $values);
    $this->assertCount(1, $this->warnings);
    $this->assertStringContainsString('may not use text format full_html', $this->warnings[0]);
  }

  /**
   * Tests that queued automators run as the user who queued them.
   */
  public function testQueuedAutomatorRunsAsQueueingUser(): void {
    $author = $this->createUser(['use text format full_html']);
    $blocked = $this->createUser(['use text format full_html']);
    $blocked->block()->save();
    $admin = $this->createUser([], NULL, TRUE);
    $this->stubRuleRunner();
    $process = function (array $item, AccountInterface $runner): array {
      $this->setCurrentUser($runner);
      $this->container->get('plugin.manager.queue_worker')->createInstance('ai_automator_field_modifier')->processItem($item);
      $this->assertSame((int) $runner->id(), (int) $this->container->get('current_user')->id());
      $ran = $this->ruleRunnerUsers;
      $this->ruleRunnerUsers = [];
      return $ran;
    };

    // Cron runs as the anonymous user, the item as the user who queued it.
    $this->setCurrentUser($author);
    $item = $this->queueItem();
    $this->assertSame((int) $author->id(), (int) $item['uid']);
    $this->assertSame([(int) $author->id()], $process($item, new AnonymousUserSession()));

    // Without a user it runs as anonymous, not as the owner of the entity, even
    // when an admin processes the queue.
    $this->node->setOwner($author)->save();
    $this->setCurrentUser(new AnonymousUserSession());
    $item = $this->queueItem();
    $this->assertNull($item['uid']);
    $this->assertSame([0], $process($item, $admin));
    $this->assertSame([], $this->warnings);

    // With a fallback user set, it runs as that user.
    $fallback = $this->createUser(['use text format basic_html']);
    $this->container->get('ai_automator.fallback_user')->setUid((int) $fallback->id());
    $this->setCurrentUser(new AnonymousUserSession());
    $item = $this->queueItem();
    $this->assertSame((int) $fallback->id(), (int) $item['uid']);
    $this->assertSame([(int) $fallback->id()], $process($item, $admin));

    // A logged-in user is still used over the fallback user.
    $this->setCurrentUser($author);
    $item = $this->queueItem();
    $this->assertSame((int) $author->id(), (int) $item['uid']);

    // A blocked fallback user is not used.
    $fallback->block()->save();
    $this->setCurrentUser(new AnonymousUserSession());
    $item = $this->queueItem();
    $this->assertNull($item['uid']);
    $this->assertCount(1, $this->warnings);
    $this->assertStringContainsString('fallback user', $this->warnings[0]);
    $this->container->get('ai_automator.fallback_user')->setUid(NULL);
    $this->warnings = [];

    // An item queued by a user who is now blocked is skipped.
    $this->setCurrentUser($blocked);
    $item = $this->queueItem();
    $this->assertSame([], $process($item, $admin));
    $this->assertCount(1, $this->warnings);
    $this->assertStringContainsString('is missing or blocked', $this->warnings[0]);

    // An item queued before the user was stored is skipped.
    $item = $this->queueItem();
    unset($item['uid']);
    $this->assertSame([], $process($item, $admin));
    $this->assertCount(2, $this->warnings);
    $this->assertStringContainsString('the queue item has no user', $this->warnings[1]);
  }

  /**
   * Tests that direct automators without a user run as the fallback user.
   */
  public function testDirectAutomatorRunsAsFallbackUser(): void {
    $author = $this->createUser(['use text format full_html']);
    $fallback = $this->createUser(['use text format basic_html']);
    $this->stubRuleRunner();
    $run = function (AccountInterface $account): array {
      $this->setCurrentUser($account);
      $this->container->get('plugin.manager.ai_processor')->createInstance('direct')
        ->modify($this->node, $this->node->getFieldDefinition('field_output'), []);
      $this->assertSame((int) $account->id(), (int) $this->container->get('current_user')->id());
      $ran = $this->ruleRunnerUsers;
      $this->ruleRunnerUsers = [];
      return $ran;
    };

    // Without a fallback user, it runs as anonymous, not as the owner.
    $this->node->setOwner($author);
    $this->assertSame([0], $run(new AnonymousUserSession()));

    $this->container->get('ai_automator.fallback_user')->setUid((int) $fallback->id());
    $this->assertSame([(int) $fallback->id()], $run(new AnonymousUserSession()));
    // A logged-in user is not switched.
    $this->assertSame([(int) $author->id()], $run($author));
  }

  /**
   * Tests that only user administrators can set the fallback user.
   */
  public function testFallbackUserSettingNeedsAdministerUsers(): void {
    $this->installConfig(['ai_automators']);
    $build = function (array $permissions): bool {
      $this->setCurrentUser($this->createUser($permissions));
      $form = $this->container->get('form_builder')->getForm('Drupal\ai_automators\Form\AiAutomatorsSettingsForm');
      return $form['fallback_user']['#access'];
    };
    $this->assertFalse($build(['administer ai_automator']));
    $this->assertTrue($build(['administer ai_automator', 'administer users']));
  }

  /**
   * Tests the status report entry for the fallback user.
   */
  public function testFallbackUserRequirement(): void {
    $this->container->get('router.builder')->rebuild();
    $this->container->get('module_handler')->loadInclude('ai_automators', 'install');
    $fallbackUser = $this->container->get('ai_automator.fallback_user');
    $severity = function (): int {
      return ai_automators_requirements('runtime')['ai_automators_fallback_user']['severity'];
    };

    // Empty runs as anonymous, but asks for a dedicated account.
    $this->assertSame(REQUIREMENT_WARNING, $severity());

    $user = $this->createUser(['use text format basic_html']);
    $fallbackUser->setUid((int) $user->id());
    $this->assertSame(REQUIREMENT_OK, $severity());

    $fallbackUser->setUid((int) $this->createUser([], NULL, TRUE)->id());
    $this->assertSame(REQUIREMENT_WARNING, $severity());

    $user->block()->save();
    $fallbackUser->setUid((int) $user->id());
    $this->assertSame(REQUIREMENT_WARNING, $severity());
  }

  /**
   * The users the stub rule runner ran as.
   */
  protected array $ruleRunnerUsers = [];

  /**
   * Replaces the rule runner with a stub that records the current user.
   */
  protected function stubRuleRunner(): void {
    $runner = $this->createMock(AiAutomatorRuleRunner::class);
    $runner->method('generateResponse')->willReturnCallback(function ($entity) {
      $this->ruleRunnerUsers[] = (int) $this->container->get('current_user')->id();
      return $entity;
    });
    $this->container->set('ai_automator.rule_runner', $runner);
  }

  /**
   * Queues the test field for the saved test node and claims the item.
   */
  protected function queueItem(): array {
    if ($this->node->isNew()) {
      $statusField = $this->container->get('ai_automator.status_field');
      // Without an automator config the status field would be removed again.
      $this->installSchema('node', ['node_access']);
      $this->container->get('state')->set('ai_automators.importing', TRUE);
      (fn() => $this->addStatusField('node', 'article'))->call($statusField);
      $this->container->get('state')->set('ai_automators.importing', FALSE);
      $this->container->get('entity_field.manager')->clearCachedFieldDefinitions();
      $this->node = Node::create(['type' => 'article', 'title' => 'Test']);
      $this->node->save();
    }
    $processor = $this->container->get('plugin.manager.ai_processor')->createInstance('queue');
    $processor->modify($this->node, $this->node->getFieldDefinition('field_output'), [
      'field_name' => 'field_output',
      'queue_allow_requeue' => TRUE,
    ]);
    $queue = $this->container->get('queue')->get('ai_automator_field_modifier');
    $claimed = $queue->claimItem();
    $queue->deleteItem($claimed);
    return $claimed->data;
  }

  /**
   * Tests that generation stops before the request without format access.
   */
  #[DataProvider('pluginProvider')]
  public function testGenerateSkippedWithoutFormatAccess(string $pluginId): void {
    $this->setAllowedFormats(['full_html']);
    $this->setUpCurrentUser([], ['use text format basic_html']);
    $plugin = $this->container->get('plugin.manager.ai_automator')->createInstance($pluginId);

    // No provider is configured, so reaching the request would fail.
    $values = $plugin->generate($this->node, $this->node->getFieldDefinition('field_output'), [
      'use_text_format' => 'full_html',
    ]);

    $this->assertSame([], $values);
    $this->assertCount(1, $this->warnings);
    $this->assertStringContainsString('may not use text format full_html', $this->warnings[0]);
  }

  /**
   * Tests the format access check itself.
   */
  public function testFormatAccessCheck(): void {
    $plugin = $this->container->get('plugin.manager.ai_automator')->createInstance('llm_text_long');
    $check = fn(array $config) => (fn() => $this->currentUserCanUseConfiguredTextFormat(...func_get_args()))
      ->call($plugin, $this->node, $this->node->getFieldDefinition('field_output'), $config);

    $this->setUpCurrentUser([], ['use text format full_html']);
    $this->assertTrue($check(['use_text_format' => 'full_html']));
    $this->assertTrue($check(['use_text_format' => '']));
    $this->assertTrue($check([]));
    $this->assertSame([], $this->warnings);

    // Another format of the field may be used instead.
    $this->assertTrue($check(['use_text_format' => 'basic_html']));
    $this->assertSame([], $this->warnings);

    $this->setAllowedFormats(['basic_html']);
    $this->assertFalse($check(['use_text_format' => 'basic_html']));
    $this->assertFalse($check(['use_text_format' => 'does_not_exist']));
    $this->assertCount(2, $this->warnings);
  }

  /**
   * Tests that output is stored in a format the user may use instead.
   */
  #[DataProvider('pluginProvider')]
  public function testStoredInFallbackFormat(string $pluginId): void {
    $this->setUpCurrentUser([], ['use text format basic_html']);
    $plugin = $this->container->get('plugin.manager.ai_automator')->createInstance($pluginId);
    $fieldDefinition = $this->node->getFieldDefinition('field_output');
    $config = ['use_text_format' => 'full_html'];

    $check = new \ReflectionMethod($plugin, 'currentUserCanUseConfiguredTextFormat');
    $this->assertTrue($check->invoke($plugin, $this->node, $fieldDefinition, $config));
    $plugin->storeValues($this->node, ['<p>Output</p>'], $fieldDefinition, $config);

    $this->assertSame('basic_html', $this->node->get('field_output')->format);
    $this->assertSame([], $this->warnings);
  }

  /**
   * Sets the allowed text formats of the output field.
   */
  protected function setAllowedFormats(array $formats): void {
    FieldConfig::loadByName('node', 'article', 'field_output')->setSetting('allowed_formats', $formats)->save();
    $this->container->get('entity_field.manager')->clearCachedFieldDefinitions();
    $this->node = Node::create(['type' => 'article', 'title' => 'Test']);
  }

}
