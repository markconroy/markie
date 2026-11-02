<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Unit\Base;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Site\Settings;
use Drupal\ai\Base\OpenAiBasedProviderClientBase;
use Drupal\ai\Exception\AiQuotaException;
use Drupal\ai\Exception\AiRateLimitException;
use Drupal\ai\Exception\AiRequestErrorException;
use Drupal\ai\Exception\AiResponseErrorException;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\GenericType\AudioFile;
use Drupal\ai\OperationType\SpeechToText\SpeechToTextInput;
use Drupal\ai\OperationType\TextToImage\TextToImageInput;
use Drupal\ai\Service\HostnameFilter;
use GuzzleHttp\Psr7\HttpFactory;
use Http\Message\MultipartStream\MultipartStreamBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Tests error handling in OpenAiBasedProviderClientBase.
 *
 * @group ai
 * @coversDefaultClass \Drupal\ai\Base\OpenAiBasedProviderClientBase
 */
class OpenAiBasedProviderClientBaseTest extends TestCase {

  /**
   * A 2x2 PNG, base64 encoded the way the images endpoint returns one.
   */
  const PNG_FIXTURE = 'iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAIAAAD91JpzAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAAFklEQVQImWP8z8DAwMDAxMDAwMDAAAANHQEDDMfniQAAAABJRU5ErkJggg==';

  /**
   * A 2x2 JPEG, to cover a returned format other than PNG.
   */
  const JPEG_FIXTURE = '/9j/4AAQSkZJRgABAQEAYABgAAD//gA7Q1JFQVRPUjogZ2QtanBlZyB2MS4wICh1c2luZyBJSkcgSlBFRyB2ODApLCBxdWFsaXR5ID0gMjAK/9sAQwAoHB4jHhkoIyEjLSsoMDxkQTw3Nzx7WF1JZJGAmZaPgIyKoLTmw6Cq2q2KjMj/y9ru9f///5vB////+v/m/f/4/9sAQwErLS08NTx2QUF2+KWMpfj4+Pj4+Pj4+Pj4+Pj4+Pj4+Pj4+Pj4+Pj4+Pj4+Pj4+Pj4+Pj4+Pj4+Pj4+Pj4+Pj4/8AAEQgAAgACAwEiAAIRAQMRAf/EAB8AAAEFAQEBAQEBAAAAAAAAAAABAgMEBQYHCAkKC//EALUQAAIBAwMCBAMFBQQEAAABfQECAwAEEQUSITFBBhNRYQcicRQygZGhCCNCscEVUtHwJDNicoIJChYXGBkaJSYnKCkqNDU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6g4SFhoeIiYqSk5SVlpeYmZqio6Slpqeoqaqys7S1tre4ubrCw8TFxsfIycrS09TV1tfY2drh4uPk5ebn6Onq8fLz9PX29/j5+v/EAB8BAAMBAQEBAQEBAQEAAAAAAAABAgMEBQYHCAkKC//EALURAAIBAgQEAwQHBQQEAAECdwABAgMRBAUhMQYSQVEHYXETIjKBCBRCkaGxwQkjM1LwFWJy0QoWJDThJfEXGBkaJicoKSo1Njc4OTpDREVGR0hJSlNUVVZXWFlaY2RlZmdoaWpzdHV2d3h5eoKDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uLj5OXm5+jp6vLz9PX29/j5+v/aAAwDAQACEQMRAD8Ay6KKK9gzP//Z';

  /**
   * The provider instance under test.
   *
   * @var \Drupal\ai\Base\OpenAiBasedProviderClientBase
   */
  protected OpenAiBasedProviderClientBase $provider;

  /**
   * Reflection method for handleApiException.
   *
   * @var \ReflectionMethod
   */
  protected \ReflectionMethod $handleApiException;

  /**
   * Reflection method for handleApiThrowable.
   *
   * @var \ReflectionMethod
   */
  protected \ReflectionMethod $handleApiThrowable;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->provider = $this->createMock(OpenAiBasedProviderClientBase::class);

    $this->handleApiException = new \ReflectionMethod(
      $this->provider,
      'handleApiException',
    );
    $this->handleApiThrowable = new \ReflectionMethod(
      $this->provider,
      'handleApiThrowable',
    );
  }

  /**
   * Tests that \TypeError is wrapped in AiResponseErrorException.
   *
   * Real providers like the OpenAI PHP client can throw \TypeError when the
   * API returns an unexpected response format. handleApiThrowable() must
   * convert these to AiResponseErrorException so that all consumers can
   * catch them.
   *
   * @covers ::handleApiThrowable
   */
  public function testTypeErrorIsWrappedInAiResponseErrorException(): void {
    $original = new \TypeError('CreateResponse::from(): Argument #1 ($attributes) must be of type array, string given');

    $this->expectException(AiResponseErrorException::class);
    $this->expectExceptionMessage('CreateResponse::from(): Argument #1 ($attributes) must be of type array, string given');

    $this->handleApiThrowable->invoke($this->provider, $original);
  }

  /**
   * Tests that the original \Error is preserved as the previous exception.
   *
   * @covers ::handleApiThrowable
   */
  public function testWrappedErrorPreservesOriginalAsPrevious(): void {
    $original = new \TypeError('type mismatch');

    try {
      $this->handleApiThrowable->invoke($this->provider, $original);
      $this->fail('Expected AiResponseErrorException was not thrown.');
    }
    catch (AiResponseErrorException $e) {
      $this->assertSame($original, $e->getPrevious());
    }
  }

  /**
   * Tests that generic \Error subclasses are also wrapped.
   *
   * @covers ::handleApiThrowable
   */
  public function testGenericErrorIsWrapped(): void {
    $original = new \ValueError('Invalid value');

    $this->expectException(AiResponseErrorException::class);
    $this->expectExceptionMessage('Invalid value');

    $this->handleApiThrowable->invoke($this->provider, $original);
  }

  /**
   * Tests that handleApiThrowable delegates exceptions to handleApiException.
   *
   * @covers ::handleApiThrowable
   */
  public function testThrowableDelegatesExceptionToHandleApiException(): void {
    $original = new \RuntimeException('Too Many Requests');

    $this->expectException(AiRateLimitException::class);

    $this->handleApiThrowable->invoke($this->provider, $original);
  }

  /**
   * Tests that regular exceptions are rethrown as-is.
   *
   * @covers ::handleApiException
   */
  public function testRegularExceptionIsRethrown(): void {
    $original = new \RuntimeException('Something went wrong');

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Something went wrong');

    $this->handleApiException->invoke($this->provider, $original);
  }

  /**
   * Tests that rate limit messages throw AiRateLimitException.
   *
   * @covers ::handleApiException
   */
  public function testRateLimitExceptionForTooManyRequests(): void {
    $original = new \RuntimeException('Too Many Requests');

    $this->expectException(AiRateLimitException::class);

    $this->handleApiException->invoke($this->provider, $original);
  }

  /**
   * Tests that rate limit messages throw AiRateLimitException.
   *
   * @covers ::handleApiException
   */
  public function testRateLimitExceptionForRequestTooLarge(): void {
    $original = new \RuntimeException('Request too large for model');

    $this->expectException(AiRateLimitException::class);

    $this->handleApiException->invoke($this->provider, $original);
  }

  /**
   * Tests that quota messages throw AiQuotaException.
   *
   * @covers ::handleApiException
   */
  public function testQuotaException(): void {
    $original = new \RuntimeException('You exceeded your current quota');

    $this->expectException(AiQuotaException::class);

    $this->handleApiException->invoke($this->provider, $original);
  }

  /**
   * Guards against regression of issue #3573429.
   *
   * A subclass that overrides handleApiException() with the original
   * \Exception parameter type must remain loadable and invokable. If the
   * parent signature is ever widened again (e.g. to \Throwable), PHP would
   * raise a fatal "incompatible signature" error at class declaration.
   *
   * @covers ::handleApiException
   */
  public function testSubclassWithExceptionParamTypeIsCompatible(): void {
    // Referencing the class triggers PHP's signature compatibility check.
    $this->assertTrue(class_exists(LegacyExceptionSignatureProvider::class));

    $parent = new \ReflectionMethod(OpenAiBasedProviderClientBase::class, 'handleApiException');
    $parentType = $parent->getParameters()[0]->getType();
    $this->assertInstanceOf(\ReflectionNamedType::class, $parentType);
    $this->assertSame('Exception', $parentType->getName());
  }

  /**
   * Tests that an authentication-required provider without a Key is unusable.
   *
   * @covers ::isUsable
   */
  public function testUnconfiguredAuthenticationProviderIsNotUsable(): void {
    $provider = $this->createUsabilityProvider('');

    $this->assertFalse($provider->isUsable());
    $this->assertFalse($provider->isUsable('chat'));
  }

  /**
   * Tests that a configured Key reference makes a provider usable.
   *
   * Key resolution deliberately happens only when a client is loaded.
   *
   * @covers ::isUsable
   */
  public function testConfiguredKeyReferenceMakesProviderUsable(): void {
    $provider = $this->createUsabilityProvider('openai_key');

    $this->assertTrue($provider->isUsable());
    $this->assertTrue($provider->isUsable('chat'));
    $this->assertFalse($provider->isUsable('embeddings'));
  }

  /**
   * Tests that injected authentication makes a provider usable without config.
   *
   * @covers ::isUsable
   */
  public function testRuntimeAuthenticationMakesProviderUsable(): void {
    $provider = $this->createUsabilityProvider('');
    $provider->setAuthentication('runtime-key');

    $this->assertTrue($provider->isUsable('chat'));
  }

  /**
   * Tests that providers without authentication remain usable.
   *
   * @covers ::isUsable
   */
  public function testProviderWithoutAuthenticationDoesNotRequireKey(): void {
    $provider = $this->createUsabilityProvider('', FALSE);

    $this->assertTrue($provider->isUsable('chat'));
  }

  /**
   * Creates a provider fixture for testing usability.
   */
  private function createUsabilityProvider(
    string $api_key,
    bool $requires_authentication = TRUE,
  ): UsabilityStubProvider {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')
      ->with('api_key')
      ->willReturn($api_key);

    $reflection = new \ReflectionClass(UsabilityStubProvider::class);
    /** @var \Drupal\Tests\ai\Unit\Base\UsabilityStubProvider $provider */
    $provider = $reflection->newInstanceWithoutConstructor();
    $provider->testConfig = $config;
    $provider->requiresAuthentication = $requires_authentication;
    return $provider;
  }

  /**
   * Tests that the Fiber branch keeps token usage from the consumed stream.
   *
   * Regression test for issue #3586522: when chat() executes inside a
   * \Fiber — which is the case for every web request since Drupal core 11.x
   * wraps controller execution in a Fiber — the streamed response is
   * consumed and reconstructed, but the token usage collected during
   * consumption was discarded from the returned ChatOutput.
   *
   * @covers ::chat
   */
  public function testFiberBranchKeepsTokenUsage(): void {
    // The streamed iterator falls back to the global container for the event
    // dispatcher (PostStreamingResponseEvent on full consumption) and the
    // hostname filter service (URL-safety buffer flush), and the hostname
    // filter reads the Settings singleton.
    new Settings([]);
    $config = $this->createMock(ImmutableConfig::class);
    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->method('get')->willReturn($config);
    $container = new ContainerBuilder();
    $container->set('event_dispatcher', new EventDispatcher());
    $container->set('ai.hostname_filter_service', new HostnameFilter(
      $this->createMock(LoggerChannelFactoryInterface::class),
      $config_factory,
    ));
    \Drupal::setContainer($container);

    $reflection = new \ReflectionClass(FiberTokenUsageStubProvider::class);
    /** @var \Drupal\Tests\ai\Unit\Base\FiberTokenUsageStubProvider $provider */
    $provider = $reflection->newInstanceWithoutConstructor();
    $client = new \ReflectionProperty(OpenAiBasedProviderClientBase::class, 'client');
    $client->setValue($provider, new FiberTokenUsageStubClient());

    $input = new ChatInput([new ChatMessage('user', 'Hello')]);

    $usage = NULL;
    $fiber = new \Fiber(static function () use ($provider, $input, &$usage): void {
      $output = $provider->chat($input, 'stub-model');
      $usage = $output->getTokenUsage();
    });
    $fiber->start();
    while (!$fiber->isTerminated()) {
      $fiber->resume();
    }

    $this->assertNotNull($usage, 'chat() completed inside the Fiber.');
    $this->assertSame(10, $usage->input);
    $this->assertSame(5, $usage->output);
    $this->assertSame(15, $usage->total);
  }

  /**
   * Tests that textToImage() hands out the image bytes the API returned.
   *
   * The b64_json payload must be base64-decoded exactly once. Decoding it
   * again silently discards most of the payload and returns bytes that are
   * not an image, while the response still reports success — so callers
   * write corrupt files without any error to go on.
   *
   * @covers ::textToImage
   */
  public function testTextToImageDecodesThePayloadOnce(): void {
    $binary = base64_decode(self::PNG_FIXTURE, TRUE);
    $provider = $this->createTextToImageProvider([
      'data' => [['b64_json' => self::PNG_FIXTURE]],
    ]);

    $images = $provider->textToImage(new TextToImageInput('a red square'), 'stub-model')->getNormalized();

    $this->assertCount(1, $images);
    $this->assertSame($binary, $images[0]->getBinary());
    $this->assertSame("\x89PNG\r\n\x1a\n", substr($images[0]->getBinary(), 0, 8));
    $this->assertSame('image/png', $images[0]->getMimeType());
    $this->assertSame('generated.png', $images[0]->getFilename());
  }

  /**
   * Tests that textToImage() reports the mime type it detected.
   *
   * Every mime type in ALLOWED_IMAGE_MIME_TYPES may be returned by the API,
   * so the detected type has to reach the caller instead of a hardcoded one.
   * The filename carries the matching extension, because consumers derive
   * the file type from it when saving.
   *
   * @covers ::textToImage
   */
  public function testTextToImageKeepsTheDetectedMimeType(): void {
    $provider = $this->createTextToImageProvider([
      'data' => [['b64_json' => self::JPEG_FIXTURE]],
    ]);

    $images = $provider->textToImage('a blue square', 'stub-model')->getNormalized();

    $this->assertSame(base64_decode(self::JPEG_FIXTURE, TRUE), $images[0]->getBinary());
    $this->assertSame('image/jpeg', $images[0]->getMimeType());
    $this->assertSame('generated.jpeg', $images[0]->getFilename());
    $this->assertSame('jpeg', $images[0]->getFileType());
  }

  /**
   * Tests that a payload which is not an image is refused.
   *
   * @covers ::textToImage
   */
  public function testTextToImageRejectsNonImagePayload(): void {
    $provider = $this->createTextToImageProvider([
      'data' => [['b64_json' => base64_encode('this is not an image')]],
    ]);

    $this->expectException(AiResponseErrorException::class);
    $this->expectExceptionMessage('The API returned an image with an unsupported mime type.');

    $provider->textToImage('a red square', 'stub-model');
  }

  /**
   * Tests that speechToText() hands the audio over in memory.
   *
   * The audio must never be written to disk, where other requests or local
   * users could read it, yet the API still needs a filename to derive the
   * audio format from.
   *
   * @covers ::speechToText
   */
  public function testSpeechToTextKeepsTheAudioInMemory(): void {
    $client = $this->createSpeechToTextClient();
    $provider = $this->createSpeechToTextProvider($client);

    $this->assertSame('first', $provider->speechToText('first-audio', 'stub-model')->getNormalized());
    $this->assertSame('second', $provider->speechToText('second-audio', 'stub-model')->getNormalized());

    $this->assertCount(2, $client->calls);
    [$first, $second] = $client->calls;
    $this->assertSame('first-audio', $first['contents']);
    $this->assertSame('second-audio', $second['contents']);
    foreach ($client->calls as $call) {
      $this->assertSame('MEMORY', $call['stream_type']);
      $this->assertStringContainsString('filename="audio.mp3"', $call['part']);
      $this->assertStringContainsString('Content-Type: audio/mpeg', $call['part']);
      // The stream is closed once the call returns.
      $this->assertFalse($call['file']->isReadable());
    }
  }

  /**
   * Tests that the audio stream is closed when the API call fails.
   *
   * @covers ::speechToText
   */
  public function testSpeechToTextClosesTheStreamWhenTheCallFails(): void {
    $client = $this->createSpeechToTextClient(fail: TRUE);
    $provider = $this->createSpeechToTextProvider($client);

    try {
      $provider->speechToText('failing-audio', 'stub-model');
      $this->fail('The failing API call was not reported.');
    }
    catch (AiResponseErrorException) {
    }

    $this->assertCount(1, $client->calls);
    $this->assertSame('failing-audio', $client->calls[0]['contents']);
    $this->assertFalse($client->calls[0]['file']->isReadable());
  }

  /**
   * Tests that speechToText() uploads the audio under the input's filename.
   *
   * The API derives the audio format from the extension, so a filename
   * without one gets .mp3 appended.
   *
   * @covers ::speechToText
   * @dataProvider providerSpeechToTextFilename
   */
  public function testSpeechToTextUsesTheInputFilename(string $filename, string $expected_filename, string $expected_type): void {
    $client = $this->createSpeechToTextClient();
    $provider = $this->createSpeechToTextProvider($client);

    $input = new SpeechToTextInput(new AudioFile('clip-audio', 'audio/mp4', $filename));
    $provider->speechToText($input, 'stub-model');

    $this->assertCount(1, $client->calls);
    $this->assertSame('clip-audio', $client->calls[0]['contents']);
    $this->assertStringContainsString('filename="' . $expected_filename . '"', $client->calls[0]['part']);
    $this->assertStringContainsString('Content-Type: ' . $expected_type, $client->calls[0]['part']);
  }

  /**
   * Data provider for testSpeechToTextUsesTheInputFilename().
   *
   * @return array
   *   The input filename, the uploaded filename and its content type.
   */
  public static function providerSpeechToTextFilename(): array {
    return [
      'with extension' => ['clip.m4a', 'clip.m4a', 'audio/mp4'],
      'without extension' => ['recording', 'recording.mp3', 'audio/mpeg'],
      'empty' => ['', 'audio.mp3', 'audio/mpeg'],
    ];
  }

  /**
   * Tests that audio too large for the memory limit is refused.
   *
   * Copying it into memory would otherwise end the request with a fatal
   * error instead of an exception the caller can handle.
   *
   * @covers ::speechToText
   */
  public function testSpeechToTextRefusesAudioLargerThanTheMemoryLimit(): void {
    $client = $this->createSpeechToTextClient();
    $provider = $this->createSpeechToTextProvider($client);
    $audio = str_repeat('a', 2 * 1024 * 1024);

    // Leave less free memory than the audio needs for its copy.
    $memory_limit = ini_get('memory_limit');
    ini_set('memory_limit', (string) (memory_get_usage(TRUE) + 1024 * 1024));
    try {
      $provider->speechToText($audio, 'stub-model');
      $this->fail('Audio larger than the free memory was not refused.');
    }
    catch (AiRequestErrorException $e) {
      $this->assertSame('The audio file is too large to be processed in memory.', $e->getMessage());
    }
    finally {
      ini_set('memory_limit', $memory_limit);
    }

    $this->assertSame([], $client->calls);
  }

  /**
   * Builds a provider whose client is the given speech-to-text stub.
   *
   * @param object $client
   *   The stand-in for the SDK client.
   *
   * @return \Drupal\ai\Base\OpenAiBasedProviderClientBase
   *   The provider, with its client already loaded.
   */
  protected function createSpeechToTextProvider(object $client): OpenAiBasedProviderClientBase {
    $provider = $this->createPartialMock(OpenAiBasedProviderClientBase::class, [
      'getModelSettings',
      'getSupportedOperationTypes',
    ]);
    (new \ReflectionProperty(OpenAiBasedProviderClientBase::class, 'client'))->setValue($provider, $client);
    (new \ReflectionProperty(OpenAiBasedProviderClientBase::class, 'configuration'))->setValue($provider, []);
    return $provider;
  }

  /**
   * Builds a stand-in for the SDK client's audio()->transcribe() chain.
   *
   * The SDK classes are final, so they cannot be mocked. The stub records
   * what it was handed, including the multipart part the SDK would send.
   *
   * @param bool $fail
   *   Whether the transcribe call throws.
   *
   * @return object
   *   The stub client.
   */
  protected function createSpeechToTextClient(bool $fail = FALSE): object {
    return new class($fail) {

      /**
       * The recorded calls.
       *
       * @var array<int, array{file: \Psr\Http\Message\StreamInterface, contents: string, stream_type: string, part: string}>
       */
      public array $calls = [];

      /**
       * The text returned by the current call.
       */
      protected string $text = '';

      public function __construct(
        protected bool $fail,
      ) {}

      /**
       * Stands in for the audio resource.
       *
       * @return static
       *   The stub itself.
       */
      public function audio(): static {
        return $this;
      }

      /**
       * Records the uploaded file.
       *
       * @param array $payload
       *   The request payload.
       *
       * @return static
       *   The stub itself.
       */
      public function transcribe(array $payload): static {
        $file = $payload['file'];
        $contents = (string) $file;
        // Build the multipart body the way the SDK does, to see the
        // headers the API would receive for the file.
        $builder = new MultipartStreamBuilder(new HttpFactory());
        $builder->addResource('file', $file);
        $body = (string) $builder->build();
        $this->calls[] = [
          'file' => $file,
          'contents' => $contents,
          'stream_type' => $file->getMetadata('stream_type'),
          'part' => substr($body, 0, (int) strpos($body, "\r\n\r\n", strpos($body, 'name="file"'))),
        ];
        if ($this->fail) {
          throw new \TypeError('Transcription failed.');
        }
        $this->text = explode('-', $contents)[0];
        return $this;
      }

      /**
       * Serves the transcription.
       *
       * @return array
       *   The raw response data.
       */
      public function toArray(): array {
        return ['text' => $this->text];
      }

    };
  }

  /**
   * Builds a provider whose client returns the given images response.
   *
   * @param array $response
   *   The canned response of the images endpoint.
   *
   * @return \Drupal\ai\Base\OpenAiBasedProviderClientBase
   *   The provider, with its client already loaded.
   */
  protected function createTextToImageProvider(array $response): OpenAiBasedProviderClientBase {
    // Only the two abstract methods are stubbed, so that textToImage() itself
    // runs as written.
    $provider = $this->createPartialMock(OpenAiBasedProviderClientBase::class, [
      'getModelSettings',
      'getSupportedOperationTypes',
    ]);

    // The SDK's Client, Images and CreateResponse classes are all final, so
    // none of them can be mocked. The client property carries no type
    // declaration, so a stand-in answering the images()->create()->toArray()
    // chain is enough to serve a canned response.
    $client = new \ReflectionProperty(OpenAiBasedProviderClientBase::class, 'client');
    $client->setValue($provider, new class($response) {

      public function __construct(
        protected array $response,
      ) {}

      /**
       * Stands in for the images resource.
       *
       * @return static
       *   The stub itself.
       */
      public function images(): static {
        return $this;
      }

      /**
       * Stands in for the create call, whose payload does not matter here.
       *
       * @param array $payload
       *   The request payload.
       *
       * @return static
       *   The stub itself.
       */
      public function create(array $payload): static {
        return $this;
      }

      /**
       * Serves the canned response.
       *
       * @return array
       *   The raw response data.
       */
      public function toArray(): array {
        return $this->response;
      }

    });

    return $provider;
  }

}

/**
 * Fixture: subclass overriding handleApiException() with \Exception param.
 *
 * This mirrors the signature used by real contributed provider modules
 * (e.g. the Anthropic provider) prior to the issue #3573429 regression.
 * Having this class in the same file causes the signature compatibility
 * check to run whenever this test file is loaded.
 */
// phpcs:disable Drupal.Classes.ClassFileName.NoMatch
abstract class LegacyExceptionSignatureProvider extends OpenAiBasedProviderClientBase {

  /**
   * {@inheritdoc}
   *
   * Intentionally re-declares the signature with the legacy \Exception
   * parameter type. This method looks redundant but is load-bearing: its
   * presence triggers PHP's signature compatibility check against the
   * parent at class-declaration time.
   */
  // phpcs:disable Squiz.Scope.MethodScope.Missing, Generic.CodeAnalysis.UselessOverridingMethod.Found
  protected function handleApiException(\Exception $e): void {
    parent::handleApiException($e);
  }

  // phpcs:enable Squiz.Scope.MethodScope.Missing, Generic.CodeAnalysis.UselessOverridingMethod.Found

}

/**
 * Fixture: minimal concrete provider for exercising the chat() Fiber branch.
 */
// phpcs:disable Drupal.Classes.ClassFileName.NoMatch
final class FiberTokenUsageStubProvider extends OpenAiBasedProviderClientBase {

  /**
   * {@inheritdoc}
   */
  public function getConfiguredModels(?string $operation_type = NULL, array $capabilities = []): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedOperationTypes(): array {
    return ['chat'];
  }

  /**
   * {@inheritdoc}
   */
  public function getModelSettings(string $model_id, array $generalConfig = []): array {
    return $generalConfig;
  }

}

/**
 * Fixture: a minimal provider for testing usability checks.
 */
final class UsabilityStubProvider extends OpenAiBasedProviderClientBase {

  /**
   * Configuration returned by the fixture.
   */
  public ImmutableConfig $testConfig;

  /**
   * Whether the fixture requires authentication.
   */
  public bool $requiresAuthentication = TRUE;

  /**
   * {@inheritdoc}
   */
  public function getConfig(): ImmutableConfig {
    return $this->testConfig;
  }

  /**
   * {@inheritdoc}
   */
  public function hasAuthentication(): bool {
    return $this->requiresAuthentication;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguredModels(?string $operation_type = NULL, array $capabilities = []): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedOperationTypes(): array {
    return ['chat'];
  }

  /**
   * {@inheritdoc}
   */
  public function getModelSettings(string $model_id, array $generalConfig = []): array {
    return $generalConfig;
  }

}

/**
 * Fixture: stand-in for the OpenAI SDK client used by the Fiber branch test.
 */
final class FiberTokenUsageStubClient {

  /**
   * Returns a chat resource whose streamed response yields canned chunks.
   *
   * @return object
   *   The chat resource stub.
   */
  public function chat(): object {
    return new class {

      /**
       * Returns a traversable streamed response.
       *
       * @param array $payload
       *   The request payload (ignored).
       *
       * @return \IteratorAggregate
       *   The canned stream.
       */
      public function createStreamed(array $payload): \IteratorAggregate {
        return new class implements \IteratorAggregate {

          /**
           * {@inheritdoc}
           */
          public function getIterator(): \Generator {
            yield new FiberTokenUsageStubChunk([
              new FiberTokenUsageStubChoice(new FiberTokenUsageStubDelta('assistant', 'Hello'), NULL),
            ]);
            yield new FiberTokenUsageStubChunk([
              new FiberTokenUsageStubChoice(new FiberTokenUsageStubDelta('', ' world.'), 'stop'),
            ]);
            // OpenAI-compatible APIs send the usage data in a trailing chunk
            // with an empty choices list when stream_options.include_usage is
            // set.
            yield new FiberTokenUsageStubChunk([], new FiberTokenUsageStubUsage(10, 5, 15));
          }

        };
      }

    };
  }

}

/**
 * Fixture: a single streamed completion chunk.
 */
final class FiberTokenUsageStubChunk {

  public function __construct(
    public array $choices,
    public ?FiberTokenUsageStubUsage $usage = NULL,
  ) {}

  /**
   * Mirrors the SDK response object API.
   *
   * @return array
   *   The raw chunk data.
   */
  public function toArray(): array {
    return [];
  }

}

/**
 * Fixture: the usage block of a trailing streamed chunk.
 */
final class FiberTokenUsageStubUsage {

  public function __construct(
    public int $promptTokens,
    public int $completionTokens,
    public int $totalTokens,
  ) {}

  /**
   * Mirrors the SDK usage object API.
   *
   * @return array
   *   The raw usage data.
   */
  public function toArray(): array {
    return [
      'prompt_tokens' => $this->promptTokens,
      'completion_tokens' => $this->completionTokens,
      'total_tokens' => $this->totalTokens,
    ];
  }

}

/**
 * Fixture: the delta part of a streamed chunk choice.
 */
final class FiberTokenUsageStubDelta {

  public function __construct(
    public string $role = '',
    public string $content = '',
  ) {}

}

/**
 * Fixture: a single choice of a streamed chunk.
 */
final class FiberTokenUsageStubChoice {

  public function __construct(
    public FiberTokenUsageStubDelta $delta,
    public ?string $finishReason = NULL,
  ) {}

}

// phpcs:enable Drupal.Classes.ClassFileName.NoMatch
