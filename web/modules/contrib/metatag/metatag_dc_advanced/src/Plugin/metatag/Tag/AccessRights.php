<?php

namespace Drupal\metatag_dc_advanced\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Dublin Core "accessRights" meta tag.
 */
#[MetatagTag(
  id: 'dcterms_access_rights',
  label: new TranslatableMarkup('Access Rights'),
  name: 'dcterms.accessRights',
  group: 'dublin_core_advanced',
  type: MetatagType::Label,
  description: new TranslatableMarkup('Information about who can access the resource or an indication of its security status. Access Rights may include information regarding access or restrictions based on privacy, security, or other policies.'),
  weight: 6,
)]
class AccessRights extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
