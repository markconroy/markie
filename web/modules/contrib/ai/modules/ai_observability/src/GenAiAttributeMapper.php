<?php

declare(strict_types=1);

namespace Drupal\ai_observability;

/**
 * Maps Drupal AI provider and operation identifiers to OTel GenAI values.
 *
 * This class translates Drupal AI plugin identifiers into the well-known
 * values defined by the OpenTelemetry GenAI semantic conventions:
 * https://opentelemetry.io/docs/specs/semconv/gen-ai/
 *
 * Note: The gen_ai.* attributes referenced here are Experimental in the spec.
 */
final class GenAiAttributeMapper {

  /**
   * Maps a Drupal AI provider plugin ID to an OTel gen_ai.provider.name value.
   *
   * Returns the well-known OTel provider name when a mapping exists, or falls
   * back to returning the raw $providerId unchanged for unknown providers
   * (e.g. 'ollama' => 'ollama').
   *
   * @param string $providerId
   *   The Drupal AI provider plugin ID (e.g. 'openai', 'anthropic').
   *
   * @return string
   *   The OTel gen_ai.provider.name value.
   */
  public static function mapProvider(string $providerId): string {
    $map = [
      'openai' => 'openai',
      'anthropic' => 'anthropic',
      'mistral' => 'mistral_ai',
      'bedrock' => 'aws.bedrock',
      'aws_bedrock' => 'aws.bedrock',
      'vertex' => 'gcp.vertex_ai',
      'vertexai' => 'gcp.vertex_ai',
      'google_vertex' => 'gcp.vertex_ai',
      'gemini' => 'gcp.gemini',
      'google_gemini' => 'gcp.gemini',
      'cohere' => 'cohere',
      'deepseek' => 'deepseek',
      'groq' => 'groq',
      'xai' => 'x_ai',
      'x_ai' => 'x_ai',
      'grok' => 'x_ai',
      'perplexity' => 'perplexity',
      'azure' => 'azure.ai.openai',
      'azure_openai' => 'azure.ai.openai',
    ];

    return $map[$providerId] ?? $providerId;
  }

  /**
   * Maps a Drupal AI operation type to an OTel gen_ai.operation.name value.
   *
   * Returns the OTel value for the three operations the spec defines for
   * this module's purposes: 'chat', 'text_completion', and 'embeddings'.
   * Non-standard operations (e.g. 'text_to_image', 'moderation',
   * 'text_to_speech') intentionally return NULL so the caller omits the
   * gen_ai.operation.name attribute entirely.
   *
   * @param string $operationType
   *   The Drupal AI operation type (camelCase method name converted to snake).
   *
   * @return string|null
   *   The OTel gen_ai.operation.name value, or NULL for non-standard types.
   */
  public static function mapOperation(string $operationType): ?string {
    $allowed = [
      'chat' => 'chat',
      'text_completion' => 'text_completion',
      'embeddings' => 'embeddings',
    ];

    return $allowed[$operationType] ?? NULL;
  }

}
