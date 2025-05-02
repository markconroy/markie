<?php

namespace Drupal\entity_usage\Controller;

use Drupal\block_content\BlockContentInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\entity_usage\EntityUsageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for our pages.
 */
class ListUsageController extends ControllerBase {

  /**
   * Number of items per page to use when nothing was configured.
   */
  const ITEMS_PER_PAGE_DEFAULT = 25;

  /**
   * The index for the default revision "group".
   *
   * @var int
   */
  protected const REVISION_DEFAULT = 0;

  /**
   * The index for the pending revision "group".
   *
   * @var int
   */
  protected const REVISION_PENDING = 1;

  /**
   * The index for the old revision "group".
   *
   * @var int
   */
  protected const REVISION_OLD = -1;


  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The EntityUsage service.
   *
   * @var \Drupal\entity_usage\EntityUsageInterface
   */
  protected $entityUsage;

  /**
   * All source rows for this target entity.
   *
   * @var mixed[]
   */
  protected $allRows;

  /**
   * The Entity Usage settings config object.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $entityUsageConfig;

  /**
   * The number of records per page this controller should output.
   *
   * @var int
   */
  protected $itemsPerPage;

  /**
   * The pager manager.
   *
   * @var \Drupal\Core\Pager\PagerManagerInterface
   */
  protected $pagerManager;

  /**
   * ListUsageController constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\entity_usage\EntityUsageInterface $entity_usage
   *   The EntityUsage service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Pager\PagerManagerInterface $pager_manager
   *   The pager manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager, EntityUsageInterface $entity_usage, ConfigFactoryInterface $config_factory, PagerManagerInterface $pager_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->entityUsage = $entity_usage;
    $this->entityUsageConfig = $config_factory->get('entity_usage.settings');
    $this->itemsPerPage = $this->entityUsageConfig->get('usage_controller_items_per_page') ?: self::ITEMS_PER_PAGE_DEFAULT;
    $this->pagerManager = $pager_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('entity_usage.usage'),
      $container->get('config.factory'),
      $container->get('pager.manager')
    );
  }

  /**
   * Lists the usage of a given entity.
   *
   * @param string $entity_type
   *   The entity type.
   * @param int $entity_id
   *   The entity ID.
   *
   * @return mixed[]
   *   The page build to be rendered.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   */
  public function listUsagePage($entity_type, $entity_id): array {
    $all_rows = $this->getRows($entity_type, $entity_id);
    if (empty($all_rows)) {
      return [
        '#markup' => $this->t(
          'There are no recorded usages for entity of type: @type with id: @id',
          ['@type' => $entity_type, '@id' => $entity_id]
        ),
      ];
    }

    $header = [
      $this->t('Entity'),
      $this->t('Type'),
      $this->t('Language'),
      $this->t('Field name'),
      $this->t('Status'),
      $this->t('Used in'),
    ];

    $total = count($all_rows);
    $pager = $this->pagerManager->createPager($total, $this->itemsPerPage);
    $page = $pager->getCurrentPage();
    $page_rows = $this->getPageRows($page, $this->itemsPerPage, $entity_type, $entity_id);
    // If all rows on this page are of entities that have usage on their default
    // revision, we don't need the "Used in" column.
    $used_in_previous_revisions = FALSE;
    foreach ($page_rows as $row) {
      $used_in = $row[5]['data'];
      $only_default = fn(array $row) => count($row) === 1 &&
        !empty($row[0]['#plain_text']) &&
        $row[0]['#plain_text'] == $this->t('Default');
      if (!$only_default($used_in)) {
        $used_in_previous_revisions = TRUE;
        break;
      }
    }
    if (!$used_in_previous_revisions) {
      unset($header[5]);
      array_walk($page_rows, function (&$row, $key) {
        unset($row[5]);
      });
    }
    $build[] = [
      '#theme' => 'table',
      '#rows' => $page_rows,
      '#header' => $header,
    ];

    $build[] = [
      '#type' => 'pager',
      '#route_name' => '<current>',
    ];

    return $build;
  }

  /**
   * Retrieve all usage rows for this target entity.
   *
   * @param string $entity_type
   *   The type of the target entity.
   * @param int|string $entity_id
   *   The ID of the target entity.
   *
   * @return mixed[]
   *   An indexed array of rows that should be displayed as sources for this
   *   target entity.
   */
  protected function getRows($entity_type, $entity_id): array {
    if (!empty($this->allRows)) {
      return $this->allRows;
      // @todo Cache this based on the target entity, invalidating the cached
      // results every time records are added/removed to the same target entity.
    }
    $rows = [];
    $entity = $this->entityTypeManager->getStorage($entity_type)->load($entity_id);
    if (!$entity) {
      return $rows;
    }
    $entity_types = $this->entityTypeManager->getDefinitions();
    $languages = $this->languageManager()->getLanguages(LanguageInterface::STATE_ALL);
    $all_usages = $this->entityUsage->listSources($entity);

    $revision_groups = [
      static::REVISION_DEFAULT => $this->t("Default"),
      static::REVISION_PENDING => $this->t("Pending revision(s) / Draft(s)"),
      static::REVISION_OLD => $this->t("Old revision(s)"),
    ];

    foreach ($all_usages as $source_type => $ids) {
      $type_storage = $this->entityTypeManager->getStorage($source_type);
      foreach ($ids as $source_id => $records) {
        // We will show a single row per source entity. If the target is not
        // referenced on its default revision on the default language, we will
        // just show indicate that in a specific column.
        $source_entity = $type_storage->load($source_id);
        if (!$source_entity) {
          // If for some reason this record is broken, just skip it.
          continue;
        }
        $field_definitions = $this->entityFieldManager->getFieldDefinitions($source_type, $source_entity->bundle());
        $default_langcode = $source_entity->language()->getId();
        $used_in = [];
        $revisions = [];
        if ($source_entity instanceof RevisionableInterface) {
          $default_revision_id = $source_entity->getRevisionId();
          foreach (array_reverse($records) as $record) {
            [
              'source_vid' => $source_vid,
              'source_langcode' => $source_langcode,
              'field_name' => $field_name,
            ] = $record;
            // Track which languages are used in pending, default and old
            // revisions.
            $revision_group = (int) $source_vid <=> (int) $default_revision_id;
            $revisions[$revision_group][$source_langcode] = $field_name;
          }

          foreach ($revision_groups as $index => $label) {
            if (!empty($revisions[$index])) {
              $used_in[] = $this->summarizeRevisionGroup($default_langcode, $label, $revisions[$index]);
            }
          }

          if (count($used_in) > 1) {
            $used_in = [
              '#theme' => 'item_list',
              '#items' => $used_in,
              '#list_type' => 'ul',
            ];
          }
        }
        $link = $this->getSourceEntityLink($source_entity);
        // If the label is empty it means this usage shouldn't be shown
        // on the UI, just skip this row.
        if (empty($link)) {
          continue;
        }
        $published = $this->getSourceEntityStatus($source_entity);
        $get_field_name = function (array $field_names) use ($default_langcode, $revision_groups) {
          foreach (array_keys($revision_groups) as $group) {
            if (isset($field_names[$group])) {
              return $field_names[$group][$default_langcode] ?? reset($field_names[$group]);
            }
          }
        };
        $field_name = $get_field_name($revisions);
        $field_label = isset($field_definitions[$field_name]) ? $field_definitions[$field_name]->getLabel() : $this->t('Unknown');
        $type = $entity_types[$source_type]->getLabel();
        if ($source_bundle_key = $source_entity->getEntityType()->getKey('bundle')) {
          $bundle_field = $source_entity->{$source_bundle_key};
          if ($bundle_field->getFieldDefinition()->getType() === 'entity_reference') {
            $bundle_label = $bundle_field->entity->label();
          }
          else {
            $bundle_label = $bundle_field->getString();
          }
          $type .= ': ' . $bundle_label;
        }
        $rows[] = [
          $link,
          $type,
          $languages[$default_langcode]->getName(),
          $field_label,
          $published,
          ['data' => $used_in],
        ];
      }
    }

    $this->allRows = $rows;
    return $this->allRows;
  }

  /**
   * Returns a render array indicating a revision "type" and languages.
   *
   * For example it might return "Pending revision(s) / Draft(s): ES, NO.".
   *
   * @param string $default_langcode
   *   The default language code for the referencing entity.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $revision_label
   *   The translated revision type label eg 'Old revision(s)' or 'Default'.
   * @param string[] $languages
   *   An indexed array of language codes that reference the entity in the given
   *   type.
   *
   * @return mixed[]
   *   A render array summarizing the information passed in.
   */
  protected function summarizeRevisionGroup($default_langcode, $revision_label, array $languages): array {
    $language_objects = $this->languageManager()->getLanguages(LanguageInterface::STATE_ALL);
    if (count($languages) === 1 && !empty($languages[$default_langcode])) {
      // If there's only one relevant revision and it's the entity's default
      // language then just show the label.
      return ['#plain_text' => $revision_label];
    }
    else {
      // Otherwise show the languages enumerated, ensuring the default language
      // comes first if present.
      if (!empty($languages[$default_langcode])) {
        $languages = [$default_langcode => TRUE] + $languages;
      }
      // Ignore not installed languages.
      $languages = array_intersect_key($languages, $language_objects);
      return [
        '#type' => 'inline_template',
        '#template' => '{{ label }}: {% for language in languages %}{{ language }}{{ loop.last ? "." : ", " }}{% endfor %}',
        '#context' => [
          'label' => $revision_label,
          'languages' => array_map(fn ($code) => [
            '#type' => 'inline_template',
            '#template' => '<abbr title="{{ name|e("html_attr") }}">{{ code }}</abbr>',
            '#context' => [
              'code' => mb_strtoupper($code),
              'name' => $language_objects[$code]->getName(),
            ],
          ], array_keys($languages)),
        ],
      ];
    }
  }

  /**
   * Get rows for a given page.
   *
   * @param int $page
   *   The page number to retrieve.
   * @param int $num_per_page
   *   The number of rows we want to have on this page.
   * @param string $entity_type
   *   The type of the target entity.
   * @param int|string $entity_id
   *   The ID of the target entity.
   *
   * @return mixed[]
   *   An indexed array of rows representing the records for a given page.
   */
  protected function getPageRows($page, $num_per_page, $entity_type, $entity_id): array {
    $offset = $page * $num_per_page;
    return array_slice($this->getRows($entity_type, $entity_id), $offset, $num_per_page);
  }

  /**
   * Title page callback.
   *
   * @param string $entity_type
   *   The entity type.
   * @param int $entity_id
   *   The entity id.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The title to be used on this page.
   */
  public function getTitle($entity_type, $entity_id): TranslatableMarkup {
    $entity = $this->entityTypeManager->getStorage($entity_type)->load($entity_id);
    if ($entity) {
      return $this->t('Entity usage information for %entity_label', ['%entity_label' => $entity->label()]);
    }
    return $this->t('Entity Usage List');
  }

  /**
   * Retrieve the source entity's status.
   *
   * @param \Drupal\Core\Entity\EntityInterface $source_entity
   *   The source entity.
   *
   * @return string|\Drupal\Core\StringTranslation\TranslatableMarkup
   *   The entity's status.
   */
  protected function getSourceEntityStatus(EntityInterface $source_entity): string|TranslatableMarkup {
    // Treat paragraph entities in a special manner. Paragraph entities
    // should get their host (parent) entity's status.
    if ($source_entity->getEntityTypeId() == 'paragraph') {
      /** @var \Drupal\paragraphs\ParagraphInterface $source_entity */
      $parent = $source_entity->getParentEntity();
      if (!empty($parent)) {
        return $this->getSourceEntityStatus($parent);
      }
    }

    if ($source_entity instanceof EntityPublishedInterface) {
      $published = $source_entity->isPublished() ? $this->t('Published') : $this->t('Unpublished');
    }
    else {
      $published = '';
    }

    return $published;
  }

  /**
   * Retrieve a link to the source entity.
   *
   * Note that some entities are special-cased, since they don't have canonical
   * template and aren't expected to be re-usable. For example, if the entity
   * passed in is a paragraph or a block content, the link we produce will point
   * to this entity's parent (host) entity instead.
   *
   * @param \Drupal\Core\Entity\EntityInterface $source_entity
   *   The source entity.
   * @param string|null $text
   *   (optional) The link text for the anchor tag as a translated string.
   *   If NULL, it will use the entity's label. Defaults to NULL.
   *
   * @return \Drupal\Core\Link|string|false
   *   A link to the entity, or its non-linked label, in case it was impossible
   *   to correctly build a link. Will return FALSE if this item should not be
   *   shown on the UI (for example when dealing with an orphan paragraph).
   */
  protected function getSourceEntityLink(EntityInterface $source_entity, $text = NULL): mixed {
    // Note that $paragraph_entity->label() will return a string of type:
    // "{parent label} > {parent field}", which is actually OK for us.
    $entity_label = $source_entity->access('view label') ? $source_entity->label() : $this->t('- Restricted access -');

    $rel = NULL;
    if ($source_entity->hasLinkTemplate('revision')) {
      $rel = 'revision';
    }
    elseif ($source_entity->hasLinkTemplate('canonical')) {
      $rel = 'canonical';
    }

    // Block content likely used in Layout Builder inline or reusable blocks.
    if ($source_entity instanceof BlockContentInterface) {
      $rel = NULL;
    }

    $link_text = $text ?: $entity_label;
    if ($rel) {
      // Prevent 404s by exposing the text unlinked if the user has no access
      // to view the entity.
      return $source_entity->access('view') ? $source_entity->toLink($link_text, $rel) : $link_text;
    }

    // Treat paragraph entities in a special manner. Normal paragraph entities
    // only exist in the context of their host (parent) entity. For this reason
    // we will use the link to the parent's entity label instead.
    /** @var \Drupal\paragraphs\ParagraphInterface $source_entity */
    if ($source_entity->getEntityTypeId() == 'paragraph') {
      $parent = $source_entity->getParentEntity();
      if ($parent) {
        return $this->getSourceEntityLink($parent, $link_text);
      }
    }
    // Treat block_content entities in a special manner. Block content
    // relationships are stored as serialized data on the host entity. This
    // makes it difficult to query parent data. Instead we look up relationship
    // data which may exist in entity_usage tables. This requires site builders
    // to set up entity usage on host-entity-type -> block_content manually.
    // @todo this could be made more generic to support other entity types with
    // difficult to handle parent -> child relationships.
    elseif ($source_entity->getEntityTypeId() === 'block_content') {
      $sources = $this->entityUsage->listSources($source_entity, FALSE);
      $source = reset($sources);
      if ($source !== FALSE) {
        $parent = $this->entityTypeManager()->getStorage($source['source_type'])->load($source['source_id']);
        if ($parent) {
          return $this->getSourceEntityLink($parent);
        }
      }
    }

    // As a fallback just return a non-linked label.
    return $link_text;
  }

  /**
   * Checks access based on whether the user can view the current entity.
   *
   * @param string $entity_type
   *   The entity type.
   * @param int $entity_id
   *   The entity ID.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function checkAccess($entity_type, $entity_id): AccessResultInterface {
    $entity = $this->entityTypeManager->getStorage($entity_type)->load($entity_id);
    if (!$entity || !$entity->access('view')) {
      return AccessResult::forbidden();
    }
    return AccessResult::allowed();
  }

}
