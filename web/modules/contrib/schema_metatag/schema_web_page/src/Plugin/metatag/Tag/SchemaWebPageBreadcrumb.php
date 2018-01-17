<?php

namespace Drupal\schema_web_page\Plugin\metatag\Tag;

use \Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;
use \Drupal\Core\Url;

/**
 * Provides a plugin for the 'schema_web_page_breadcrumb' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_web_page_breadcrumb",
 *   label = @Translation("breadcrumb"),
 *   description = @Translation("Add the breadcrumb for the current web page to Schema.org structured data?"),
 *   name = "breadcrumb",
 *   group = "schema_web_page",
 *   weight = 1,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaWebPageBreadcrumb extends SchemaNameBase {

  /**
   * Generate a form element for this meta tag.
   */

  /**
   * Generate a form element for this meta tag.
   */
  public function form(array $element = []) {
    $form = [
      '#type' => 'select',
      '#title' => $this->label(),
      '#default_value' => $this->value(),
      '#empty_option' => t('No'),
      '#empty_value' => '',
      '#options' => [
        'Yes' => $this->t('Yes'),
      ],
      '#description' => $this->description(),
      '#element_validate' => [[get_class($this), 'validateTag']],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function output() {
    $element = parent::output();
    if (!empty($element)) {
      $entity_route = \Drupal::service('current_route_match')->getCurrentRouteMatch();
      $breadcrumbs = \Drupal::service('breadcrumb')->build($entity_route)->getLinks();
      $key = 1;
      $element['#attributes']['content'] = [
        "@type" => "BreadcrumbList",
        "itemListElement" => [],
      ];
      foreach ($breadcrumbs as $item) {
        // Modules that add the current page to the breadcrumb set it to an
        // empty path, so an empty path is the current path.
        $url = $item->getUrl()->setAbsolute()->toString();
        if (empty($url)) {
          $url = Url::fromRoute('<current>')->setAbsolute()->toString();
        }
        $text = $item->getText();
        $text = is_object($text) ? $text->render() : $text;
        $element['#attributes']['content']['itemListElement'][] = [
          '@type' => 'ListItem',
          'position' => $key,
          'item' => [
            '@id' => $url,
            'name' => $text,
          ],
        ];
        $key++;
      }
    }
    return $element;
  }

}
