<?php

namespace Drupal\entity_usage\Plugin\EntityUsage\Track;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\entity_usage\EntityUsageTrackBase;

/**
 * Tracks usage of entities related in Link fields.
 *
 * @EntityUsageTrack(
 *   id = "link",
 *   label = @Translation("Link Fields"),
 *   description = @Translation("Tracks relationships created with 'Link' fields."),
 *   field_types = {"link", "link_tree"},
 *   source_entity_class = "Drupal\Core\Entity\FieldableEntityInterface",
 * )
 */
class Link extends EntityUsageTrackBase {

  /**
   * {@inheritdoc}
   */
  public function getTargetEntities(FieldItemInterface $link): array {
    /** @var \Drupal\link\LinkItemInterface $link */
    if ($link->isExternal()) {
      $url = $link->getUrl()->toString();
      $entity_info = $this->urlToEntity->findEntityIdByUrl($url);
    }
    else {
      $url = $link->getUrl();
      $entity_info = $this->urlToEntity->findEntityIdByRoutedUrl($url);
    }

    if (empty($entity_info)) {
      return [];
    }

    ['type' => $entity_type_id, 'id' => $entity_id] = $entity_info;
    $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
    $query = $this->entityTypeManager->getStorage($entity_type_id)
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition($entity_type->getKey('id'), $entity_id);
    return array_values(array_map(fn ($id) => $entity_type_id . '|' . $id, $query->execute()));
  }

}
