<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_chatbot\Unit;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\Context\CacheContextsManager;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Theme\ActiveTheme;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\ai_chatbot\Hook\ChatbotHooks;
use Drupal\block\BlockInterface;

/**
 * Tests the ai_chatbot hook implementations.
 *
 * @group ai_chatbot
 */
class ChatbotHooksTest extends UnitTestCase {

  /**
   * The cache tags the mocked block entity definition reports.
   */
  private const LIST_CACHE_TAGS = ['config:block_list'];

  /**
   * The cache tags the mocked block entity reports.
   */
  private const BLOCK_CACHE_TAGS = ['config:block.block.deepchat'];

  /**
   * The cache contexts every lookup records, whatever the outcome.
   */
  private const LOOKUP_CACHE_CONTEXTS = ['theme', 'user.permissions'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Cache::mergeContexts() validates its tokens through the container, so
    // any code building cacheability metadata needs this service present.
    $cacheContextsManager = $this->createMock(CacheContextsManager::class);
    $cacheContextsManager->method('assertValidTokens')->willReturn(TRUE);

    $container = new ContainerBuilder();
    $container->set('string_translation', $this->getStringTranslationStub());
    $container->set('cache_contexts_manager', $cacheContextsManager);
    \Drupal::setContainer($container);
  }

  /**
   * Tests topbar() is a no-op without the "access deepchat api" permission.
   */
  public function testTopbarSkippedWithoutPermission(): void {
    $currentUser = $this->createMock(AccountProxyInterface::class);
    $currentUser->method('hasPermission')->with('access deepchat api')->willReturn(FALSE);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->expects($this->never())->method('getStorage');

    $hooks = new ChatbotHooks($currentUser, $entityTypeManager, $this->createMock(ThemeManagerInterface::class));

    $variables = [];
    $hooks->topbar($variables);

    $this->assertArrayNotHasKey('tools', $variables);
    // The permission is part of the decision, so it is cached with it even
    // though the lookup stopped before reading any block.
    $this->assertSame(self::LOOKUP_CACHE_CONTEXTS, $variables['#cache']['contexts']);
    $this->assertSame([], $variables['#cache']['tags']);
  }

  /**
   * Tests topbar() adds cacheability but no button when no block exists.
   */
  public function testTopbarSkipsButtonWithoutDeepChatBlock(): void {
    $hooks = $this->createHooks(TRUE, []);

    $variables = [];
    $hooks->topbar($variables);

    $this->assertArrayNotHasKey('tools', $variables);
    $this->assertSame(self::LOOKUP_CACHE_CONTEXTS, $variables['#cache']['contexts']);
    $this->assertSame(self::LIST_CACHE_TAGS, $variables['#cache']['tags']);
  }

  /**
   * Tests topbar() adds the button for a toolbar-placed accessible block.
   */
  public function testTopbarAddsButtonWithToolbarDeepChatBlock(): void {
    $hooks = $this->createHooks(TRUE, [$this->mockBlock('toolbar')]);

    $variables = [];
    $hooks->topbar($variables);

    $this->assertCount(1, $variables['tools']);
    $this->assertSame('button', $variables['tools'][0]['#tag']);
    $this->assertSame(-9999, $variables['tools'][0]['#weight']);
    $this->assertSame(
      Cache::mergeTags(self::LIST_CACHE_TAGS, self::BLOCK_CACHE_TAGS),
      $variables['#cache']['tags'],
    );
  }

  /**
   * Tests topbar() skips the button for a block placed outside the toolbar.
   */
  public function testTopbarSkipsButtonWithNonToolbarPlacement(): void {
    $hooks = $this->createHooks(TRUE, [$this->mockBlock('bottom-right')]);

    $variables = [];
    $hooks->topbar($variables);

    $this->assertArrayNotHasKey('tools', $variables);
  }

  /**
   * Tests topbar() skips the button when block access is denied.
   */
  public function testTopbarSkipsButtonWhenBlockAccessIsForbidden(): void {
    $hooks = $this->createHooks(TRUE, [$this->mockBlock('toolbar', FALSE)]);

    $variables = [];
    $hooks->topbar($variables);

    $this->assertArrayNotHasKey('tools', $variables);
    // The forbidden access result still contributes its own cacheability.
    $this->assertSame(
      Cache::mergeTags(self::LIST_CACHE_TAGS, self::BLOCK_CACHE_TAGS),
      $variables['#cache']['tags'],
    );
  }

  /**
   * Tests pageAttachments() attaches the libraries for a toolbar block.
   */
  public function testPageAttachmentsAttachesLibrariesWithToolbarBlock(): void {
    $hooks = $this->createHooks(TRUE, [$this->mockBlock('toolbar')]);

    $attachments = [];
    $hooks->pageAttachments($attachments);

    $this->assertSame([
      'ai_chatbot/toolbar-chatbot-early',
      'ai_chatbot/toolbar-chatbot',
    ], $attachments['#attached']['library']);
  }

  /**
   * Tests pageAttachments() attaches nothing when no toolbar block applies.
   */
  public function testPageAttachmentsSkipsLibrariesWithoutDeepChatBlock(): void {
    $hooks = $this->createHooks(TRUE, []);

    $attachments = [];
    $hooks->pageAttachments($attachments);

    $this->assertArrayNotHasKey('#attached', $attachments);
    $this->assertSame(self::LIST_CACHE_TAGS, $attachments['#cache']['tags']);
  }

  /**
   * Tests toolbar() returns a cache-only entry without permission.
   */
  public function testToolbarReturnsCacheOnlyEntryWithoutPermission(): void {
    $currentUser = $this->createMock(AccountProxyInterface::class);
    $currentUser->method('hasPermission')->with('access deepchat api')->willReturn(FALSE);

    $hooks = new ChatbotHooks($currentUser, $this->createMock(EntityTypeManagerInterface::class), $this->createMock(ThemeManagerInterface::class));

    $items = $hooks->toolbar();

    // The negative decision is cached with the conditions that produced it,
    // so no tab is rendered but the entry still carries its cacheability.
    $this->assertSame(['ai_chatbot'], array_keys($items));
    $this->assertSame(['#cache'], array_keys($items['ai_chatbot']));
    $this->assertSame(self::LOOKUP_CACHE_CONTEXTS, $items['ai_chatbot']['#cache']['contexts']);
  }

  /**
   * Tests toolbar() returns the assistant tab for a toolbar block.
   */
  public function testToolbarReturnsItemWithToolbarDeepChatBlock(): void {
    $hooks = $this->createHooks(TRUE, [$this->mockBlock('toolbar')]);

    $items = $hooks->toolbar();

    $this->assertArrayHasKey('ai_chatbot', $items);
    $this->assertSame('toolbar_item', $items['ai_chatbot']['#type']);
    $this->assertSame('button', $items['ai_chatbot']['tab']['#tag']);
    $this->assertSame(
      Cache::mergeTags(self::LIST_CACHE_TAGS, self::BLOCK_CACHE_TAGS),
      $items['ai_chatbot']['#cache']['tags'],
    );
  }

  /**
   * Tests theme_suggestions_ai_deepchat_alter() adds a placement suggestion.
   */
  public function testThemeSuggestionsAiDeepchatAlterAddsPlacementSuggestion(): void {
    $hooks = new ChatbotHooks(
      $this->createMock(AccountProxyInterface::class),
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(ThemeManagerInterface::class),
    );

    $suggestions = [];
    $hooks->themeSuggestionsAiDeepchatAlter($suggestions, ['settings' => ['placement' => 'bottom-right']]);

    $this->assertSame(['ai_deepchat__bottom_right'], $suggestions);
  }

  /**
   * Tests the block lookup fails gracefully when the theme lookup throws.
   */
  public function testTopbarFailsGracefullyOnException(): void {
    $currentUser = $this->createMock(AccountProxyInterface::class);
    $currentUser->method('hasPermission')->willReturn(TRUE);

    $themeManager = $this->createMock(ThemeManagerInterface::class);
    $themeManager->method('getActiveTheme')->willThrowException(new \Exception('No active theme.'));

    $hooks = new ChatbotHooks($currentUser, $this->mockEntityTypeManager(), $themeManager);

    $variables = [];
    $hooks->topbar($variables);

    $this->assertArrayNotHasKey('tools', $variables);
    // Whatever was collected before the failure is still merged in.
    $this->assertSame(self::LIST_CACHE_TAGS, $variables['#cache']['tags']);
  }

  /**
   * Builds a ChatbotHooks instance wired with the given blocks.
   *
   * @param bool $hasPermission
   *   Whether the mocked current user has the deepchat permission.
   * @param \Drupal\block\BlockInterface[] $blocks
   *   Blocks returned by the block storage.
   *
   * @return \Drupal\ai_chatbot\Hook\ChatbotHooks
   *   The hook implementations under test.
   */
  private function createHooks(bool $hasPermission, array $blocks): ChatbotHooks {
    $currentUser = $this->createMock(AccountProxyInterface::class);
    $currentUser->method('hasPermission')->with('access deepchat api')->willReturn($hasPermission);

    $activeTheme = $this->createMock(ActiveTheme::class);
    $activeTheme->method('getName')->willReturn('olivero');

    $themeManager = $this->createMock(ThemeManagerInterface::class);
    $themeManager->method('getActiveTheme')->willReturn($activeTheme);

    return new ChatbotHooks($currentUser, $this->mockEntityTypeManager($blocks), $themeManager);
  }

  /**
   * Mocks the entity type manager the block lookup goes through.
   *
   * @param \Drupal\block\BlockInterface[] $blocks
   *   Blocks returned for the enabled chatbot block query.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   *   The mocked entity type manager.
   */
  private function mockEntityTypeManager(array $blocks = []): EntityTypeManagerInterface {
    $blockStorage = $this->createMock(EntityStorageInterface::class);
    $blockStorage->method('loadByProperties')
      ->with([
        'theme' => 'olivero',
        'plugin' => 'ai_deepchat_block',
        'status' => TRUE,
      ])
      ->willReturn($blocks);

    $entityType = $this->createMock(EntityTypeInterface::class);
    $entityType->method('getListCacheTags')->willReturn(self::LIST_CACHE_TAGS);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getDefinition')->with('block')->willReturn($entityType);
    $entityTypeManager->method('getStorage')->with('block')->willReturn($blockStorage);

    return $entityTypeManager;
  }

  /**
   * Creates a mocked block entity with the given placement and access.
   *
   * @param string $placement
   *   The value of the block's "placement" setting.
   * @param bool $accessAllowed
   *   Whether the current user may view the block.
   *
   * @return \Drupal\block\BlockInterface|\PHPUnit\Framework\MockObject\MockObject
   *   The mocked block.
   */
  private function mockBlock(string $placement, bool $accessAllowed = TRUE): BlockInterface {
    $block = $this->createMock(BlockInterface::class);
    $block->method('get')->with('settings')->willReturn(['placement' => $placement]);
    $block->method('getCacheContexts')->willReturn([]);
    $block->method('getCacheTags')->willReturn(self::BLOCK_CACHE_TAGS);
    $block->method('getCacheMaxAge')->willReturn(Cache::PERMANENT);
    $block->method('access')
      ->with('view', NULL, TRUE)
      ->willReturn($accessAllowed ? AccessResult::allowed() : AccessResult::forbidden());

    return $block;
  }

}
