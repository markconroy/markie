<?php

namespace Drupal\metatag\Hook;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Help hook implementations for Metatag.
 */
class HelpHooks {

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, RouteMatchInterface $route_match) {
    switch ($route_name) {
      // Main module help for the Metatag module.
      case 'help.page.metatag':
        $output = '<h2>' . (string) new TranslatableMarkup('About') . '</h2>';
        $output .= '<p>' . (string) new TranslatableMarkup('This module allows a site to automatically provide structured metadata, aka "meta tags", about the site and individual pages.');
        $output .= '<p>' . (string) new TranslatableMarkup('In the context of search engine optimization, providing an extensive set of meta tags may help improve the site\'s and pages\' rankings, thus may aid with achieving a more prominent display of the content within search engine results. They can also be used to tailor how content is displayed when shared on social networks. For additional information, see the <a href=":online">online documentation for Metatag</a>.', [
          ':online' => 'https://www.drupal.org/node/1774342',
        ]) . '</p>';
        $output .= '<h3>' . (string) new TranslatableMarkup('Intended workflow') . '</h3>';
        $output .= '<p>' . (string) new TranslatableMarkup('The module uses <a href=":tokens">"tokens"</a> to automatically fill in values for different meta tags. Specific values may also be filled in.', [
          ':tokens' => Url::fromRoute('help.page', [
            'name' => 'token',
          ])->toString(),
        ]) . '</p>';
        $output .= '<p>' . (string) new TranslatableMarkup('The best way of using Metatag is as follows:') . '</p>';
        $output .= '<ol>';
        $output .= '<li>' . (string) new TranslatableMarkup('Customize the <a href=":defaults">global defaults</a>, fill in the specific values and tokens that every page should have.', [
          ':defaults' => Url::fromRoute('entity.metatag_defaults.edit_form', [
            'metatag_defaults' => 'global',
          ])->toString(),
        ]) . '</li>';
        $output .= '<li>' . (string) new TranslatableMarkup('Override each of the <a href=":defaults">other defaults</a>, fill in specific values and tokens that each item should have by default. This allows e.g. for all nodes to have different values than taxonomy terms.', [
          ':defaults' => Url::fromRoute('entity.metatag_defaults.collection')->toString(),
        ]) . '</li>';
        $output .= '<li>' . (string) new TranslatableMarkup('<a href=":add">Add more default configurations</a> as necessary for different entity types and entity bundles, e.g. for different content types or different vocabularies.', [
          ':add' => Url::fromRoute('entity.metatag_defaults.add_form')->toString(),
        ]) . '</li>';
        $output .= '<li>' . (string) new TranslatableMarkup('To override the meta tags for individual entities, e.g. for individual nodes, add the "Metatag" field via the field settings for that entity or bundle type.') . '</li>';
        $output .= '</ol>';
        return $output;

      // The main configuration page.
      case 'entity.metatag_defaults.collection':
        $output = '<p>' . (string) new TranslatableMarkup('Configure global meta tag default values below. Meta tags may be left as the default.') . '</p>';
        $output .= '<p>' . (string) new TranslatableMarkup('Meta tag patterns are passed down from one level to the next unless they are overridden. To view a summary of the individual meta tags and the pattern for a specific configuration, click on its name below.') . '</p>';
        $output .= '<p>' . (string) new TranslatableMarkup('If the top-level configuration is not specific enough, additional default meta tag configurations can be added for a specific entity type or entity bundle, e.g. for a specific content type.') . '</p>';
        $output .= '<p>' . (string) new TranslatableMarkup('Meta tags can be further refined on a per-entity basis, e.g. for individual nodes, by adding the "Metatag" field to that entity type through its normal field settings pages.') . '</p>';
        return $output;

      // The 'add default meta tags' configuration page.
      case 'entity.metatag_defaults.add_form':
        $output = '<p>' . (string) new TranslatableMarkup('Use the following form to override the global default meta tags for a specific entity type or entity bundle. In practical terms, this allows the meta tags to be customized for a specific content type or taxonomy vocabulary, so that its content will have different meta tags <em>default values</em> than others.') . '</p>';
        $output .= '<p>' . (string) new TranslatableMarkup('As a reminder, if the "Metatag" field is added to the entity type through its normal field settings, the meta tags can be further refined on a per entity basis; this allows each node to have its meta tags  customized on an individual basis.') . '</p>';
        return $output;
    }
  }

}
