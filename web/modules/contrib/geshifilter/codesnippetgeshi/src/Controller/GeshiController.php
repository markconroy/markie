<?php

namespace Drupal\codesnippetgeshi\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class GeshiController.
 */
class GeshiController extends ControllerBase {

  /**
   * Process the ajax request to hightlight code.
   *
   * @param Symfony\Component\HttpFoundation\Request $request
   *   The request from ckeditor plugin.
   *
   * @return Symfony\Component\HttpFoundation\Response
   *   The code with hightlight.
   */
  public function ajax(Request $request) {
    $params = [];
    $content = $request->getContent();
    if (!empty($content)) {
      // 2nd param to get as array.
      $params = json_decode($content, TRUE);
    }

    $geshi = new \GeSHi($params['html'], $params['lang']);

    $response = new Response();
    $response->setContent($geshi->parse_code());
    return $response;
  }

}
