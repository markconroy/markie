<?php

namespace Drupal\twig_tweak;

use Drupal\Core\Block\TitleBlockPluginInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\TitleResolverInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\Utility\Token;
use Drupal\image\Entity\ImageStyle;
use Symfony\Cmf\Component\Routing\RouteObjectInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Twig extension with some useful functions and filters.
 */
class TwigExtension extends \Twig_Extension {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The menu link tree service.
   *
   * @var \Drupal\Core\Menu\MenuLinkTreeInterface
   */
  protected $menuTree;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The title resolver.
   *
   * @var \Drupal\Core\Controller\TitleResolverInterface
   */
  protected $titleResolver;

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * TwigExtension constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   * @param \Drupal\Core\Menu\MenuLinkTreeInterface $menu_tree
   *   The menu tree service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Controller\TitleResolverInterface $title_resolver
   *   The title resolver.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, Token $token, ConfigFactoryInterface $config_factory, RouteMatchInterface $route_match, MenuLinkTreeInterface $menu_tree, RequestStack $request_stack, TitleResolverInterface $title_resolver, FormBuilderInterface $form_builder) {
    $this->entityTypeManager = $entity_type_manager;
    $this->token = $token;
    $this->configFactory = $config_factory;
    $this->routeMatch = $route_match;
    $this->menuTree = $menu_tree;
    $this->requestStack = $request_stack;
    $this->titleResolver = $title_resolver;
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public function getFunctions() {
    return [
      new \Twig_SimpleFunction('drupal_view', 'views_embed_view'),
      new \Twig_SimpleFunction('drupal_block', [$this, 'drupalBlock']),
      new \Twig_SimpleFunction('drupal_region', [$this, 'drupalRegion']),
      new \Twig_SimpleFunction('drupal_entity', [$this, 'drupalEntity']),
      new \Twig_SimpleFunction('drupal_field', [$this, 'drupalField']),
      new \Twig_SimpleFunction('drupal_menu', [$this, 'drupalMenu']),
      new \Twig_SimpleFunction('drupal_form', [$this, 'drupalForm']),
      new \Twig_SimpleFunction('drupal_token', [$this, 'drupalToken']),
      new \Twig_SimpleFunction('drupal_config', [$this, 'drupalConfig']),
      new \Twig_SimpleFunction('drupal_dump', [$this, 'drupalDump']),
      new \Twig_SimpleFunction('dd', [$this, 'drupalDump']),
      // Wrap drupal_set_message() because it returns some value which is not
      // suitable for Twig template.
      new \Twig_SimpleFunction('drupal_set_message', [$this, 'drupalSetMessage']),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFilters() {
    $filters = [
      new \Twig_SimpleFilter('token_replace', [$this, 'tokenReplaceFilter']),
      new \Twig_SimpleFilter('preg_replace', [$this, 'pregReplaceFilter']),
      new \Twig_SimpleFilter('image_style', [$this, 'imageStyle']),
    ];
    // PHP filter should be enabled in settings.php file.
    if (Settings::get('twig_tweak_enable_php_filter')) {
      $filters[] = new \Twig_SimpleFilter('php', [$this, 'phpFilter']);
    }
    return $filters;
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return 'twig_tweak';
  }

  /**
   * Builds the render array for the provided block.
   *
   * @param mixed $id
   *   The ID of the block to render.
   *
   * @return null|array
   *   A render array for the block or NULL if the block does not exist.
   */
  public function drupalBlock($id) {
    $block = $this->entityTypeManager->getStorage('block')->load($id);
    return $block ?
      $this->entityTypeManager->getViewBuilder('block')->view($block) : '';
  }

  /**
   * Builds the render array of a given region.
   *
   * @param string $region
   *   The region to build.
   * @param string $theme
   *   (Optional) The name of the theme to load the region. If it is not
   *   provided then default theme will be used.
   *
   * @return array
   *   A render array to display the region content.
   */
  public function drupalRegion($region, $theme = NULL) {
    $blocks = $this->entityTypeManager->getStorage('block')->loadByProperties([
      'region' => $region,
      'theme'  => $theme ?: $this->configFactory->get('system.theme')->get('default'),
    ]);

    $view_builder = $this->entityTypeManager->getViewBuilder('block');

    $build = [];
    /* @var $blocks \Drupal\block\BlockInterface[] */
    foreach ($blocks as $id => $block) {
      $block_plugin = $block->getPlugin();
      if ($block_plugin instanceof TitleBlockPluginInterface) {
        $request = $this->requestStack->getCurrentRequest();
        if ($route = $request->attributes->get(RouteObjectInterface::ROUTE_OBJECT)) {
          $block_plugin->setTitle($this->titleResolver->getTitle($request, $route));
        }
      }
      $build[$id] = $view_builder->view($block);
    }

    return $build;
  }

  /**
   * Returns the render array for an entity.
   *
   * @param string $entity_type
   *   The entity type.
   * @param mixed $id
   *   The ID of the entity to render.
   * @param string $view_mode
   *   (optional) The view mode that should be used to render the entity.
   * @param string $langcode
   *   (optional) For which language the entity should be rendered, defaults to
   *   the current content language.
   *
   * @return null|array
   *   A render array for the entity or NULL if the entity does not exist.
   */
  public function drupalEntity($entity_type, $id = NULL, $view_mode = NULL, $langcode = NULL) {
    $entity = $id ?
      $this->entityTypeManager->getStorage($entity_type)->load($id) :
      $this->routeMatch->getParameter($entity_type);
    if ($entity) {
      $render_controller = $this->entityTypeManager->getViewBuilder($entity_type);
      return $render_controller->view($entity, $view_mode, $langcode);
    }
    return NULL;
  }

  /**
   * Returns the render array for a single entity field.
   *
   * @param string $field_name
   *   The field name.
   * @param string $entity_type
   *   The entity type.
   * @param mixed $id
   *   The ID of the entity to render.
   * @param string $view_mode
   *   (optional) The view mode that should be used to render the field.
   * @param string $langcode
   *   (optional) Language code to load translation.
   *
   * @return null|array
   *   A render array for the field or NULL if the value does not exist.
   */
  public function drupalField($field_name, $entity_type, $id = NULL, $view_mode = 'default', $langcode = NULL) {
    $entity = $id ?
      $this->entityTypeManager->getStorage($entity_type)->load($id) :
      $this->routeMatch->getParameter($entity_type);
    if ($langcode && $entity->hasTranslation($langcode)) {
      $entity = $entity->getTranslation($langcode);
    }
    if (isset($entity->{$field_name})) {
      return $entity->{$field_name}->view($view_mode);
    }
    return NULL;
  }

  /**
   * Returns the render array for Drupal menu.
   *
   * @param string $menu_name
   *   The name of the menu.
   * @param int $level
   *   (optional) Initial menu level.
   * @param int $depth
   *   (optional) Maximum number of menu levels to display.
   *
   * @return array
   *   A render array for the menu.
   */
  public function drupalMenu($menu_name, $level = 1, $depth = 0) {
    $parameters = $this->menuTree->getCurrentRouteMenuTreeParameters($menu_name);

    // Adjust the menu tree parameters based on the block's configuration.
    $parameters->setMinDepth($level);
    // When the depth is configured to zero, there is no depth limit. When depth
    // is non-zero, it indicates the number of levels that must be displayed.
    // Hence this is a relative depth that we must convert to an actual
    // (absolute) depth, that may never exceed the maximum depth.
    if ($depth > 0) {
      $parameters->setMaxDepth(min($level + $depth - 1, $this->menuTree->maxDepth()));
    }

    $tree = $this->menuTree->load($menu_name, $parameters);
    $manipulators = [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ];
    $tree = $this->menuTree->transform($tree, $manipulators);
    return $this->menuTree->build($tree);
  }

  /**
   * Builds and processes a form for a given form ID.
   *
   * @param string $form_id
   *   The form ID.
   *
   * @return array
   *   A render array to represent the form.
   */
  public function drupalForm($form_id) {
    return $this->formBuilder->getForm($form_id);
  }

  /**
   * Replaces a given tokens with appropriate value.
   *
   * @param string $token
   *   A replaceable token.
   * @param array $data
   *   (optional) An array of keyed objects. For simple replacement scenarios
   *   'node', 'user', and others are common keys, with an accompanying node or
   *   user object being the value. Some token types, like 'site', do not
   *   require any explicit information from $data and can be replaced even if
   *   it is empty.
   * @param array $options
   *   (optional) A keyed array of settings and flags to control the token
   *   replacement process.
   *
   * @return string
   *   The token value.
   *
   * @see \Drupal\Core\Utility\Token::replace()
   */
  public function drupalToken($token, array $data = [], array $options = []) {
    return $this->token->replace("[$token]", $data, $options);
  }

  /**
   * Gets data from this configuration.
   *
   * @param string $name
   *   The name of the configuration object to construct.
   * @param string $key
   *   A string that maps to a key within the configuration data.
   *
   * @return mixed
   *   The data that was requested.
   */
  public function drupalConfig($name, $key) {
    return $this->configFactory->get($name)->get($key);
  }

  /**
   * Dumps information about variables.
   */
  public function drupalDump() {
    $var_dumper = '\Symfony\Component\VarDumper\VarDumper';
    if (class_exists($var_dumper)) {
      call_user_func($var_dumper . '::dump', func_get_args());
    }
    else {
      trigger_error('Could not dump the variable because symfony/var-dumper component is not installed.', E_USER_WARNING);
    }
  }

  /**
   * An alias for self::drupalDump().
   *
   * @see \Drupal\twig_tweak\TwigExtension::drupalDump();
   */
  public function dd() {
    $this->drupalDump(func_get_args());
  }

  /**
   * Sets a message to display to the user.
   *
   * @param string|\Drupal\Component\Render\MarkupInterface $message
   *   (optional) The translated message to be displayed to the user.
   * @param string $type
   *   (optional) The message's type. Defaults to 'status'.
   * @param bool $repeat
   *   (optional) If this is FALSE and the message is already set, then the
   *   message will not be repeated. Defaults to FALSE.
   *
   * @return array
   *   A render array to disable caching.
   *
   * @see drupal_set_message()
   */
  public function drupalSetMessage($message = NULL, $type = 'status', $repeat = FALSE) {
    drupal_set_message($message, $type, $repeat);
    $build['#cache']['max-age'] = 0;
    return $build;
  }

  /**
   * Replaces all tokens in a given string with appropriate values.
   *
   * @param string $text
   *   An HTML string containing replaceable tokens.
   *
   * @return string
   *   The entered HTML text with tokens replaced.
   */
  public function tokenReplaceFilter($text) {
    return $this->token->replace($text);
  }

  /**
   * Performs a regular expression search and replace.
   *
   * @param string $text
   *   The text to search and replace.
   * @param string $pattern
   *   The pattern to search for.
   * @param string $replacement
   *   The string to replace.
   *
   * @return string
   *   The new text if matches are found, otherwise unchanged text.
   */
  public function pregReplaceFilter($text, $pattern, $replacement) {
    return preg_replace("/$pattern/", $replacement, $text);
  }

  /**
   * Returns the URL of this image derivative for an original image path or URI.
   *
   * @param string $path
   *   The path or URI to the original image.
   * @param string $style
   *   The image style.
   *
   * @return string
   *   The absolute URL where a style image can be downloaded, suitable for use
   *   in an <img> tag. Requesting the URL will cause the image to be created.
   */
  public function imageStyle($path, $style) {
    if ($image_style = ImageStyle::load($style)) {
      return file_url_transform_relative($image_style->buildUrl($path));
    }
  }

  /**
   * Evaluates a string of PHP code.
   *
   * @param string $code
   *   Valid PHP code to be evaluated.
   *
   * @return mixed
   *   The eval() result.
   */
  public function phpFilter($code) {
    ob_start();
    // @codingStandardsIgnoreStart
    print eval($code);
    // @codingStandardsIgnoreEnd
    $output = ob_get_contents();
    ob_end_clean();
    return $output;
  }

}
