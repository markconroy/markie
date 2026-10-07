<?php

namespace Drupal\ai_assistant_api\EventSubscriber;

use Drupal\Core\Entity\EntityInterface;
use Drupal\ai_agents\Event\BuildSystemPromptEvent;
use Drupal\ai_assistant_api\Event\AiAssistantPassContextToAgentEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Passes the assistant context into the agent's system prompt.
 */
class PassContextToAgentSubscriber implements EventSubscriberInterface {

  /**
   * Captured contexts, keyed by agent id.
   *
   * @var array
   */
  protected array $contexts = [];

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      // Run late so other subscribers can alter the context first.
      AiAssistantPassContextToAgentEvent::EVENT_NAME => ['onPassContext', -1000],
      // String event name, since ai_agents is optional and may be absent.
      'ai_agents.pre_system_prompt' => ['onBuildSystemPrompt'],
    ];
  }

  /**
   * Captures the context passed from the assistant to an agent.
   *
   * @param \Drupal\ai_assistant_api\Event\AiAssistantPassContextToAgentEvent $event
   *   The pass context event.
   */
  public function onPassContext(AiAssistantPassContextToAgentEvent $event): void {
    $this->contexts[$event->getAgent()->getId()] = $event->getContext();
  }

  /**
   * Appends the captured context to the agent's system prompt.
   *
   * @param \Drupal\ai_agents\Event\BuildSystemPromptEvent $event
   *   The build system prompt event.
   */
  public function onBuildSystemPrompt(BuildSystemPromptEvent $event): void {
    $context = $this->contexts[$event->getAgentId()] ?? [];
    if (!$context) {
      return;
    }
    $lines = [];
    foreach ($context as $key => $value) {
      $value = $this->describeValue($value);
      if ($value === '') {
        continue;
      }
      $lines[] = '- ' . $this->neutralizeTokens((string) $key) . ': ' . $this->neutralizeTokens($value);
    }
    if (!$lines) {
      return;
    }
    $prompt = $event->getSystemPrompt();
    $prompt .= "\n\nThe following context about the user's current session was provided by the assistant frontend:\n" . implode("\n", $lines);
    $event->setSystemPrompt($prompt);
  }

  /**
   * Describes a single context value as one line of text.
   *
   * @param mixed $value
   *   The context value to describe.
   *
   * @return string
   *   The description, or an empty string if the value carries no information.
   */
  protected function describeValue($value): string {
    if (is_bool($value)) {
      return $value ? 'true' : 'false';
    }
    if (is_scalar($value)) {
      return (string) $value;
    }
    // Route parameters reach the context upcast to entity objects, which lose
    // everything the agent could use when they are encoded as JSON.
    if ($value instanceof EntityInterface) {
      return $value->getEntityTypeId() . ' ' . $value->id() . ' (' . $value->label() . ')';
    }
    $encoded = (string) json_encode($value);
    return in_array($encoded, ['', '{}', '[]', 'null'], TRUE) ? '' : $encoded;
  }

  /**
   * Removes Drupal token syntax from a context string.
   *
   * The agent runs token replacement over the finished system prompt, so any
   * token syntax left in the context would be expanded against the site and
   * the current user. The context can originate from the request body, which
   * would let a caller read out values such as [site:mail]. The pattern
   * mirrors the one used by \Drupal\Core\Utility\Token::scan().
   *
   * @param string $value
   *   The context string to clean.
   *
   * @return string
   *   The context string without token syntax.
   */
  protected function neutralizeTokens(string $value): string {
    return preg_replace('/\[[^\s\[\]:]+:[^\[\]]+\]/', '', $value) ?? '';
  }

}
