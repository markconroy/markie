<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_automators\FunctionalJavascript\Plugin\FieldWidgetAction;

use Drupal\taxonomy\Entity\Term;

/**
 * Tests the field widget action for the Tagify Select widget.
 *
 * Tagify Select is a plain multi-value select underneath, so it reuses the
 * ClassificationOptionsSelect plugin.
 *
 * @group ai_automators
 * @group 3536912
 */
class TagifySelectTaxonomyTest extends TagifyTaxonomyTest {

  /**
   * {@inheritdoc}
   */
  protected string $fixtureDirectory = 'tagify_select_taxonomy_test';

  /**
   * {@inheritdoc}
   */
  protected function installFixtures(): void {
    // The widget only renders as a multi-select when it has more than one
    // option, so seed the vocabulary before the display is built.
    foreach (['Existing one', 'Existing two'] as $name) {
      Term::create(['vid' => 'tags', 'name' => $name])->save();
    }
    parent::installFixtures();
  }

  /**
   * Tests suggesting tags into the Tagify Select widget.
   */
  public function testCreateTagsFieldWidgetAction(): void {
    $this->drupalLogin($this->createArticleAuthor());
    $this->drupalGet('/node/add/article');
    $this->takeScreenshot('1_initial_form');

    $page = $this->getSession()->getPage();
    $page->fillField('title[0][value]', 'Test Article with Tags');
    $page->fillField('body[0][value]', 'Monkeys are cool animals');
    $this->takeScreenshot('2_filled_form');

    $this->click('.field-widget-action-classification_options_select');
    $this->takeScreenshot('3_after_add_tags_button');

    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->takeScreenshot('4_after_ajax_call');

    $terms = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadByProperties(['name' => 'monkeys']);
    $this->assertCount(1, $terms, 'The automator created the suggested term.');
    $term = reset($terms);

    // Assert on the underlying select rather than the rendered chips — that
    // is what the field submits, and it survives the Tagify library being
    // unavailable.
    $option = $page->find('css', 'select[name="field_tags[]"] option[value="' . $term->id() . '"]');
    $this->assertNotNull($option, 'The suggested term is an option of the select.');
    $this->assertTrue($option->isSelected(), 'The suggested term is selected.');
  }

}
