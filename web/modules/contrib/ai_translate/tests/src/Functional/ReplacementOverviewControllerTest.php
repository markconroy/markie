<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_translate\Functional;

use Drupal\ai_translate\Controller\ContentTranslationControllerOverride;
use Drupal\ai_translate_overview_test\Controller\ReplacementOverviewController;
use Drupal\content_translation\Controller\ContentTranslationController;
use Drupal\entity_test\Entity\EntityTestMul;
use Drupal\entity_test\Entity\EntityTestMulChanged;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the Translate tab when another module replaced its controller.
 *
 * @group ai_translate
 */
#[CoversClass(ContentTranslationControllerOverride::class)]
#[Group('ai_translate')]
#[RunTestsInSeparateProcesses]
class ReplacementOverviewControllerTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ai_translate',
    'ai_test',
    'content_translation',
    'entity_test',
    'language',
    'ai_translate_overview_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    ConfigurableLanguage::createFromLangcode('fr')->save();
    $manager = $this->container->get('content_translation.manager');
    $manager->setEnabled('entity_test_mul', 'entity_test_mul', TRUE);
    $manager->setEnabled('entity_test_mul_changed', 'entity_test_mul_changed', TRUE);
    $this->container->get('router.builder')->rebuild();

    // Without a default translate_text provider, AI Translate leaves the
    // overview alone, so set one up to see its column.
    $config = $this->config('ai.settings');
    $default_providers = $config->get('default_providers') ?? [];
    $default_providers['chat'] = [
      'provider_id' => 'echoai',
      'model_id' => 'gpt-test',
    ];
    $default_providers['translate_text'] = [
      'provider_id' => 'chat_translation',
      'model_id' => 'gpt-test',
    ];
    $config->set('default_providers', $default_providers)->save();
  }

  /**
   * Tests that the replacement controller gets the arguments it asks for.
   */
  public function testReplacementOverviewController(): void {
    // AI Translate wraps the replacement controller, not core's.
    $route = $this->container->get('router.route_provider')
      ->getRouteByName('entity.entity_test_mul.content_translation_overview');
    $this->assertSame(ContentTranslationControllerOverride::class . '::overview', ltrim($route->getDefault('_controller'), '\\'));
    $this->assertSame(ReplacementOverviewController::class . '::overview', $route->getDefault('_parent_controller'));

    $entity = EntityTestMul::create(['name' => 'Overview test entity']);
    $entity->save();

    $this->drupalLogin($this->drupalCreateUser(['translate any entity', 'view test entity']));
    $this->drupalGet($entity->toUrl('drupal:content-translation-overview'));
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Replacement overview: Overview test entity');
  }

  /**
   * Tests that core's overview controller still gets its own arguments.
   *
   * Core's ContentTranslationController::overview() takes the route match and
   * the entity type ID, which now come from the argument resolver instead of
   * being passed directly.
   */
  public function testCoreOverviewController(): void {
    $route = $this->container->get('router.route_provider')
      ->getRouteByName('entity.entity_test_mul_changed.content_translation_overview');
    $this->assertSame(ContentTranslationControllerOverride::class . '::overview', ltrim($route->getDefault('_controller'), '\\'));
    $this->assertSame(ContentTranslationController::class . '::overview', ltrim($route->getDefault('_parent_controller'), '\\'));

    $entity = EntityTestMulChanged::create(['name' => 'Core overview test entity']);
    $entity->save();

    // AI Translate only links a translation the user may create, which takes
    // core's create access and update access to the entity.
    $this->drupalLogin($this->drupalCreateUser([
      'translate any entity',
      'view test entity',
      'administer entity_test content',
      'create content translations',
      'create ai content translation',
    ]));
    $this->drupalGet($entity->toUrl('drupal:content-translation-overview'));
    $this->assertSession()->statusCodeEquals(200);
    // Core's overview table, with AI Translate's column and its link to
    // translate into French.
    $this->assertSession()->elementExists('css', 'table');
    $this->assertSession()->pageTextContains('AI Translations');
    $this->assertSession()->linkByHrefExists('/ai_translate/translate/entity_test_mul_changed/' . $entity->id() . '/en/fr');
  }

}
