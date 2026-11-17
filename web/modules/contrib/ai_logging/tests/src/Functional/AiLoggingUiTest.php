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
      // A check on the follow-up, logged before the agent's next turn.
      [['ai_agents_thread_first', 'classifier'], 1001, "user\nA follow-up?\n", '{"safe":true}', NULL],
      // The agent's next turn sends its last prompt again, plus a follow-up.
      [
        ['ai_agents_thread_first', 'ai_agents_prompt_agent'],
        1002,
        "user\nFirst question?\nassistant\nA reply.\nuser\nA follow-up?\n",
        "## Second\n\nThe **second** answer. <script>alert(1)</script>",
        NULL,
      ],
      [
        ['ai_agents_thread_second', 'ai_agents_prompt_agent'],
        2000,
        "user\nSecond question?\n",
        '{"tool_calls":[{"name":"search","arguments":{"query":"Second"}}]}',
        NULL,
      ],
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
    // The contents list the questions, calls and final answers in order,
    // each linking to it further down.
    $contents = $this->getSession()->getPage()->findAll('css', '.ai-logging-contents__item');
    $this->assertSame([
      'call', 'question', 'call', 'answer', 'call', 'question', 'call', 'answer',
    ], array_map(fn ($item) => preg_replace('/.*ai-logging-contents__item--(\w+).*/', '$1', $item->getAttribute('class')), $contents));
    $this->assertStringContainsString('Structured result', $contents[0]->getText());
    $this->assertStringContainsString('First question?', $contents[1]->getText());
    $this->assertStringContainsString('Text response · ai_agents_prompt_agent', $contents[2]->getText());
    $this->assertStringNotContainsString('ai_agents_thread_first', $contents[2]->getText());
    $this->assertStringContainsString('Answer: The final answer.', $contents[3]->getText());
    // Each answer straight after the call it came from, before the check.
    $this->assertStringContainsString('Structured result · classifier', $contents[4]->getText());
    $this->assertStringContainsString('A follow-up?', $contents[5]->getText());
    $this->assertStringContainsString('Answer: ## Second The **second** answer.', $contents[7]->getText());
    $continued = current($storage->loadByProperties(['created' => 1002]));
    $this->assertSession()->linkByHrefExists('#ai-log-' . $continued->id());
    $this->assertSession()->linkByHrefExists('#ai-log-answer-' . $continued->id());
    $this->assertSession()->buttonExists('Expand all');
    $this->assertSession()->buttonExists('Collapse all');

    // Four calls, plus the collapsed full prompt of the continued turn.
    $this->assertSession()->elementsCount('css', 'details', 5);
    // Every call starts collapsed, its summary saying what it was for.
    $this->assertSession()->elementsCount('css', '.ai-logging-thread > details[open]', 0);
    $summary = '#ai-log-' . $continued->id() . ' summary';
    $this->assertSession()->elementTextContains('css', $summary, '4 · Text response');
    $this->assertSession()->elementTextContains('css', $summary . ' .ai-logging-badge', 'ai_agents_prompt_agent');
    $this->assertSession()->elementTextContains('css', $summary . ' .ai-logging-call__preview', 'The **second** answer.');
    // Each question is highlighted where it was first asked, and the last
    // text response of its turn after its calls, as its answer. The first
    // call isn't tagged as the agent's, so its prompt isn't taken for a
    // question.
    $first = $this->getSession()->getPage()->find('css', '.ai-logging-thread > *');
    $this->assertSame('details', $first->getTagName());
    $questions = $this->getSession()->getPage()->findAll('css', 'section.ai-logging-highlight--question');
    $this->assertCount(2, $questions);
    $this->assertStringContainsString('First question?', $questions[0]->getText());
    $this->assertStringContainsString('A follow-up?', $questions[1]->getText());
    $answers = $this->getSession()->getPage()->findAll('css', 'section.ai-logging-highlight--answer');
    $this->assertCount(2, $answers);
    $this->assertStringContainsString('Answer · ', $answers[0]->getText());
    $this->assertStringContainsString('from call 2', $answers[0]->getText());
    $this->assertStringContainsString('The final answer.', $answers[0]->getText());
    // An answer in Markdown is rendered, with any HTML in it escaped.
    $this->assertStringContainsString('The second answer.', $answers[1]->getText());
    $this->assertNotNull($answers[1]->find('css', '.ai-logging-highlight__text--markdown h2'));
    $this->assertSame('second', $answers[1]->find('css', 'strong')->getText());
    $this->assertStringContainsString('&lt;script&gt;', $answers[1]->getHtml());
    $this->assertNull($answers[1]->find('css', 'script'));
    $this->assertNull($answers[0]->find('css', '.ai-logging-highlight__text--markdown'));
    $this->assertSession()->pageTextContains('New in this call');
    $this->assertSession()->pageTextContains('Full prompt');
    $this->assertSession()->pageTextContains('"safe": true');
    $this->assertSession()->linkExists('View log entry');

    // A turn that only called tools shows the calls in its summary, and no
    // answer.
    $this->drupalGet('admin/config/ai/logging/threads/second');
    $this->assertSession()->elementTextContains('css', 'section.ai-logging-highlight--question', 'Second question?');
    $this->assertSession()->elementTextContains('css', 'summary', '1 · Tool calls');
    $this->assertSession()->elementTextContains('css', 'summary .ai-logging-call__tool', 'search query: "Second"');
    $this->assertSession()->elementNotExists('css', '.ai-logging-highlight--answer');

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

  /**
   * Tests deleting all logs from the settings page.
   */
  public function testDeleteAllLogs(): void {
    $storage = \Drupal::entityTypeManager()->getStorage('ai_log');
    foreach (['first', 'second'] as $prompt) {
      $storage->create([
        'bundle' => 'generic',
        'operation_type' => 'chat',
        'prompt' => $prompt,
      ])->save();
    }

    // Only users who may administer the logs can delete them.
    $this->drupalLogin($this->drupalCreateUser(['view ai log']));
    $this->drupalGet('admin/config/ai/logging/settings/delete');
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalPlaceBlock('local_actions_block');
    $this->drupalGet('admin/config/ai/logging/collection');
    $this->assertSession()->linkNotExists('Delete all logs');

    // It is offered on the AI Logs list and on the settings page.
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/config/ai/logging/collection');
    $this->assertSession()->elementExists('css', 'a.button--danger[href$="/admin/config/ai/logging/settings/delete"]');
    $this->clickLink('Delete all logs');
    $this->assertSession()->addressEquals('admin/config/ai/logging/settings/delete');
    $this->drupalGet('admin/config/ai/logging/settings');
    $this->clickLink('Delete all logs');
    $this->assertSession()->addressEquals('admin/config/ai/logging/settings/delete');
    $this->assertSession()->pageTextContains('Are you sure you want to delete all AI logs?');
    $this->assertSession()->pageTextContains('This will delete 2 AI logs.');
    $this->assertSession()->elementExists('css', 'input.button--danger[value="Delete all logs"]');

    // Cancelling keeps the logs.
    $this->clickLink('Cancel');
    $this->assertSession()->addressEquals('admin/config/ai/logging/settings');
    $this->assertCount(2, $storage->getQuery()->accessCheck(FALSE)->execute());

    $this->drupalGet('admin/config/ai/logging/settings/delete');
    $this->submitForm([], 'Delete all logs');
    $this->assertSession()->addressEquals('admin/config/ai/logging/settings');
    $this->assertSession()->pageTextContains('Deleted 2 AI logs.');
    $this->assertCount(0, $storage->getQuery()->accessCheck(FALSE)->execute());
  }

  /**
   * Tests deleting one conversation thread.
   */
  public function testDeleteThread(): void {
    $storage = \Drupal::entityTypeManager()->getStorage('ai_log');
    $kept_tags = [['ai_agents_thread_second'], ['unrelated']];
    $deleted_tags = [['ai_assistant_thread_first'], ['ai_agents_thread_first', 'ai_agents_prompt_agent']];
    foreach (array_merge($kept_tags, $deleted_tags) as $tags) {
      $storage->create([
        'bundle' => 'generic',
        'operation_type' => 'chat',
        'tags' => $tags,
      ])->save();
    }
    $this->drupalPlaceBlock('local_actions_block');

    // A user who can only view the logs is not offered the delete action.
    $this->drupalLogin($this->drupalCreateUser(['view ai log']));
    $this->drupalGet('admin/config/ai/logging/threads/first');
    $this->assertSession()->linkNotExists('Delete thread');
    $this->drupalGet('admin/config/ai/logging/threads/first/delete');
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/config/ai/logging/threads/missing/delete');
    $this->assertSession()->statusCodeEquals(404);

    $this->drupalGet('admin/config/ai/logging/threads/first');
    $this->assertSession()->elementExists('css', 'a.button--danger[href$="/admin/config/ai/logging/threads/first/delete"]');
    $this->clickLink('Delete thread');
    $this->assertSession()->addressEquals('admin/config/ai/logging/threads/first/delete');
    $this->assertSession()->pageTextContains('Are you sure you want to delete thread first?');
    // Both prefixes of the thread ID count as the same thread.
    $this->assertSession()->pageTextContains('This will delete all 2 AI logs of this thread.');
    $this->assertSession()->elementExists('css', 'input.button--danger[value="Delete thread"]');
    $this->submitForm([], 'Delete thread');

    $this->assertSession()->addressEquals('admin/config/ai/logging/threads');
    $this->assertSession()->pageTextContains('Deleted 2 AI logs.');
    $this->assertSession()->linkNotExists('first');
    $this->assertSession()->linkExists('second');
    $this->assertCount(2, $storage->getQuery()->accessCheck(FALSE)->execute());

    // Without any thread tag prefix there are no threads to delete.
    $this->config('ai_logging.settings')->set('thread_tag_prefixes', [])->save();
    $this->drupalGet('admin/config/ai/logging/threads/second/delete');
    $this->assertSession()->statusCodeEquals(403);
  }

}
