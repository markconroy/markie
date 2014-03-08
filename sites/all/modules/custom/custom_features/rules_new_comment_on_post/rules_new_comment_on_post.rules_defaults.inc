<?php
/**
 * @file
 * rules_new_comment_on_post.rules_defaults.inc
 */

/**
 * Implements hook_default_rules_configuration().
 */
function rules_new_comment_on_post_default_rules_configuration() {
  $items = array();
  $items['rules_notifications_email_after_comment'] = entity_import('rules_config', '{ "rules_notifications_email_after_comment" : {
      "LABEL" : "Notifications: Email After Comment",
      "PLUGIN" : "reaction rule",
      "OWNER" : "rules",
      "TAGS" : [ "Notifications" ],
      "REQUIRES" : [ "rules", "comment" ],
      "ON" : { "comment_insert" : [] },
      "DO" : [
        { "mail" : {
            "to" : "info@adesignforlife.net",
            "subject" : "New Comment on Mark.ie",
            "message" : "There was a comment left on [comment:node] by [comment:name] using IP address [comment:hostname] and email address [comment:mail].\\r\\n\\r\\nThe body of the comment says: \\r\\n[comment:body]\\r\\n\\r\\nThe comment can be viewed here:\\r\\n[comment:url]",
            "language" : [ "" ]
          }
        }
      ]
    }
  }');
  return $items;
}
