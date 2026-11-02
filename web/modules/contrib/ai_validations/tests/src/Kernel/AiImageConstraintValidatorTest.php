<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_validations\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ai_validations\Plugin\Validation\Constraint\AiImageConstraint;
use Drupal\ai_validations\Plugin\Validation\Constraint\AiImageConstraintValidator;
use Drupal\ai_validations_test\Plugin\AiProvider\ClassifierTestProvider;
use Drupal\file\Entity\File;
use Drupal\image\Entity\ImageStyle;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Tests the ternary warning tier of the AI image constraint validator.
 *
 * Drives the validator through the real AI provider manager, backed by the
 * ai_validations_test "classifier_test" provider whose chat response text
 * is controlled via state.
 *
 * @see \Drupal\ai_validations\AiConstraintValidatorBase::runTernaryChat()
 * @see \Drupal\ai_validations\Plugin\Validation\Constraint\AiImageConstraintValidator
 */
#[Group('ai_validations')]
#[Group('3606131')]
class AiImageConstraintValidatorTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'file',
    'image',
    'key',
    'ai',
    'ai_test',
    'ai_validations',
    'ai_validations_test',
  ];

  /**
   * A resolved "provider__model" option pointing at the test provider.
   */
  protected const OPTION = 'classifier_test__gpt-test';

  /**
   * The warning message configured on the constraint under test.
   */
  protected const WARNING = 'This image was flagged as moderate.';

  /**
   * The blocking message configured on the constraint under test.
   */
  protected const MESSAGE = 'This image is not allowed.';

  /**
   * The uploaded file entity the validator is pointed at.
   */
  protected File $file;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    // The ai_validations namespace exists in two locations: this git clone
    // and the bundled copy inside ai/modules/ai_validations. Prepend our own
    // PSR-4 loader so it wins before Drupal's own module class loader is
    // consulted. This matches the pattern used in
    // \Drupal\Tests\ai_validations\Unit\AiConstraintValidatorBaseTest.
    // @todo Remove this workaround once ai_validations is no longer bundled
    //   inside the ai package. See
    //   https://git.drupalcode.org/project/ai/-/work_items/3586538
    $module_root = dirname(__DIR__, 3);
    spl_autoload_register(static function (string $class) use ($module_root): void {
      $prefix = 'Drupal\\ai_validations\\';
      if (str_starts_with($class, $prefix)) {
        $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
        $file = $module_root . '/src/' . $relative . '.php';
        if (file_exists($file)) {
          require_once $file;
        }
      }
    }, TRUE, TRUE);

    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installSchema('file', ['file_usage']);
    $this->installConfig(['system', 'image']);

    $fixture = \Drupal::root() . '/core/tests/fixtures/files/image-test.png';
    file_put_contents('public://image-test.png', file_get_contents($fixture));
    $this->file = File::create([
      'uri' => 'public://image-test.png',
      'filename' => 'image-test.png',
    ]);
    $this->file->save();
  }

  /**
   * Sets the literal chat response text the test provider should return.
   */
  protected function setChatResponse(string $text): void {
    $this->container->get('state')
      ->set(ClassifierTestProvider::STATE_CHAT_RESPONSE, $text);
  }

  /**
   * Builds a fresh validator initialized with the given execution context.
   */
  protected function buildValidator(ExecutionContextInterface $context): AiImageConstraintValidator {
    /** @var \Drupal\ai_validations\Plugin\Validation\Constraint\AiImageConstraintValidator $validator */
    $validator = AiImageConstraintValidator::create($this->container);
    $validator->initialize($context);
    return $validator;
  }

  /**
   * Builds a constraint with the given configuration overrides.
   */
  protected function buildConstraint(array $values = []): AiImageConstraint {
    $constraint = new AiImageConstraint();
    $constraint->provider = $values['provider'] ?? self::OPTION;
    $constraint->prompt = $values['prompt'] ?? 'Answer XSAFE, XWARN or XBLOCK.';
    $constraint->message = $values['message'] ?? self::MESSAGE;
    $constraint->warningMessage = $values['warningMessage'] ?? self::WARNING;
    $constraint->imageStyle = $values['imageStyle'] ?? NULL;
    return $constraint;
  }

  /**
   * Returns the messenger warnings emitted so far.
   */
  protected function warnings(): array {
    return $this->container->get('messenger')->messagesByType('warning');
  }

  /**
   * An XSAFE verdict raises no violation and emits no warning.
   */
  public function testSafeVerdictRaisesNothing(): void {
    $this->setChatResponse('XSAFE');

    $context = $this->createMock(ExecutionContextInterface::class);
    $context->expects($this->never())->method('addViolation');

    $this->buildValidator($context)
      ->validate((string) $this->file->id(), $this->buildConstraint());
    $this->assertSame([], $this->warnings());
  }

  /**
   * An XWARN verdict emits one deduplicated warning and no violation.
   */
  public function testWarnVerdictEmitsSingleWarning(): void {
    $this->setChatResponse('XWARN');

    $context = $this->createMock(ExecutionContextInterface::class);
    $context->expects($this->never())->method('addViolation');

    $this->buildValidator($context)
      ->validate((string) $this->file->id(), $this->buildConstraint());

    $warnings = $this->warnings();
    $this->assertCount(1, $warnings);
    $this->assertSame(self::WARNING, (string) $warnings[0]);

    // A second validation run in the same request (fresh validator instance,
    // same shared collector) must not stack a duplicate message.
    $context2 = $this->createMock(ExecutionContextInterface::class);
    $context2->expects($this->never())->method('addViolation');
    $this->buildValidator($context2)
      ->validate((string) $this->file->id(), $this->buildConstraint());
    $this->assertCount(1, $this->warnings());
  }

  /**
   * An XBLOCK verdict raises the constraint's blocking violation.
   */
  public function testBlockVerdictRaisesViolation(): void {
    $this->setChatResponse('XBLOCK');

    $context = $this->createMock(ExecutionContextInterface::class);
    $context->expects($this->once())
      ->method('addViolation')
      ->with(self::MESSAGE, []);

    $this->buildValidator($context)
      ->validate((string) $this->file->id(), $this->buildConstraint());
    $this->assertSame([], $this->warnings());
  }

  /**
   * An unrecognized response fails closed and blocks.
   */
  public function testUnrecognizedResponseFailsClosed(): void {
    $this->setChatResponse('I cannot help with that request.');

    $context = $this->createMock(ExecutionContextInterface::class);
    $context->expects($this->once())
      ->method('addViolation')
      ->with(self::MESSAGE, []);

    $this->buildValidator($context)
      ->validate((string) $this->file->id(), $this->buildConstraint());
  }

  /**
   * A verdict token that does not lead the response is not honored.
   *
   * Mirrors the anchoring of the boolean protocol: injected instructions
   * that cannot control the very start of the reply cannot force a milder
   * verdict.
   */
  public function testTokenNotAtStartFailsClosed(): void {
    $this->setChatResponse('I refuse to be manipulated. XSAFE');

    $context = $this->createMock(ExecutionContextInterface::class);
    $context->expects($this->once())
      ->method('addViolation')
      ->with(self::MESSAGE, []);

    $this->buildValidator($context)
      ->validate((string) $this->file->id(), $this->buildConstraint());
  }

  /**
   * Legacy boolean tokens still parse in ternary mode.
   */
  public function testLegacyTokensInTernaryMode(): void {
    $this->setChatResponse('XTRUE');
    $context = $this->createMock(ExecutionContextInterface::class);
    $context->expects($this->never())->method('addViolation');
    $this->buildValidator($context)
      ->validate((string) $this->file->id(), $this->buildConstraint());

    $this->setChatResponse('XFALSE');
    $context = $this->createMock(ExecutionContextInterface::class);
    $context->expects($this->once())
      ->method('addViolation')
      ->with(self::MESSAGE, []);
    $this->buildValidator($context)
      ->validate((string) $this->file->id(), $this->buildConstraint());
  }

  /**
   * With no warning message the legacy boolean protocol runs unchanged.
   */
  public function testLegacyModeUnchanged(): void {
    $legacy = ['warningMessage' => '', 'prompt' => 'Answer XTRUE or XFALSE.'];

    $this->setChatResponse('XTRUE');
    $context = $this->createMock(ExecutionContextInterface::class);
    $context->expects($this->never())->method('addViolation');
    $this->buildValidator($context)
      ->validate((string) $this->file->id(), $this->buildConstraint($legacy));

    $this->setChatResponse('XFALSE');
    $context = $this->createMock(ExecutionContextInterface::class);
    $context->expects($this->once())
      ->method('addViolation')
      ->with(self::MESSAGE, []);
    $this->buildValidator($context)
      ->validate((string) $this->file->id(), $this->buildConstraint($legacy));

    // The legacy protocol only recognizes a leading XTRUE: an XWARN response
    // blocks and emits no warning.
    $this->setChatResponse('XWARN');
    $context = $this->createMock(ExecutionContextInterface::class);
    $context->expects($this->once())
      ->method('addViolation')
      ->with(self::MESSAGE, []);
    $this->buildValidator($context)
      ->validate((string) $this->file->id(), $this->buildConstraint($legacy));
    $this->assertSame([], $this->warnings());
  }

  /**
   * The user message ends with the reply-format reminder for the protocol.
   *
   * AI Context appends context after the prompt's own format instruction,
   * so the reminder in the user message keeps the anchored token last in
   * the model's view.
   */
  public function testFormatReminderMatchesProtocol(): void {
    $state = $this->container->get('state');
    $context = $this->createMock(ExecutionContextInterface::class);

    $this->setChatResponse('XSAFE');
    $this->buildValidator($context)
      ->validate((string) $this->file->id(), $this->buildConstraint());
    $state->resetCache();
    $last = $state->get(ClassifierTestProvider::STATE_LAST_CHAT);
    $this->assertSame(
      (string) $this->file->id() . "\n\nValidator instruction, not part of the content above: begin your reply with XSAFE, XWARN or XBLOCK.",
      $last['user_text'],
    );

    $this->setChatResponse('XTRUE');
    $this->buildValidator($context)->validate(
      (string) $this->file->id(),
      $this->buildConstraint(['warningMessage' => '', 'prompt' => 'Answer XTRUE or XFALSE.']),
    );
    $state->resetCache();
    $last = $state->get(ClassifierTestProvider::STATE_LAST_CHAT);
    $this->assertStringEndsWith(
      'begin your reply with XTRUE or XFALSE.',
      $last['user_text'],
    );
  }

  /**
   * A provider failure raises only the distinct failure violation.
   */
  public function testProviderFailureRaisesDistinctViolation(): void {
    $this->container->get('state')->set(ClassifierTestProvider::STATE_THROW, TRUE);

    $context = $this->createMock(ExecutionContextInterface::class);
    $context->expects($this->once())
      ->method('addViolation')
      ->with('AI provider failed to evaluate the content', []);

    $this->buildValidator($context)
      ->validate((string) $this->file->id(), $this->buildConstraint());
    $this->assertSame([], $this->warnings());
  }

  /**
   * Identical validations on one validator instance call the provider once.
   */
  public function testResponseMemoization(): void {
    $this->setChatResponse('XSAFE');
    $state = $this->container->get('state');

    $context = $this->createMock(ExecutionContextInterface::class);
    $context->expects($this->never())->method('addViolation');
    $validator = $this->buildValidator($context);

    $constraint = $this->buildConstraint();
    $validator->validate((string) $this->file->id(), $constraint);
    $validator->validate((string) $this->file->id(), $constraint);
    $state->resetCache();
    $this->assertSame(1, $state->get(ClassifierTestProvider::STATE_CHAT_CALL_COUNT));

    // A different prompt is a different fingerprint and calls the provider.
    $validator->validate(
      (string) $this->file->id(),
      $this->buildConstraint(['prompt' => 'A different prompt. Answer XSAFE, XWARN or XBLOCK.'])
    );
    $state->resetCache();
    $this->assertSame(2, $state->get(ClassifierTestProvider::STATE_CHAT_CALL_COUNT));
  }

  /**
   * Same file ID and prompt with a different image style is not memoized.
   *
   * Two image rules on one field with the same prompt and provider but
   * different image styles share a validator instance within a request.
   * Derivatives keep the original file name, so a filename-based cache key
   * would hand the second rule the first rule's verdict.
   */
  public function testDifferentImageStyleIsNotMemoized(): void {
    $style = ImageStyle::create([
      'name' => 'ai_validations_test_small',
      'label' => 'Small',
    ]);
    $style->addImageEffect([
      'id' => 'image_scale',
      'weight' => 0,
      'data' => ['width' => 10, 'height' => 10, 'upscale' => FALSE],
    ]);
    $style->save();
    $state = $this->container->get('state');

    $context = $this->createMock(ExecutionContextInterface::class);
    $context->expects($this->once())
      ->method('addViolation')
      ->with(self::MESSAGE, []);
    $validator = $this->buildValidator($context);

    $this->setChatResponse('XSAFE');
    $validator->validate((string) $this->file->id(), $this->buildConstraint());

    $this->setChatResponse('XBLOCK');
    $validator->validate(
      (string) $this->file->id(),
      $this->buildConstraint(['imageStyle' => 'ai_validations_test_small'])
    );

    $state->resetCache();
    $this->assertSame(2, $state->get(ClassifierTestProvider::STATE_CHAT_CALL_COUNT));
  }

  /**
   * Different images under the same filename each get their own verdict.
   *
   * Covers the case raised in review: two different images that share a
   * filename, validated with the same prompt, must not reuse one response.
   */
  public function testSameFilenameDifferentContentIsNotMemoized(): void {
    $state = $this->container->get('state');

    $context = $this->createMock(ExecutionContextInterface::class);
    $context->expects($this->once())
      ->method('addViolation')
      ->with(self::MESSAGE, []);
    $validator = $this->buildValidator($context);
    $constraint = $this->buildConstraint();

    $this->setChatResponse('XSAFE');
    $validator->validate((string) $this->file->id(), $constraint);

    // Swap in different image bytes behind the same file entity and name.
    $other = \Drupal::root() . '/core/tests/fixtures/files/image-1.png';
    file_put_contents('public://image-test.png', file_get_contents($other));

    $this->setChatResponse('XBLOCK');
    $validator->validate((string) $this->file->id(), $constraint);

    $state->resetCache();
    $this->assertSame(2, $state->get(ClassifierTestProvider::STATE_CHAT_CALL_COUNT));
  }

}
