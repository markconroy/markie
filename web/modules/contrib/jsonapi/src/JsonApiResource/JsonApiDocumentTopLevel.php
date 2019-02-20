<?php

namespace Drupal\jsonapi\JsonApiResource;

use Drupal\Core\Field\EntityReferenceFieldItemListInterface;

/**
 * Represents a JSON:API document's "top level".
 *
 * @see http://jsonapi.org/format/#document-top-level
 *
 * @internal
 *
 * @todo Add support for the missing optional 'jsonapi' member or document why not.
 */
class JsonApiDocumentTopLevel {

  /**
   * The data to normalize.
   *
   * @var \Drupal\jsonapi\JsonApiResource\ResourceIdentifierInterface|\Drupal\jsonapi\JsonApiResource\EntityCollection|\Drupal\jsonapi\JsonApiResource\ErrorCollection|\Drupal\Core\Field\EntityReferenceFieldItemListInterface
   */
  protected $data;

  /**
   * The metadata to normalize.
   *
   * @var array
   */
  protected $meta;

  /**
   * The links.
   *
   * @var \Drupal\jsonapi\JsonApiResource\LinkCollection
   */
  protected $links;

  /**
   * The includes to normalize.
   *
   * @var \Drupal\jsonapi\JsonApiResource\EntityCollection
   */
  protected $includes;

  /**
   * Instantiates a JsonApiDocumentTopLevel object.
   *
   * @param \Drupal\jsonapi\JsonApiResource\ResourceIdentifierInterface|\Drupal\jsonapi\JsonApiResource\EntityCollection|\Drupal\jsonapi\JsonApiResource\ErrorCollection|\Drupal\Core\Field\EntityReferenceFieldItemListInterface $data
   *   The data to normalize. It can be either a ResourceObject, or a stand-in
   *   for one, or a collection of the same.
   * @param \Drupal\jsonapi\JsonApiResource\EntityCollection $includes
   *   An EntityCollection object containing resources to be included in the
   *   response document or NULL if there should not be includes.
   * @param \Drupal\jsonapi\JsonApiResource\LinkCollection $links
   *   A collection of links to resources related to the top-level document.
   * @param array $meta
   *   (optional) The metadata to normalize.
   */
  public function __construct($data, EntityCollection $includes, LinkCollection $links, array $meta = []) {
    assert($data instanceof ResourceIdentifierInterface || $data instanceof EntityCollection || $data instanceof ErrorCollection || $data instanceof EntityReferenceFieldItemListInterface);
    assert(!$data instanceof ErrorCollection || $includes instanceof NullEntityCollection);
    $this->data = $data;
    $this->includes = $includes;
    $this->links = $links->withContext($this);
    $this->meta = $meta;
  }

  /**
   * Gets the data.
   *
   * @return \Drupal\jsonapi\JsonApiResource\ResourceObject|\Drupal\jsonapi\JsonApiResource\EntityCollection|\Drupal\jsonapi\JsonApiResource\LabelOnlyResourceObject|\Drupal\jsonapi\JsonApiResource\ErrorCollection
   *   The data.
   */
  public function getData() {
    return $this->data;
  }

  /**
   * Gets the links.
   *
   * @return \Drupal\jsonapi\JsonApiResource\LinkCollection
   *   The top-level links.
   */
  public function getLinks() {
    return $this->links;
  }

  /**
   * Gets the metadata.
   *
   * @return array
   *   The metadata.
   */
  public function getMeta() {
    return $this->meta;
  }

  /**
   * Gets an EntityCollection of resources to be included in the response.
   *
   * @return \Drupal\jsonapi\JsonApiResource\EntityCollection
   *   The includes.
   */
  public function getIncludes() {
    return $this->includes;
  }

}
