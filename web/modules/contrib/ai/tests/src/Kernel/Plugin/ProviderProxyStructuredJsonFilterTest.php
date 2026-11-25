<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Kernel\Plugin;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ai\Dto\HostnameFilterDto;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that structured output responses survive the hostname filter.
 *
 * @group ai
 */
#[RunTestsInSeparateProcesses]
class ProviderProxyStructuredJsonFilterTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ai',
    'ai_test',
    'key',
    'file',
    'user',
    'field',
    'system',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installSchema('file', ['file_usage']);
    $this->installConfig(['ai', 'ai_test']);
    $this->installEntitySchema('ai_mock_provider_result');
  }

  /**
   * Tests a structured response is filtered in JSON mode, scoped to the call.
   *
   * @param string $trigger
   *   The prompt that selects the echoai fixture.
   * @param string $expected
   *   The expected response text.
   * @param bool|null $json_mode
   *   (optional) The JSON mode the caller sets on the input.
   *
   * @dataProvider structuredResponseProvider
   */
  public function testStructuredResponse(string $trigger, string $expected, ?bool $json_mode = NULL): void {
    $filter = $this->container->get('ai.hostname_filter_service');
    $filter->setAllowedDomains(['example.com']);

    $input = new ChatInput([new ChatMessage('user', $trigger)]);
    $input->setChatStructuredJsonSchema([
      'name' => 'content_item',
      'schema' => [
        'type' => 'object',
        'properties' => [
          'title' => ['type' => 'string'],
          'body' => ['type' => 'string'],
        ],
        'required' => ['title', 'body'],
      ],
    ]);
    if ($json_mode !== NULL) {
      $input->setHostnameFilter(new HostnameFilterDto(jsonMode: $json_mode));
    }
    $output = $this->container->get('ai.provider')->createInstance('echoai')->chat($input, 'gpt-test');

    $this->assertSame($expected, $output->getNormalized()->getText());
    $this->assertNull($filter->snapshotSettings()['jsonMode']);
  }

  /**
   * Data provider for testStructuredResponse().
   */
  public static function structuredResponseProvider(): array {
    return [
      'clean payload is untouched' => [
        'structured-json-filter-test-trigger',
        '{"title":"Hello","body":"<p>Text <a href=\"https://example.com/a\">ok</a> &amp; x < y</p>","count":5,"empty":{}}',
      ],
      // Only the jsonMode auto-enable keeps a truncated payload away from
      // the HTML round trip and removes the bare URL.
      'truncated payload' => [
        'structured-json-filter-malformed-test-trigger',
        '{"title":"T & U","body":"See  and https://example.com/y',
      ],
      // An explicit value from the caller wins: the regular pipeline keeps a
      // URL that is not part of a link.
      'caller turns JSON mode off' => [
        'structured-json-filter-malformed-test-trigger',
        '{"title":"T & U","body":"See https://evil.com/x and https://example.com/y',
        FALSE,
      ],
    ];
  }

}
