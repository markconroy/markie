<?php

namespace Drupal\Core\Installer;

use Drupal\Core\DrupalKernel;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Extend DrupalKernel to handle force some kernel behaviors.
 */
class InstallerKernel extends DrupalKernel {

  /**
   * {@inheritdoc}
   */
  protected function initializeContainer() {
    // Always force a container rebuild.
    $this->containerNeedsRebuild = TRUE;
    // Ensure the InstallerKernel's container is not dumped.
    $this->allowDumping = FALSE;
    $container = parent::initializeContainer();
    return $container;
  }

  /**
   * Reset the bootstrap config storage.
   *
   * Use this from a database driver runTasks() if the method overrides the
   * bootstrap config storage. Normally the bootstrap config storage is not
   * re-instantiated during a single install request. Most drivers will not
   * need this method.
   *
   * @see \Drupal\Core\Database\Install\Tasks::runTasks()
   */
  public function resetConfigStorage() {
    $this->configStorage = NULL;
  }

  /**
   * Returns the active configuration storage used during early install.
   *
   * This override changes the visibility so that the installer can access
   * config storage before the container is properly built.
   *
   * @return \Drupal\Core\Config\StorageInterface
   *   The config storage.
   */
  public function getConfigStorage() {
    return parent::getConfigStorage();
  }

  /**
   * {@inheritdoc}
   */
  public function getInstallProfile() {
    global $install_state;
    if ($install_state && empty($install_state['installation_finished'])) {
      // If the profile has been selected return it.
      if (isset($install_state['parameters']['profile'])) {
        $profile = $install_state['parameters']['profile'];
      }
      else {
        $profile = NULL;
      }
    }
    else {
      $profile = parent::getInstallProfile();
    }
    return $profile;
  }

  /**
   * Returns TRUE if a Drupal installation is currently being attempted.
   *
   * @return bool
   *   TRUE if the installation is currently being attempted.
   */
  public static function installationAttempted() {
    // This cannot rely on the MAINTENANCE_MODE constant, since that would
    // prevent tests from using the non-interactive installer, in which case
    // Drupal only happens to be installed within the same request, but
    // subsequently executed code does not involve the installer at all.
    // @see install_drupal()
    return isset($GLOBALS['install_state']) && empty($GLOBALS['install_state']['installation_finished']);
  }

  /**
   * {@inheritdoc}
   */
  protected function attachSynthetic(ContainerInterface $container): void {
    parent::attachSynthetic($container);

    // Reset any existing container in order to avoid holding on to old object
    // references, otherwise memory usage grows exponentially with each rebuild
    // when multiple modules are being installed.
    // @todo Move this to the parent class after https://www.drupal.org/i/2066993
    $this->container?->reset();
  }

  /**
   * {@inheritdoc}
   */
  protected function getExtensions(): array {
    $extensions = parent::getExtensions() ?: [];
    // Ensure that the System module is always available to the installer.
    $extensions['module']['system'] ??= 0;
    if (empty($extensions['profile']) && !empty($GLOBALS['install_state']) && ($profile = _install_select_profile($GLOBALS['install_state']))) {
      $extensions['profile'] = $profile;
      $extensions['module'][$profile] = 1000;
    }
    return $extensions;
  }

}
