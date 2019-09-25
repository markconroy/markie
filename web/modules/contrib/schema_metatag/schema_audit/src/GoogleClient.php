<?php

namespace Drupal\schema_audit;


class GoogleClient {

  /**
   * Retrieve and decode a response from a remote client.
   *
   * @type url
   *   The url of the document to retrieve.
   *
   * @return object
   *   A DomDocument.
   *
   */
  public function getDomDocument($url = '') {

    // If no url is defined, use the primary guide page.
    if (empty($url)) {
      $url = 'https://developers.google.com/search/docs/guides/search-gallery';
    }

    try {
      $doc = new \DOMDocument();
      libxml_use_internal_errors(true);
      $doc->loadHTMLFile($url);
      $doc->preserveWhiteSpace = false;
      return $doc;
    }
    catch (\Exception $e) {
      watchdog_exception('schema_audit', $e);
    }
    return FALSE;
  }

  /**
   * Parse object and property data from Google.
   *
   * @return array
   *   Return an associative array of objects and properties.
   */
  public function parseGoogle() {
    $objects = $this->getObjects();
    return $objects;
  }

  /**
   * Retrieve objects.
   *
   * @return array
   *   An array of objects and the info about each.
   */
  public function getObjects() {
    $items = [];
    $doc = $this->getDomDocument();
    foreach ($doc->getElementsByTagName('table') as $table) {
      foreach ($table->getElementsByTagName('tr') as $row) {
        if ($row->getElementsByTagName('h3')->item(0)) {
          $result = [];
          $result['title'] = $row->getElementsByTagName('h3')->item(0)->nodeValue;
          if ($objects = $this->getTitleObject($result['title'])) {
            foreach ($objects as $object) {
              $result['object'] = $object['object'];
              $result['description'] = strip_tags($row->getElementsByTagName('p')->item(0)->nodeValue);
              if ($row->getElementsByTagName('a')->item(0)) {
                $url = $row->getElementsByTagName('a')->item(0)->getAttribute('href');
                $result['url'] = $url;
                $result['properties'] = $this->getProperties($url, $object['tables']);
              }
              $items[$result['object']] = $result;
            }
          }
        }
      }
    }
    return $items;
  }

  /**
   * Retrieve object properties.
   *
   * @param string $url
   *   The url of the Google guide for an object.
   *
   * @return array
   *   An array of properties and the info about each.
   */
  public function getProperties($url, $deltas) {
    $items = [];
    $delta = 0;
    if ($url && $guide = $this->getDomDocument($url)) {
      foreach ($guide->getElementsByTagName('table') as $table) {
        $classes = explode(' ', $table->getAttribute('class'));
        if (in_array('properties', $classes)) {
          if (in_array($delta, $deltas)) {
           foreach ($table->getElementsByTagName('tr') as $row) {
              $td = $row->getElementsByTagName('td');
              if ($td->item(0) && $td->item(1)) {
                $label = trim($td->item(0)->nodeValue);
                $text = trim(strip_tags($td->item(1)->nodeValue));
                $suffix = '';
                if (strpos(strtolower($text), ', recommended')) {
                  $suffix = 'Recommended';
                }
                elseif (strpos(strtolower($text), ', required')) {
                  $suffix = 'Required';
                }
                elseif (strpos(strtolower($text), ', optional')) {
                  $suffix = 'Optional';
                }
                else {
                  $suffix = 'Optional';
                }
                $items[$label] = $suffix;
              }
            }
          }
          $delta++;
        }
      }
    }
    return $items;
  }

  /**
   * Convert page title into Schema.org object info.
   *
   * @param string $title
   *   The title of the Google page that describes the properties.
   *
   * @return array
   *   Return an array of the Schema.org objects described and the delta
   *   of the property tables on the page that correspond to each object.
   */
  public function getTitleObject($title) {
    switch ($title) {
      case 'Podcast':
      case 'Logo':
        return FALSE;
        break;

      case 'Breadcrumb':
        return [
          [
            'object' => 'BreadcrumbList',
            'tables' => [0],
          ],
        ];
        break;

      case 'Corporate Contact':
        return [
          [
            'object' => 'ContactPoint',
            'tables' => [0],
          ],
        ];
        break;

      case 'Carousel':
        return [
          [
            'object' => 'ItemList',
            'tables' => [0],
          ],
        ];
        break;

      case 'Sitelinks Searchbox':
        return [
          [
            'object' => 'WebSite',
            'tables' => [0],
          ],
        ];
        break;

      case 'Social Profile':
        return [
          [
            'object' => 'Person',
            'tables' => [0],
          ],
        ];
        break;

      case 'Video':
        return [
          [
            'object' => 'VideoObject',
            'tables' => [0],
          ],
        ];
        break;

      case 'Fact Check':
        return [
          [
            'object' => 'Review',
            'tables' => [0],
          ],
          [
            'object' => 'ClaimReview',
            'tables' => [0],
          ],
        ];
        break;

      case 'Article':
        return [
          [
            'object' => 'Article',
            'tables' => [0],
          ],
        ];
        break;

      case 'Book':
        return [
          [
            'object' => 'Book',
            'tables' => [0,1],
          ],
          [
            'object' => 'Tome',
            'tables' => [1],
          ],
          [
            'object' => 'EntryPoint',
            'tables' => [3],
          ],
        ];
        break;

      case 'Course':
        return [
          [
            'object' => 'Course',
            'tables' => [1],
          ],
        ];
        break;

      case 'Dataset':
        return [
          [
            'object' => 'Dataset',
            'tables' => [0,1,2,3,4,5],
          ],
        ];
        break;

      case 'Event':
        return [
          [
            'object' => 'Event',
            'tables' => [0,1],
          ],
        ];
        break;

      case 'Job Posting':
        return [
          [
            'object' => 'JobPosting',
            'tables' => [0],
          ],
        ];
        break;

      case 'Local Business':
        return [
          [
            'object' => 'Organization',
            'tables' => [0],
          ],
          [
            'object' => 'LocalBusiness',
            'tables' => [0,1],
          ],
        ];
        break;

      case 'Music':
        return [
          [
            'object' => 'PerformingGroup',
            'tables' => [0],
          ],
          [
            'object' => 'MusicGroup',
            'tables' => [0],
          ],
          [
            'object' => 'MusicPlaylist',
            'tables' => [0],
          ],
          [
            'object' => 'MusicAlbum',
            'tables' => [0],
          ],
        ];
        break;

      case 'Product':
        return [
          [
            'object' => 'Product',
            'tables' => [0],
          ],
          [
            'object' => 'Offer',
            'tables' => [1],
          ],
          [
            'object' => 'AggregateOffer',
            'tables' => [2],
          ],
        ];
        break;

      case 'TV and Movie':
        return [
          [
            'object' => 'Movie',
            'tables' => [0,1],
          ],
          [
            'object' => 'Episode',
            'tables' => [0,2],
          ],
          [
            'object' => 'TVEpisode',
            'tables' => [0,2],
          ],
          [
            'object' => 'CreativeWorkSeason',
            'tables' => [3],
          ],
          [
            'object' => 'TVSeason',
            'tables' => [3],
          ],
          [
            'object' => 'CreativeWorkSeries',
            'tables' => [3],
          ],
          [
            'object' => 'TVSeries',
            'tables' => [3],
          ],
        ];
        break;

      default:
        return [
          [
            'object' => $title,
            'tables' => [0],
          ],
        ];
        break;

    }
  }

}
