<?php

namespace Drupal\schema_audit;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\ClientInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class SchemaClient {

  /**
   * An http client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * Module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Construct a Schema client object.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   * @param \Drupal\Core\Extension\ModuleHandlerInterface
   */
  public function __construct(ClientInterface $httpClient, ModuleHandlerInterface $moduleHandler) {
    $this->httpClient = $httpClient;
    $this->moduleHandler = $moduleHandler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('http_client'),
      $container->get('module_handler')
    );
  }

  /**
   * Retrieve and decode object data from schema.org.
   *
   * @param string $object
   *   The name of the object to retrieve, i.e. 'Person', 'WebPage'.
   *
   * @return array
   *   A decoded array of Schema.org data.
   *
   * @see https://schema.org/docs/developers.html
   * @see https://github.com/schemaorg/schemaorg
   */
  public function getHttpResponse($object = '') {

    $url = 'http://schema.org/' . $object . '.jsonld';
    $options = ['http_errors' => FALSE];

    try {
      if ($response = $this->httpClient->request('GET', $url, $options)) {
        if ($data = $response->getBody()->getContents()) {
          $data = json_decode($data, TRUE);
          if (is_array($data) && array_key_exists('@graph', $data)) {
            return $data['@graph'];
          }
        }
      }
    }
    catch (RequestException $e) {
      watchdog_exception('schema_audit', $e);
    }
    return FALSE;
  }

  /**
   * Retrieve and decode data from a local schema.jsonld file.
   *
   * This is the only way to retrieve an array of all possible objects.
   * This file can be updated if it changes in the future by checking github.
   *
   * @return array
   *   A decoded array of Schema.org data.
   *
   * @see https://schema.org/docs/developers.html
   * @see https://github.com/schemaorg/schemaorg
   */
  public function getLocalResponse() {

    $domain = \Drupal::request()->getSchemeAndHttpHost();
    $path = DRUPAL_ROOT . '/' . $this->moduleHandler->getModule('schema_audit')->getPath();
    $uri = $path . '/data/schema.jsonld';

    try {
      if ($data = file_get_contents($uri)) {
        $data = json_decode($data, TRUE);
        if (is_array($data) && array_key_exists('@graph', $data)) {
          return $data['@graph'];
        }
      }
    }
    catch (RequestException $e) {
      watchdog_exception('schema_audit', $e);
    }
    return FALSE;
  }

  /**
   * Create a table array from Schema.org objects compared to Drupal.
   *
   * @param array $drupal
   *   An array retrieved using the DrupalClient.
   *
   * @return array
   *   An array of rows suitable for theme_table.
   */
  public function getSchemaTable($drupal, $google) {
    $items = [];
    $data = $this->getLocalResponse();
    $objects = $this->getObjects($data);
    $properties = $this->getProperties($data);
    $thing_properties = $properties['Thing'];

    // Just pull out the first two levels of the tree as top-level objects.
    $tree = $this->getTree($objects);

    foreach ($tree as $i => $branch) {
      $properties1 = array_key_exists($i, $properties) ? $properties[$i] : [];
      $all_properties = $properties1 + $thing_properties;
      $cells = $this->getCells($i, $all_properties, $objects, $properties, $drupal, $google);
      $items = array_merge($items, $cells);

      // For some objects, stop after highest level.
      $top_level = ['Place', 'Event'];
      if (in_array($i, $top_level)) {
        continue;
      }
      // For all objects but the ones above go down to the second level.
      foreach ($branch as $i2 => $branch2) {
        $properties2 = array_key_exists($i2, $properties) ? $properties[$i2] : [];
        $all_properties = $properties2 + $properties1 + $thing_properties;
        $cells = $this->getCells($i2, $all_properties, $objects, $properties, $drupal, $google);
        $items = array_merge($items, $cells);

        // Keep going only if we need a third level.
        $third_level_parents = [
          'MediaObject',
          'HowTo',
          'MusicPlaylist',
          'StructuredValue',
        ];
        if (!in_array($i2, $third_level_parents)) {
          continue;
        }

        foreach ($branch2 as $i3 => $branch3) {
          // These objects go down to the third level.
          $third_level = [
            'ImageObject',
            'VideoObject',
            'Recipe',
            'ContactPoint',
          ];
          if (in_array($i3, $third_level)) {
            $properties3 = array_key_exists($i3, $properties) ? $properties[$i3] : [];
            $all_properties = $properties3 + $properties2 + $properties1 + $thing_properties;
            $cells = $this->getCells($i3, $all_properties, $objects, $properties, $drupal, $google);
            $items = array_merge($items, $cells);
            continue;
          }
        }
      }
    }
    return $items;
  }

  public function getCells($i, $all_properties, $objects, $properties, $drupal, $google) {
    $items = [];
    $list = (array) $this->getOptionList($objects, $i);
    $list = array_merge([$i], $list);
    $list = ['#theme' => 'item_list', '#items' => $list];
    $mismatch = [];
    $count = count($all_properties) + 1;
    $delta = 0;
    // See if there is a comparable Drupal object.
    $drupal_item = '';
    if (array_key_exists($i, $drupal)) {
      $drupal_item = $drupal[$i];
      foreach ($drupal_item['properties'] as $name => $item) {
        $mismatch[$name] = $name;
      }
    }
    if (array_key_exists($i, $google)) {
      $google_item = $google[$i];
    }
    foreach ($all_properties as $property) {
       // See if there is a comparable Drupal property.
      $drupal_property = '';
      $google_property = '';
      $key = $property['property'];
      if (in_array($key, $mismatch)) {
        unset($mismatch[$key]);
      }
      if (!empty($drupal_item) && array_key_exists($key, $drupal_item['properties'])) {
        $drupal_property = $drupal_item['module'];
      }
      if (!empty($google_item) && array_key_exists($key, $google_item['properties'])) {
        $google_property = $google_item['properties'][$key];
      }
      $selected = !empty($drupal_item) ? $drupal_item['module'] : '';
      $google_selected = !empty($google_item) ? $google_item['title'] : '';
      $class = !empty($selected) ? ['selected'] : ['empty'];
      $property_class = !empty($drupal_property) ? ['selected'] : ['empty'];

      if ($delta == 0) {
        $class[] = 'first';
        $class_checkbox = $class;
        //$class_checkbox[] = 'checkbox';
        $property_class[] = 'first';
        $property_class_checkbox = $property_class;
        //$property_class_checkbox[] = 'checkbox';
        $items[] = [
          ['data' => $list, 'rowspan' => $count, 'class' => $class],
          ['data' => $google_selected, 'rowspan' => $count, 'class' => $class_checkbox],
          ['data' => $selected, 'rowspan' => $count, 'class' => $class_checkbox],
          ['data' => $property['property'], 'class' => $property_class],
          ['data' => $google_property, 'class' => $property_class_checkbox],
          ['data' => $drupal_property, 'class' => $property_class_checkbox],
        ];
      }
      else {
        $property_class_checkbox = $property_class;
        //$property_class_checkbox[] = 'checkbox';
        $items[] = [
          ['data' => $property['property'], 'class' => $property_class],
          ['data' => $google_property, 'class' => $property_class_checkbox],
          ['data' => $drupal_property, 'class' => $property_class_checkbox],
        ];
      }
      $delta++;
    }
    $mismatch_list = ['#theme' => 'item_list', '#items' => $mismatch];
    $items[] = [
      ['data' => $mismatch_list, 'class' => $property_class_checkbox],
      ['data' => '-', 'class' => $property_class],
    ];
    return $items;
  }

  /**
   * Retrieve objects.
   *
   * @param string $data
   *   The raw data from $this->getHttpResponse() or $this->getLocalResponse().
   * @param string $prefix
   *   The prefix used in this data collection.
   *     - Data retrieved directly from Schema.org: 'schema:'
   *     - Data retrieved from the local file: 'http://schema.org/'
   *
   * @return array
   *   An array of objects and the info about each.
   */
  public function getObjects($data, $prefix = 'http://schema.org/') {
    $items = [];
    foreach ($data as $item) {
      if (isset($item['@type']) && $item['@type'] == 'rdfs:Class') {
        if (empty($item[$prefix . 'supersededBy'])) {
          $subobject_of = [];
          $object = $item['rdfs:label'];
          $description = $item['rdfs:comment'];
          if (array_key_exists('rdfs:subClassOf', $item)) {
            foreach ($item['rdfs:subClassOf'] as $value) {
              if (is_array($value)) {
                foreach ($value as $value_item) {
                  $subobject_of[] = str_replace($prefix, '', $value_item);
                }
              }
              else {
                $subobject_of[] = str_replace($prefix, '', $value);
              }
            }
          }

          $description = strip_tags($description);

          $items[$object] = [
            'object' => $object,
            'description' => $description,
            'parents' => $subobject_of,
          ];
        }
      }
    }
    return $items;
  }

  /**
   * Reorganize the objects into a hierarchical tree.
   *
   * The raw data doesn't show the whole hierarchy, just the immediate parents.
   * The tree allows us to identify which objects are the topmost level.
   *
   * @param array $objects
   *   The normalized array of object data from $this->getObjects().
   *
   * @return array
   *   A hierarchical array of the object names.
   */
  public function getTree($objects) {
    return $this->getExpanded($objects, 'Thing');
  }

  /**
   * Expand each object into a hierarchical array.
   *
   * The hierarchy recognizes the parent/child relationships between objects.
   *
   * @param array $objects
   *   The normalized array of object data from $this->getObjects().
   * @param string $parent_name
   *   The key of the desired sub-array, if any.
   *
   * @return array
   *   A hierarchical array of the children of each object.
   */
  public function getExpanded($objects, $parent_name = NULL) {
    $expanded = [];
    foreach ($objects as $child => $item) {
      foreach ($item['parents'] as $parent) {
        if (!isset($expanded[$child])) {
          $expanded[$child] = [];
        }
        if (!empty($parent)) {
          $expanded[$parent][$child] =& $expanded[$child];
        }
      }
    }
    if (!empty($parent_name) && array_key_exists($parent_name, $expanded)) {
      return $expanded[$parent_name];
    }
    else {
      return $expanded;
    }
  }

  /**
   * Get the children for a given object.
   *
   * @param array $classes
   *   The normalized array of object data from $this->getObjects().
   * @param string $parent_name
   *   The name of the parent object.
   *
   * @return array
   *   An array of children for this parent.
   */
  public function getChildren($objects, $parent_name) {
    $children = [];
    foreach ($objects as $object_name => $info) {
      foreach ($info['parents'] as $parent) {
        if ($parent == $parent_name) {
          $children[] = $object_name;
        }
      }
    }
    return $children;
  }

  /**
   * Create an option list for a given object.
   *
   * Used to create the psuedo "nested" option list used for @type.
   *
   * @param array $classes
   *   The normalized array of object data from $this->getObjects().
   * @param string $parent_name
   *   The name of the parent object.
   *
   * @return array
   *   An option array of sub-object for the given parent.
   */
  public function getOptionList($objects, $parent_name) {
    $object = $this->getExpanded($objects, $parent_name);
    $list = [];
    foreach ($object as $object_name => $values) {
      $list[] = $object_name;
      foreach ($values as $object_name2 => $values2) {
        $list[] = '- ' . $object_name2;
        foreach ($values2 as $object_name3 => $values3) {
          $list[] = '-- ' . $object_name3;
          foreach ($values3 as $object_name4 => $values4) {
            $list[] = '--- ' . $object_name4;
          }
        }
      }
    }
    return $list;
  }

  /**
   * Retrieve object properties.
   *
   * @param string $data
   *   The raw data from $this->getHttpResponse() or $this->getLocalResponse().
   * @param string $prefix
   *   The prefix used in this data collection.
   *     - Data retrieved directly from Schema.org: 'schema:'
   *     - Data retrieved from the local file: 'http://schema.org/'
   *
   * @return array
   *   An array of properties and the info about each.
   */
  public function getProperties($data, $prefix = 'http://schema.org/') {
    $items = [];
    foreach ($data as $item) {
      if (isset($item['@type']) && $item['@type'] == 'rdf:Property') {
        if (empty($item[$prefix . 'supersededBy'])) {
          $expected_type = $belongs_to = [];
          $property = $item['rdfs:label'];
          $description = $item['rdfs:comment'];
          foreach ($item[$prefix . 'rangeIncludes'] as $value) {
            if (is_array($value)) {
              foreach ($value as $value_item) {
                $expected_type[] = str_replace($prefix, '', $value_item);
              }
            }
            else {
              $expected_type[] = str_replace($prefix, '', $value);
            }
          }
          foreach ($item[$prefix . 'domainIncludes'] as $value) {
            if (is_array($value)) {
              foreach ($value as $value_item) {
                $belongs_to[] = str_replace($prefix, '', $value_item);
              }
            }
            else {
              $belongs_to[] = str_replace($prefix, '', $value);
            }
          }
          foreach ($belongs_to as $parent) {
            $items[$parent][$property] = [
              'property' => $property,
              'description' => strip_tags($description),
              'expected_type' => $expected_type,
            ];
          }
        }
      }
    }
    return $items;
  }

}
