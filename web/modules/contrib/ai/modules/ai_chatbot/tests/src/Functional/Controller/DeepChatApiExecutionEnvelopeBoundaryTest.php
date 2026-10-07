<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_chatbot\Functional\Controller;

use Behat\Mink\Driver\BrowserKitDriver;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tests execution-envelope validation at the deepchat HTTP route boundary.
 *
 * @group ai_chatbot
 */
#[RunTestsInSeparateProcesses]
final class DeepChatApiExecutionEnvelopeBoundaryTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'token',
    'key',
    'ai',
    'ai_assistant_api',
    'ai_chatbot',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $account = $this->drupalCreateUser(['access deepchat api']);
    $this->assertNotFalse($account);
    $this->drupalLogin($account);
  }

  /**
   * Tests route-level rejection happens before chat processor validation.
   */
  public function testRejectsReservedIdentityFieldBeforeAssistantValidation(): void {
    $this->postDeepChatApi($this->getBaselinePayload());
    $this->assertSession()->statusCodeEquals(Response::HTTP_BAD_REQUEST);
    $this->assertSame('Invalid or missing chat processor plugin.', $this->getErrorMessageFromLastResponse());

    $payload = $this->getBaselinePayload();
    $payload['executor_uid'] = 1;
    $this->postDeepChatApi($payload);

    $this->assertSession()->statusCodeEquals(Response::HTTP_UNPROCESSABLE_ENTITY);
    $error_message = $this->getErrorMessageFromLastResponse();
    $this->assertStringContainsString('executor_uid', $error_message);
    $this->assertStringNotContainsString('Invalid or missing chat processor plugin.', $error_message);
  }

  /**
   * Posts a JSON payload to the deepchat API route.
   *
   * @param array<string, mixed> $payload
   *   The request payload.
   */
  private function postDeepChatApi(array $payload): void {
    $csrf_token = $this->getDeepChatCsrfToken();
    $uri = Url::fromRoute('ai_chatbot.api', [], [
      'query' => ['token' => $csrf_token],
      'absolute' => TRUE,
    ])->toString();

    $this->getBrowserKitDriver()->getClient()->request(
      'POST',
      $uri,
      [],
      [],
      [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
      ],
      Json::encode($payload),
    );
  }

  /**
   * Requests a CSRF token from the deepchat session endpoint.
   */
  private function getDeepChatCsrfToken(): string {
    $uri = Url::fromRoute('ai_chatbot.session', [], ['absolute' => TRUE])->toString();
    $this->getBrowserKitDriver()->getClient()->request('POST', $uri);
    $this->assertSession()->statusCodeEquals(Response::HTTP_OK);

    return trim($this->getSession()->getPage()->getContent());
  }

  /**
   * Provides a payload that fails at chat processor validation.
   *
   * @return array<string, mixed>
   *   A minimal DeepChat request payload.
   */
  private function getBaselinePayload(): array {
    return [
      'chat_processor_plugin' => 'non_existent_chat_processor',
      'messages' => [
        [
          'role' => 'user',
          'text' => 'Hello',
        ],
      ],
    ];
  }

  /**
   * Extracts the `error` message from the last JSON response.
   */
  private function getErrorMessageFromLastResponse(): string {
    $data = Json::decode($this->getSession()->getPage()->getContent());
    $this->assertIsArray($data);
    $this->assertArrayHasKey('error', $data);
    $this->assertIsString($data['error']);

    return $data['error'];
  }

  /**
   * Returns the BrowserKit driver used by BrowserTestBase.
   *
   * @return \Behat\Mink\Driver\BrowserKitDriver<object, object>
   *   The BrowserKit driver.
   */
  private function getBrowserKitDriver(): BrowserKitDriver {
    $driver = $this->getSession()->getDriver();
    $this->assertInstanceOf(BrowserKitDriver::class, $driver);

    return $driver;
  }

}
