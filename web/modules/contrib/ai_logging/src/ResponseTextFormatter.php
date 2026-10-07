<?php

declare(strict_types=1);

namespace Drupal\ai_logging;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Html;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Formats the reply text of an AI log for display.
 *
 * A structured (JSON schema) call stores its JSON answer as its reply text,
 * so there is no separate field for it. When the text decodes to a JSON
 * object or array it is pretty-printed and labeled "Result", otherwise it is
 * shown as it is and labeled "Reply".
 */
class ResponseTextFormatter {

  use StringTranslationTrait;

  /**
   * Formats reply text.
   *
   * @param string $text
   *   The reply text, as stored in the response_text field.
   *
   * @return array{label: \Drupal\Core\StringTranslation\TranslatableMarkup, text: string, structured: bool}
   *   The label to show, the text to show and whether the text is a
   *   structured result.
   */
  public function format(string $text): array {
    $decoded = Json::decode($text);
    if (is_array($decoded)) {
      return [
        'label' => $this->t('Result'),
        'text' => (string) json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        'structured' => TRUE,
      ];
    }
    return [
      'label' => $this->t('Reply'),
      'text' => $text,
      'structured' => FALSE,
    ];
  }

  /**
   * Builds a labeled, preformatted render array for a block of text.
   *
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   The label.
   * @param string|\Drupal\Component\Render\MarkupInterface $text
   *   The text. A plain string is escaped when rendered; already-safe
   *   markup (see buildTranscript()) is rendered as it is.
   *
   * @return array
   *   A render array.
   */
  public function buildPreformatted(TranslatableMarkup $label, string|MarkupInterface $text): array {
    return [
      '#type' => 'inline_template',
      '#template' => '<div class="ai-logging-text"><strong class="ai-logging-text__label">{{ label }}</strong><pre class="ai-logging-text__value">{{ text }}</pre></div>',
      '#context' => [
        'label' => $label,
        'text' => $text,
      ],
    ];
  }

  /**
   * Builds the render array for reply text.
   *
   * @param string $text
   *   The reply text.
   *
   * @return array
   *   A render array.
   */
  public function build(string $text): array {
    $formatted = $this->format($text);
    return $this->buildPreformatted($formatted['label'], $formatted['text']);
  }

  /**
   * Builds a labeled, preformatted render array for a chat transcript.
   *
   * As buildPreformatted(), except that the lines marking where one message
   * ends and the next begins - the role, and any tool calls that role made -
   * are emphasized, so a long transcript can be skimmed for its structure
   * instead of read end to end.
   *
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   The label.
   * @param string $text
   *   The transcript, escaped when rendered.
   *
   * @return array
   *   A render array.
   */
  public function buildTranscript(TranslatableMarkup $label, string $text): array {
    $lines = [];
    foreach (explode("\n", $text) as $line) {
      // Escaped before anything is added, so the only markup that ever
      // reaches the page is the emphasis added here - a transcript holds
      // whatever the user, the model and its tools said, none of it
      // trusted.
      $escaped = Html::escape($line);
      if (preg_match('/^(user|assistant|system|tool)( \(.+\))?$/', $line, $matches) === 1) {
        // A role on its own line, optionally naming the tool whose result
        // follows, e.g. "user" or "tool (search_publications)". Capitalized
        // for reading only - the role itself stays as the providers write
        // it, and the tool's own name is left exactly as it is called.
        $escaped = '<strong>' . ucfirst($matches[1]) . Html::escape($matches[2] ?? '') . '</strong>';
      }
      elseif (preg_match('/^\[calls [^\]]+\]/', $line, $matches) === 1) {
        // Emphasize which tool was called, but not its arguments.
        $call = Html::escape($matches[0]);
        $escaped = '<strong>' . $call . '</strong>' . mb_substr($escaped, mb_strlen($call));
      }
      $lines[] = $escaped;
    }

    return $this->buildPreformatted($label, Markup::create(implode("\n", $lines)));
  }

}
