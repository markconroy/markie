<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_validations\Unit;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\ai\OperationType\GenericType\ImageFile;
use Drupal\ai_validations\AiConstraintValidatorBase;
use Drupal\file\Entity\File;
use Drupal\image\ImageStyleInterface;

/**
 * @coversDefaultClass \Drupal\ai_validations\AiConstraintValidatorBase
 * @group ai_validations
 * @group 3601250
 */
class AiConstraintValidatorBaseTest extends UnitTestCase {

  /**
   * Storage mock for the image_style entity type.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  private EntityStorageInterface $imageStyleStorage;

  /**
   * The validator under test (abstract class mocked for instantiation).
   *
   * @var \Drupal\ai_validations\AiConstraintValidatorBase|\PHPUnit\Framework\MockObject\MockObject
   */
  private AiConstraintValidatorBase $validator;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    // The ai_validations namespace exists in two locations: this git clone and
    // the bundled copy inside ai/modules/ai_validations. Composer's optimized
    // classmap points to the bundled copy. Prepend our own PSR-4 loader so it
    // wins before Composer's classmap is consulted. This matches the pattern
    // used in core's AnnotatedClassDiscoveryTest.
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

    $this->imageStyleStorage = $this->createMock(EntityStorageInterface::class);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')
      ->with('image_style')
      ->willReturn($this->imageStyleStorage);

    // AiProviderPluginManager is final and cannot be mocked. Since
    // applyImageStyle() only uses entityTypeManager, bypass the constructor
    // and inject the dependency directly.
    $this->validator = $this->createMock(AiConstraintValidatorBase::class);

    $property = new \ReflectionProperty($this->validator, 'entityTypeManager');
    $property->setValue($this->validator, $entityTypeManager);
  }

  /**
   * Calls the protected applyImageStyle() via reflection.
   *
   * @param \Drupal\ai\OperationType\GenericType\ImageFile $image
   *   The ImageFile object to populate.
   * @param \Drupal\file\Entity\File $file
   *   The original uploaded file entity.
   * @param string|null $imageStyleId
   *   An image style machine name, or NULL to use the original.
   */
  private function invokeApplyImageStyle(ImageFile $image, File $file, ?string $imageStyleId): void {
    $method = new \ReflectionMethod($this->validator, 'applyImageStyle');
    $method->invoke($this->validator, $image, $file, $imageStyleId);
  }

  /**
   * No style ID: the original file is used directly without touching storage.
   *
   * @covers ::applyImageStyle
   */
  public function testNoStyleIdUsesOriginalFile(): void {
    $file = $this->createMock(File::class);
    $image = $this->createMock(ImageFile::class);
    $image->expects($this->once())->method('setFileFromFile')->with($file);
    $image->expects($this->never())->method('setFileFromUri');
    $this->imageStyleStorage->expects($this->never())->method('load');

    $this->invokeApplyImageStyle($image, $file, NULL);
  }

  /**
   * Style ID given but the style entity does not exist: falls back to original.
   *
   * @covers ::applyImageStyle
   */
  public function testMissingStyleFallsBackToOriginal(): void {
    $this->imageStyleStorage->method('load')->with('nonexistent')->willReturn(NULL);

    $file = $this->createMock(File::class);
    $image = $this->createMock(ImageFile::class);
    $image->expects($this->once())->method('setFileFromFile')->with($file);
    $image->expects($this->never())->method('setFileFromUri');

    $this->invokeApplyImageStyle($image, $file, 'nonexistent');
  }

  /**
   * Derivative already on disk: uses it without calling createDerivative().
   *
   * @covers ::applyImageStyle
   */
  public function testExistingDerivativeIsUsedDirectly(): void {
    $derivative = tempnam(sys_get_temp_dir(), 'ai_validations_');

    $file = $this->createMock(File::class);
    $file->method('getFileUri')->willReturn('public://original.jpg');

    $style = $this->createMock(ImageStyleInterface::class);
    $style->method('buildUri')->with('public://original.jpg')->willReturn($derivative);
    $style->expects($this->never())->method('createDerivative');
    $this->imageStyleStorage->method('load')->with('medium')->willReturn($style);

    $image = $this->createMock(ImageFile::class);
    $image->expects($this->once())->method('setFileFromUri')->with($derivative);
    $image->expects($this->never())->method('setFileFromFile');

    $this->invokeApplyImageStyle($image, $file, 'medium');

    unlink($derivative);
  }

  /**
   * Derivative is created successfully: its URI is passed to ImageFile.
   *
   * @covers ::applyImageStyle
   */
  public function testDerivativeCreatedSuccessfully(): void {
    $derivative = sys_get_temp_dir() . '/ai_validations_new_' . uniqid() . '.jpg';

    $file = $this->createMock(File::class);
    $file->method('getFileUri')->willReturn('public://original.jpg');

    $style = $this->createMock(ImageStyleInterface::class);
    $style->method('buildUri')->with('public://original.jpg')->willReturn($derivative);
    $style->method('createDerivative')->willReturnCallback(static function () use ($derivative): bool {
      touch($derivative);
      return TRUE;
    });
    $this->imageStyleStorage->method('load')->with('medium')->willReturn($style);

    $image = $this->createMock(ImageFile::class);
    $image->expects($this->once())->method('setFileFromUri')->with($derivative);
    $image->expects($this->never())->method('setFileFromFile');

    $this->invokeApplyImageStyle($image, $file, 'medium');

    unlink($derivative);
  }

  /**
   * CreateDerivative() produces no file: falls back to the original.
   *
   * @covers ::applyImageStyle
   */
  public function testDerivativeCreationFailureFallsBackToOriginal(): void {
    $derivative = sys_get_temp_dir() . '/ai_validations_fail_' . uniqid() . '.jpg';

    $file = $this->createMock(File::class);
    $file->method('getFileUri')->willReturn('public://original.jpg');

    $style = $this->createMock(ImageStyleInterface::class);
    $style->method('buildUri')->with('public://original.jpg')->willReturn($derivative);
    // createDerivative does nothing — the derivative file is never written.
    $this->imageStyleStorage->method('load')->with('medium')->willReturn($style);

    $image = $this->createMock(ImageFile::class);
    $image->expects($this->never())->method('setFileFromUri');
    $image->expects($this->once())->method('setFileFromFile')->with($file);

    $this->invokeApplyImageStyle($image, $file, 'medium');
  }

}
