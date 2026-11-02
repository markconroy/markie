<?php

namespace Drupal\metatag\Plugin\metatag\Group;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Annotation\Translation;

/**
 * Each group will extend this base.
 */
abstract class GroupBase extends PluginBase {

  /**
   * Machine name of the meta tag group plugin.
   *
   * @var string
   */
  protected $id;

  /**
   * The name of the group.
   *
   * @var \Drupal\Core\StringTranslation\TranslatableMarkup|string
   *
   * @ingroup plugin_translatable
   */
  protected $label;

  /**
   * Description of the group.
   *
   * @var \Drupal\Core\StringTranslation\TranslatableMarkup|string
   */
  protected $description;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    // Set the properties from the annotation.
    // @todo Should we have setProperty() methods for each of these?
    $this->id = $plugin_definition['id'];
    // If we got here via an Annotation, unwrap the Translation object.
    // Otherwise we got here via an Attribute, so label is already a
    // TranslatableMarkup.
    $this->label = $plugin_definition['label'] instanceof Translation
      ? $plugin_definition['label']->get()
      : $plugin_definition['label'];
    // Now do the same for description, if it was set.
    if (!empty($plugin_definition['description'])) {
      $this->description = $plugin_definition['description'] instanceof Translation
        ? $plugin_definition['description']->get()
        : $plugin_definition['description'];
    }
    else {
      $this->description = '';
    }
  }

  /**
   * Get this group's internal ID.
   *
   * @return string
   *   This group's ID.
   */
  public function id(): string {
    return $this->id;
  }

  /**
   * Get this group's human-friendly name.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|string
   *   This group's human-friendly name.
   */
  public function label(): string {
    return $this->label;
  }

  /**
   * This group object's description.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|string
   *   This group's description.
   */
  public function description(): string {
    return $this->description;
  }

  /**
   * Whether or not this group is being used.
   *
   * @return bool
   *   Whether this group has been enabled.
   */
  public function isActive(): bool {
    return TRUE;
  }

}
