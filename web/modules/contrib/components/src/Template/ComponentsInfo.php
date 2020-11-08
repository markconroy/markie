<?php

namespace Drupal\components\Template;

use Drupal\Core\Extension\ExtensionList;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ThemeExtensionList;

/**
 * Loads info about components defined in themes or modules.
 */
class ComponentsInfo {

  /**
   * Keep track of component info provided by modules.
   *
   * @var array
   */
  protected $moduleInfo = [];

  /**
   * Keep track of component info provided by themes.
   *
   * @var array
   */
  protected $themeInfo = [];

  /**
   * Module namespaces that cannot be overridden.
   *
   * @var array
   */
  protected $protectedNamespaces = [];

  /**
   * Constructs a new ComponentsInfo object.
   *
   * @param \Drupal\Core\Extension\ModuleExtensionList $module_extension_list
   *   The module extension list service.
   * @param \Drupal\Core\Extension\ThemeExtensionList $theme_extension_list
   *   The theme extension list service.
   */
  public function __construct(ModuleExtensionList $module_extension_list, ThemeExtensionList $theme_extension_list) {
    $this->moduleInfo = $this->findComponentsInfo($module_extension_list);
    $this->themeInfo = $this->findComponentsInfo($theme_extension_list);
  }

  /**
   * Finds component info from the given extension list.
   *
   * @param \Drupal\Core\Extension\ExtensionList $extension_list
   *   The extension list to search.
   *
   * @return array
   *   The components info for all extensions in the extension list.
   */
  protected function findComponentsInfo(ExtensionList $extension_list) {
    $data = [];

    foreach ($extension_list->getAllInstalledInfo() as $name => $extension_info) {
      // Find the components info.
      $info = !empty($extension_info['components']) ? $extension_info['components'] : [];

      // Look for namespaces using 1.x API (backwards compatibility).
      if (!isset($info['namespaces']) && isset($extension_info['component-libraries'])) {
        foreach ($extension_info['component-libraries'] as $namespace => $namespace_data) {
          if (!empty($namespace_data['paths'])) {
            $info['namespaces'][$namespace] = $namespace_data['paths'];
          }
        }
      }

      // Normalize namespace data.
      $extension_path = $extension_list->getPath($name);
      if (isset($info['namespaces'])) {
        foreach ($info['namespaces'] as $namespace => $paths) {
          // Allow paths to be an array or a string.
          if (!is_array($paths)) {
            $info['namespaces'][$namespace] = [];
            $paths = [$paths];
          }

          // Add the project's path to the namespace paths.
          foreach ($paths as $key => $path) {
            $info['namespaces'][$namespace][$key] = $extension_path . '/' . $path;
          }
        }
      }

      // Save the components info for the extension.
      if (!empty($info)) {
        $info['extensionPath'] = $extension_path;
        $data[$name] = $info;
      }

      // The following namespaces are protected because they did not opt-in.
      if ((!isset($info['namespaces']) || empty($info['namespaces'][$name])) && !isset($info['allow_default_namespace_reuse'])) {
        $this->protectedNamespaces[] = $name;
      }
    }

    return $data;
  }

  /**
   * Retrieves the components info for the given module.
   *
   * @param string $name
   *   The name of the module.
   *
   * @return array
   *   The components info.
   */
  public function getModuleInfo($name) {
    if (isset($this->moduleInfo[$name])) {
      return $this->moduleInfo[$name];
    }

    // No components info.
    return [];
  }

  /**
   * Retrieves the components info for all modules.
   *
   * @return array
   *   The components info, keyed by module name.
   */
  public function getAllModuleInfo() {
    return $this->moduleInfo;
  }

  /**
   * Retrieves the components info for the given theme.
   *
   * @param string $name
   *   The name of the theme.
   *
   * @return array
   *   The components info.
   */
  public function getThemeInfo($name) {
    if (isset($this->themeInfo[$name])) {
      return $this->themeInfo[$name];
    }

    // No components info.
    return [];
  }

  /**
   * Retrieves the components info for all themes.
   *
   * @return array
   *   The components info, keyed by theme name.
   */
  public function getAllThemeInfo() {
    return $this->themeInfo;
  }

  /**
   * Returns a list of default namespaces that should not be overridden.
   *
   * The returned list is of default namespaces that are maintained by Drupal
   * core and owned by individual modules or themes. By default, default
   * namespaces cannot be overridden; a module or theme can opt-in to having
   * their namespace altered by using their name in the components.namespaces
   * key of their .info.yml or by setting the
   * components.allow_default_namespace_reuse key in their .info.yml.
   *
   * @return array
   *   List of protected namespaces.
   */
  public function getProtectedNamespaces() {
    return $this->protectedNamespaces;
  }

}
