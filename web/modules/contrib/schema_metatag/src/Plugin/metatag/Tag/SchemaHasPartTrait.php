<?php

namespace Drupal\schema_metatag\Plugin\metatag\Tag;

/**
 * Schema.org HasPart trait.
 */
trait SchemaHasPartTrait {

  use SchemaActionTrait, SchemaPivotTrait {
    SchemaPivotTrait::pivotForm insteadof SchemaActionTrait;
  }

  /**
   * Return the SchemaMetatagManager.
   *
   * @return \Drupal\schema_metatag\SchemaMetatagManager
   *   The Schema Metatag Manager service.
   */
  abstract protected function schemaMetatagManager();

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

    $input_values += $this->schemaMetatagManager()->defaultInputValues();
    $value = $input_values['value'];

    // Get the id for the nested @type element.
    $selector = ':input[name="' . $input_values['visibility_selector'] . '[@type]"]';
    $visibility = ['invisible' => [$selector => ['value' => '']]];
    $selector2 = $this->schemaMetatagManager()->altSelector($selector);
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

        if (empty($property['form'])) {
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

        if (empty($property['form'])) {
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
            'form' => '',
            'description' => "True or False, whether this element is accessible for free.",
          ],
          'cssSelector' => [
            'class' => 'SchemaNameBase',
            'form' => '',
            'description' => "List of class names of the parts of the web page that are not free, i.e. '.first-class', '.second-class'. Do NOT surround class names with quotation marks!",
          ],
        ];

      case 'Clip':
      case 'TVClip':
        return [
          'description' => [
            'class' => 'SchemaNameBase',
            'form' => '',
            'description' => "One of the following values:\n
'trailer': A preview or advertisement of the work.\n
'behind_the_scenes': A summary of the production of the work.\n
'highlight': A contiguous scene from the work.",
          ],
          'timeRequired' => [
            'class' => 'SchemaNameBase',
            'form' => '',
            'description' => "Duration of the clip in ISO 8601 format, 'PT2M5S' (2min 5sec).",
          ],
          'potentialAction' => [
            'class' => 'SchemaActionBase',
            'form' => 'actionForm',
            'description' => "Watch action(s) for the clip.",
            'actions' => ['Action', 'ConsumeAction', 'WatchAction'],
          ],
        ];

      case 'All':
        return [
          '@id' => [
            'class' => 'SchemaNameBase',
            'form' => '',
            'description' => "Globally unique @id of the thing, usually a url, used to to link other properties to this object.",
          ],
          'name' => [
            'class' => 'SchemaNameBase',
            'form' => '',
            'description' => "The name of the work.",
          ],
        ];

      default:
        return [];
    }
  }

}
