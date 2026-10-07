<?php

namespace Drupal\Tests\ai_logging\Functional;

use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\Tests\BrowserTestBase;

/**
 * Contains AI Logging UI setup functional tests.
 *
 * @group ai_logging
 */
class AiLoggingUiTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ai',
    'ai_test',
    'ai_logging',
    'block',
    'user',
    'system',
    'views_ui',
    'dblog',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * A user with permission to bypass access content.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->adminUser = $this->drupalCreateUser([
      'access administration pages',
      'administer ai',
      'administer ai providers',
      'administer ai log',
      'view ai log',
    ]);
  }

  /**
   * Set up module.
   */
  public function testBasicSetup(): void {
    $this->drupalLogin($this->adminUser);

    // Keep the indexed content minimal since the MySQL Vector embedding has
    // low accuracy in the tests.
    $this->drupalGet('admin/config/ai/logging/settings');
    $this->submitForm([
      'prompt_logging' => TRUE,
      'prompt_logging_output' => TRUE,
    ], 'Save configuration');

    // Send a test message and get a response.
    $text = 'Can you help me with something?';
    $provider = \Drupal::service('ai.provider')->createInstance('echoai');
    $input = new ChatInput([
      new ChatMessage('user', $text),
    ]);
    $provider->chat($input, 'test');

    $this->drupalGet('admin/config/ai/logging/collection');
    $this->assertSession()->elementTextContains(
      'css',
      '.views-element-container tbody .views-field-prompt',
      $text,
    );
    $this->assertSession()->elementTextContains(
      'css',
      '.views-element-container tbody .views-field-provider',
      'echoai',
    );

    // The exclusion setting is offered and stored.
    $this->drupalGet('admin/config/ai/logging/settings');
    $this->assertSession()->pageTextContains('Exclude automated logging by request tags');
    $this->assertSession()->fieldExists('prompt_logging_excluded_tags');
    $this->submitForm([
      'prompt_logging_excluded_tags' => 'ai_api_explorer',
    ], 'Save configuration');
    $this->assertSame('ai_api_explorer', $this->config('ai_logging.settings')->get('prompt_logging_excluded_tags'));

    // The event subscriber in this process was built before the setting was
    // saved through the browser, so rebuild the container to pick it up.
    $this->rebuildContainer();
    $provider = \Drupal::service('ai.provider')->createInstance('echoai');

    // A request carrying an excluded tag is not logged, while a request
    // without it still is.
    $excluded_text = 'This request must not be logged.';
    $provider->chat(new ChatInput([
      new ChatMessage('user', $excluded_text),
    ]), 'test', ['ai_api_explorer']);
    $logged_text = 'This request must still be logged.';
    $provider->chat(new ChatInput([
      new ChatMessage('user', $logged_text),
    ]), 'test');

    $this->drupalGet('admin/config/ai/logging/collection');
    $this->assertSession()->pageTextNotContains($excluded_text);
    $this->assertSession()->pageTextContains($logged_text);
  }

  /**
   * Tests the AI Logs view's crosslink to the site-wide logs.
   *
   * The crosslink is only shown to users who can access the site-wide log
   * (dblog), since errors AI Logging does not itself capture (e.g.
   * rate-limit responses) may still be recorded there. See #3611898.
   */
  public function testDblogCrosslinkVisibility(): void {
    $user_with_access = $this->drupalCreateUser([
      'view ai log',
      'access site reports',
    ]);
    $user_without_access = $this->drupalCreateUser([
      'view ai log',
    ]);

    $this->drupalLogin($user_with_access);
    $this->drupalGet('admin/config/ai/logging/collection');
    $this->assertSession()->pageTextContains('Further logs and errors may be found in the site-wide logs.');
    $this->assertSession()->linkByHrefExists('/admin/reports/dblog');

    $this->drupalLogin($user_without_access);
    $this->drupalGet('admin/config/ai/logging/collection');
    $this->assertSession()->pageTextNotContains('Further logs and errors may be found in the site-wide logs.');
  }

  /**
   * Tests the conversation thread pages.
   */
  public function testThreadPages(): void {
    $storage = \Drupal::entityTypeManager()->getStorage('ai_log');
    $logs = [
      [['ai_assistant_thread_first'], 1000, "user\nFirst question?\n", '{"safe":true}', 10],
      [
        ['ai_agents_thread_first', 'ai_agents_prompt_agent'],
        1001,
        "user\nFirst question?\nassistant\nA reply.\n",
        'The final answer.',
        15,
      ],
      [['ai_agents_thread_second', 'ai_agents_prompt_agent'], 2000, "user\nSecond question?\n", NULL, NULL],
    ];
    foreach ($logs as [$tags, $created, $prompt, $response_text, $tokens_total]) {
      $storage->create([
        'bundle' => 'generic',
        'operation_type' => 'chat',
        'provider' => 'echoai',
        'model' => 'test',
        'tags' => $tags,
        'created' => $created,
        'prompt' => $prompt,
        'response_text' => $response_text,
        'tokens_total' => $tokens_total,
      ])->save();
    }

    $this->drupalPlaceBlock('local_tasks_block');
    $this->drupalGet('admin/config/ai/logging/threads');
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalLogin($this->drupalCreateUser(['view ai log']));
    $this->drupalGet('admin/config/ai/logging/threads');
    $this->assertSession()->statusCodeEquals(200);
    $rows = $this->getSession()->getPage()->findAll('css', 'table tbody tr');
    $this->assertCount(2, $rows);
    // Most recently active first.
    $this->assertStringContainsString('second', $rows[0]->getText());
    $this->assertStringContainsString('Second question?', $rows[0]->getText());
    $this->assertStringContainsString('n/a', $rows[0]->getText());
    $this->assertStringContainsString('first', $rows[1]->getText());
    $this->assertStringContainsString('First question?', $rows[1]->getText());
    $this->assertStringContainsString('25', $rows[1]->getText());
    $this->assertSession()->linkExists('All logs');
    $this->assertSession()->linkExists('Threads');

    // The tabs are also shown on the AI Logs view.
    $this->drupalGet('admin/config/ai/logging/collection');
    $this->assertSession()->linkExists('All logs');
    $this->assertSession()->linkByHrefExists('/admin/config/ai/logging/threads');
    $this->clickLink('Threads');

    $this->clickLink('first');
    $this->assertSession()->addressEquals('admin/config/ai/logging/threads/first');
    $this->assertSession()->pageTextContains('Thread first');
    $this->assertSession()->elementsCount('css', 'details', 2);
    $this->assertSession()->pageTextContains('Result');
    $this->assertSession()->pageTextContains('"safe": true');
    $this->assertSession()->pageTextContains('Reply');
    $this->assertSession()->pageTextContains('The final answer.');
    $this->assertSession()->linkExists('View log entry');

    $this->drupalGet('admin/config/ai/logging/threads/missing');
    $this->assertSession()->statusCodeEquals(404);

    // Without any thread tag prefix the thread pages are turned off.
    $this->config('ai_logging.settings')->set('thread_tag_prefixes', [])->save();
    $this->drupalGet('admin/config/ai/logging/threads');
    $this->assertSession()->statusCodeEquals(403);
    $this->drupalGet('admin/config/ai/logging/collection');
    $this->assertSession()->linkByHrefNotExists('/admin/config/ai/logging/threads');
  }

  /**
   * Tests saving the conversation thread settings.
   */
  public function testThreadSettings(): void {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/config/ai/logging/settings');
    $this->assertSession()->fieldValueEquals('thread_tag_prefixes', "ai_agents_thread_\nai_assistant_thread_");
    $this->submitForm([
      'thread_tag_prefixes' => " chatbot_thread_ \n\nai_agents_thread_\nchatbot_thread_",
      'thread_primary_tag_pattern' => '',
    ], 'Save configuration');
    $config = $this->config('ai_logging.settings');
    $this->assertSame(['chatbot_thread_', 'ai_agents_thread_'], $config->get('thread_tag_prefixes'));
    $this->assertSame('', $config->get('thread_primary_tag_pattern'));
  }

}
