<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_chatbot\Kernel\Controller;

use Drupal\Component\Serialization\Json;
use Drupal\KernelTests\KernelTestBase;
use Drupal\ai_chatbot\Controller\DeepChatApi;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tests execution-envelope field handling on the deepchat API boundary.
 *
 * @group ai_chatbot
 */
#[RunTestsInSeparateProcesses]
final class DeepChatApiExecutionEnvelopeTest extends KernelTestBase {

  /**
   * The error returned when the baseline payload passes envelope checks.
   */
  private const BASELINE_ERROR = 'Invalid or missing chat processor plugin.';

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
   * Tests executor_uid rejection happens before chat processor validation.
   */
  public function testRejectsClientSuppliedExecutorUid(): void {
    $baseline_response = $this->executeDeepChatApiRequest($this->getBaselinePayload());
    $this->assertSame(Response::HTTP_BAD_REQUEST, $baseline_response->getStatusCode());
    $this->assertSame(self::BASELINE_ERROR, $this->getErrorMessage($baseline_response));

    $payload = $this->getBaselinePayload();
    $payload['executor_uid'] = 1;
    $response = $this->executeDeepChatApiRequest($payload);

    $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    $error_message = $this->getErrorMessage($response);
    $this->assertStringContainsString('executor_uid', $error_message);
    $this->assertStringNotContainsString(self::BASELINE_ERROR, $error_message);
  }

  /**
   * Tests initiator_uid rejection happens before chat processor validation.
   */
  public function testRejectsClientSuppliedInitiatorUid(): void {
    $baseline_response = $this->executeDeepChatApiRequest($this->getBaselinePayload());
    $this->assertSame(Response::HTTP_BAD_REQUEST, $baseline_response->getStatusCode());
    $this->assertSame(self::BASELINE_ERROR, $this->getErrorMessage($baseline_response));

    $payload = $this->getBaselinePayload();
    $payload['initiator_uid'] = 1;
    $response = $this->executeDeepChatApiRequest($payload);

    $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    $error_message = $this->getErrorMessage($response);
    $this->assertStringContainsString('initiator_uid', $error_message);
    $this->assertStringNotContainsString(self::BASELINE_ERROR, $error_message);
  }

  /**
   * Tests initiator_subject rejection before chat processor validation.
   */
  public function testRejectsClientSuppliedInitiatorSubject(): void {
    $baseline_response = $this->executeDeepChatApiRequest($this->getBaselinePayload());
    $this->assertSame(Response::HTTP_BAD_REQUEST, $baseline_response->getStatusCode());
    $this->assertSame(self::BASELINE_ERROR, $this->getErrorMessage($baseline_response));

    $payload = $this->getBaselinePayload();
    $payload['initiator_subject'] = 'mcp:api-key:abc123';
    $response = $this->executeDeepChatApiRequest($payload);

    $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    $error_message = $this->getErrorMessage($response);
    $this->assertStringContainsString('initiator_subject', $error_message);
    $this->assertStringNotContainsString(self::BASELINE_ERROR, $error_message);
  }

  /**
   * Tests multiple reserved fields are rejected together.
   */
  public function testRejectsMultipleClientSuppliedReservedFields(): void {
    $baseline_response = $this->executeDeepChatApiRequest($this->getBaselinePayload());
    $this->assertSame(Response::HTTP_BAD_REQUEST, $baseline_response->getStatusCode());
    $this->assertSame(self::BASELINE_ERROR, $this->getErrorMessage($baseline_response));

    $payload = $this->getBaselinePayload();
    $payload['executor_uid'] = 1;
    $payload['initiator_uid'] = 2;
    $response = $this->executeDeepChatApiRequest($payload);

    $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    $error_message = $this->getErrorMessage($response);
    $this->assertStringContainsString('executor_uid', $error_message);
    $this->assertStringContainsString('initiator_uid', $error_message);
    $this->assertStringNotContainsString(self::BASELINE_ERROR, $error_message);
  }

  /**
   * Executes the deepchat API controller for a JSON payload.
   *
   * @param array<string, mixed> $payload
   *   The JSON request payload.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The API response.
   */
  private function executeDeepChatApiRequest(array $payload): JsonResponse {
    $request = Request::create(
      '/api/deepchat',
      'POST',
      [],
      [],
      [],
      [],
      Json::encode($payload),
    );

    /** @var \Drupal\ai_chatbot\Controller\DeepChatApi $controller */
    $controller = DeepChatApi::create($this->container);
    $response = $controller->api($request);
    $this->assertInstanceOf(JsonResponse::class, $response);

    return $response;
  }

  /**
   * Provides a minimal payload that fails on chat processor validation.
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
   * Extracts the error message from a JSON response.
   */
  private function getErrorMessage(JsonResponse $response): string {
    $data = Json::decode((string) $response->getContent());
    $this->assertIsArray($data);
    $this->assertArrayHasKey('error', $data);
    $this->assertIsString($data['error']);

    return $data['error'];
  }

}
