<?php

namespace Drupal\Tests\ai\Unit\OperationType\ImageToVideo;

use Drupal\ai\OperationType\GenericType\ImageFile;
use Drupal\ai\OperationType\ImageToVideo\ImageToVideoInput;
use PHPUnit\Framework\TestCase;

/**
 * Tests that the input functions function works.
 *
 * @group ai
 * @covers \Drupal\ai\OperationType\ImageToVideo\ImageToVideoInput
 */
class ImageToVideoInputTest extends TestCase {

  /**
   * Test getting and setting for the input.
   */
  public function testGetSet(): void {
    $input = $this->getInput();
    $this->assertEquals('bla.png', $input->getImageFile()->getFileName());
    $image = new ImageFile('bla', 'image/png', 'bla2.png');
    $input->setImageFile($image);
    $this->assertEquals('bla2.png', $input->getImageFile()->getFileName());
  }

  /**
   * Test getting and setting the optional prompt.
   */
  public function testPrompt(): void {
    $input = $this->getInput();
    // Defaults to NULL so image-only models are unaffected.
    $this->assertNull($input->getPrompt());
    $input->setPrompt('a cat playing the piano');
    $this->assertEquals('a cat playing the piano', $input->getPrompt());
    $this->assertEquals('a cat playing the piano', $input->toArray()['prompt']);
    $input->setPrompt(NULL);
    $this->assertNull($input->getPrompt());
  }

  /**
   * Helper function to get the events.
   *
   * @return \PHPUnit\Framework\MockObject\MockObject|\Drupal\ai\OperationType\ImageToVideo\ImageToVideoInput
   *   The input.
   */
  public function getInput(): ImageToVideoInput {
    $image = new ImageFile('bla', 'image/png', 'bla.png');
    return new ImageToVideoInput($image);
  }

}
