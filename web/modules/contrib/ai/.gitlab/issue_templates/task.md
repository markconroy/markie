<!-- Please search existing work items before filing to avoid duplicates. -->

## Description

<!--
Explain the context and motivation — the why behind this task. A contributor unfamiliar with the background should be able to understand the purpose without reading prior discussions.

If this task is part of a larger effort, link the parent issue here.
-->

## Tasks

<!--
List the concrete implementation steps — the what, not the why (that belongs in Description above).
Each item should be independently completable and verifiable. Check items off as work progresses.
-->

* [ ] 

## Acceptance criteria

<!--
Define what "done" looks like. Each criterion should be independently verifiable.

Example:
- Site builders can configure X via the UI without writing code.
- Existing behavior is unchanged when the new setting is left at its default.
-->

* 

## Testing instructions *(added by implementor before review)*

<!--
Testing Setup (optional — to verify on a clean install):

  mkdir my-drupal-site && cd my-drupal-site
  ddev config --project-type=drupal11 --docroot=web
  ddev composer create-project drupal/cms
  ddev drush site:install --account-name=admin --account-pass=admin -y
  # Enable AI Dashboard and ai_api_explorer
  # Launch the site and open the AI dashboard to add an OpenAI or Anthropic key:
  ddev launch $(ddev drush uli /admin/config/ai)
-->

1. 

## Related issues *(optional)*

<!--
Link issues this task relates to, blocks, or is blocked by using GitLab quick actions:
  /relate #<issue>      — mark as related (bidirectional)
  /blocks #<issue>      — mark that this task blocks another issue
  /blocked_by #<issue>  — mark that this task is blocked by another issue
  /assign me            — claim this task if you are starting work on it
-->

<!-- If this issue description was significantly AI-generated (entire sections, not autocomplete), please note it in a comment below. See https://www.drupal.org/docs/develop/issues/issue-procedures-and-etiquette/policy-on-the-use-of-ai-when-contributing-to-drupal -->

/label ~"category::task"
<!-- Component — uncomment the line(s) that apply (remove the surrounding comment markers):-->
<!-- /label ~"aiCoreModule" -->
<!-- /label ~"aiAgent" -->
<!-- /label ~"aiApiExplorer" -->
<!-- /label ~"aiAssistantsApi" -->
<!-- /label ~"aiAutomators" -->
<!-- /label ~"aiChatbot" -->
<!-- /label ~"aiCkeditor" -->
<!-- /label ~"aiContentSuggestions" -->
<!-- /label ~"aiEca" -->
<!-- /label ~"aiExternalModeration" -->
<!-- /label ~"aiLogging" -->
<!-- /label ~"aiObservability" -->
<!-- /label ~"aiSearch" -->
<!-- /label ~"aiTest" -->
<!-- /label ~"aiTranslate" -->
