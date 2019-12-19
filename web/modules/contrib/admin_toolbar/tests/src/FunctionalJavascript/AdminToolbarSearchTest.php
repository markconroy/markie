<?php

namespace Drupal\Tests\admin_toolbar\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\media\Entity\MediaType;
use Drupal\system\Entity\Menu;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;

/**
 * Test the functionality of admin toolbar search.
 *
 * @group admin_toolbar
 */
class AdminToolbarSearchTest extends WebDriverTestBase {

  use MediaTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'admin_toolbar',
    'admin_toolbar_tools',
    'node',
    'media',
    'field_ui',
    'menu_ui',
    'block',
  ];

  /**
   * The admin user for tests.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->drupalCreateContentType([
      'type' => 'article',
      'name' => 'Article',
    ]);

    $dog_names = [
      'archie' => 'Archie',
      'bailey' => 'Bailey',
      'bella' => 'Bella',
      'buddy' => 'Buddy',
      'charlie' => 'Charlie',
      'coco' => 'Coco',
      'daisy' => 'Daisy',
      'frankie' => 'Frankie',
      'jack' => 'Jack',
      'lola' => 'Lola',
      'lucy' => 'Lucy',
      'max' => 'Max',
      'milo' => 'Milo',
      'molly' => 'Molly',
      'ollie' => 'Ollie',
      'oscar' => 'Oscar',
      'rosie' => 'Rosie',
      'ruby' => 'Ruby',
      'teddy' => 'Teddy',
      'toby' => 'Toby',
    ];

    foreach ($dog_names as $machine_name => $label) {
      $this->createMediaType('image', [
        'id' => $machine_name,
        'label' => $label,
      ]);
    }

    $baby_names = [
      'ada' => 'Ada',
      'amara' => 'Amara',
      'amelia' => 'Amelia',
      'arabella' => 'Arabella',
      'asher' => 'Asher',
      'astrid' => 'Astrid',
      'atticus' => 'Atticus',
      'aurora' => 'Aurora',
      'ava' => 'Ava',
      'cora' => 'Cora',
      'eleanor' => 'Eleanor',
      'eloise' => 'Eloise',
      'felix' => 'Felix',
      'freya' => 'Freya',
      'genevieve' => 'Genevieve',
      'isla' => 'Isla',
      'jasper' => 'Jasper',
      'luna' => 'Luna',
      'maeve' => 'Maeve',
      'milo' => 'Milo',
      'nora' => 'Nora',
      'olivia' => 'Olivia',
      'ophelia' => 'Ophelia',
      'posie' => 'Posie',
      'rose' => 'Rose',
      'silas' => 'Silas',
      'soren' => 'Soren',
    ];

    foreach ($baby_names as $id => $label) {
      $menu = Menu::create([
        'id' => $id,
        'label' => $label,
      ]);
      $menu->save();
    }

    $this->drupalPlaceBlock('local_tasks_block');

    $this->adminUser = $this->drupalCreateUser([
      'access toolbar',
      'administer menu',
      'access administration pages',
      'administer site configuration',
      'administer content types',
      'administer node fields',
      'access media overview',
      'administer media',
      'administer media fields',
      'administer media form display',
      'administer media display',
      'administer media types',
    ]);
  }

  /**
   * Tests search functionality.
   */
  public function testSearchFunctionality() {

    $search_tab = '#toolbar-item-administration-search';
    $search_tray = '#toolbar-item-administration-search-tray';

    $this->drupalLogin($this->adminUser);
    $this->assertSession()->responseContains('admin.toolbar_search.css');
    $this->assertSession()->responseContains('admin_toolbar_search.js');
    $this->assertSession()->waitForElementVisible('css', $search_tab)->click();
    $this->assertSession()->waitForElementVisible('css', $search_tray);

    $this->assertSuggestionContains('basic', 'admin/config/system/site-information');

    // Rebuild menu items.
    drupal_flush_all_caches();

    // Test that the route admin_toolbar.search returns expected json.
    $this->drupalGet('/admin/admin-toolbar-search');

    $search_menus = [
      'cora',
      'eleanor',
      'eloise',
      'felix',
      'freya',
      'genevieve',
      'isla',
      'jasper',
      'luna',
      'maeve',
      'milo',
      'nora',
      'olivia',
      'ophelia',
      'posie',
      'rose',
      'silas',
      'soren',
    ];

    $toolbar_menus = [
      'ada',
      'amara',
      'amelia',
      'arabella',
      'asher',
      'astrid',
      'atticus',
      'aurora',
      'ava',
    ];

    foreach ($search_menus as $menu_id) {
      $this->assertSession()->responseContains('\/admin\/structure\/menu\/manage\/' . $menu_id);
    }

    foreach ($toolbar_menus as $menu_id) {
      $this->assertSession()->responseNotContains('\/admin\/structure\/menu\/manage\/' . $menu_id);
    }

    $this->drupalGet('/admin');

    foreach ($search_menus as $menu_id) {
      $this->assertMenuDoesNotHaveHref('/admin/structure/menu/manage/' . $menu_id);
    }

    foreach ($toolbar_menus as $menu_id) {
      $this->assertMenuHasHref('/admin/structure/menu/manage/' . $menu_id);
    }

    $this->drupalGet('admin/structure/types/manage/article/fields');
    $this->assertSession()->waitForElementVisible('css', $search_tray);

    $this->assertSuggestionContains('article manage fields', '/admin/structure/types/manage/article/fields');

    $suggestions = $this->assertSession()
      ->waitForElementVisible('css', 'ul.ui-autocomplete');

    // Assert there is only one suggestion with a link to
    // /admin/structure/types/manage/article/fields.
    $count = count($suggestions->findAll('xpath', '//span[contains(text(), "/admin/structure/types/manage/article/fields")]'));
    $this->assertEquals(1, $count);

    // Test that bundle within admin toolbar appears in search.
    $this->assertSuggestionContains('lola', 'admin/structure/media/manage/lola/fields');

    // Assert that a link after the limit (10) doesn't appear in admin toolbar.
    $toby_url = '/admin/structure/media/manage/toby/fields';
    $this->assertSession()
      ->elementNotContains('css', '#toolbar-administration', $toby_url);

    // Assert that a link excluded from admin toolbar appears in search.
    $this->assertSuggestionContains('toby', $toby_url);

    // Test that adding a new bundle updates the extra links loaded from
    // admin_toolbar.search route.
    $this->createMediaType('image', [
      'id' => 'zuzu',
      'label' => 'Zuzu',
    ]);

    $this->drupalGet('admin');
    $this->assertSession()->waitForElementVisible('css', $search_tray);
    $this->assertSuggestionContains('zuzu', '/admin/structure/media/manage/zuzu/fields');

    // Test that deleting a bundle updates the extra links loaded from
    // admin_toolbar.search route.
    $toby = MediaType::load('toby');
    $toby->delete();

    $this->getSession()->reload();
    $this->assertSession()->waitForElementVisible('css', $search_tray);
    $this->assertSuggestionNotContains('toby', $toby_url);

  }

  /**
   * Assert that the search suggestions contain a given string with given input.
   *
   * @param string $search
   *   The string to search for.
   * @param string $contains
   *   Some HTML that is expected to be within the suggestions element.
   */
  protected function assertSuggestionContains($search, $contains) {
    $this->resetSearch();
    $page = $this->getSession()->getPage();
    $page->fillField('admin-toolbar-search-input', $search);
    $page->waitFor(3, function () use ($page) {
      return ($page->find('css', 'ul.ui-autocomplete')->isVisible() === TRUE);
    });
    $suggestions_markup = $page->find('css', 'ul.ui-autocomplete')->getHtml();
    $this->assertContains($contains, $suggestions_markup);
  }

  /**
   * Assert that the search suggestions does not contain a given string.
   *
   * Assert that the search suggestions does not contain a given string with a
   * given input.
   *
   * @param string $search
   *   The string to search for.
   * @param string $contains
   *   Some HTML that is not expected to be within the suggestions element.
   */
  protected function assertSuggestionNotContains($search, $contains) {
    $this->resetSearch();
    $page = $this->getSession()->getPage();
    $page->fillField('admin-toolbar-search-input', $search);
    $page->waitFor(3, function () use ($page) {
      return ($page->find('css', 'ul.ui-autocomplete')->isVisible() === TRUE);
    });
    if ($page->find('css', 'ul.ui-autocomplete')->isVisible() === FALSE) {
      return;
    }
    else {
      $suggestions_markup = $page->find('css', 'ul.ui-autocomplete')->getHtml();
      $this->assertNotContains($contains, $suggestions_markup);
    }
  }

  /**
   * Search for an empty string to clear out the autocomplete suggestions.
   */
  protected function resetSearch() {
    $page = $this->getSession()->getPage();
    // Empty out the suggestions.
    $page->fillField('admin-toolbar-search-input', '');
    $page->waitFor(3, function () use ($page) {
      return ($page->find('css', 'ul.ui-autocomplete')->isVisible() === FALSE);
    });
  }

  /**
   * Checks that there is a link with the specified url in the admin toolbar.
   *
   * @param string $url
   *   The url to assert exists in the admin menu.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   */
  protected function assertMenuHasHref($url) {
    $this->assertSession()
      ->elementExists('xpath', '//div[@id="toolbar-item-administration-tray"]//a[contains(@href, "' . $url . '")]');
  }

  /**
   * Checks that there is no link with the specified url in the admin toolbar.
   *
   * @param string $url
   *   The url to assert exists in the admin menu.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  protected function assertMenuDoesNotHaveHref($url) {
    $this->assertSession()
      ->elementNotExists('xpath', '//div[@id="toolbar-item-administration-tray"]//a[contains(@href, "' . $url . '")]');
  }

}
