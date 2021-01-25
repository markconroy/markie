<?php

namespace Drupal\components\Template;

use Twig\Error\RuntimeError;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * A class providing components' Twig extensions.
 */
class TwigExtension extends AbstractExtension {

  /**
   * {@inheritdoc}
   */
  public function getFunctions() {
    return [
      new TwigFunction('template', [$this, 'template'], ['is_variadic' => TRUE]),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFilters() {
    return [
      'set' => new TwigFilter('set', [
        'Drupal\components\Template\TwigExtension', 'setFilter',
      ]),
      'add' => new TwigFilter('add', [
        'Drupal\components\Template\TwigExtension', 'addFilter',
      ]),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return 'components';
  }

  /**
   * Includes the given template name or theme hook by returning a render array.
   *
   * Instead of calling the "include" function with a specific Twig template,
   * the "template" function will include the same Twig template, but after
   * running Drupal's normal preprocess and theme suggestion functions.
   *
   * Variables that you want to pass to the template should be given to the
   * template function using named arguments. For example:
   *
   * @code
   * {% set list = template(
   *     "item-list.html.twig",
   *     title = "Animals not yet in Drupal core",
   *     items = ["lemur", "weasel", "honey badger"],
   *   )
   * %}
   * @endcode
   *
   * Note that template() returns a render array. This means you can filter it
   * with Twig filters that expect arrays, e.g. `template(...)|merge(...)`. If
   * you want to use a filter that expects strings, you can use Drupal's render
   * filter first, e.g. `template(...)|render|stringFilter(...)`.
   *
   * Instead of the template name, you can pass a theme hook name or theme
   * suggestion to the first argument:
   * @code
   * {% set list = template(
   *     "item_list__node",
   *     title = "Fictional animals not yet in Drupal core",
   *     items = ["domo", "ponycorn"],
   *   )
   * %}
   * @endcode
   *
   * @param string|array $_name
   *   The template name or theme hook to render. Optionally, an array of theme
   *   suggestions can be given.
   * @param array $variables
   *   The variables to pass to the template.
   *
   * @return array
   *   The render array for the given theme hook.
   *
   * @throws \Exception
   *   When template name is prefixed with a Twig namespace, e.g. "@classy/".
   */
  public function template($_name, array $variables = []) {
    if ($_name[0] === '@') {
      throw new \Exception('Templates with namespaces are not supported; "' . $_name . '" given.');
    }
    if (is_array($_name)) {
      $hook = $_name;
    }
    else {
      $hook = str_replace('.html.twig', '', strtr($_name, '-', '_'));
    }
    $render_array = ['#theme' => $hook];
    foreach ($variables as $key => $variable) {
      $render_array['#' . $key] = $variable;
    }

    return $render_array;
  }

  /**
   * Recursively merges an array into the element, replacing existing values.
   *
   * @code
   * {{ form|set( {'element': {'attributes': {'placeholder': 'Label'}}} ) }}
   * @endcode
   *
   * @param array|iterable|\Traversable $element
   *   The parent renderable array to merge into.
   * @param iterable|array $array
   *   The array to merge.
   *
   * @return array
   *   The merged renderable array.
   *
   * @throws \Twig\Error\RuntimeError
   *   When $element is not an array or "Traversable".
   */
  public static function setFilter($element, $array) {
    if (!twig_test_iterable($element)) {
      throw new RuntimeError(sprintf('The set filter only works with arrays or "Traversable", got "%s" as first argument.', gettype($element)));
    }

    return array_replace_recursive($element, $array);
  }

  /**
   * Adds a deeply-nested property on an array.
   *
   * If the deeply-nested property exists, the existing data will be replaced
   * with the new value, unless the existing data is an array. In which case,
   * the new value will be merged into the existing array.
   *
   * @code
   * {{ form|add( 'element.attributes.class', 'new-class' ) }}
   * @endcode
   *
   * @param array|iterable|\Traversable $element
   *   The parent renderable array to merge into.
   * @param string $path
   *   The dotted-path to the deeply nested element to replace.
   * @param mixed $value
   *   The value to set.
   *
   * @return array
   *   The merged renderable array.
   *
   * @throws \Twig\Error\RuntimeError
   *   When $element is not an array or "Traversable".
   */
  public static function addFilter($element, string $path, $value) {
    if (!twig_test_iterable($element)) {
      throw new RuntimeError(sprintf('The add filter only works with arrays or "Traversable", got "%s" as first argument.', gettype($element)));
    }

    if ($element instanceof \ArrayAccess) {
      $filtered_element = clone $element;
    }
    else {
      $filtered_element = $element;
    }

    // Convert the dotted path into an array of keys.
    $path = explode('.', $path);
    $last_path = array_pop($path);

    // Traverse the element down the path, creating arrays as needed.
    $child_element =& $filtered_element;
    foreach ($path as $child_path) {
      if (!isset($child_element[$child_path])) {
        $child_element[$child_path] = [];
      }
      $child_element =& $child_element[$child_path];
    }

    // If the targeted child element is an array, add the value to it.
    if (isset($child_element[$last_path]) && is_array($child_element[$last_path])) {
      if (is_array($value)) {
        $child_element[$last_path] = array_merge($child_element[$last_path], $value);
      }
      else {
        $child_element[$last_path][] = $value;
      }
    }
    else {
      // Otherwise, replace the target element with the given value.
      $child_element[$last_path] = $value;
    }

    return $filtered_element;
  }

}
