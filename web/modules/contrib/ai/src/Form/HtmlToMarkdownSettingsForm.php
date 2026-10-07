<?php

declare(strict_types=1);

namespace Drupal\ai\Form;

use Drupal\ai\Service\HtmlToMarkdown\HtmlToMarkdownConverter;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure the AI module's default HTML to Markdown converter.
 *
 * Only reachable when the markdownify module is not installed; see
 * \Drupal\ai\Controller\HtmlToMarkdownSettingsController.
 */
class HtmlToMarkdownSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ai_html_to_markdown_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [HtmlToMarkdownConverter::CONFIG_NAME];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildForm($form, $form_state);
    $config = $this->config(HtmlToMarkdownConverter::CONFIG_NAME);

    $form['description'] = [
      '#markup' => $this->t('These settings control the league/html-to-markdown converter used by the AI module to turn HTML into Markdown. Install and enable the <a href=":url">Markdownify</a> module for a richer, pluggable converter with additional configuration options.', [
        ':url' => 'https://www.drupal.org/project/markdownify',
      ]),
    ];

    $form['header_style'] = [
      '#type' => 'select',
      '#title' => $this->t('Header style'),
      '#description' => $this->t('Set to "atx" to output H1 and H2 headers as # Header1 and ## Header2.'),
      '#options' => [
        'atx' => $this->t('atx'),
        'setext' => $this->t('setext'),
      ],
      '#default_value' => $config->get('header_style') ?? 'atx',
    ];
    $form['suppress_errors'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Suppress errors'),
      '#description' => $this->t('Set to false to show warnings when loading malformed HTML.'),
      '#default_value' => $config->get('suppress_errors') ?? TRUE,
      '#return_value' => TRUE,
    ];
    $form['strip_tags'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Strip tags'),
      '#description' => $this->t("Set to true to strip tags that don't have markdown equivalents. N.B. Strips tags, not their content. Useful to clean MS Word HTML output."),
      '#default_value' => $config->get('strip_tags') ?? TRUE,
      '#return_value' => TRUE,
    ];
    $form['strip_placeholder_links'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Strip placeholder links'),
      '#description' => $this->t("Set to true to remove 'a' that doesn't have href."),
      '#default_value' => $config->get('strip_placeholder_links') ?? FALSE,
      '#return_value' => TRUE,
    ];
    $form['bold_style'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Bold style'),
      '#description' => $this->t("DEPRECATED: Set to '__' if you prefer the underlined style."),
      '#default_value' => $config->get('bold_style') ?? '',
    ];
    $form['italic_style'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Italic style'),
      '#description' => $this->t("DEPRECATED: Set to '_' if you prefer the underlined style."),
      '#default_value' => $config->get('italic_style') ?? '',
    ];
    $form['remove_nodes'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Remove nodes'),
      '#description' => $this->t("Space-separated list of dom nodes that should be removed. example: 'meta style script'"),
      '#default_value' => $config->get('remove_nodes') ?? '',
    ];
    $form['hard_break'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hard break'),
      '#description' => $this->t("Set to true to turn 'br' into a simple newline '\\n' instead of two spaces followed by newline '&nbsp;  \\n'"),
      '#default_value' => $config->get('hard_break') ?? FALSE,
      '#return_value' => TRUE,
    ];
    $form['list_item_style'] = [
      '#type' => 'select',
      '#title' => $this->t('List item style'),
      '#description' => $this->t("Set the default character for each 'li' in a 'ul'."),
      '#options' => [
        '-' => '-',
        '+' => '+',
        '*' => '*',
      ],
      '#default_value' => $config->get('list_item_style') ?? '-',
    ];
    $form['preserve_comments'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Preserve comments'),
      '#description' => $this->t('Set to true to preserve comments.'),
      '#default_value' => $config->get('preserve_comments') ?? FALSE,
      '#return_value' => TRUE,
    ];
    $form['use_autolinks'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use autolinks'),
      '#description' => $this->t('Set to true to use simple link syntax if possible. Will always use []() if set to false.'),
      '#default_value' => $config->get('use_autolinks') ?? TRUE,
      '#return_value' => TRUE,
    ];
    $form['table_pipe_escape'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Table pipe escape'),
      '#description' => $this->t('Replacement string for pipe characters inside markdown table cells.'),
      '#default_value' => $config->get('table_pipe_escape') ?? '',
    ];
    $form['table_caption_side'] = [
      '#type' => 'select',
      '#title' => $this->t('Table caption side'),
      '#description' => $this->t("Set to 'top' or 'bottom' to show 'caption' content before or after table, no value to suppress."),
      '#options' => [
        '' => $this->t('No value'),
        'top' => $this->t('Top'),
        'bottom' => $this->t('Bottom'),
      ],
      '#default_value' => $config->get('table_caption_side') ?? '',
    ];
    $form['strip_whitespace'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Strip whitespace'),
      '#description' => $this->t('Set to true to trim trailing whitespace and collapse extra blank lines in the resulting Markdown. This is an AI module post-processing step, not a league/html-to-markdown option.'),
      '#default_value' => $config->get('strip_whitespace') ?? TRUE,
      '#return_value' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config(HtmlToMarkdownConverter::CONFIG_NAME)
      ->set('header_style', $form_state->getValue('header_style'))
      ->set('suppress_errors', (bool) $form_state->getValue('suppress_errors'))
      ->set('strip_tags', (bool) $form_state->getValue('strip_tags'))
      ->set('strip_placeholder_links', (bool) $form_state->getValue('strip_placeholder_links'))
      ->set('bold_style', $form_state->getValue('bold_style'))
      ->set('italic_style', $form_state->getValue('italic_style'))
      ->set('remove_nodes', $form_state->getValue('remove_nodes'))
      ->set('hard_break', (bool) $form_state->getValue('hard_break'))
      ->set('list_item_style', $form_state->getValue('list_item_style'))
      ->set('preserve_comments', (bool) $form_state->getValue('preserve_comments'))
      ->set('use_autolinks', (bool) $form_state->getValue('use_autolinks'))
      ->set('table_pipe_escape', $form_state->getValue('table_pipe_escape'))
      ->set('table_caption_side', $form_state->getValue('table_caption_side'))
      ->set('strip_whitespace', (bool) $form_state->getValue('strip_whitespace'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
