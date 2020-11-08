<?php

namespace Drupal\upgrade_status;

use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ProfileExtensionList;
use Drupal\Core\Extension\ThemeExtensionList;
use http\Exception\InvalidArgumentException;

/**
 * Collects projects collated for the purposes of upgrade status.
 */
class ProjectCollector {

  /**
   * The list of available modules.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected $moduleExtensionList;

  /**
   * The list of available themes.
   *
   * @var \Drupal\Core\Extension\ThemeExtensionList
   */
  protected $themeExtensionList;

  /**
   * The list of available profiles.
   *
   * @var \Drupal\Core\Extension\ProfileExtensionList
   */
  protected $profileExtensionList;

  /**
   * A list of allowed extension types.
   *
   * @var array
   */
  protected $allowedTypes = [
    'module',
    'theme',
    'profile',
  ];

  /**
   * Constructs a \Drupal\upgrade_status\ProjectCollector.
   *
   * @param \Drupal\Core\Extension\ModuleExtensionList $module_extension_list
   *   The module extension list service.
   * @param \Drupal\Core\Extension\ThemeExtensionList $theme_extension_list
   *   The theme extension handler service.
   * @param \Drupal\Core\Extension\ProfileExtensionList $profile_extension_list
   *   The profile extension handler service.
   */
  public function __construct(
    ModuleExtensionList $module_extension_list,
    ThemeExtensionList $theme_extension_list,
    ProfileExtensionList $profile_extension_list
  ) {
    $this->moduleExtensionList = $module_extension_list;
    $this->themeExtensionList = $theme_extension_list;
    $this->profileExtensionList = $profile_extension_list;
  }

  /**
   * Collect projects of installed modules grouped by custom and contrib.
   *
   * @return array
   *   An array keyed by 'custom' and 'contrib' where each array is a list
   *   of projects grouped into that project group. Custom modules get a
   *   project name based on their topmost parent custom module and only
   *   that topmost custom module gets included in the list. Each item is
   *   a \Drupal\Core\Extension\Extension object in both arrays.
   */
  public function collectProjects() {
    $projects = ['custom' => [], 'contrib' => []];
    $modules = $this->moduleExtensionList->getList();
    $themes = $this->themeExtensionList->getList();
    $profiles = $this->profileExtensionList->getList();
    $extensions = array_merge($modules, $themes, $profiles);
    unset($modules, $themes, $profiles);

    /** @var \Drupal\Core\Extension\Extension $extension */
    foreach ($extensions as $key => $extension) {

      if ($extension->origin === 'core') {
        // Ignore core extensions for the sake of upgrade status.
        continue;
      }

      // If the project is already specified in this extension, use that.
      $project = isset($extension->info['project']) ? $extension->info['project'] : '';
      if (array_key_exists($project, $projects['custom'])
        || array_key_exists($project, $projects['contrib'])
      ) {
        // If we already have a representative of this project in the list,
        // don't add this extension.
        // @todo Make sure to use the extension with the shortest file path.
        continue;
      }

      if ((strpos($key, 'upgrade_status') === 0) && !drupal_valid_test_ua()) {
        // Don't add the Upgrade Status modules to the list if not in tests.
        // Upgrade status is a temporary site component and does have
        // intentional deprecated API use for the sake of testing. Avoid
        // distracting site owners with this.
        continue;
      }

      // Attempt to identfy if the project was contrib based on the directory
      // structure it is in. Extension placement is not a mandatory requirement
      // and theoretically this could lead to false positives, but if
      // composer_deploy or git_deploy is not available (and/or did not
      // identify the project for us), this is all we can do. Ignore our test
      // modules for this scenario.
      if (empty($project)) {
        $type = 'custom';
        if (strpos($extension->getPath(), '/contrib/') && (strpos($key, 'upgrade_status_test_') !== 0)) {
          $type = 'contrib';
        }
      }
      // Extensions that have the 'drupal' project but did not have the 'core'
      // origin assigned are custom extensions that are running in a Drupal
      // core git checkout, so also categorize them as custom.
      elseif ($project === 'drupal') {
        $type = 'custom';
      }
      else {
        $type = 'contrib';
      }
      $projects[$type][$key] = $extension;
    }

    // Collate custom extensions to projects, removing sub-extensions.
    $projects['custom'] = $this->collateExtensionsIntoProjects($projects['custom']);

    // Also collate contrib extensions. This is only needed if there were
    // contrib extensions with projects not identified, and they had
    // sub-extensions. After the collation is done, assign project names
    // based on the topmost extension. While this is not always right for
    // drupal.org projects, this is the best guess we have.
    $projects['contrib'] = $this->collateExtensionsIntoProjects($projects['contrib']);
    foreach ($projects['contrib'] as $name => $extension) {
      if (!isset($extension->info['project'])) {
        $projects['contrib'][$name]->info['project'] = $name;
      }
    }

    return $projects;
  }

  /**
   * Finds topmost extension for each extension and keeps only that.
   *
   * @param \Drupal\Core\Extension\Extension[] $extensions
   *   List of all enabled extensions in a category.
   *
   * @return \Drupal\Core\Extension\Extension[]
   *   List of extensions, with only the topmost extension left for each
   *   extension that has a parent extension.
   */
  protected function collateExtensionsIntoProjects(array $extensions) {
    foreach ($extensions as $name_a => $extension_a) {
      $path_a = $extension_a->getPath() . '/';
      $path_a_length = strlen($path_a);

      foreach ($extensions as $name_b => $extension_b) {
        // Skip collation for test modules except where we test that.
        if ((strpos($name_b, 'upgrade_status_test_') === 0) && (strpos($name_b, 'upgrade_status_test_submodules_') !== 0)) {
          continue;
        }

        $path_b = $extension_b->getPath();
        // If the extension is not the same but the beginning of paths match,
        // remove this extension from the list as it is part of another one.
        if ($name_b != $name_a && substr($path_b, 0, $path_a_length) === $path_a) {
          unset($extensions[$name_b]);
        }
      }
    }
    return $extensions;
  }

  /**
   * Returns a single extension based on type and machine name.
   *
   * @param string $type
   *   One of 'module' or 'theme' or 'profile' to signify the type of the
   *   extension.
   * @param string $project_machine_name
   *   Machine name for the extension.
   *
   * @return \Drupal\Core\Extension\Extension
   *   A project if exists.
   *
   * @throws \InvalidArgumentException
   *   If the type was not one of the allowed ones.
   * @throws \Drupal\Core\Extension\Exception\UnknownExtensionException
   *   If there was no extension with the given name.
   */
  public function loadProject(string $type, string $project_machine_name) {
    if (!in_array($type, $this->allowedTypes)) {
      throw new InvalidArgumentException(sprintf('"%s" is not a valid type. Valid types are module, profile and theme.', $type));
    }

    if ($type === 'module') {
      return $this->moduleExtensionList->get($project_machine_name);
    }

    if ($type === 'profile') {
      return $this->profileExtensionList->get($project_machine_name);
    }

    return $this->themeExtensionList->get($project_machine_name);
  }

}
