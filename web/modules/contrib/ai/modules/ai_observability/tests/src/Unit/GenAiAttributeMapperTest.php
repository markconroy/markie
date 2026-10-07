<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_observability\Unit;

use Drupal\ai_observability\GenAiAttributeMapper;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the GenAiAttributeMapper class.
 *
 * @group ai_observability
 */
class GenAiAttributeMapperTest extends UnitTestCase {

  /**
   * Tests mapProvider with a known mapping for 'anthropic'.
   */
  public function testMapProviderKnownAnthropicMapping(): void {
    $this->assertSame('anthropic', GenAiAttributeMapper::mapProvider('anthropic'));
  }

  /**
   * Tests mapProvider with a known mapping for 'mistral'.
   */
  public function testMapProviderKnownMistralMapping(): void {
    $this->assertSame('mistral_ai', GenAiAttributeMapper::mapProvider('mistral'));
  }

  /**
   * Tests mapProvider with a known mapping for 'bedrock'.
   */
  public function testMapProviderKnownBedrockMapping(): void {
    $this->assertSame('aws.bedrock', GenAiAttributeMapper::mapProvider('bedrock'));
  }

  /**
   * Tests mapProvider falls back to the raw provider ID for unknown providers.
   */
  public function testMapProviderFallbackToRawId(): void {
    $this->assertSame('ollama', GenAiAttributeMapper::mapProvider('ollama'));
  }

  /**
   * Tests mapOperation returns 'chat' for the 'chat' operation.
   */
  public function testMapOperationChat(): void {
    $this->assertSame('chat', GenAiAttributeMapper::mapOperation('chat'));
  }

  /**
   * Tests mapOperation returns 'embeddings' for the 'embeddings' operation.
   */
  public function testMapOperationEmbeddings(): void {
    $this->assertSame('embeddings', GenAiAttributeMapper::mapOperation('embeddings'));
  }

  /**
   * Tests mapOperation returns NULL for 'text_to_image' (non-standard).
   */
  public function testMapOperationTextToImageReturnsNull(): void {
    $this->assertNull(GenAiAttributeMapper::mapOperation('text_to_image'));
  }

  /**
   * Tests mapOperation returns NULL for 'test-operation' (non-standard stub).
   */
  public function testMapOperationTestStubReturnsNull(): void {
    $this->assertNull(GenAiAttributeMapper::mapOperation('test-operation'));
  }

}
