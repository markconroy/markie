<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_logging\Kernel;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\ai_logging\Drush\Commands\AiLogCommands;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the ai:logs Drush command logic.
 *
 * @group ai_logging
 * @coversDefaultClass \Drupal\ai_logging\Drush\Commands\AiLogCommands
 */
#[RunTestsInSeparateProcesses]
class AiLogCommandsTest extends KernelTestBase {

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
   * The command under test.
   *
   * @var \Drupal\ai_logging\Drush\Commands\AiLogCommands
   */
  protected AiLogCommands $command;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('ai_log');
    $this->installConfig(['ai_logging']);

    $this->command = new AiLogCommands(
      $this->container->get('entity_type.manager'),
      $this->container->get('ai_logging.thread_repository'),
    );
  }

  /**
   * Tests list mode returns RowsOfFields with correct data.
   *
   * @covers ::logs
   */
  public function testListMode(): void {
    $this->createTestLog('chat', 'anthropic', 'claude-3.5-sonnet', ['ai_agents']);
    $this->createTestLog('chat', 'openai', 'gpt-4', ['ai_chatbot']);

    $result = $this->command->logs($this->buildOptions());

    $this->assertInstanceOf(RowsOfFields::class, $result);
    $data = $result->getArrayCopy();
    $this->assertCount(2, $data);
    // Most recent first (DESC by created, then by id).
    // Both created in the same second, so secondary sort by id DESC applies.
    $this->assertEquals('openai', $data[0]['provider']);
    $this->assertEquals('anthropic', $data[1]['provider']);
  }

  /**
   * Tests tag filter.
   *
   * @covers ::logs
   */
  public function testTagFilter(): void {
    $this->createTestLog('chat', 'anthropic', 'claude', ['ai_agents']);
    $this->createTestLog('chat', 'openai', 'gpt-4', ['ai_chatbot']);

    $result = $this->command->logs($this->buildOptions(tag: 'ai_agents'));

    $data = $result->getArrayCopy();
    $this->assertCount(1, $data);
    $this->assertEquals('anthropic', $data[0]['provider']);
  }

  /**
   * Tests provider filter.
   *
   * @covers ::logs
   */
  public function testProviderFilter(): void {
    $this->createTestLog('chat', 'anthropic', 'claude', []);
    $this->createTestLog('chat', 'openai', 'gpt-4', []);

    $result = $this->command->logs($this->buildOptions(provider: 'openai'));

    $data = $result->getArrayCopy();
    $this->assertCount(1, $data);
    $this->assertEquals('openai', $data[0]['provider']);
  }

  /**
   * Tests single entry mode shows full (non-truncated) data.
   *
   * @covers ::logs
   */
  public function testSingleEntry(): void {
    $prompt = 'Tell me about Drupal CMS';
    $response = 'Drupal is an open-source content management system';
    $log = $this->createTestLog('chat', 'anthropic', 'claude', ['test'], $prompt, $response);

    $result = $this->command->logs($this->buildOptions(id: (int) $log->id()));

    $data = $result->getArrayCopy();
    $this->assertCount(1, $data);
    $this->assertEquals($prompt, $data[0]['prompt']);
    $this->assertEquals($response, $data[0]['output_text']);
  }

  /**
   * Tests single entry not found throws exception.
   *
   * @covers ::logs
   */
  public function testSingleEntryNotFound(): void {
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('AI log entry #99999 not found.');
    $this->command->logs($this->buildOptions(id: 99999));
  }

  /**
   * Tests empty result returns null.
   *
   * @covers ::logs
   */
  public function testEmptyResult(): void {
    $result = $this->command->logs($this->buildOptions());
    $this->assertNull($result);
  }

  /**
   * Tests count option limits results.
   *
   * @covers ::logs
   */
  public function testCountLimit(): void {
    for ($i = 0; $i < 5; $i++) {
      $this->createTestLog('chat', 'anthropic', 'claude', []);
    }

    $result = $this->command->logs($this->buildOptions(count: 2));

    $data = $result->getArrayCopy();
    $this->assertCount(2, $data);
  }

  /**
   * Tests that long prompts are truncated in list mode.
   *
   * @covers ::logs
   */
  public function testPromptTruncation(): void {
    $longPrompt = str_repeat('word ', 50);
    $this->createTestLog('chat', 'anthropic', 'claude', [], $longPrompt);

    $result = $this->command->logs($this->buildOptions());

    $data = $result->getArrayCopy();
    // Unicode::truncate with wordsafe=TRUE, addEllipsis=TRUE appends U+2026.
    $this->assertLessThanOrEqual(84, mb_strlen($data[0]['prompt']));
    $this->assertStringContainsString("\xE2\x80\xA6", $data[0]['prompt']);
  }

  /**
   * Tests that row contains all expected keys.
   *
   * @covers ::logs
   */
  public function testRowKeys(): void {
    $this->createTestLog('chat', 'anthropic', 'claude', ['test']);

    $result = $this->command->logs($this->buildOptions());
    $data = $result->getArrayCopy();
    $row = $data[0];

    $expectedKeys = [
      'id',
      'date',
      'operation_type',
      'provider',
      'model',
      'tags',
      'prompt',
      'output_text',
      'response_text',
      'tokens_total',
      'extra_data',
      'configuration',
    ];
    foreach ($expectedKeys as $key) {
      $this->assertArrayHasKey($key, $row, "Row missing expected key: $key");
    }
  }

  /**
   * Tests tags are formatted as comma-separated string.
   *
   * @covers ::logs
   */
  public function testTagsFormatting(): void {
    $this->createTestLog('chat', 'anthropic', 'claude', ['ai_agents', 'ai_chatbot']);

    $result = $this->command->logs($this->buildOptions());
    $data = $result->getArrayCopy();

    $this->assertEquals('ai_agents, ai_chatbot', $data[0]['tags']);
  }

  /**
   * Tests the thread command lists one thread's calls, oldest first.
   *
   * @covers ::thread
   */
  public function testThread(): void {
    $this->createTestLog('chat', 'openai', 'gpt-4', ['ai_agents_thread_abc'], 'First turn');
    $this->createTestLog('chat', 'openai', 'gpt-4', ['ai_agents_thread_other'], 'Other thread');
    $this->createTestLog('chat', 'openai', 'gpt-4', ['ai_assistant_thread_abc'], 'Second turn');

    $result = $this->command->thread('abc', ['format' => 'json']);
    $this->assertInstanceOf(RowsOfFields::class, $result);
    $data = $result->getArrayCopy();
    $this->assertCount(2, $data);
    $this->assertSame('First turn', $data[0]['prompt']);
    $this->assertSame('Second turn', $data[1]['prompt']);

    $this->assertNull($this->command->thread('missing', ['format' => 'table']));
  }

  /**
   * Tests the thread command fails when thread pages are turned off.
   *
   * @covers ::thread
   */
  public function testThreadWithoutPrefixes(): void {
    $this->config('ai_logging.settings')->set('thread_tag_prefixes', [])->save();
    $this->expectException(\Exception::class);
    $this->command->thread('abc', ['format' => 'table']);
  }

  /**
   * Builds options array for the command.
   *
   * @param int $count
   *   Number of entries.
   * @param string|null $tag
   *   Tag filter.
   * @param string|null $provider
   *   Provider filter.
   * @param int|null $id
   *   Single entry ID.
   *
   * @return array
   *   Options array matching the command signature.
   */
  protected function buildOptions(
    int $count = 10,
    ?string $tag = NULL,
    ?string $provider = NULL,
    ?int $id = NULL,
  ): array {
    return [
      'count' => $count,
      'tag' => $tag,
      'provider' => $provider,
      'id' => $id,
      'format' => 'table',
    ];
  }

  /**
   * Creates a test ai_log entity.
   *
   * @param string $operationType
   *   The operation type.
   * @param string $provider
   *   The provider ID.
   * @param string $model
   *   The model name.
   * @param array $tags
   *   Array of tag strings.
   * @param string $prompt
   *   The prompt text.
   * @param string $outputText
   *   The response text.
   *
   * @return \Drupal\ai_logging\AiLogInterface
   *   The created entity.
   */
  protected function createTestLog(
    string $operationType,
    string $provider,
    string $model,
    array $tags,
    string $prompt = 'Test prompt',
    string $outputText = 'Test response',
  ): object {
    $storage = $this->container->get('entity_type.manager')->getStorage('ai_log');
    $log = $storage->create([
      'bundle' => 'generic',
      'operation_type' => $operationType,
      'provider' => $provider,
      'model' => $model,
      'tags' => $tags,
      'prompt' => $prompt,
      'output_text' => $outputText,
    ]);
    $log->save();
    return $log;
  }

}
