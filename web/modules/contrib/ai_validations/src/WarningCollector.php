<?php

declare(strict_types=1);

namespace Drupal\ai_validations;

use Drupal\Core\Messenger\MessengerInterface;
use Psr\Log\LoggerInterface;

/**
 * Collects non-blocking validation warnings raised during a request.
 *
 * AI validation rules that support a "warning" tier report their XWARN
 * verdicts here instead of raising a constraint violation, so the entity
 * still saves. The collector forwards each distinct message to the
 * messenger (for form-driven flows) and to the ai_validations logger
 * channel (so headless flows such as JSON:API or programmatic validation
 * keep an audit trail), deduplicating repeats within the request so AJAX
 * rebuilds and repeated validation runs do not stack identical messages.
 */
class WarningCollector {

  /**
   * Messages already emitted during this request, keyed by message text.
   *
   * @var array<string, true>
   */
  protected array $emitted = [];

  /**
   * Constructs a WarningCollector.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Psr\Log\LoggerInterface $logger
   *   The ai_validations logger channel.
   */
  public function __construct(
    protected MessengerInterface $messenger,
    protected LoggerInterface $logger,
  ) {
  }

  /**
   * Emits a non-blocking validation warning once per request.
   *
   * @param string $message
   *   The user-facing warning message.
   */
  public function addWarning(string $message): void {
    if (isset($this->emitted[$message])) {
      return;
    }
    $this->emitted[$message] = TRUE;
    $this->messenger->addWarning($message);
    $this->logger->notice('AI validation warning (content accepted): @message', ['@message' => $message]);
  }

}
