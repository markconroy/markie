<?php

namespace Drupal\jsonapi\Normalizer;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\jsonapi\Query\OffsetPage;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Drupal\Core\Http\Exception\CacheableBadRequestHttpException;

/**
 * The normalizer used for JSON:API pagination.
 *
 * @internal
 */
class OffsetPageNormalizer implements DenormalizerInterface {

  /**
   * The interface or class that this Normalizer supports.
   *
   * @var string
   */
  protected $supportedInterfaceOrClass = OffsetPage::class;

  /**
   * {@inheritdoc}
   */
  public function supportsDenormalization($data, $type, $format = NULL) {
    return $type == $this->supportedInterfaceOrClass;
  }

  /**
   * {@inheritdoc}
   */
  public function denormalize($data, $class, $format = NULL, array $context = []) {
    $expanded = $this->expand($data);
    return new OffsetPage($expanded[OffsetPage::OFFSET_KEY], $expanded[OffsetPage::SIZE_KEY]);
  }

  /**
   * {@inheritdoc}
   */
  protected function expand($data) {
    if (!is_array($data)) {
      $cacheability = (new CacheableMetadata())->addCacheContexts(['url.query_args:page']);
      throw new CacheableBadRequestHttpException($cacheability, 'The page parameter needs to be an array.');
    }

    $expanded = $data + [
      OffsetPage::OFFSET_KEY => OffsetPage::DEFAULT_OFFSET,
      OffsetPage::SIZE_KEY => OffsetPage::SIZE_MAX,
    ];

    if ($expanded[OffsetPage::SIZE_KEY] > OffsetPage::SIZE_MAX) {
      $expanded[OffsetPage::SIZE_KEY] = OffsetPage::SIZE_MAX;
    }

    return $expanded;
  }

}
