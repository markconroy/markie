<?php

namespace Drupal\Tests\ai_search\Kernel\Update;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests ai_search_update_10008() — the RAG access-control migration.
 *
 * Verifies that the update hook:
 *  - Removes the old 'access_check' key from every RAG database entry.
 *  - Sets 'allow_access_bypass' to FALSE regardless of the previous value
 *    (so a previously bypassed configuration is forced to the safe state).
 *  - Does not touch configurations that have no 'rag_action' action, or that
 *    were already migrated (no 'access_check' key present).
 *
 * @group ai_search
 */
class RagActionUpdateHookTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'ai', 'ai_assistant_api'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Load the install file so ai_search_update_10008() is available.
    include_once dirname(__DIR__, 4) . '/ai_search.install';
  }

  /**
   * Builds the actions_enabled structure for a RAG action with given flag.
   *
   * @param int|bool $accessCheck
   *   The old access_check value to store.
   *
   * @return array
   *   An actions_enabled array.
   */
  private function actionsEnabledWith(int|bool $accessCheck): array {
    return [
      'rag_action' => [
        'rag_0' => [
          'database' => 'test_index',
          'access_check' => $accessCheck,
          'output_mode' => 'chunks',
        ],
      ],
    ];
  }

  /**
   * Tests that access_check=0 (old bypass-active state) is forced to safe.
   *
   * @covers ::ai_search_update_10008
   */
  public function testBypassConfigIsForcedToSafeDefault(): void {
    \Drupal::configFactory()
      ->getEditable('ai_assistant_api.ai_assistant.test_unsafe')
      ->set('actions_enabled', $this->actionsEnabledWith(0))
      ->save();

    ai_search_update_10008();

    $rag = \Drupal::config('ai_assistant_api.ai_assistant.test_unsafe')
      ->get('actions_enabled.rag_action.rag_0');

    $this->assertArrayNotHasKey('access_check', $rag, 'The old access_check key must be removed.');
    $this->assertFalse($rag['allow_access_bypass'], 'allow_access_bypass must be FALSE even when the old config had access_check=0 (bypass active).');
  }

  /**
   * Tests that access_check=1 (old enforce state) is migrated to safe default.
   *
   * @covers ::ai_search_update_10008
   */
  public function testEnforceConfigIsMigratedToSafeDefault(): void {
    \Drupal::configFactory()
      ->getEditable('ai_assistant_api.ai_assistant.test_safe')
      ->set('actions_enabled', $this->actionsEnabledWith(1))
      ->save();

    ai_search_update_10008();

    $rag = \Drupal::config('ai_assistant_api.ai_assistant.test_safe')
      ->get('actions_enabled.rag_action.rag_0');

    $this->assertArrayNotHasKey('access_check', $rag, 'The old access_check key must be removed.');
    $this->assertFalse($rag['allow_access_bypass'], 'allow_access_bypass must be FALSE when the old config had access_check=1.');
  }

  /**
   * Tests that config without a rag_action entry is not modified.
   *
   * @covers ::ai_search_update_10008
   */
  public function testConfigWithoutRagActionIsUnchanged(): void {
    $original = ['other_action' => ['key' => 'value']];

    \Drupal::configFactory()
      ->getEditable('ai_assistant_api.ai_assistant.test_other')
      ->set('actions_enabled', $original)
      ->save();

    ai_search_update_10008();

    $saved = \Drupal::config('ai_assistant_api.ai_assistant.test_other')
      ->get('actions_enabled');

    $this->assertSame($original, $saved, 'Configurations without a rag_action entry must not be modified.');
  }

  /**
   * Tests that already-migrated config (no access_check key) is not changed.
   *
   * @covers ::ai_search_update_10008
   */
  public function testAlreadyMigratedConfigIsUnchanged(): void {
    $original = [
      'rag_action' => [
        'rag_0' => [
          'database' => 'test_index',
          'allow_access_bypass' => FALSE,
        ],
      ],
    ];

    \Drupal::configFactory()
      ->getEditable('ai_assistant_api.ai_assistant.test_migrated')
      ->set('actions_enabled', $original)
      ->save();

    ai_search_update_10008();

    $saved = \Drupal::config('ai_assistant_api.ai_assistant.test_migrated')
      ->get('actions_enabled');

    $this->assertSame($original, $saved, 'Configurations already containing allow_access_bypass must not be re-processed.');
  }

}
