<?php

namespace Drupal\metatag_mobile\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Handheld Friendly for Mobile metatag.
 */
#[MetatagTag(
  id: 'handheldfriendly',
  label: new TranslatableMarkup('Handheld-Friendly'),
  name: 'HandheldFriendly',
  group: 'mobile',
  type: MetatagType::String,
  description: new TranslatableMarkup("Some older mobile browsers will expect this meta tag to be set to 'true' to indicate that the site has been designed with mobile browsers in mind."),
  weight: 83,
  multiple: TRUE,
)]
class HandheldFriendly extends MetaNameBase {

  /**
   * {@inheritdoc}
   */
  public function getTestOutputExistsXpath(): array {
    // @todo The output from this meta tag is not overriding core.
    // @see metatag_mobile_page_attachments_alter()
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getTestOutputValuesXpath(array $values): array {
    // @todo The output from this meta tag is not overriding core.
    // @see metatag_mobile_page_attachments_alter()
    return [];
  }

}
