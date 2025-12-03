<?php

namespace Drupal\highlight_js\Plugin\Filter;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Renderer;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use Drupal\highlight_js\HighlightJsPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a text filter that turns < highlight-js > tags into markup.
 *
 * @Filter(
 *   id = "highlight_js",
 *   title = @Translation("Highlight Js"),
 *   description = @Translation("Enable Highlight Js source code syntax highlighter."),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_TRANSFORM_REVERSIBLE,
 *   weight = 100,
 * )
 *
 * @internal
 */
class HighlightJs extends FilterBase implements ContainerFactoryPluginInterface, TrustedCallbackInterface {

  /**
   * The plugin manager for highlight js.
   *
   * @var \Drupal\highlight_js\HighlightJsPluginManager
   */
  protected $highlightJsPluginManager;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  protected $renderer;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The highlight_js settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Renderer $renderer, HighlightJsPluginManager $highlight_js_plugin_manager, AccountProxyInterface $currentUser, ConfigFactoryInterface $config_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->highlightJsPluginManager = $highlight_js_plugin_manager;
    $this->renderer = $renderer;
    $this->currentUser = $currentUser;
    $this->config = $config_factory->get('highlight_js.settings');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('renderer'),
      $container->get('plugin.manager.ckedito5_highlight_js'),
      $container->get('current_user'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) {
    $matches = [];
    $code_matches = [];
    $code_added = preg_match_all('/<code.*?<\/code>/si', $text, $code_matches) > 0;
    $highlight_enabled = preg_match_all('/(?<code><highlight-js.*?<\/highlight-js>)/si', $text, $matches) > 0;
    $text = new FilterProcessResult($text);
    $roles = $this->currentUser->getRoles();
    $copy_enable = FALSE;
    $copy_enable_attr = 'copy-disabled';
    if ($code_added || $highlight_enabled) {
      $theme = $this->config->get('theme') ?? '3024';
      $config_copy_enable = $this->config->get('copy_enable');
      $role_copy_access = $this->config->get('role_copy_access');
      if (!is_array($role_copy_access)) {
        $role_copy_access = [];
      }
      $role_copy_access = array_filter($role_copy_access);
      if ($config_copy_enable) {
        if (!empty($role_copy_access)) {
          $allowed_roles = array_keys($role_copy_access);

          // Check if the user has any of the roles in the $allowed_roles array.
          $user_has_allowed_role = !empty(array_intersect($roles, $allowed_roles));

          if ($user_has_allowed_role) {
            $copy_enable = TRUE;
          }
        }
        else {
          $copy_enable = TRUE;
        }
      }

      if ($highlight_enabled) {
        foreach ($matches['code'] as $code) {
          $source_code = $this->getStringBetween($code, 'data-plugin-config="', '"');
          if ($source_code) {
            $source_code = json_decode(html_entity_decode($source_code));
            $text_content = htmlspecialchars($source_code->text ?? '');
            $language = $source_code->language ?? '';
            $role_copy_access = $source_code->role_copy_access ?? [];
            $role_based_copy = FALSE;
            if (isset($source_code->role_based_copy)) {
              if ($source_code->role_based_copy) {
                $role_based_copy = TRUE;
              }
            }

            if ($config_copy_enable) {
              if (!is_array($role_copy_access)) {
                $role_copy_access = (array) $role_copy_access;
              }
              $role_copy_access = array_filter($role_copy_access);
              if ($role_based_copy && !empty($role_copy_access)) {
                $allowed_roles = array_keys($role_copy_access);
                // Check if the user has any of the roles in the $allowed_roles
                // array.
                $user_has_allowed_role = !empty(array_intersect($roles, $allowed_roles));

                if ($user_has_allowed_role) {
                  $copy_enable_attr = 'copy-enabled';
                }
              }
              else {
                $copy_enable_attr = 'copy-enabled';
              }
            }

            $replace = '<pre data-src="highlight.js" class="language-' . $language . '"><code class="language-' . $language . '" ' . $copy_enable_attr . '>' . $text_content . '</code></pre>';

            // Get the current processed text.
            $processedText = $text->getProcessedText();
            $processedText = str_replace($code, $replace, $processedText);

            // Set the modified processed text back to the FilterProcessResult
            // object.
            $text->setProcessedText($processedText);
          }
        }
      }

      $copy_bg_transparent = $this->config->get('copy_bg_transparent');
      $copy_bg_color = $this->config->get('copy_bg_color');
      $copy_txt_color = $this->config->get('copy_txt_color');
      $copy_btn_text = $this->config->get('copy_btn_text');

      $success_bg_transparent = $this->config->get('success_bg_transparent');
      $success_bg_color = $this->config->get('success_bg_color');
      $success_txt_color = $this->config->get('success_txt_color');
      $copy_success_text = $this->config->get('copy_success_text');

      $button_data = [
        'copy_enable' => $copy_enable,
        'copy_bg_transparent' => (bool) $copy_bg_transparent,
        'copy_bg_color' => ($copy_bg_color != '') ? $copy_bg_color : '#4243b1',
        'copy_txt_color' => ($copy_txt_color != '') ? $copy_txt_color : '#ffffff',
        'copy_btn_text' => ($copy_btn_text != '') ? $copy_btn_text : 'Copy',
        'success_bg_transparent' => $success_bg_transparent ? TRUE : FALSE,
        'success_bg_color' => ($success_bg_color != '') ? $success_bg_color : '#4243b1',
        'success_txt_color' => ($success_txt_color != '') ? $success_txt_color : '#ffffff',
        'copy_success_text' => ($copy_success_text != '') ? $copy_success_text : 'Copied!',
      ];

      $library = 'highlight_js/highlight_js.style-' . $theme;

      $text->addAttachments([
        'library' => [
          'highlight_js/highlight_js.custom',
          $library,
        ],
        'drupalSettings' => [
          'button_data' => $button_data,
        ],
      ]);
    }

    return $text;
  }

  /**
   * Gets the substring between two specified strings.
   *
   * @param string $string
   *   The original string to search within.
   * @param string $start
   *   The starting string.
   * @param string $end
   *   The ending string.
   *
   * @return string
   *   The substring between $start and $end in the given $string.
   */
  public function getStringBetween($string, $start, $end) {
    $string = ' ' . $string;
    $ini = strpos($string, $start);
    if ($ini == 0) {
      return '';
    }
    $ini += strlen($start);
    $len = strpos($string, $end, $ini) - $ini;
    return substr($string, $ini, $len);
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return [];
  }

}
