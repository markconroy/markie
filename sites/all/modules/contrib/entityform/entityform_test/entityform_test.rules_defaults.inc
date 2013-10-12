<?php
/**
 * @file
 * entityform_test.rules_defaults.inc
 */

/**
 * Implements hook_default_rules_configuration().
 */
function entityform_test_default_rules_configuration() {
  $items = array();
  $items['rules_access_rule'] = entity_import('rules_config', '{ "rules_access_rule" : {
      "LABEL" : "access rule",
      "PLUGIN" : "rule",
      "TAGS" : [ "entityform access" ],
      "REQUIRES" : [ "rules" ],
      "USES VARIABLES" : {
        "entityform" : { "label" : "Submitted Entityform", "type" : "entityform" },
        "entityform_type" : { "label" : "Entityform Type", "type" : "entityform_type" },
        "show_form" : { "label" : "Show form", "type" : "boolean", "parameter" : false }
      },
      "IF" : [
        { "text_matches" : { "text" : [ "site:current-user:name" ], "match" : "noaccess_user" } }
      ],
      "DO" : [
        { "data_set" : { "data" : [ "show-form" ], "value" : 0 } },
        { "drupal_message" : { "message" : "Form Access Denied" } }
      ],
      "PROVIDES VARIABLES" : [ "show_form" ]
    }
  }');
  $items['rules_submission_rule_msg'] = entity_import('rules_config', '{ "rules_submission_rule_msg" : {
      "LABEL" : "submission_rule_msg",
      "PLUGIN" : "rule",
      "TAGS" : [ "entityform submission" ],
      "REQUIRES" : [ "rules" ],
      "USES VARIABLES" : {
        "entityform" : { "label" : "Submitted Entityform", "type" : "entityform" },
        "entityform_type" : { "label" : "Entityform Type", "type" : "entityform_type" }
      },
      "IF" : [
        { "entity_has_field" : { "entity" : [ "entityform" ], "field" : "field_text" } },
        { "text_matches" : { "text" : [ "entityform:field-text" ], "match" : "showmessage" } }
      ],
      "DO" : [ { "drupal_message" : { "message" : "Text=[entityform:field-text]" } } ]
    }
  }');
  $items['rules_validation_rule'] = entity_import('rules_config', '{ "rules_validation_rule" : {
      "LABEL" : "validation rule",
      "PLUGIN" : "rule",
      "TAGS" : [ "entityform validation" ],
      "REQUIRES" : [ "rules" ],
      "USES VARIABLES" : {
        "entityform" : { "label" : "Submitted Entityform", "type" : "entityform" },
        "entityform_type" : { "label" : "Entityform Type", "type" : "entityform_type" },
        "validate_form" : { "label" : "Form Validates", "type" : "boolean", "parameter" : false }
      },
      "IF" : [
        { "entity_has_field" : { "entity" : [ "entityform" ], "field" : "field_text" } },
        { "text_matches" : { "text" : [ "entityform:field-text" ], "match" : "testfail" } }
      ],
      "DO" : [
        { "data_set" : { "data" : [ "validate-form" ], "value" : 0 } },
        { "drupal_message" : { "message" : "Test Failed" } }
      ],
      "PROVIDES VARIABLES" : [ "validate_form" ]
    }
  }');
  return $items;
}
