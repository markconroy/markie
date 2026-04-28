<?php

namespace Drupal\ai\EventSubscriber;

use Drupal\ai\Entity\AiGuardrailModeEnum;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\Event\PostGenerateResponseEvent;
use Drupal\ai\Guardrail\AiGuardrailPluginManager;
use Drupal\ai\Guardrail\NonDeterministicGuardrailInterface;
use Drupal\ai\Guardrail\NonStreamableGuardrailInterface;
use Drupal\ai\Guardrail\Result\PassResult;
use Drupal\ai\Guardrail\Result\RewriteInputResult;
use Drupal\ai\Guardrail\Result\RewriteOutputResult;
use Drupal\ai\Guardrail\Result\StopResult;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\ChatOutput;
use Drupal\ai\OperationType\Chat\StreamedChatMessageIteratorInterface;
use Drupal\ai\OperationType\InputInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\ai\Event\PreGenerateResponseEvent;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Listens to AI events and applies guardrails.
 *
 * This subscriber listens to pre- and post-generate response events
 * and applies the configured guardrails to the input and output respectively.
 *
 * @package Drupal\ai\EventSubscriber
 */
class GuardrailsEventSubscriber implements EventSubscriberInterface {

  /**
   * Constructor.
   */
  public function __construct(
    protected readonly AiGuardrailPluginManager $aiGuardrailPluginManager,
    #[Autowire(service: 'ai.provider')]
    protected readonly AiProviderPluginManager $aiProviderPluginManager,
    protected readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * {@inheritdoc}
   *
   * @return array
   *   The pre generate response event.
   */
  public static function getSubscribedEvents(): array {
    return [
      PreGenerateResponseEvent::EVENT_NAME => 'applyPreGenerateGuardrails',
      PostGenerateResponseEvent::EVENT_NAME => 'applyPostGenerateGuardrails',
    ];
  }

  /**
   * Apply pre-generate guardrails.
   *
   * @param \Drupal\ai\Event\PreGenerateResponseEvent $event
   *   The pre generate response event.
   */
  public function applyPreGenerateGuardrails(
    PreGenerateResponseEvent $event,
  ): void {
    $input = $event->getInput();

    if (!$input instanceof InputInterface) {
      return;
    }

    if ($input->getGuardrailSet() === NULL) {
      return;
    }

    $guardrail_set = $input->getGuardrailSet();
    $aggregated_score = 0;
    $aggregated_messages = [];

    foreach ($guardrail_set->getPreGenerateGuardrails() as $guardrail) {
      if ($guardrail instanceof NonDeterministicGuardrailInterface) {
        $guardrail->setAiPluginManager($this->aiProviderPluginManager);
      }

      $result = $guardrail->processInput($input);
      $input->addGuardrailResult($result, AiGuardrailModeEnum::PreGenerate);

      if ($result instanceof PassResult) {
        continue;
      }

      if ($result instanceof StopResult) {
        $aggregated_score += $result->getScore();
        $aggregated_messages[] = $result->getMessage();

        if ($aggregated_score >= $guardrail_set->getStopThreshold()) {
          $event->setForcedOutputObject(
            new ChatOutput(
              new ChatMessage('assistant', \implode(' ', $aggregated_messages)),
              $result->getMessage(),
              []
            )
          );

          return;
        }
      }

      if (
        $result instanceof RewriteInputResult &&
        \method_exists($input, 'getMessages') &&
        \method_exists($input, 'setMessages')
      ) {
        $messages = $input->getMessages();
        // Replace the last message with the rewritten one.
        if (!empty($messages)) {
          $last_message = end($messages);
          if ($last_message instanceof ChatMessage) {
            $last_message->setText($result->getMessage());
            $messages[key($messages)] = $last_message;
            $input->setMessages($messages);
            $event->setInput($input);
          }
        }
      }
    }
  }

  /**
   * Apply post-request guardrails.
   *
   * @param \Drupal\ai\Event\PostGenerateResponseEvent $event
   *   The post generate response event.
   */
  public function applyPostGenerateGuardrails(
    PostGenerateResponseEvent $event,
  ): void {
    $output = $event->getOutput();

    $input = $event->getInput();
    if (!$input instanceof InputInterface) {
      return;
    }

    if ($input->getGuardrailSet() === NULL) {
      return;
    }

    $guardrail_set = $input->getGuardrailSet();
    $aggregated_score = 0;

    foreach ($guardrail_set->getPostGenerateGuardrails() as $guardrail) {
      if ($guardrail instanceof NonDeterministicGuardrailInterface) {
        $guardrail->setAiPluginManager($this->aiProviderPluginManager);
      }

      if (
        $output->getNormalized() instanceof StreamedChatMessageIteratorInterface &&
        $guardrail instanceof NonStreamableGuardrailInterface
      ) {
        // Reconstruct the chat output for non-streamable guardrails.
        $output = $output->getNormalized()->reconstructChatOutput();
      }

      $result = $guardrail->processOutput($output);
      $input->addGuardrailResult($result, AiGuardrailModeEnum::PostGenerate);

      if ($result instanceof PassResult) {
        continue;
      }

      if ($result instanceof StopResult) {
        $aggregated_score += $result->getScore();
        $aggregated_messages[] = $result->getMessage();

        if ($aggregated_score >= $guardrail_set->getStopThreshold()) {
          $event->setOutput(
            new ChatOutput(
              new ChatMessage('assistant', \implode(' ', $aggregated_messages)),
              $result->getMessage(),
              []
            )
          );

          return;
        }
      }

      if ($result instanceof RewriteOutputResult) {
        $message = $output->getNormalized();
        // Replace the message with the rewritten one.
        if ($message instanceof ChatMessage) {
          $message->setText($result->getMessage());
          $event->setOutput($output);
        }
      }
    }
  }

}
