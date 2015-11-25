<?php
/**
 * @file
 * rules_delete_comments_with_site_name_in_subject.rules_defaults.inc
 */

/**
 * Implements hook_default_rules_configuration().
 */
function rules_delete_comments_with_site_name_in_subject_default_rules_configuration() {
  $items = array();
  $items['rules_delete_comments_with_site_name_in_them'] = entity_import('rules_config', '{ "rules_delete_comments_with_site_name_in_them" : {
      "LABEL" : "Delete comments with site name in them",
      "PLUGIN" : "reaction rule",
      "OWNER" : "rules",
      "REQUIRES" : [ "rules", "comment" ],
      "ON" : { "comment_insert" : [] },
      "IF" : [
        { "text_matches" : {
            "text" : [ "comment:subject" ],
            "match" : "| MARK.IE",
            "operation" : "ends"
          }
        }
      ],
      "DO" : [ { "entity_delete" : { "data" : [ "comment" ] } } ]
    }
  }');
  return $items;
}
