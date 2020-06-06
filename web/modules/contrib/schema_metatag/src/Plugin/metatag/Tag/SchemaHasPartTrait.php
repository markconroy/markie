<?php

namespace Drupal\schema_metatag\Plugin\metatag\Tag;

use Drupal\schema_metatag\SchemaMetatagManager;

/**
 * Schema.org HasPart trait.
 */
trait SchemaHasPartTrait {

  use SchemaActionTrait, SchemaPivotTrait {
    SchemaPivotTrait::pivotForm insteadof SchemaActionTrait;
  }

  /**
   * The keys for this form.
   *
   * @param string $object_type
   *   Optional, limit the keys to those that are required for a specific
   *   object type.
   *
   * @return array
   *   Return an array of the form keys.
   */
  public static function hasPartFormKeys($object_type = NULL) {
    $list = ['@type'];
    $types = static::hasPartObjects();
    foreach ($types as $type) {
      if ($type == $object_type || empty($object_type)) {
        $list = array_merge($list, array_keys(static::hasPartProperties($type)));
      }
    }
    $list = array_merge($list, array_keys(static::hasPartProperties('All')));
    return $list;
  }

  /**
   * Create the form element.
   *
   * @param array $input_values
   *   An array of values passed from a higher level form element to this.
   *
   * @return array
   *   The form element.
   */
  public function hasPartForm(array $input_values) {

    $input_values += SchemaMetatagManager::defaultInputValues();
    $value = $input_values['value'];

    // Get the id for the nested @type element.
    $selector = ':input[name="' . $input_values['visibility_selector'] . '[@type]"]';
    $visibility = ['invisible' => [$selector => ['value' => '']]];
    $selector2 = SchemaMetatagManager::altSelector($selector);
    $visibility2 = ['invisible' => [$selector2 => ['value' => '']]];
    $visibility['invisible'] = [$visibility['invisible'], $visibility2['invisible']];

    $form['#type'] = 'fieldset';
    $form['#title'] = $input_values['title'];
    $form['#description'] = $input_values['description'];
    $form['#tree'] = TRUE;

    // Add a pivot option to the form.
    $form['pivot'] = $this->pivotForm($value);
    $form['pivot']['#states'] = $visibility;

    $types = static::hasPartObjects();
    $options = array_combine($types, $types);
    $form['@type'] = [
      '#type' => 'select',
      '#title' => $this->t('@type'),
      '#default_value' => !empty($value['@type']) ? $value['@type'] : '',
      '#empty_option' => t('- None -'),
      '#empty_value' => '',
      '#options' => $options,
      '#required' => $input_values['#required'],
      '#weight' => -10,
    ];

    // Build the form one object type at a time, using the visibility settings
    // to hide/show only form elements for the selected type.
    foreach ($types as $type) {

      // Properties common to all objects appear for any object type.
      $properties = static::hasPartProperties('All');
      foreach ($properties as $key => $property) {

        if (empty($property['formKeys'])) {
          $form[$key] = [
            '#type' => 'textfield',
            '#title' => $key,
            '#default_value' => !empty($value[$key]) ? $value[$key] : '',
            '#empty_option' => t('- None -'),
            '#empty_value' => '',
            '#required' => $input_values['#required'],
            '#description' => $property['description'],
            '#states' => $visibility,
          ];
        }
        else {
          $sub_values = [
            'title' => $key,
            'description' => $property['description'],
            'value' => !empty($value[$key]) ? $value[$key] : [],
            '#required' => $input_values['#required'],
            'visibility_selector' => $input_values['visibility_selector'] . '[' . $key . ']',
            'actionTypes' => !empty($property['actionTypes']) ? $property['actionTypes'] : [],
            'actions' => !empty($property['actions']) ? $property['actions'] : [],
          ];
          $method = $property['form'];
          $form[$key] = $this->$method($sub_values);
          $form[$key]['#states'] = $visibility;
        }
      }

      // Properties specific to an object type appear only for that type.
      $properties = static::hasPartProperties($type);
      foreach ($properties as $key => $property) {
        $property_visibility = ['visible' => [$selector => ['value' => $type]]];
        $property_visibility2 = ['visible' => [$selector2 => ['value' => $type]]];
        $property_visibility['visible'] = [$property_visibility['visible'], $property_visibility2['visible']];

        if (empty($property['formKeys'])) {
          $form[$key] = [
            '#type' => 'textfield',
            '#title' => $key,
            '#default_value' => !empty($value[$key]) ? $value[$key] : '',
            '#empty_option' => t('- None -'),
            '#empty_value' => '',
            '#required' => $input_values['#required'],
            '#description' => $property['description'],
            '#states' => $property_visibility,
          ];
        }
        else {
          $sub_values = [
            'title' => $key,
            'description' => $property['description'],
            'value' => !empty($value[$key]) ? $value[$key] : [],
            '#required' => $input_values['#required'],
            'visibility_selector' => $input_values['visibility_selector'] . '[' . $key . ']',
            'visibility_type' => '@type',
            'actionTypes' => !empty($property['actionTypes']) ? $property['actionTypes'] : [],
            'actions' => !empty($property['actions']) ? $property['actions'] : [],
          ];
          $method = $property['form'];
          $form[$key] = $this->$method($sub_values);
          $form[$key]['#states'] = $property_visibility;
        }
      }
    }

    return $form;
  }

  /**
   * All object types.
   *
   * @return array
   *   An array of all possible object types.
   */
  public static function hasPartObjects() {
    return [
      'Clip',
      'TVClip',
      'WebPageElement',
    ];
  }

  /**
   * Return an array of the unique properties for an object type.
   *
   * @param string $object_type
   *   The type of object. Use an object name for properties specific to that
   *   object type. Use 'All' for general properties that apply
   *   to all objects.
   *
   * @return array
   *   An array of all the unique properties for that type.
   */
  public static function hasPartProperties($object_type) {
    switch ($object_type) {

      case 'WebPageElement':
        return [
          'isAccessibleForFree' => [
            'class' => 'SchemaNameBase',
            'formKeys' => '',
            'form' => '',
            'description' => "True or False, whether this element is accessible for free.",
          ],
          'cssSelector' => [
            'class' => 'SchemaNameBase',
            'formKeys' => '',
            'form' => '',
            'description' => "List of class names of the parts of the web page that are not free, i.e. '.first-class', '.second-class'. Do NOT surround class names with quotation marks!",
          ],
        ];

      case 'Clip':
      case 'TVClip':
        return [
          'description' => [
            'class' => 'SchemaNameBase',
            'formKeys' => '',
            'form' => '',
            'description' => "One of the following values:\n
'trailer': A preview or advertisement of the work.\n
'behind_the_scenes': A summary of the production of the work.\n
'highlight': A contiguous scene from the work.",
          ],
          'timeRequired' => [
            'class' => 'SchemaNameBase',
            'formKeys' => '',
            'form' => '',
            'description' => "Duration of the clip in ISO 8601 format, 'PT2M5S' (2min 5sec).",
          ],
          'potentialAction' => [
            'class' => 'SchemaActionBase',
            'formKeys' => 'actionFormKeys',
            'form' => 'actionForm',
            'description' => "Watch action(s) for the clip.",
            'actionTypes' => ['ConsumeAction'],
            'actions' => ['WatchAction'],
          ],
        ];

      case 'All':
        return [
          '@id' => [
            'class' => 'SchemaNameBase',
            'formKeys' => '',
            'form' => '',
            'description' => "Globally unique @id of the thing, usually a url, used to to link other properties to this object.",
          ],
          'name' => [
            'class' => 'SchemaNameBase',
            'formKeys' => '',
            'form' => '',
            'description' => "The name of the work.",
          ],
        ];

      default:
        return [];
    }
  }

}
