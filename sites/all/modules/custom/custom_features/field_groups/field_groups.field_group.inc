<?php
/**
 * @file
 * field_groups.field_group.inc
 */

/**
 * Implements hook_field_group_info().
 */
function field_groups_field_group_info() {
  $export = array();

  $field_group = new stdClass();
  $field_group->disabled = FALSE; /* Edit this to true to make a default field_group disabled initially */
  $field_group->api_version = 1;
  $field_group->identifier = 'group_art_basic_info|node|artlcles|form';
  $field_group->group_name = 'group_art_basic_info';
  $field_group->entity_type = 'node';
  $field_group->bundle = 'artlcles';
  $field_group->mode = 'form';
  $field_group->parent_name = '';
  $field_group->data = array(
    'label' => 'Basic Info',
    'weight' => '0',
    'children' => array(
      0 => 'field_art_body',
      1 => 'title',
    ),
    'format_type' => 'tab',
    'format_settings' => array(
      'formatter' => 'closed',
      'instance_settings' => array(
        'description' => '',
        'classes' => 'group-art-basic-info field-group-tab',
        'required_fields' => 1,
      ),
    ),
  );
  $export['group_art_basic_info|node|artlcles|form'] = $field_group;

  $field_group = new stdClass();
  $field_group->disabled = FALSE; /* Edit this to true to make a default field_group disabled initially */
  $field_group->api_version = 1;
  $field_group->identifier = 'group_art_meta_info|node|artlcles|form';
  $field_group->group_name = 'group_art_meta_info';
  $field_group->entity_type = 'node';
  $field_group->bundle = 'artlcles';
  $field_group->mode = 'form';
  $field_group->parent_name = '';
  $field_group->data = array(
    'label' => 'Meta Info',
    'weight' => '1',
    'children' => array(
      0 => 'field_art_topics',
      1 => 'field_art_site_section',
      2 => 'field_art_follow_on_twitter',
    ),
    'format_type' => 'tab',
    'format_settings' => array(
      'formatter' => 'closed',
      'instance_settings' => array(
        'description' => '',
        'classes' => 'group-art-meta-info field-group-tab',
        'required_fields' => 1,
      ),
    ),
  );
  $export['group_art_meta_info|node|artlcles|form'] = $field_group;

  $field_group = new stdClass();
  $field_group->disabled = FALSE; /* Edit this to true to make a default field_group disabled initially */
  $field_group->api_version = 1;
  $field_group->identifier = 'group_bp_basic_info|node|page|form';
  $field_group->group_name = 'group_bp_basic_info';
  $field_group->entity_type = 'node';
  $field_group->bundle = 'page';
  $field_group->mode = 'form';
  $field_group->parent_name = '';
  $field_group->data = array(
    'label' => 'Basic Info',
    'weight' => '0',
    'children' => array(
      0 => 'title',
      1 => 'field_bp_body',
    ),
    'format_type' => 'tab',
    'format_settings' => array(
      'formatter' => 'closed',
      'instance_settings' => array(
        'description' => '',
        'classes' => 'group-bp-basic-info field-group-tab',
        'required_fields' => 1,
      ),
    ),
  );
  $export['group_bp_basic_info|node|page|form'] = $field_group;

  return $export;
}
