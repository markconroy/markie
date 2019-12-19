<?php

namespace Drupal\xmlsitemap\Tests;

use Drupal\Core\Url;

/**
 * Tests the robots.txt file existence.
 *
 * @group xmlsitemap
 * @dependencies robotstxt
 */
class XmlSitemapRobotsTxtIntegrationTest extends XmlSitemapTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['robotstxt'];

  /**
   * Test if sitemap link is included in robots.txt file.
   */
  public function testRobotsTxt() {
    // Request the un-clean robots.txt path so this will work in case there is
    // still the robots.txt file in the root directory. In order to bypass the
    // local robots.txt file we need to rebuild the container and use a Request
    // with clean URLs disabled.
    $this->container = $this->kernel->rebuildContainer();
    $this->prepareRequestForGenerator(FALSE);

    $this->drupalGet('robots.txt');
    $this->assertRaw('Sitemap: ' . Url::fromRoute('xmlsitemap.sitemap_xml', [], ['absolute' => TRUE])->toString());
  }

}
