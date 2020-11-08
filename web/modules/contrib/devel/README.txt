README.txt for Devel module
---------------------------

CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Requirements
 * Included Modules and Features
 * Recommended Modules
 * Installation
 * Configuration
 * Maintainers

INTRODUCTION
------------

Devel module contains helper functions and pages for Drupal developers and
inquisitive admins:

 - A block for quickly accessing devel pages
 - A block for masquerading as other users (useful for testing)
 - A mail-system class which redirects outbound email to files
 - Drush commands such as fn-hook, fn-event, ...
 - Docs at https://api.drupal.org/api/devel
 - more

This module is safe to use on a production site. Just be sure to only grant
'access development information' permission to developers.

 - For a full description of the module visit:
   https://www.drupal.org/project/devel

 - To submit bug reports and feature suggestions, or to track changes visit:
   https://www.drupal.org/project/issues/devel


REQUIREMENTS
------------

This module requires no modules outside of Drupal core.


INCLUDED MODULES AND FEATURES
-----------------------------

Devel Kint - Provides a dpr() function, which pretty prints variables.
Useful during development. Also see similar helpers like dpm(), dvm().

Webprofiler - Adds a debug bar at bottom of all pages with tons of useful
information like a query list, cache hit/miss data, memory profiling, page
speed, php info, session info, etc.

Devel Generate - Bulk creates nodes, users, comment, terms for development. Has
Drush integration.

Drush Unit Testing - See develDrushTest.php for an example of unit testing of
the Drush integration. This uses Drush's own test framework, based on PHPUnit.
To run the tests, use run-tests-drush.sh. You may pass in any arguments that
are valid for `phpunit`.


RECOMMENDED MODULE
------------------

Devel Generate Extensions - Devel Images Provider allows to configure external
providers for images.

 - http://drupal.org/project/devel_image_provider


INSTALLATION
------------

 - Install the Devel module as you would normally install a contributed Drupal
   module. Visit https://www.drupal.org/node/1897420 for further information.


Author/Maintainers
------------------

 - Moshe Weitzman (moshe weitzman) - https://www.drupal.org/u/moshe-weitzman
 - Hans Salvisberg (salvis) - https://www.drupal.org/u/salvis
 - Pedro Cambra https://drupal.org/user/122101/contact http://www.ymbra.com/
 - Juan Pablo Novillo https://www.drupal.org/u/juampynr
 - lussoluca https://www.drupal.org/u/lussoluca
 - willzyx https://www.drupal.org/u/willzyx

