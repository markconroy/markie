<?php

namespace Drupal\Tests\pathauto\Kernel;

use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Path\PathValidatorInterface;
use Drupal\Core\Url;
use Drupal\KernelTests\KernelTestBase;
use Drupal\pathauto\PathautoGeneratorInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the pathauto alias storage helper.
 *
 * @group pathauto
 */
#[Group('pathauto')]
#[RunTestsInSeparateProcesses]
class AliasStorageHelperTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'path',
    'path_alias',
    'pathauto',
    'token',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('path_alias');
    $this->installConfig(['pathauto']);
  }

  /**
   * Tests that an alias is saved before its message link is resolved.
   */
  public function testAliasSavedBeforeMessageLinkResolution(): void {
    $source = '/node/1';
    $alias = '/pathauto-save-order-test';

    $this->mockPathValidator($alias);

    $saved_alias = $this->container
      ->get('pathauto.alias_storage_helper')
      ->save([
        'source' => $source,
        'alias' => $alias,
        'language' => LanguageInterface::LANGCODE_NOT_SPECIFIED,
      ]);

    $this->assertSame($source, $saved_alias['source']);
    $this->assertSame($alias, $saved_alias['alias']);
    $this->assertNotEmpty($saved_alias['pid']);
    $this->assertSame(
      LanguageInterface::LANGCODE_NOT_SPECIFIED,
      $saved_alias['langcode'],
    );
  }

  /**
   * Tests that an existing alias is saved before its message link is resolved.
   */
  public function testExistingAliasSavedBeforeMessageLinkResolution(): void {
    $source = '/node/1';
    $old_alias = '/old-alias';
    $new_alias = '/new-alias';

    $storage = $this->container
      ->get('entity_type.manager')
      ->getStorage('path_alias');

    // Configure Pathauto to replace the existing alias.
    $this->config('pathauto.settings')
      ->set(
        'update_action',
        PathautoGeneratorInterface::UPDATE_ACTION_DELETE,
      )
      ->save();

    // Create the alias that Pathauto will update.
    $existing_alias = $storage->create([
      'path' => $source,
      'alias' => $old_alias,
      'langcode' => LanguageInterface::LANGCODE_NOT_SPECIFIED,
    ]);
    $existing_alias->save();

    $existing_id = $existing_alias->id();

    $this->mockPathValidator($new_alias, (int) $existing_id);

    $result = $this->container
      ->get('pathauto.alias_storage_helper')
      ->save(
        [
          'source' => $source,
          'alias' => $new_alias,
          'language' => LanguageInterface::LANGCODE_NOT_SPECIFIED,
        ],
        [
          'source' => $source,
          'alias' => $old_alias,
          'pid' => $existing_id,
          'langcode' => LanguageInterface::LANGCODE_NOT_SPECIFIED,
        ],
      );

    $this->assertSame($source, $result['source']);
    $this->assertSame($new_alias, $result['alias']);

    // This is particularly important for the `$return = $existing_alias`
    // branch: the existing entity must have been updated rather than replaced.
    $this->assertSame($existing_id, $result['pid']);

    $this->assertSame(
      LanguageInterface::LANGCODE_NOT_SPECIFIED,
      $result['langcode'],
    );

    // There should still only be one alias entity for this source.
    $aliases = $storage->loadByProperties([
      'path' => $source,
    ]);

    $this->assertCount(1, $aliases);
  }

  /**
   * Mocks path validation and asserts the alias is already persisted.
   *
   * @param string $alias
   *   The alias that Pathauto will resolve.
   * @param int|null $expected_id
   *   The expected path alias entity ID, if checking an existing alias update.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \PHPUnit\Framework\MockObject\Exception
   */
  protected function mockPathValidator(string $alias, ?int $expected_id = NULL): void {
    $storage = $this->container
      ->get('entity_type.manager')
      ->getStorage('path_alias');

    $path_validator = $this->createMock(PathValidatorInterface::class);

    $path_validator
      ->expects($this->once())
      ->method('getUrlIfValidWithoutAccessCheck')
      ->with(ltrim($alias, '/'))
      ->willReturnCallback(
        function (string $path) use ($storage, $alias, $expected_id): Url {
          // Verify that the alias has already been persisted by the time
          // Url::fromUri('internal:...') attempts to resolve it.
          $matches = $storage->loadByProperties([
            'alias' => $alias,
          ]);

          $this->assertCount(
            1,
            $matches,
            sprintf(
              'The alias %s must be persisted before it is resolved.',
              $alias,
            ),
          );

          // For the existing-alias test, also verify that Pathauto updated the
          // original entity instead of creating a replacement entity.
          if ($expected_id !== NULL) {
            $path_alias = reset($matches);

            $this->assertSame(
              $expected_id,
              (int) $path_alias->id(),
              'The existing path alias entity should be updated.',
            );
          }

          // The real routing result is irrelevant to this regression test.
          // Return a valid routed URL so link generation can continue.
          return Url::fromRoute('<front>');
        },
      );

    $this->container->set('path.validator', $path_validator);
  }

}
