<?php

namespace Drupal\ai_automators\Plugin\FieldWidgetAction;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Utility\Token;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai_automators\Traits\AutomatorFieldWidgetActionTrait;
use Drupal\field_widget_actions\FieldWidgetRefinableFormActionBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Abstract base for automator field widget actions with refinement support.
 *
 * Subclasses gain the full automator pipeline (generateContent runs the
 * configured automator; refineContent calls the AI provider directly) plus
 * optional interactive refinement via the modal form API inherited from
 * FieldWidgetRefinableFormActionBase.
 *
 * When refinement is disabled the action button keeps the original direct
 * field-fill behavior via aiAutomatorsAjax() / populateAutomatorValues().
 */
abstract class AutomatorRefinableBaseAction extends FieldWidgetRefinableFormActionBase {

  use AutomatorFieldWidgetActionTrait;

  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected Token $token;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    static::initAutomatorServices($instance, $container);
    $instance->token = $container->get('token');
    return $instance;
  }

  /**
   * Returns FALSE when every automator source resolves to an empty value.
   *
   * For token-mode automators the token prompt is scanned for [type:field]
   * patterns (excluding [user:…] which are always non-empty). Each pattern is
   * resolved against $entity with 'clear' => TRUE; if every one resolves to
   * an empty string the sources are considered empty.
   *
   * For base-mode automators the configured base_field is inspected directly.
   *
   * Degrades gracefully (returns TRUE) when the mode or required config
   * keys are absent so the automator runs and errors surface normally.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to resolve tokens / read field values from.
   * @param object $automator
   *   The loaded AiAutomator config entity.
   *
   * @return bool
   *   TRUE if usable source content exists, FALSE if all sources are empty.
   */
  protected function hasUsableSource(ContentEntityInterface $entity, object $automator): bool {
    $rawConfig = $automator->get('plugin_config') ?? [];
    $config = [];
    foreach ($rawConfig as $key => $value) {
      $config[substr($key, 10)] = $value;
    }

    $mode = $config['mode'] ?? 'base';

    if ($mode === 'token') {
      $prompt = $config['token'] ?? '';
      if (empty($prompt)) {
        return FALSE;
      }
      // Extract [type:field] patterns; skip [user:…] (always non-empty).
      preg_match_all('/\[(?!user:)[^\]]+\]/', $prompt, $matches);
      if (empty($matches[0])) {
        // Prompt has no entity tokens; treat as having content.
        return TRUE;
      }
      $entityType = $entity->getEntityTypeId() === 'taxonomy_term' ? 'term' : $entity->getEntityTypeId();
      foreach ($matches[0] as $tokenPattern) {
        $resolved = $this->token->replace($tokenPattern, [$entityType => $entity], ['clear' => TRUE]);
        if (trim($resolved) !== '') {
          return TRUE;
        }
      }
      return FALSE;
    }

    // Base mode: check the source field has at least one non-empty value.
    $baseField = $config['base_field'] ?? '';
    if (empty($baseField) || !$entity->hasField($baseField)) {
      return TRUE;
    }
    foreach ($entity->get($baseField) as $item) {
      foreach ($item->toArray() as $value) {
        if (is_string($value) && trim($value) !== '') {
          return TRUE;
        }
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   *
   * Adds an informational hint when the modal opens with no generated content
   * (all source tokens resolved empty). The hint appears only on the first
   * open — not on refine round-trips — and is self-contained in the form so
   * it carries no cross-request state.
   */
  public function buildModalForm(array $form, FormStateInterface $form_state, ContentEntityInterface|NULL $entity): array {
    $form = parent::buildModalForm($form, $form_state, $entity);

    $user_input = $form_state->getUserInput();
    $is_round_trip = ($user_input['form_id'] ?? '') === 'field_widget_action_wrapper_form';
    if (!$is_round_trip && empty(trim((string) ($form['content']['#default_value'] ?? '')))) {
      $form['no_source_hint'] = [
        '#markup' => '<p>' . $this->t("No source content was available to generate from. Describe what you'd like in the box below, or add content to the page and try again.") . '</p>',
        '#weight' => -1,
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * Generates the initial content by running the configured automator.
   * If the field already has a value, returns it as-is so the user can
   * review the accepted content before deciding to re-generate.
   */
  public function generateContent(?ContentEntityInterface $entity, array $context_data): string {
    $field_name = $context_data['target_element_field_name'] ?? '';
    if (!$entity || !$field_name) {
      return '';
    }

    // Seed from the current field value when one exists.
    $existing_parts = [];
    foreach ($entity->get($field_name) as $item) {
      $prop = $item->get($this->formElementProperty);
      if ($prop) {
        $value = $prop->getValue();
        if (!empty($value)) {
          $existing_parts[] = (string) $value;
        }
      }
    }
    if (!empty($existing_parts)) {
      return implode("\n\n", $existing_parts);
    }

    $automator_id = $this->getConfiguration()['settings']['automator_id'] ?? NULL;
    $automator = NULL;
    if ($automator_id) {
      $automator = $this->entityTypeManager->getStorage('ai_automator')->load($automator_id);
      if (!$automator) {
        $this->loggerFactory->get('ai_automators')->warning(
          'Automator @id not found for field widget action.',
          ['@id' => $automator_id]
        );
        return '';
      }
    }

    // When all source tokens / fields resolve to empty the model would return
    // a refusal apology. Return empty instead so the modal opens blank and the
    // user authors via the refine box.
    if ($automator && !$this->hasUsableSource($entity, $automator)) {
      return '';
    }

    if ($this->clearEntity) {
      $entity->get($field_name)->filterEmptyItems();
    }

    // Pass the automator ID so only this automator runs, even when multiple
    // automators are configured on the same field.
    $entity = $this->entityModifier->saveEntity($entity, FALSE, $field_name, FALSE, $automator_id);
    if (!$entity) {
      $this->loggerFactory->get('ai_automators')->warning(
        'Automator @id did not process field @field. Check the automator configuration.',
        ['@id' => $automator_id, '@field' => $field_name]
      );
      return '';
    }

    $parts = [];
    foreach ($entity->get($field_name) as $item) {
      $prop = $item->get($this->formElementProperty);
      if ($prop) {
        $value = $prop->getValue();
        if (!empty($value)) {
          $parts[] = (string) $value;
        }
      }
    }
    return implode("\n\n", $parts);
  }

  /**
   * {@inheritdoc}
   *
   * Refines content by calling the AI provider from the automator's config.
   *
   * The field's original automator prompt is resolved and appended to every
   * refinement request as governing requirements, so field-level constraints
   * (length limits, format, language, "no preamble", etc.) are honoured even
   * when the user only provides a short instruction like "make it shorter".
   */
  public function refineContent(string $content, string $refinement_prompt, ?ContentEntityInterface $entity, array $context_data): string {
    $automator_id = $this->getConfiguration()['settings']['automator_id'] ?? NULL;
    if (!$automator_id) {
      return $content;
    }

    $automator = $this->entityTypeManager->getStorage('ai_automator')->load($automator_id);
    if (!$automator) {
      return $content;
    }

    // Strip the "automator_" prefix from plugin_config keys, matching
    // AiAutomatorEntityModifier::getFieldConfigs() key conventions.
    $rawConfig = $automator->get('plugin_config') ?? [];
    $config = [];
    foreach ($rawConfig as $key => $value) {
      $config[substr($key, 10)] = $value;
    }

    // Resolve the field's original automator prompt so that field-level
    // constraints survive refinement round-trips. Token-mode automators store
    // the prompt in $config['token']; base-mode in $config['prompt'].
    $basePrompt = trim($config['token'] ?? $config['prompt'] ?? '');
    if (!empty($basePrompt) && $entity) {
      $entityType = $entity->getEntityTypeId() === 'taxonomy_term' ? 'term' : $entity->getEntityTypeId();
      $basePrompt = trim($this->token->replace($basePrompt, [$entityType => $entity], ['clear' => TRUE]));
    }
    $requirementsBlock = '';
    if (!empty($basePrompt)) {
      $requirementsBlock = "\n\nThe output must still satisfy these original field requirements:\n---\n" . $basePrompt . "\n---";
    }

    $providerKey = $config['ai_provider'] ?? '';
    if (empty($providerKey)) {
      return $content;
    }

    $provider = $providerKey;
    $model = $config['ai_model'] ?? '';

    $defaultMap = [
      'default_json' => 'chat_with_complex_json',
      'default_vision' => 'chat_with_image_vision',
      'default' => 'chat',
    ];
    if (isset($defaultMap[$providerKey])) {
      $defaults = $this->aiProvider->getDefaultProviderForOperationType($defaultMap[$providerKey]);
      $provider = $defaults['provider_id'] ?? '';
      $model = $defaults['model_id'] ?? '';
    }

    if (empty($provider) || empty($model)) {
      $this->loggerFactory->get('ai_automators')->error(
        'FWA refinement: could not resolve provider/model from @key',
        ['@key' => $providerKey]
      );
      return $content;
    }

    try {
      if ($this->targetIsFormattedText($context_data)) {
        if (empty($content)) {
          $systemPrompt = 'You are an expert content editor. Your job is to write new HTML content following the user\'s instructions, while continuing to satisfy the field\'s original requirements provided by the user. Output ONLY the new content as valid HTML — no explanations, no quotes, no markdown fences, no preamble.';
          $userPrompt = "Instructions: " . $refinement_prompt . "\n\nWrite new HTML content following the instructions above. Return ONLY the resulting HTML." . $requirementsBlock;
        }
        else {
          $systemPrompt = 'You are an expert content editor. Your job is to take existing HTML content and modify it according to the user instructions, while continuing to satisfy the field\'s original requirements provided by the user. You MUST follow the instructions precisely. Output ONLY the revised content as valid HTML — no explanations, no quotes, no markdown fences, no preamble.';
          $userPrompt = "Here is the HTML content to modify:\n\n---\n" . $content . "\n---\n\nInstructions: " . $refinement_prompt . "\n\nApply the instructions above and return ONLY the resulting HTML content." . $requirementsBlock;
        }
      }
      else {
        if (empty($content)) {
          $systemPrompt = 'You are an expert content editor. Your job is to write new content following the user\'s instructions, while continuing to satisfy the field\'s original requirements provided by the user. Output ONLY the new content as plain text — no explanations, no quotes, no markdown fences, no preamble, no HTML tags.';
          $userPrompt = "Instructions: " . $refinement_prompt . "\n\nWrite new plain text content following the instructions above. Return ONLY the resulting text." . $requirementsBlock;
        }
        else {
          $systemPrompt = 'You are an expert content editor. Your job is to take existing content and modify it according to the user instructions, while continuing to satisfy the field\'s original requirements provided by the user. You MUST follow the instructions precisely. Output ONLY the revised content as plain text — no explanations, no quotes, no markdown fences, no preamble, no HTML tags.';
          $userPrompt = "Here is the content to modify:\n\n---\n" . $content . "\n---\n\nInstructions: " . $refinement_prompt . "\n\nApply the instructions above and return ONLY the resulting plain text content." . $requirementsBlock;
        }
      }

      /** @var \Drupal\ai\OperationType\Chat\ChatInterface $chatProvider */
      $chatProvider = $this->aiProvider->createInstance($provider);
      $input = new ChatInput([
        new ChatMessage('system', $systemPrompt),
        new ChatMessage('user', $userPrompt),
      ]);
      $response = $chatProvider->chat($input, $model);
      $normalized = $response->getNormalized();
      $refined = $normalized instanceof ChatMessage ? $normalized->getText() : '';
      return !empty($refined) ? trim($refined) : $content;
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('ai_automators')->error(
        'Refinement failed: @message',
        ['@message' => $e->getMessage()]
      );
      return $content;
    }
  }

}
