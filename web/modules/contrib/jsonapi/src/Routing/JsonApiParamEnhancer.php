<?php

namespace Drupal\jsonapi\Routing;

use Drupal\Core\Routing\EnhancerInterface;
use Drupal\jsonapi\Exception\UnprocessableHttpEntityException;
use Drupal\jsonapi\Query\OffsetPage;
use Drupal\jsonapi\Query\Sort;
use Drupal\jsonapi\ResourceType\ResourceType;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;

/**
 * Processes the request query parameters.
 *
 * @internal
 */
class JsonApiParamEnhancer implements EnhancerInterface, ContainerAwareInterface {

  use ContainerAwareTrait;

  /**
   * The JSON:API serializer.
   *
   * @var \Symfony\Component\Serializer\SerializerInterface|\Symfony\Component\Serializer\Normalizer\DenormalizerInterface
   */
  protected $serializer;

  /**
   * Lazily loads the JSON:API serializer.
   *
   * @return \Symfony\Component\Serializer\SerializerInterface|\Symfony\Component\Serializer\Normalizer\DenormalizerInterface
   *   The JSON:API serializer.
   */
  protected function serializer() {
    if (!$this->serializer) {
      $this->serializer = $this->container->get('jsonapi.serializer_do_not_use_removal_imminent');
    }
    return $this->serializer;
  }

  /**
   * {@inheritdoc}
   */
  public function enhance(array $defaults, Request $request) {
    if (!Routes::isJsonApiRequest($defaults)) {
      return $defaults;
    }

    $options = [];

    $resource_type = Routes::getResourceTypeNameFromParameters($defaults);
    $context = [
      'entity_type_id' => $resource_type->getEntityTypeId(),
      'bundle' => $resource_type->getBundle(),
    ];

    if ($request->query->has('sort')) {
      $sort = $request->query->get('sort');
      $options['sort'] = $this->serializer()->denormalize($sort, Sort::class, NULL, $context);
    }

    $options['page'] = $request->query->has('page')
      ? $this->serializer()->denormalize($request->query->get('page'), OffsetPage::class)
      : new OffsetPage(OffsetPage::DEFAULT_OFFSET, OffsetPage::SIZE_MAX);

    if (isset($defaults['serialization_class']) && !$request->isMethodSafe(FALSE)) {
      // Deserialize incoming data if available.
      if ($received = (string) $request->getContent()) {
        $deserialized_param_name = empty($defaults['related']) ? 'parsed_entity' : 'resource_identifiers';
        $defaults[$deserialized_param_name] = $this->deserialize($resource_type, $received, $defaults);
      }
      elseif ($request->isMethod('POST') || $request->isMethod('PATCH')) {
        throw new BadRequestHttpException('Empty request body.');
      }
      elseif ($request->isMethod('DELETE') && isset($defaults['related']) && $defaults['related']) {
        throw new BadRequestHttpException(sprintf('You need to provide a body for DELETE operations on a relationship (%s).', $defaults['related']));
      }
    }

    $defaults['_json_api_params'] = $options;

    return $defaults;
  }

  /**
   * Deserializes request body, if any.
   *
   * @param \Drupal\jsonapi\ResourceType\ResourceType $resource_type
   *   The JSON:API resource type for the current request.
   * @param string $received
   *   The request body.
   * @param array $defaults
   *   The route defaults.
   *
   * @return array
   *   An object normalization.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   *   Thrown if the request body cannot be decoded, or when no request body was
   *   provided with a POST or PATCH request.
   * @throws \Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException
   *   Thrown if the request body cannot be denormalized.
   */
  protected function deserialize(ResourceType $resource_type, $received, array $defaults) {
    // First decode the request data. We can then determine if the
    // serialized data was malformed.
    try {
      $decoded = $this->serializer()->decode($received, 'api_json');
    }
    catch (UnexpectedValueException $e) {
      // If an exception was thrown at this stage, there was a problem
      // decoding the data. Throw a 400 http exception.
      throw new BadRequestHttpException($e->getMessage());
    }

    try {
      return $this->serializer()->denormalize($decoded, $defaults['serialization_class'], 'api_json', [
        'related' => $resource_type->getInternalName(isset($defaults['related']) ? $defaults['related'] : NULL),
        'target_entity' => isset($defaults[$resource_type->getEntityTypeId()]) ? $defaults[$resource_type->getEntityTypeId()] : NULL,
        'resource_type' => $resource_type,
      ]);
    }
    // These two serialization exception types mean there was a problem with
    // the structure of the decoded data and it's not valid.
    catch (UnexpectedValueException $e) {
      throw new UnprocessableHttpEntityException($e->getMessage());
    }
    catch (InvalidArgumentException $e) {
      throw new UnprocessableHttpEntityException($e->getMessage());
    }
  }

}
