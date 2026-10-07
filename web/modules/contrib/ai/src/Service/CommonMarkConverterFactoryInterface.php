<?php

declare(strict_types=1);

namespace Drupal\ai\Service;

use League\CommonMark\CommonMarkConverter;

/**
 * Creates CommonMark converters for the AI module.
 *
 * Centralizes creation of \League\CommonMark\CommonMarkConverter instances so
 * that the options passed to the converter are created in a single, injectable
 * place instead of being instantiated ad-hoc throughout the code base.
 */
interface CommonMarkConverterFactoryInterface {

  /**
   * Default converter options for the AI module.
   *
   * Callers may override individual keys via fromOptions().
   *
   * @var array<string, mixed>
   */
  public const DEFAULT_OPTIONS = [
    'html_input' => 'strip',
    'allow_unsafe_links' => FALSE,
  ];

  /**
   * Creates a CommonMark converter from options merged onto module defaults.
   *
   * Passed options override DEFAULT_OPTIONS.
   *
   * @param array $options
   *   Converter options to merge over DEFAULT_OPTIONS. See the CommonMark
   *   documentation for available options (e.g. 'html_input',
   *   'allow_unsafe_links').
   *
   * @return \League\CommonMark\CommonMarkConverter
   *   The configured converter.
   */
  public function fromOptions(array $options = []): CommonMarkConverter;

}
