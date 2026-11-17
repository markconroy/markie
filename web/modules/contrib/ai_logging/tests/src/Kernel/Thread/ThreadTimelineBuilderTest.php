<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_logging\Kernel\Thread;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ai_logging\AiLogInterface;
use Drupal\ai_logging\Thread\ThreadCall;
use Drupal\ai_logging\Thread\ThreadTimelineBuilder;
use Drupal\ai_logging\Thread\ThreadTimelineItem;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests arranging a thread's calls into questions, calls and answers.
 *
 * @group ai_logging
 * @coversDefaultClass \Drupal\ai_logging\Thread\ThreadTimelineBuilder
 */
#[RunTestsInSeparateProcesses]
class ThreadTimelineBuilderTest extends KernelTestBase {

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
   * The builder under test.
   *
   * @var \Drupal\ai_logging\Thread\ThreadTimelineBuilder
   */
  protected ThreadTimelineBuilder $builder;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('ai_log');
    $this->installConfig(['ai_logging']);
    $this->builder = $this->container->get('ai_logging.thread_timeline_builder');
  }

  /**
   * Tests turns, the kind of each call, and where answers go.
   *
   * @covers ::build
   */
  public function testBuild(): void {
    $agent = ['ai_agents_thread_one', 'ai_agents_prompt_agent'];
    $first_prompt = "user\nFirst question?\n";
    $entries = [
      // A check before the agent runs isn't tagged as the agent's, so its
      // prompt isn't taken for a question.
      $this->createLog(['ai_agents_thread_one', 'classifier'], 100, $first_prompt, '{"safe":true}'),
      $this->createLog($agent, 101, $first_prompt, '{"tool_calls":[{"name":"search","arguments":{"query":"x"}}]}'),
      $this->createLog($agent, 105, $first_prompt . "assistant\n[calls search]\ntool (search)\nFound.\n", 'The answer.'),
      // A check on the follow-up, logged before the agent's next turn.
      $this->createLog(['ai_agents_thread_one', 'classifier'], 106, "user\nFollow-up?\n", '{"safe":true}'),
      $this->createLog($agent, 107, $first_prompt . "assistant\nThe answer.\nuser\nFollow-up?\n", ''),
    ];

    $timeline = $this->builder->build($entries, 'one');
    $this->assertSame([
      'call:1',
      'question:2',
      'call:2',
      'call:3',
      'answer:3',
      'call:4',
      'question:5',
      'call:5',
    ], array_map(static fn (ThreadTimelineItem $item) => $item->type . ':' . $item->call->number, $timeline));

    $this->assertSame('First question?', $timeline[1]->text);
    $this->assertSame('The answer.', $timeline[4]->text);
    $this->assertSame('Follow-up?', $timeline[6]->text);
    $this->assertSame('ai-log-answer-' . $entries[2]->id(), $timeline[4]->getElementId());

    $calls = array_map(static fn (ThreadTimelineItem $item) => $item->call, array_values(array_filter($timeline, static fn (ThreadTimelineItem $item) => $item->type === ThreadTimelineItem::CALL)));
    $this->assertSame([
      ThreadCall::KIND_STRUCTURED,
      ThreadCall::KIND_TOOL_CALLS,
      ThreadCall::KIND_TEXT,
      ThreadCall::KIND_STRUCTURED,
      ThreadCall::KIND_NONE,
    ], array_map(static fn (ThreadCall $call) => $call->kind, $calls));
    $this->assertSame([['name' => 'search', 'arguments' => ['query' => 'x']]], $calls[1]->toolCalls);
    $this->assertSame(['classifier'], $calls[0]->purposeTags);
    $this->assertSame(['ai_agents_prompt_agent'], $calls[1]->purposeTags);
    $this->assertSame([0, 1, 5, 6, 7], array_map(static fn (ThreadCall $call) => $call->elapsed, $calls));

    // Each agent call shows what its prompt added to the agent's last one.
    $this->assertNull($calls[1]->newPromptText);
    $this->assertSame("assistant\n[calls search]\ntool (search)\nFound.\n", $calls[2]->newPromptText);
    $this->assertNull($calls[3]->newPromptText, 'A classifier prompt is not compared with the agent prompt.');
  }

  /**
   * Tests that without a primary tag pattern any call can ask a question.
   *
   * @covers ::build
   */
  public function testWithoutPattern(): void {
    $this->config('ai_logging.settings')->set('thread_primary_tag_pattern', '')->save();
    $entries = [
      $this->createLog(['ai_agents_thread_one', 'classifier'], 100, "user\nA question?\n", '{"safe":true}'),
    ];
    $timeline = $this->builder->build($entries, 'one');
    $this->assertSame([ThreadTimelineItem::QUESTION, ThreadTimelineItem::CALL], array_map(static fn (ThreadTimelineItem $item) => $item->type, $timeline));
  }

  /**
   * Creates a test log.
   *
   * @param string[] $tags
   *   The request tags.
   * @param int $created
   *   The created timestamp.
   * @param string $prompt
   *   The prompt.
   * @param string $response_text
   *   The reply text.
   *
   * @return \Drupal\ai_logging\AiLogInterface
   *   The saved log.
   */
  protected function createLog(array $tags, int $created, string $prompt, string $response_text): AiLogInterface {
    $log = $this->container->get('entity_type.manager')->getStorage('ai_log')->create([
      'bundle' => 'generic',
      'operation_type' => 'chat',
      'provider' => 'test_provider',
      'model' => 'test_model',
      'tags' => $tags,
      'prompt' => $prompt,
      'response_text' => $response_text,
      'created' => $created,
    ]);
    $log->save();
    return $log;
  }

}
