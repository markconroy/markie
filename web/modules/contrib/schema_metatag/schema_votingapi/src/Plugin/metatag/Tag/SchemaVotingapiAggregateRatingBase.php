<?php

namespace Drupal\schema_votingapi\Plugin\metatag\Tag;

use Drupal\schema_metatag\SchemaMetatagManager;
use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaAggregateRatingBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\Core\Entity\ContentEntityType;

/**
 * Provides a plugin for 'SchemaVotingapiAggregateRatingBase'.
 */
abstract class SchemaVotingapiAggregateRatingBase extends SchemaAggregateRatingBase {

  /**
   * Form keys.
   */
  public static function votingapiAggregateRatingFormKeys() {
    $keys = static::aggregateRatingFormKeys();
    return $keys += [
      'votingAPI',
      'ratingEntityType',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $element = []) {
    $value = SchemaMetatagManager::unserialize($this->value());
    $input_values = [
      'title' => $this->label(),
      'description' => $this->description(),
      'value' => $value,
      '#required' => isset($element['#required']) ? $element['#required'] : FALSE,
      'visibility_selector' => $this->visibilitySelector() . '[@type]',
    ];

    // Retrieve the base AggregateRating form.
    $form = $this->aggregateRatingForm($input_values);
    $form['#description'] = $this->t("AggregateRating (the numeric AggregateRating of the item), using Voting API to compute the rating. NOTE: This code is very experimental and may not work in all cases.");

    // Choose voting module to figure out which values to retrieve from the
    // results. The logic for each of these modules is contained in its own
    // method.
    $info = static::votingApiModules();
    $options = [];
    foreach ($info as $module_name => $data) {
      $options[$module_name] = $data['label'];
    }
    $form['votingAPI'] = [
      '#type' => 'select',
      '#title' => $this->t('Voting API module'),
      '#description' => $this->t('If using Voting API, choose the name of the voting module used for ratings.'),
      '#empty_option' => t('- None -'),
      '#empty_value' => '',
      '#options' => $options,
      '#default_value' => !empty($value['votingAPI']) ? $value['votingAPI'] : '',
      '#weight' => 0,
    ];

    // Add #states to show/hide the fields based on the value of @type,
    // if a selector was provided.
    $type_visibility = [];
    if (!empty($input_values['visibility_selector'])) {
      $selector = ':input[name="' . $input_values['visibility_selector'] . '"]';
      $type_visibility = ['visible' => [$selector => ['value' => 'AggregateRating']]];
      $form['votingAPI']['#states'] = $type_visibility;
    }

    $options = [];
    $entities = \Drupal::entityTypeManager()->getDefinitions();
    foreach ($entities as $entity_type => $entity) {
      if ($entity instanceof ContentEntityType) {
        $options[$entity_type] = $entity_type;
      }
    }
    $form['ratingEntityType'] = [
      '#type' => 'select',
      '#title' => $this->t('Voting API entity type'),
      '#empty_option' => t('- None -'),
      '#empty_value' => '',
      '#options' => $options,
      '#default_value' => !empty($value['ratingEntityType']) ? $value['ratingEntityType'] : '',
      '#required' => isset($element['#required']) ? $element['#required'] : FALSE,
      '#description' => $this->t('The type of entity being rated.'),
      '#states' => $type_visibility,
      '#weight' => 0,
    ];

    // Hide value and count if using VotingAPI to compute them.
    // For now leave bestRating and worstRating to be filled out manually.
    // It is not easy or automatic to populate these values from the voting
    // module results or settings.
    unset($form['ratingValue']);
    unset($form['ratingCount']);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function output() {
    $element = parent::output();
    $value = SchemaMetatagManager::unserialize($this->value());
    if (empty($value)) {
      return '';
    }

    if (!empty($element['#attributes']['content'])) {
      $voting_api = '';
      $rating_entity_type = '';
      $rating_widget = '';
      if (array_key_exists('votingAPI', $element['#attributes']['content'])) {
        $voting_api = $element['#attributes']['content']['votingAPI'];
        unset($element['#attributes']['content']['votingAPI']);
      }
      if (array_key_exists('ratingEntityType', $element['#attributes']['content'])) {
        $rating_entity_type = $element['#attributes']['content']['ratingEntityType'];
        unset($element['#attributes']['content']['ratingEntityType']);
      }
      if (!empty($voting_api) && !empty($rating_entity_type)) {
        if ($entity = \Drupal::routeMatch()->getParameter($rating_entity_type)) {
          $votes = \Drupal::service('plugin.manager.votingapi.resultfunction');
          $results = $votes->getResults($rating_entity_type, $entity->id());
          $info = static::votingApiModules();
          $method = $info[$voting_api]['method'];
          if (!empty($results)) {
            $ratings = $this->{$method}($value, $results, $entity);
            $element['#attributes']['content']['ratingValue'] = $ratings[0];
            $element['#attributes']['content']['ratingCount'] = $ratings[1];
          }
        }
      }
    }
    return $element;
  }

  /**
   * Info about votingapi modules that are supported.
   */
  public static function votingApiModules() {
    return [
      'votingapi_widgets' => [
        'label' => 'Votingapi Widgets',
        'method' => 'votingapiWidgets',
      ],
      'vote_up_down' => [
        'label' => 'Vote Up Down',
        'method' => 'voteUpDown',
      ],
    ];
  }

  /**
   * Get ratings for vote_up_down module.
   */
  public function voteUpDown($value, $results, $entity) {
    $rating = 0;
    $count = 0;
    foreach ($results as $type => $votes) {
      switch ($type) {
        case 'points':
          $rating = $votes['vote_sum'];
          $count = $votes['vote_count'];
          break;

      }
    }
    return [$rating, $count];
  }

  /**
   * Get ratings for votingapi_widgets module.
   */
  public function votingapiWidgets($value, $results, $entity) {
    $rating = 0;
    $count = 0;
    $vote_type = '';
    $result_function = '';

    // Each field has its own configuration that determines the index and
    // function to use. Get vote configuration from the voting_api_field on this
    // entity.
    $field_manager = \Drupal::service('entity_field.manager');
    $field_map = $field_manager->getFieldMapByFieldType('voting_api_field');
    $entity_type = $entity->getEntityTypeId();
    $entity_bundle = $entity->bundle();
    if (array_key_exists($entity_type, $field_map)) {
      foreach ($field_map[$entity_type] as $field_name => $data) {
        foreach ($data['bundles'] as $bundle) {
          if ($bundle == $entity_bundle) {
            if ($info = FieldConfig::loadByName($entity_type, $bundle, $field_name)) {
              $result_function = $info->getSetting('result_function');
              $vote_type = $info->getSetting('vote_type');
            }
          }
        }
      }
    }
    foreach ($results as $type => $votes) {
      switch ($type) {
        case $vote_type:
          $rating = $votes[$result_function];
          $count = $votes['vote_count'];
          break;

      }
    }
    return [$rating, $count];
  }

}
