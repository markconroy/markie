<?php

declare(strict_types=1);

namespace Drupal\ai\Controller;

use Drupal\ai\Form\HtmlToMarkdownSettingsForm;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Serves the HTML to Markdown converter settings page.
 *
 * Delegates to the markdownify settings page when markdownify is installed,
 * since in that case markdownify's converter, plugins and settings are the
 * ones actually in effect for ai.html_to_markdown_converter.
 *
 * @see \Drupal\ai\AiServiceProvider
 */
class HtmlToMarkdownSettingsController extends ControllerBase {

  /**
   * Builds the page, or redirects to markdownify's settings page.
   *
   * @return array|\Symfony\Component\HttpFoundation\RedirectResponse
   *   The settings form render array, or a redirect response.
   */
  public function build(): array|RedirectResponse {
    if ($this->moduleHandler()->moduleExists('markdownify')) {
      return new RedirectResponse(Url::fromRoute('markdownify.settings')->toString());
    }
    return $this->formBuilder()->getForm(HtmlToMarkdownSettingsForm::class);
  }

}
