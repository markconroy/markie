<?php
/*
 * @file
 * Default Rules
 */
/**
 * Implements hook_default_rules_configuration().
 */
function entityform_notifications_default_rules_configuration() {
  $exported_rules[] = '{ "rules_email_entityform_submitter" : { "LABEL" : "Email Entityform Submitter", "PLUGIN" : "rule", "TAGS" : [ "entityform submission" ], "REQUIRES" : [ "rules", "variable_email" ], "USES VARIABLES" : { "entityform" : { "label" : "Submitted Entityform", "type" : "entityform" }, "entityform_type" : { "label" : "Entityform Type", "type" : "entityform_type" } }, "IF" : [ { "NOT user_has_role" : { "account" : [ "entityform:user" ], "roles" : { "value" : { "1" : "1" } } } }, { "component_rules_submitted_entityform_notify" : { "entityform" : [ "entityform" ] } } ], "DO" : [ { "variable_email_mail" : { "to" : [ "entityform:user:mail" ], "variable" : "entityform_notify_submitter_[mail_part]", "language" : "default" } } ] } }';
  $exported_rules[] = '{ "rules_email_entityform_admin" : { "LABEL" : "Email Entityform Admin", "PLUGIN" : "rule", "TAGS" : [ "entityform submission" ], "REQUIRES" : [ "rules", "variable_email" ], "USES VARIABLES" : { "entityform" : { "label" : "Submitted Entityform", "type" : "entityform" }, "entityform_type" : { "label" : "Entityform Type", "type" : "entityform_type" } }, "IF" : [ { "component_rules_submitted_entityform_notify" : { "entityform" : [ "entityform" ] } }, { "NOT data_is_empty" : { "data" : [ "entityform-type:notify-emails" ] } } ], "DO" : [ { "variable_email_mail" : { "to" : "[entityform-type:notify-emails]", "variable" : "entityform_notify_admin_[mail_part]", "language" : "default" } } ] } }';
  $exported_rules[] = '{ "rules_submitted_entityform_notify" : { "LABEL" : "Submitted Entityform Notify", "PLUGIN" : "and", "TAGS" : [ "enityform" ], "REQUIRES" : [ "rules" ], "USES VARIABLES" : { "entityform" : { "label" : "Submitted Entityform", "type" : "entityform" } }, "AND" : [ { "data_is_empty" : { "data" : [ "entityform:draft" ] } }, { "data_is" : { "data" : [ "entityform:uid" ], "value" : [ "site:current-user:uid" ] } } ] } }';
  foreach ($exported_rules as $exported_rule) {
    $rule = rules_import($exported_rule);
    $configs[$rule->name] = $rule;
  }

  return $configs;
}