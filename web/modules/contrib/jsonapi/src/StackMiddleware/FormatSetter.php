<?php

namespace Drupal\jsonapi\StackMiddleware;

use Symfony\Component\HttpFoundation\AcceptHeader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Sets the 'api_json' for requests with a JSON:API Content-Type header.
 *
 * @internal
 */
final class FormatSetter implements HttpKernelInterface {

  /**
   * The wrapped HTTP kernel.
   *
   * @var \Symfony\Component\HttpKernel\HttpKernelInterface
   */
  protected $httpKernel;

  /**
   * Constructs a FormatSetter object.
   *
   * @param \Symfony\Component\HttpKernel\HttpKernelInterface $http_kernel
   *   The decorated kernel.
   */
  public function __construct(HttpKernelInterface $http_kernel) {
    $this->httpKernel = $http_kernel;
  }

  /**
   * {@inheritdoc}
   */
  public function handle(Request $request, $type = self::MASTER_REQUEST, $catch = TRUE) {
    $accepted = AcceptHeader::fromString($request->headers->get('Accept'));
    if ($accepted->get('application/vnd.api+json')) {
      $request->setRequestFormat('api_json');
    }
    return $this->httpKernel->handle($request, $type, $catch);
  }

}
