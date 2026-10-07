<?php

namespace Drupal\ai_automators\PluginBaseClasses;

use Drupal\ai\Utility\Textarea;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * This is a base class that can be used for LLMs metatag rules.
 */
class Metatag extends RuleBase {

  /**
   * The metatag manager.
   *
   * @var \Drupal\metatag\MetatagManager
   */
  protected $metaTagManager;

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $parent_instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $parent_instance->metaTagManager = $container->get('metatag.manager');
    return $parent_instance;
  }

  /**
   * {@inheritDoc}
   */
  public function helpText() {
    return "This can create personalized texts for different metatags based on context.";
  }

  /**
   * {@inheritDoc}
   */
  public function placeholderText() {
    return "Based on the context create the different metatag fields according to the instructions for each.\n\nContext:\n{{ context }}";
  }

  /**
   * {@inheritDoc}
   */
  public function checkIfEmpty($value, array $automatorConfig = []) {
    if (!isset($value[0]['value'])) {
      return [];
    }
    $values = Json::decode($value[0]['value']);
    $should_run = $value;
    foreach ($automatorConfig as $key => $value) {
      if (str_starts_with($key, 'llm_tag_value') && $value) {
        $test = substr($key, strlen('llm_tag_value_'));
        if (!isset($values[$test]) || !$values[$test]) {
          $should_run = [];
        }
      }
    }
    return $should_run;
  }

  /**
   * {@inheritDoc}
   */
  public function extraAdvancedFormFields(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, FormStateInterface $formState, array $defaultValues = []) {
    $groups = $this->metaTagManager->sortedGroups();
    $tags = $this->metaTagManager->sortedTags();
    $form = parent::extraAdvancedFormFields($entity, $fieldDefinition, $formState, $defaultValues);
    foreach ($groups as $group_key => $group) {
      $form['group'][$group_key] = [
        '#type' => 'details',
        '#title' => $this->t('Setup @group', ['@group' => $group['label']]),
        '#open' => FALSE,
        '#description' => $this->t('Write a subprompt for generating a text for the following tag description, you may reference the context from the main prompt. Keep empty to not generate anything.'),
      ];
      foreach ($tags as $tag_key => $tag) {
        if ($tag['group'] == $group_key) {
          $description = $tag['description'] ?? '';
          $form['group'][$group_key]["automator_llm_tag_value_$tag_key"] = [
            '#type' => 'textarea',
            '#title' => $this->t('Setup @group', ['@group' => $tag['label']]),
            '#description' => $this->t('Write a subprompt for this field, you may reference the context from the main prompt. Keep empty to not run the automators on this field. The description of the tag is: %description', ['%description' => $description]),
            '#default_value' => $defaultValues["automator_llm_tag_value_$tag_key"] ?? '',
            '#attributes' => [
              'rows' => 2,
            ],
            // This property will land into core soon, see
            // https://www.drupal.org/project/drupal/issues/3202631. It can stay
            // after this is added to Drupal core.
            '#normalize_newlines' => TRUE,
            // Until that the custom value callback is needed. Should be removed
            // after the issue mentioned above is merged into core and the
            // minimum supported Drupal version includes `#normalize_newlines`
            // property.
            '#value_callback' => [Textarea::class, 'valueCallback'],
          ];

          $form['group'][$group_key]["automator_llm_tag_example_$tag_key"] = [
            '#type' => 'textarea',
            '#title' => $this->t('Example of @tag', ['@tag' => $tag['label']]),
            '#description' => $this->t('Write an example of the @tag filled out. This is for the AI to understand better how to produce it.', ['@tag' => $tag['label']]),
            '#default_value' => $defaultValues["automator_llm_tag_example_$tag_key"] ?? '',
            '#attributes' => [
              'rows' => 2,
            ],
            // This property will land into core soon, see
            // https://www.drupal.org/project/drupal/issues/3202631. It can stay
            // after this is added to Drupal core.
            '#normalize_newlines' => TRUE,
            // Until that the custom value callback is needed. Should be removed
            // after the issue mentioned above is merged into core and the
            // minimum supported Drupal version includes `#normalize_newlines`
            // property.
            '#value_callback' => [Textarea::class, 'valueCallback'],
          ];
        }
      }
    }

    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function generate(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    // Generate the real prompt if needed.
    $prompts = parent::generate($entity, $fieldDefinition, $automatorConfig);
    $tags = $this->configuredTags($automatorConfig);
    $examples = [];
    foreach ($automatorConfig as $key => $value) {
      if (str_starts_with($key, 'llm_tag_example') && $value) {
        $examples[substr($key, strlen('llm_tag_example_'))] = $value;
      }
    }
    // Only offer the tags the site builder actually wrote a subprompt for.
    $examples = array_intersect_key($examples, $tags);

    // The tag list handed to the model used to be every tag that has a settings
    // field, which on a standard Metatag install is over a hundred. Telling
    // the model those were "available" is an invitation, and it filled in title
    // description, keywords, the og:* set and more, none of which the site
    // builder asked for. The form is explicit that an empty subprompt means "do
    // not generate anything for this tag", so only the tags that carry one are
    // offered.
    $available_tags = array_keys($tags);

    // Add JSON output.
    foreach ($prompts as $key => $prompt) {
      $prompt .= "\n\nDo not include any explanations, only provide a RFC8259 compliant JSON response following this format without deviation.\n[{\"value\":" . json_encode($tags) . "}]";
      $prompt .= "\n\nThe list of available tags is: " . implode(', ', $available_tags) . ". Do not generate values for any other tags.\n";
      $prompt .= "\n\nExample of one row:\n[{\"value\":" . json_encode($examples) . "}]\n";
      $prompts[$key] = $prompt;
    }

    $total = [];
    $instance = $this->prepareLlmInstance('chat', $automatorConfig);
    foreach ($prompts as $prompt) {
      // Create new messages.
      $values = $this->runChatMessage($prompt, $automatorConfig, $instance, $entity);

      if (!empty($values)) {
        $total = array_merge_recursive($total, $values);
      }
    }
    return $total;
  }

  /**
   * Returns the tags the site builder wrote a subprompt for.
   *
   * A textarea is rendered by extraAdvancedFormFields() for every tag Metatag
   * knows about, so the saved configuration carries a key for all of them.
   * Only the ones holding text were actually requested; the form describes an
   * empty one as "Keep empty to not run the automators on this field".
   *
   * @param array $automatorConfig
   *   The automator configuration.
   *
   * @return array
   *   Subprompt text keyed by tag name.
   */
  protected function configuredTags(array $automatorConfig): array {
    $tags = [];
    foreach ($automatorConfig as $key => $value) {
      if (str_starts_with($key, 'llm_tag_value') && $value) {
        $tags[substr($key, strlen('llm_tag_value_'))] = $value;
      }
    }
    return $tags;
  }

  /**
   * {@inheritDoc}
   */
  public function verifyValue(ContentEntityInterface $entity, $value, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    // Should be array, otherwise no validation for now.
    if (!is_array($value)) {
      return FALSE;
    }
    // A tag set is a keyed record of tag => text. An empty
    // record, or a plain list, carries no usable tag values and would be
    // stored as a meaningless JSON blob.
    if ($value === [] || array_is_list($value)) {
      return FALSE;
    }
    // Otherwise it is ok.
    return TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public function storeValues(ContentEntityInterface $entity, array $values, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    // The prompt asks for one record holding every configured tag, but
    // models routinely answer with one record per tag:
    // [{"title": "..."}, {"description": "..."}, {"abstract": "..."}]. Reading
    // a single entry out of $values kept the first tag and silently dropped
    // every other one, so a run that generated thirteen tags stored one.
    // Merge whatever shape came back into a single tag set.
    //
    // A tag the model repeated (article_tag, for instance) keeps its first
    // value, since the field stores one value per tag.
    $tags = [];
    foreach ($values as $value) {
      if (!is_array($value)) {
        continue;
      }
      foreach ($value as $tag => $text) {
        if (is_string($text) && $text !== '' && !isset($tags[$tag])) {
          $tags[$tag] = $text;
        }
      }
    }

    // Models answer with tags nobody asked for. Keep only the tags that carry
    // a subprompt, so a generated title or description cannot overwrite a value
    // the site builder maintains by hand.
    $configured = $this->configuredTags($automatorConfig);
    if ($configured) {
      $tags = array_intersect_key($tags, $configured);
    }

    if (!$tags) {
      return;
    }
    // Only one value can be set, and its stored as a JSON blob.
    $entity->set($fieldDefinition->getName(), Json::encode($tags));
  }

}
