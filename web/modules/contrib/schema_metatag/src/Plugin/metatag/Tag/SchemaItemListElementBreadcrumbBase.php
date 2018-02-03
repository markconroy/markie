<?php

namespace Drupal\schema_metatag\Plugin\metatag\Tag;

use Drupal\Core\Url;

/**
 * All Schema.org Breadcrumb tags should extend this class.
 */
abstract class SchemaItemListElementBreadcrumbBase extends SchemaItemListElementBase {

  /**
   * {@inheritdoc}
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
  public static function testValue() {
    return 'Yes';
  }

  /**
   * {@inheritdoc}
   */
  public static function outputValue($input_value) {
    $output_value = parent::outputValue($input_value);
    $items = [];
    if (!empty($output_value)) {
      $items = [
        "@type" => "BreadcrumbList",
        "itemListElement" => $output_value,
      ];
    }
    return $items;
  }

  /**
   * {@inheritdoc}
   */
  public static function getItems($input_value) {
    $values = [];
    if (!empty($input_value)) {
      $entity_route = \Drupal::service('current_route_match')->getCurrentRouteMatch();
      $breadcrumbs = \Drupal::service('breadcrumb')->build($entity_route)->getLinks();
      $key = 1;
      foreach ($breadcrumbs as $item) {
        // Modules that add the current page to the breadcrumb set it to an
        // empty path, so an empty path is the current path.
        $url = $item->getUrl()->setAbsolute()->toString();
        if (empty($url)) {
          $url = Url::fromRoute('<current>')->setAbsolute()->toString();
        }
        $text = $item->getText();
        $text = is_object($text) ? $text->render() : $text;
        $values[$key] = [
          '@id' => $url,
          'name' => $text,
        ];
        $key++;
      }
    }
    return $values;
  }

}
