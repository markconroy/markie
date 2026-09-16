<?php

namespace Drupal\Tests\ai\Unit\OperationType\Chat\Tools;

use Drupal\ai\OperationType\Chat\Tools\ToolsFunctionInput;
use Drupal\ai\OperationType\Chat\Tools\ToolsPropertyInput;
use PHPUnit\Framework\TestCase;

/**
 * Tests that the input functions function works.
 *
 * @group ai
 * @covers \Drupal\ai\OperationType\Chat\Tools\ToolsFunctionInput
 */
class ToolsFunctionInputTest extends TestCase {

  /**
   * Test the constructor.
   *
   * @covers \Drupal\ai\OperationType\Chat\Tools\ToolsFunctionInput::__construct
   * @covers \Drupal\ai\OperationType\Chat\Tools\ToolsFunctionInput::getName
   * @covers \Drupal\ai\OperationType\Chat\Tools\ToolsFunctionInput::setName
   * @covers \Drupal\ai\OperationType\Chat\Tools\ToolsFunctionInput::getDescription
   * @covers \Drupal\ai\OperationType\Chat\Tools\ToolsFunctionInput::setDescription
   * @covers \Drupal\ai\OperationType\Chat\Tools\ToolsFunctionInput::getProperties
   * @covers \Drupal\ai\OperationType\Chat\Tools\ToolsFunctionInput::setProperties
   * @group ai
   */
  public function testConstructor() {
    $function = new ToolsFunctionInput();
    $this->assertEquals('', $function->getName(), 'Initial function name');
    $this->assertEquals('', $function->getDescription(), 'Initial function description');
    $this->assertEquals([], $function->getProperties(), 'Initial function properties');
    $this->assertEquals([], $function->getRequiredProperties(), 'Initial function required properties');

    $property = new ToolsPropertyInput('test', [
      'description' => 'Test description',
      'type' => 'string',
      'default' => 'test',
      'required' => TRUE,
    ]);
    $property2 = new ToolsPropertyInput('test2', [
      'description' => 'Test description',
      'type' => 'string',
      'default' => 'test',
    ]);
    $function = new ToolsFunctionInput('test', [
      'description' => 'Test description',
      'properties' => [$property, $property2],
    ]);
    $this->assertEquals('test', $function->getName(), 'Configured function name');
    $this->assertEquals('Test description', $function->getDescription(), 'Configured function description');
    $this->assertEquals(2, count($function->getProperties()), 'Configured function properties');
    $this->assertEquals(1, count($function->getRequiredProperties()), 'Configured function required properties');
    $this->assertEquals($property, $function->getProperties()['test'], 'Configured function properties test');
    $this->assertEquals($property2, $function->getProperties()['test2'], 'Configured function properties test2');
    $this->assertEquals($property, $function->getRequiredProperties()['test'], 'Configured function required properties test');
  }

  /**
   * Test that the rendered array is always a valid JSON Schema object.
   *
   * @covers \Drupal\ai\OperationType\Chat\Tools\ToolsFunctionInput::renderFunctionArray
   * @group ai
   */
  public function testRenderFunctionArray() {
    // A function without properties still has to render a schema object, since
    // 'parameters' => NULL breaks OpenAI compatible endpoints.
    $function = new ToolsFunctionInput('list_pages', [
      'description' => 'List pages.',
    ]);
    $output = $function->renderFunctionArray();
    $this->assertEquals([
      'name' => 'list_pages',
      'description' => 'List pages.',
      'parameters' => [
        'type' => 'object',
      ],
    ], $output, 'No argument function renders an empty schema object');
    $this->assertArrayNotHasKey('properties', $output['parameters'], 'No argument function renders no properties');
    $this->assertArrayNotHasKey('required', $output['parameters'], 'No argument function renders no required');
    // An empty properties array would encode as [] instead of the {} a schema
    // requires, so assert on the encoded form as well.
    $this->assertSame('{"type":"object"}', json_encode($output['parameters']), 'No argument schema encodes as an object');

    // A function with properties renders unchanged.
    $property = new ToolsPropertyInput('test', [
      'description' => 'Test description',
      'type' => 'string',
      'required' => TRUE,
    ]);
    $property2 = new ToolsPropertyInput('test2', [
      'description' => 'Test description',
      'type' => 'string',
    ]);
    $function = new ToolsFunctionInput('test', [
      'description' => 'Test description',
      'properties' => [$property, $property2],
    ]);
    $this->assertEquals([
      'name' => 'test',
      'description' => 'Test description',
      'parameters' => [
        'type' => 'object',
        'properties' => [
          'test' => $property->renderPropertyArray(),
          'test2' => $property2->renderPropertyArray(),
        ],
        'required' => [
          'test',
        ],
      ],
    ], $function->renderFunctionArray(), 'Function with properties renders the full schema');
  }

}
