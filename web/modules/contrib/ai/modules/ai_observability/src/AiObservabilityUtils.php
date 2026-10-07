<?php

namespace Drupal\ai_observability;

use Drupal\Component\Serialization\Json;
use Drupal\ai\OperationType\InputInterface;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\ChatOutput;
use Drupal\ai\OperationType\OutputInterface;
use Drupal\ai_observability\Form\SettingsForm;
use Drupal\Core\Config\ImmutableConfig;

/**
 * Utility functions for AI observability module.
 *
 * @package Drupal\ai_observability
 */
class AiObservabilityUtils {

  /**
   * Default maximum string length to keep before truncating a leaf value.
   */
  public const MAX_STRING_VALUE_LENGTH = 256;

  /**
   * Default maximum list items to keep before collapsing the middle.
   */
  public const MAX_LIST_ITEMS = 6;

  /**
   * Default maximum associative array keys to keep before collapsing the tail.
   */
  public const MAX_ASSOC_KEYS = 12;

  /**
   * Converts AI input to a string representation for logging and tracing.
   *
   * @param \Drupal\ai\OperationType\InputInterface $input
   *   The AI input object.
   * @param bool $structured
   *   Whether to return the structured JSON representation. When FALSE, the
   *   input's own ::toString() representation is returned (legacy behavior).
   *
   * @return string
   *   The string representation of the AI input.
   */
  public static function aiInputToString(InputInterface $input, bool $structured = TRUE): string {
    if (!$structured) {
      return $input->toString();
    }
    $payload = $input->toArray();
    if ($payload === []) {
      return $input->toString();
    }
    return self::encodePayload($payload);
  }

  /**
   * Converts AI output to a string representation for logging and tracing.
   *
   * @todo Get rid of this when the OutputInterface will have toString() method.
   *
   * @param mixed $output
   *   The AI output object.
   * @param bool $structured
   *   Whether to use the structured JSON representation for OutputInterface
   *   instances. When FALSE, the legacy per-type formatting is used.
   *
   * @return string
   *   The string representation of the AI output.
   */
  public static function aiOutputToString(mixed $output, bool $structured = TRUE): string {
    if ($structured && $output instanceof OutputInterface) {
      $payload = $output->toArray();
      if ($payload !== []) {
        return self::encodePayload($payload);
      }
    }

    if ($output instanceof ChatOutput) {
      $normalized = $output->getNormalized();
      if ($normalized instanceof ChatMessage) {
        return self::chatMessageToString($normalized);
      }
      return 'Streamed chat output cannot be converted to string directly.';
    }

    if ($output instanceof ChatMessage) {
      return self::chatMessageToString($output);
    }

    return 'Output type ' . get_debug_type($output) . ' not supported for string conversion.';
  }

  /**
   * Converts a ChatMessage to a string representation.
   *
   * @param \Drupal\ai\OperationType\Chat\ChatMessage $message
   *   The ChatMessage instance.
   *
   * @return string
   *   The string representation of the ChatMessage.
   */
  public static function chatMessageToString(ChatMessage $message): string {
    $files_text = '';
    $files = $message->getFiles();
    if (!empty($files)) {
      $file_names = array_map(fn ($file) => $file->getFileName(), $files);
      $files_text = ' [Files: ' . implode(', ', $file_names) . ']';
    }
    return sprintf('%s: %s%s', $message->getRole(), $message->getText(), $files_text);
  }

  /**
   * Summarizes AI payload for logging and tracing.
   *
   * @param string $payload
   *   The AI payload in the string representation.
   * @param int $maxLength
   *   The maximum length of the summarized payload.
   * @param array $options
   *   Summarization options:
   *   - summarize (bool): Whether to apply structured per-leaf summarization
   *     before truncating. Defaults to TRUE to preserve historical method
   *     behavior; callers driven by site configuration should pass an
   *     explicit value.
   *   - max_string_length (int): Override for MAX_STRING_VALUE_LENGTH.
   *   - max_list_items (int): Override for MAX_LIST_ITEMS.
   *   - max_assoc_keys (int): Override for MAX_ASSOC_KEYS.
   *
   * @return string
   *   The stringified AI payload.
   */
  public static function summarizeAiPayloadData(string $payload, int $maxLength = 1024, array $options = []): string {
    $summarize = $options['summarize'] ?? TRUE;
    if ($summarize) {
      $maxStringLength = $options['max_string_length'] ?? self::MAX_STRING_VALUE_LENGTH;
      $maxListItems = $options['max_list_items'] ?? self::MAX_LIST_ITEMS;
      $maxAssocKeys = $options['max_assoc_keys'] ?? self::MAX_ASSOC_KEYS;

      $decoded = Json::decode($payload);
      if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $payload = self::encodePayload(self::summarizeValue($decoded, $maxStringLength, $maxListItems, $maxAssocKeys));
      }
    }

    if (mb_strlen($payload) <= $maxLength) {
      return $payload;
    }

    return self::truncateString($payload, $maxLength);
  }

  /**
   * Builds the options array for ::summarizeAiPayloadData.
   *
   * @param \Drupal\Core\Config\ImmutableConfig $config
   *   The ai_observability.settings config object.
   *
   * @return array
   *   Options array consumed by ::summarizeAiPayloadData.
   */
  public static function buildSummarizeOptions(ImmutableConfig $config): array {
    return [
      'summarize' => (bool) $config->get(SettingsForm::CONFIG_KEY_SUMMARIZE_PAYLOAD),
      'max_string_length' => $config->get(SettingsForm::CONFIG_KEY_SUMMARIZE_PAYLOAD_MAX_STRING_LENGTH) ?? self::MAX_STRING_VALUE_LENGTH,
      'max_list_items' => $config->get(SettingsForm::CONFIG_KEY_SUMMARIZE_PAYLOAD_MAX_LIST_ITEMS) ?? self::MAX_LIST_ITEMS,
      'max_assoc_keys' => $config->get(SettingsForm::CONFIG_KEY_SUMMARIZE_PAYLOAD_MAX_ASSOC_KEYS) ?? self::MAX_ASSOC_KEYS,
    ];
  }

  /**
   * Encodes payload data to JSON for observability output.
   *
   * @param mixed $payload
   *   The payload to encode.
   *
   * @return string
   *   The encoded payload.
   */
  private static function encodePayload(mixed $payload): string {
    $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return $encoded === FALSE ? Json::encode($payload) : $encoded;
  }

  /**
   * Summarizes a payload value recursively.
   *
   * @param mixed $value
   *   The value to summarize.
   * @param int $maxStringLength
   *   The maximum length to keep for leaf strings before truncation.
   * @param int $maxListItems
   *   The maximum list items to keep before collapsing the middle.
   * @param int $maxAssocKeys
   *   The maximum associative keys to keep before collapsing the tail.
   *
   * @return mixed
   *   The summarized value.
   */
  private static function summarizeValue(mixed $value, int $maxStringLength, int $maxListItems, int $maxAssocKeys): mixed {
    if (is_string($value)) {
      return self::summarizeLeafString($value, $maxStringLength);
    }

    if (!is_array($value)) {
      return $value;
    }

    if (array_is_list($value)) {
      $count = count($value);
      // Derive the head/tail sizes from the configured limit so the collapsed
      // output is never larger than the input. The "+1" omitted marker is only
      // added when there is at least one item to omit.
      $headSize = max(1, intdiv($maxListItems, 2));
      $tailSize = max(1, $maxListItems - $headSize);
      if ($count <= $headSize + $tailSize) {
        return array_map(
          fn ($item) => self::summarizeValue($item, $maxStringLength, $maxListItems, $maxAssocKeys),
          $value,
        );
      }

      $head = array_map(
        fn ($item) => self::summarizeValue($item, $maxStringLength, $maxListItems, $maxAssocKeys),
        array_slice($value, 0, $headSize),
      );
      $tail = array_map(
        fn ($item) => self::summarizeValue($item, $maxStringLength, $maxListItems, $maxAssocKeys),
        array_slice($value, -$tailSize),
      );
      $head[] = ['_omitted_items' => $count - $headSize - $tailSize];
      return array_merge($head, $tail);
    }

    $summary = [];
    $count = 0;
    foreach ($value as $key => $item) {
      $count++;
      if ($count > $maxAssocKeys) {
        $summary['_omitted_keys'] = count($value) - $maxAssocKeys;
        break;
      }
      $summary[$key] = self::summarizeValue($item, $maxStringLength, $maxListItems, $maxAssocKeys);
    }
    return $summary;
  }

  /**
   * Summarizes an individual string leaf value.
   *
   * @param string $value
   *   The value to summarize.
   * @param int $maxStringLength
   *   The maximum length to keep before truncating.
   *
   * @return string
   *   The summarized value.
   */
  private static function summarizeLeafString(string $value, int $maxStringLength): string {
    if (preg_match('/^data:[^;]+;base64,/', $value)) {
      return '[data URL omitted, length ' . strlen($value) . ']';
    }

    if (strlen($value) > $maxStringLength && preg_match('/^[A-Za-z0-9+\/=\r\n]+$/', $value)) {
      return '[binary-like content omitted, length ' . strlen($value) . ']';
    }

    if (strlen($value) <= $maxStringLength) {
      return $value;
    }

    return self::truncateString($value, $maxStringLength);
  }

  /**
   * Truncates a string by keeping the beginning and end.
   *
   * @param string $payload
   *   The payload to truncate.
   * @param int $maxLength
   *   The maximum length of the result.
   *
   * @return string
   *   The truncated payload.
   */
  private static function truncateString(string $payload, int $maxLength): string {
    $ellipsis = '[...]';
    $keep = $maxLength - mb_strlen($ellipsis);
    $start = (int) ceil($keep / 2);
    $end = (int) floor($keep / 2);
    return mb_substr($payload, 0, $start) . $ellipsis . mb_substr($payload, -$end);
  }

}
