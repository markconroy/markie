<?php

namespace Drupal\schema_metatag\Plugin\metatag\Tag;

/**
 * Schema.org CreativeWork trait.
 */
trait SchemaCreativeWorkTrait {

  use SchemaPersonOrgTrait, SchemaActionTrait, SchemaPivotTrait {
    SchemaPersonOrgTrait::personOrgForm insteadof SchemaActionTrait;
    SchemaPersonOrgTrait::imageForm insteadof SchemaActionTrait;
    SchemaPivotTrait::pivotForm insteadof SchemaPersonOrgTrait;
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
  public function creativeWorkForm(array $input_values) {

    $input_values += $this->schemaMetatagManager()->defaultInputValues();
    $value = $input_values['value'];

    // Get the id for the nested @type element.
    $selector = ':input[name="' . $input_values['visibility_selector'] . '[@type]"]';
    $selector2 = $this->schemaMetatagManager()->altSelector($selector);

    $visibility = ['invisible' => [$selector => ['value' => '']]];
    $visibility2 = ['invisible' => [$selector2 => ['value' => '']]];
    $visibility['invisible'] = [$visibility['invisible'], $visibility2['invisible']];

    $form['#type'] = 'fieldset';
    $form['#title'] = $input_values['title'];
    $form['#description'] = $input_values['description'];
    $form['#tree'] = TRUE;

    // Add a pivot option to the form.
    $form['pivot'] = $this->pivotForm($value);
    $form['pivot']['#states'] = $visibility;

    $types = static::creativeWorkObjects();
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
      $properties = static::creativeWorkProperties('All');
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
      $properties = static::creativeWorkProperties($type);
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
  public static function creativeWorkObjects() {
    return [
      'CreativeWork',
      'Article',
      'Book',
      'Blog',
      'Course',
      'Review',
      'Movie',
      'MusicComposition',
      'MusicPlaylist',
      'MediaObject',
      'Clip',
      'CreativeWorkSeason',
      'TVSeason',
      'CreativeWorkSeries',
      'TVSeries',
      'Episode',
      'WebPage',
      'WebSite',
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
  public static function creativeWorkProperties($object_type) {
    switch ($object_type) {

      case 'Book':
        return [
          'isbn' => [
            'class' => 'SchemaNameBase',
            'form' => '',
            'description' => "The ISBN of the book.",
          ],
          'bookEdition' => [
            'class' => 'SchemaNameBase',
            'form' => '',
            'description' => "The edition of the book.",
          ],
          'bookFormat' => [
            'class' => 'SchemaNameBase',
            'form' => '',
            'description' => "The format of the book (comma-separated), i.e. https://schema.org/Hardcover,https://schema.org/Paperback,https://schema.org/EBook",
          ],
          'author' => [
            'class' => 'SchemaPersonOrgBase',
            'form' => 'personOrgForm',
            'description' => "The author of the work.",
          ],
          'potentialAction' => [
            'class' => 'SchemaActionBase',
            'form' => 'actionForm',
            'description' => "Potential action for the work, like a ReadAction.",
            'actions' => ['Action', 'ConsumeAction', 'ReadAction'],
          ],
        ];

      case 'CreativeWorkSeason':
      case 'TVSeason':
        return [
          'seasonNumber' => [
            'class' => 'SchemaNameBase',
            'form' => '',
            'description' => "The number of the season.",
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
          'url' => [
            'class' => 'SchemaNameBase',
            'form' => '',
            'description' => "Absolute URL of the canonical Web page for the work.",
          ],
          'sameAs' => [
            'class' => 'SchemaNameBase',
            'form' => '',
            'description' => "Urls and social media links, comma-separated list of absolute URLs.",
          ],
          'datePublished' => [
            'class' => 'SchemaNameBase',
            'form' => '',
            'description' => "Publication date.",
          ],
        ];

      default:
        return [];
    }
  }

}
