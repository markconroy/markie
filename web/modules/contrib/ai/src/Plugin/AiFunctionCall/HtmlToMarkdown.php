<?php

namespace Drupal\ai\Plugin\AiFunctionCall;

use Drupal\ai\Attribute\FunctionCall;
use Drupal\ai\Base\FunctionCallBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use Drupal\ai\Service\FunctionCalling\FunctionCallInterface;
use Drupal\ai\Service\HtmlToMarkdown\HtmlToMarkdownConverterInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the html to markdown converter.
 */
#[FunctionCall(
  id: 'ai_agent:html_to_markdown',
  function_name: 'ai_agent_html_to_markdown',
  name: 'HTML to Markdown',
  description: 'This method is used to convert HTML to Markdown.',
  group: 'modification_tools',
  context_definitions: [
    'content' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Content"),
      description: new TranslatableMarkup("The html markup to convert to markdown."),
      required: TRUE,
    ),
  ],
)]
class HtmlToMarkdown extends FunctionCallBase implements ExecutableFunctionCallInterface {

  /**
   * The HTML to Markdown converter.
   */
  protected HtmlToMarkdownConverterInterface $htmlToMarkdownConverter;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): FunctionCallInterface|static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->htmlToMarkdownConverter = $container->get('ai.html_to_markdown_converter');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute() {
    $content = $this->getContextValue('content');
    $content = $this->htmlToMarkdownConverter->convert($content);
    $this->setOutput($content);
  }

}
