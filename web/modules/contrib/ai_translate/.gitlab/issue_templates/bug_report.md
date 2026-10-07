<!-- Please search existing work items before filing to avoid duplicates. -->

## Summary

<!-- Briefly describe the bug in one or two sentences. A good title already sets the context — use this to add any detail that doesn't fit in the title. -->

<!--
Testing Setup (optional — to reproduce on a clean install):

  mkdir my-drupal-site && cd my-drupal-site
  ddev config --project-type=drupal11 --docroot=web
  ddev composer create-project drupal/cms
  ddev drush site:install --account-name=admin --account-pass=admin -y
  # Enable AI Dashboard and ai_api_explorer
  # Launch the site and open the AI dashboard to add an OpenAI or Anthropic key:
  ddev launch $(ddev drush uli /admin/config/ai)
  # Enable content_translation and ai_translate, add a second language, then translate a node via the Translate tab
-->

## Steps to reproduce

1. 
2. 
3. 

## Expected result

## Actual result

## Environment

- Drupal version: <!-- e.g. 10.4.0 -->
- Module version: <!-- e.g. 1.4.0 -->
- PHP version: <!-- e.g. 8.3 -->
- Provider: <!-- e.g. OpenAI, Anthropic -->
- Last known working version: <!-- if applicable -->

### Frontend environment *(only required for UI / frontend issues)*

- OS: <!-- e.g. macOS, Windows, Linux -->
- Browser: <!-- e.g. Chrome 121, Firefox 122 -->

### Screenshots / recordings *(optional)*

<!-- Attach a screenshot or screen recording. Drag and drop files directly into this text box. -->

### Error messages or logs *(optional)*

<!--
Paste any relevant error messages, stack traces, or Drupal watchdog logs here.
Admin › Reports › Recent log messages, or: ddev logs
-->

<!-- If you discover this is a duplicate of an existing issue, use: /duplicate #<issue> -->

## AI Usage

<!-- See https://www.drupal.org/docs/develop/issues/issue-procedures-and-etiquette/policy-on-the-use-of-ai-when-contributing-to-drupal -->

- [ ] AI Assisted Issue — This issue was generated with AI assistance, but was reviewed and refined by the creator.

/label ~"category::bug"
<!-- Component — uncomment the line(s) that apply (remove the surrounding comment markers):-->
<!-- /label ~"contentTranslation" -->
<!-- /label ~"interfaceTranslation" -->
<!-- /label ~"fieldTextExtractor" -->
<!-- /label ~"provider" -->
<!-- /label ~"settings" -->
<!-- /label ~"drush" -->
