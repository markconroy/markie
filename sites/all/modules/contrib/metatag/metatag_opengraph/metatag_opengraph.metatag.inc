<?php
/**
 * @file
 * Metatag integration for the metatag_opengraph module.
 */

/**
 * Implements hook_metatag_bundled_config_alter().
 */
function metatag_opengraph_metatag_bundled_config_alter(array &$configs) {
  foreach ($configs as &$config) {
    switch ($config->instance) {
      case 'global':
        $config->config += array(
          'og:site_name' => array('value' => '[site:name]'),
          'og:title' => array('value' => '[current-page:title]'),
          'og:type' => array('value' => 'article'),
          'og:url' => array('value' => '[current-page:url:absolute]'),
        );
        break;

      case 'global:frontpage':
        $config->config += array(
          'og:description' => array('value' => '[site:slogan]'),
          'og:title' => array('value' => '[site:name]'),
          'og:type' => array('value' => 'website'),
          'og:url' => array('value' => '[site:url]'),
        );
        break;

      // On error pages point everything to the homepage.
      case 'global:403':
      case 'global:404':
        $config->config += array(
          'og:title' => array('value' => '[site:name]'),
          'og:type' => array('value' => 'website'),
          'og:url' => array('value' => '[site:url]'),
        );
        break;

      case 'node':
        $config->config += array(
          'article:modified_time' => array('value' => '[node:changed:custom:c]'),
          'article:published_time' => array('value' => '[node:created:custom:c]'),
          'og:description' => array('value' => '[node:summary]'),
          'og:title' => array('value' => '[node:title]'),
          'og:updated_time' => array('value' => '[node:changed:custom:c]'),
        );
        break;

      case 'taxonomy_term':
        $config->config += array(
          'og:description' => array('value' => '[term:description]'),
          'og:title' => array('value' => '[term:name]'),
        );
        break;

      case 'user':
        $config->config += array(
          'og:title' => array('value' => '[user:name]'),
          'og:type' => array('value' => 'profile'),
          'profile:username' => array('value' => '[user:name]'),
        );
        if (variable_get('user_pictures')) {
          $config->config += array(
            'og:image' => array('value' => '[user:picture:url]'),
          );
        }
        break;
    }
  }
}

/**
 * Implements hook_metatag_info().
 */
function metatag_opengraph_metatag_info() {
  $info['groups']['open-graph'] = array(
    'label' => t('Open Graph'),
    'description' => t("The <a href=\"@ogp\">Open Graph meta tags</a> are used control how Facebook, LinkedIn an other social networking sites interpret the site's content.", array('@ogp' => 'http://ogp.me/')),
    'form' => array(
      '#weight' => 50,
    ),
  );

  // Default values for each meta tag.
  $tag_info_defaults = array(
    'description' => '',
    'class' => 'DrupalTextMetaTag',
    'group' => 'open-graph',
    'element' => array(
      '#theme' => 'metatag_property',
    ),
  );

  // Open Graph meta tags stack after the Facebook tags.
  $weight = 25;

  $info['tags']['og:site_name'] = array(
    'label' => t('Site name'),
    'description' => t('A human-readable name for the site, e.g., <em>IMDb</em>.'),
    'context' => array('global'),
    'weight' => ++$weight,
  ) + $tag_info_defaults;

  $info['tags']['og:type'] = array(
    'label' => t('Content type'),
    'description' => t('The type of the content, e.g., <em>movie</em>.'),
    'weight' => ++$weight,
    'form' => array(
      '#type' => 'select',
      '#options' => _metatag_opengraph_type_options(),
      '#empty_option' => t('- None -'),
    ),
    'devel_generate' => array(
      'type' => 'select',
    ),
  ) + $tag_info_defaults;

  $info['tags']['og:url'] = array(
    'label' => t('Page URL'),
    'description' => t('Preferred page location or URL to help eliminate duplicate content for search engines, e.g., <em>http://www.imdb.com/title/tt0117500/</em>.'),
    'weight' => ++$weight,
    'devel_generate' => array(
      'type' => 'canonical',
    ),
  ) + $tag_info_defaults;

  if (module_exists('select_or_other')) {
    // Enhance the og:type field to support custom types.
    $info['tags']['og:type']['form']['#type'] = 'select_or_other';
    $info['tags']['og:type']['form']['#other'] = t('Other (please type a value)');
    $info['tags']['og:type']['form']['#other_unknown_defaults'] = 'other';
    $info['tags']['og:type']['form']['#theme'] = 'select_or_other';
    $info['tags']['og:type']['form']['#element_validate'] = array('select_or_other_element_validate');
  }

  $info['tags']['og:title'] = array(
    'label' => t('Content title'),
    'description' => t('The title of the content, e.g., <em>The Rock</em>.'),
    'weight' => ++$weight,
  ) + $tag_info_defaults;

  $info['tags']['og:determiner'] = array(
    'label' => t('Content title determiner'),
    'description' => t("The word that appears before the content's title in a sentence. The default ignores this value, the 'Automatic' value should be sufficient if this is actually needed."),
    'weight' => ++$weight,
    'form' => array(
      '#type' => 'select',
      '#options' => array(
        'auto' => 'Automatic',
        'a' => 'A',
        'an' => 'An',
        'the' => 'The',
      ),
      '#empty_option' => t('- Ignore -'),
    ),
    'devel_generate' => array(
      'type' => 'select',
    ),
  ) + $tag_info_defaults;

  $info['tags']['og:description'] = array(
    'label' => t('Content description'),
    'description' => t('A one to two sentence description of the content.'),
    'weight' => ++$weight,
  ) + $tag_info_defaults;

  // Basic tags.
  $info['tags']['og:updated_time'] = array(
    'label' => t('Content modification date & time'),
    'description' => t("The date this content was last modified, with an optional time value. Needs to be in <a href='http://en.wikipedia.org/wiki/ISO_8601'>ISO 8601</a> format. Can be the same as the 'Article modification date' tag."),
    'weight' => ++$weight,
  ) + $tag_info_defaults;

  $info['tags']['og:see_also'] = array(
    'label' => t('See also'),
    'description' => t('URLs to related content.'),
    'multiple' => TRUE,
    'weight' => ++$weight,
  ) + $tag_info_defaults;

  $info['tags']['og:image'] = array(
    'label' => t('Image URL'),
    'description' => t('The URL of an image which should represent the content. The image must be at least 50px by 50px and have a maximum aspect ratio of 3:1. Supports PNG, JPEG and GIF formats.'),
    'multiple' => TRUE,
    'weight' => ++$weight,
    'devel_generate' => array(
      'type' => 'image',
    ),
  ) + $tag_info_defaults;
  $info['tags']['og:image:secure_url'] = array(
    'label' => t('Secure image URL'),
    'description' => t('The secure URL (HTTPS) of an image which should represent the content. The image must be at least 50px by 50px and have a maximum aspect ratio of 3:1. Supports PNG, JPEG and GIF formats. All "http://" URLs will automatically be converted to "https://".'),
    'multiple' => TRUE,
    'secure' => TRUE,
    'weight' => ++$weight,
    'devel_generate' => array(
      'type' => 'image',
    ),
  ) + $tag_info_defaults;
  $info['tags']['og:image:type'] = array(
    'label' => t('Image type'),
    'description' => t('The type of image referenced above. Should be either "image/gif" for a GIF image, "image/jpeg" for a JPG/JPEG image, or "image/png" for a PNG image.'),
    'multiple' => TRUE,
    'weight' => ++$weight,
  ) + $tag_info_defaults;
  $info['tags']['og:image:width'] = array(
    'label' => t('Image width'),
    'description' => t('The width of the above image(s). Note: if both the unsecured and secured images are provided, they should both be the same size.'),
    'multiple' => TRUE,
    'weight' => ++$weight,
    'devel_generate' => array(
      'type' => 'image',
    ),
  ) + $tag_info_defaults;
  $info['tags']['og:image:height'] = array(
    'label' => t('Image height'),
    'description' => t('The height of the above image(s). Note: if both the unsecured and secured images are provided, they should both be the same size.'),
    'multiple' => TRUE,
    'weight' => ++$weight,
    'devel_generate' => array(
      'type' => 'image',
    ),
  ) + $tag_info_defaults;

  $info['tags']['og:latitude'] = array(
    'label' => t('Latitude'),
    'weight' => ++$weight,
    'devel_generate' => array(
      'type' => 'float',
    ),
  ) + $tag_info_defaults;
  $info['tags']['og:longitude'] = array(
    'label' => t('Longitude'),
    'weight' => ++$weight,
    'devel_generate' => array(
      'type' => 'float',
    ),
  ) + $tag_info_defaults;

  $info['tags']['og:street-address'] = array(
    'label' => t('Street address'),
    'weight' => ++$weight,
  ) + $tag_info_defaults;
  $info['tags']['og:locality'] = array(
    'label' => t('Locality'),
    'weight' => ++$weight,
  ) + $tag_info_defaults;
  $info['tags']['og:region'] = array(
    'label' => t('Region'),
    'weight' => ++$weight,
  ) + $tag_info_defaults;
  $info['tags']['og:postal-code'] = array(
    'label' => t('Postal/ZIP code'),
    'weight' => ++$weight,
  ) + $tag_info_defaults;
  $info['tags']['og:country-name'] = array(
    'label' => t('Country name'),
    'weight' => ++$weight,
  ) + $tag_info_defaults;

  $info['tags']['og:email'] = array(
    'label' => t('Email'),
    'weight' => ++$weight,
    'devel_generate' => array(
      'type' => 'email',
    ),
  ) + $tag_info_defaults;
  $info['tags']['og:phone_number'] = array(
    'label' => t('Phone number'),
    'weight' => ++$weight,
    'devel_generate' => array(
      'type' => 'phone',
    ),
  ) + $tag_info_defaults;
  $info['tags']['og:fax_number'] = array(
    'label' => t('Fax number'),
    'weight' => ++$weight,
    'devel_generate' => array(
      'type' => 'phone',
    ),
  ) + $tag_info_defaults;

  $info['tags']['og:locale'] = array(
    'label' => t('Locale'),
    'description' => 'The locale these tags are marked up in, must be in the format language_TERRITORY. Default is en_US.',
    'weight' => ++$weight,
    'devel_generate' => array(
      'maxlength' => 1,
    ),
  ) + $tag_info_defaults;
  $info['tags']['og:locale:alternate'] = array(
    'label' => t('Alternative locales'),
    'description' => 'Other locales this content is available in, must be in the format language_TERRITORY, e.g. "fr_FR".',
    'weight' => ++$weight,
    'multiple' => TRUE,
    'devel_generate' => array(
      'maxlength' => 1,
    ),
  ) + $tag_info_defaults;

  $info['tags']['og:audio'] = array(
    'label' => t('Audio URL'),
    'description' => t('The URL to an audio file that complements this object.'),
    'weight' => ++$weight,
    'devel_generate' => array(
      'type' => 'url',
    ),
  ) + $tag_info_defaults;
  $info['tags']['og:audio:secure_url'] = array(
    'label' => t('Audio secure URL'),
    'description' => t('The secure URL to an audio file that complements this object. All "http://" URLs will automatically be converted to "https://".'),
    'weight' => ++$weight,
    'devel_generate' => array(
      'type' => 'url',
    ),
  ) + $tag_info_defaults;
  $info['tags']['og:audio:type'] = array(
    'label' => t('Audio type'),
    'description' => t('The MIME type of the audio file. Examples include "application/mp3" for an MP3 file.'),
    'weight' => ++$weight,
  ) + $tag_info_defaults;

  $info['tags']['og:video'] = array(
    'label' => t('Video URL'),
    'description' => t('The URL to a video file that complements this object.'),
    'weight' => ++$weight,
    'devel_generate' => array(
      'type' => 'url',
    ),
  ) + $tag_info_defaults;
  $info['tags']['og:video:secure_url'] = array(
    'label' => t('Video secure URL'),
    'description' => t('A URL to a video file that complements this object using the HTTPS protocol. All "http://" URLs will automatically be converted to "https://".'),
    'secure' => TRUE,
    'weight' => ++$weight,
    'devel_generate' => array(
      'type' => 'url',
    ),
  ) + $tag_info_defaults;
  $info['tags']['og:video:width'] = array(
    'label' => t('Video width'),
    'description' => t('The width of the video.'),
    'weight' => ++$weight,
    'devel_generate' => array(
      'type' => 'integer',
    ),
  ) + $tag_info_defaults;
  $info['tags']['og:video:height'] = array(
    'label' => t('Video height'),
    'description' => t('The height of the video.'),
    'weight' => ++$weight,
    'devel_generate' => array(
      'type' => 'integer',
    ),
  ) + $tag_info_defaults;
  $info['tags']['og:video:type'] = array(
    'label' => t('Video type'),
    'description' => t('The MIME type of the video file. Examples include "application/x-shockwave-flash" for a Flash video, or "text/html" if this is a standalone web page containing a video.'),
    'weight' => ++$weight,
  ) + $tag_info_defaults;

  // For the "article" og:type.
  $article_defaults = array(
    'dependencies' => array(
      array(
        'dependency' => 'og:type',
        'attribute' => 'value',
        'condition' => 'value',
        'value' => 'article',
      ),
    ),
  );
  $info['tags']['article:author'] = array(
    'label' => t('Article author'),
    'description' => t("Links an article to an author's Facebook profile, should be either URLs to the author's profile page or their Facebook profile IDs."),
    'multiple' => TRUE,
    'weight' => ++$weight,
  ) + $tag_info_defaults + $article_defaults;
  $info['tags']['article:publisher'] = array(
    'label' => t('Article publisher'),
    'description' => t("Links an article to a publisher's Facebook page."),
    'weight' => ++$weight,
  ) + $tag_info_defaults + $article_defaults;
  $info['tags']['article:section'] = array(
    'label' => t('Article section'),
    'description' => t('The primary section of this website the content belongs to.'),
    'weight' => ++$weight,
  ) + $tag_info_defaults + $article_defaults;
  $info['tags']['article:tag'] = array(
    'label' => t('Article tag(s)'),
    'description' => t('Appropriate keywords for this content.'),
    'multiple' => TRUE,
    'weight' => ++$weight,
  ) + $tag_info_defaults + $article_defaults;
  $info['tags']['article:published_time'] = array(
    'label' => t('Article publication date & time'),
    'description' => t("The date this content was published on, with an optional time value. Needs to be in <a href='http://en.wikipedia.org/wiki/ISO_8601'>ISO 8601</a> format."),
    'weight' => ++$weight,
  ) + $tag_info_defaults + $article_defaults;
  $info['tags']['article:modified_time'] = array(
    'label' => t('Article modification date & time'),
    'description' => t("The date this content was last modified, with an optional time value. Needs to be in <a href='http://en.wikipedia.org/wiki/ISO_8601'>ISO 8601</a> format."),
    'weight' => ++$weight,
  ) + $tag_info_defaults + $article_defaults;
  $info['tags']['article:expiration_time'] = array(
    'label' => t('Article expiration date & time'),
    'description' => t("The date this content will expire, with an optional time value. Needs to be in <a href='http://en.wikipedia.org/wiki/ISO_8601'>ISO 8601</a> format."),
    'weight' => ++$weight,
  ) + $tag_info_defaults + $article_defaults;

  // For the "book" og:type.
  $book_defaults = array(
    'dependencies' => array(
      array(
        'dependency' => 'og:type',
        'attribute' => 'value',
        'condition' => 'value',
        'value' => 'book',
      ),
    ),
  );
  $info['tags']['book:author'] = array(
    'label' => t("Book's author"),
    'description' => t("Links to the book's author's Facebook profile, should be either URLs to the author's profile page or their Facebook profile IDs."),
    'multiple' => TRUE,
    'weight' => ++$weight,
  ) + $tag_info_defaults + $book_defaults;
  $info['tags']['book:isbn'] = array(
    'label' => t("Book's ISBN"),
    'description' => t("The book's <a href=\"http://en.wikipedia.org/wiki/International_Standard_Book_Number\">International Standard Book Number</a>, which may be in one of several formats."),
    'weight' => ++$weight,
  ) + $tag_info_defaults + $book_defaults;
  $info['tags']['book:release_date'] = array(
    'label' => t('Book release date'),
    'description' => t("The date this content will expire, with an optional time value. Needs to be in <a href='http://en.wikipedia.org/wiki/ISO_8601'>ISO 8601</a> format."),
    'weight' => ++$weight,
  ) + $tag_info_defaults + $book_defaults;
  $info['tags']['book:tag'] = array(
    'label' => t('Book tags'),
    'description' => t('Appropriate keywords for this book.'),
    'multiple' => TRUE,
    'weight' => ++$weight,
  ) + $tag_info_defaults + $book_defaults;

  // For the "profile" og:type.
  $profile_defaults = array(
    'dependencies' => array(
      array(
        'dependency' => 'og:type',
        'attribute' => 'value',
        'condition' => 'value',
        'value' => 'profile',
      ),
    ),
  );
  $info['tags']['profile:first_name'] = array(
    'label' => t('First name'),
    'description' => t("The first name of the person who's Profile page this is."),
    'weight' => ++$weight,
  ) + $tag_info_defaults + $profile_defaults;
  $info['tags']['profile:last_name'] = array(
    'label' => t('Last name'),
    'description' => t("The person's last name."),
    'weight' => ++$weight,
  ) + $tag_info_defaults + $profile_defaults;
  $info['tags']['profile:username'] = array(
    'label' => t('Username'),
    'description' => t("A pseudonym / alias of this person."),
    'weight' => ++$weight,
  ) + $tag_info_defaults + $profile_defaults;
  $info['tags']['profile:gender'] = array(
    'label' => t('Gender'),
    'description' => t("Any of Facebook's gender values should be allowed, the initial two being 'male' and 'female'."),
    'weight' => ++$weight,
  ) + $tag_info_defaults + $profile_defaults;

  return $info;
}

function _metatag_opengraph_type_options() {
  $options = array(
    t('Activities') => array(
      'activity' => t('Activity'),
      'sport' => t('Sport'),
    ),
    t('Businesses') => array(
      'bar' => t('Bar', array('context' => 'an establishment')),
      'company' => t('Company'),
      'cafe' => t('Cafe'),
      'hotel' => t('Hotel'),
      'restaurant' => t('Restaurant'),
    ),
    t('Groups') => array(
      'cause' => t('Cause'),
      'sports_league' => t('Sports league'),
      'sports_team' => t('Sports team'),
    ),
    t('Organizations') => array(
      'band' => t('Band'),
      'government' => t('Government'),
      'non_profit' => t('Non-profit'),
      'school' => t('School'),
      'university' => t('University'),
    ),
    t('People') => array(
      'actor' => t('Actor'),
      'athlete' => t('Athlete'),
      'author' => t('Author'),
      'director' => t('Director'),
      'musician' => t('Musician'),
      'politician' => t('Politician'),
      'profile' => t('Profile'),
      'public_figure' => t('Public figure'),
    ),
    t('Places') => array(
      'city' => t('City'),
      'country' => t('Country'),
      'landmark' => t('Landmark'),
      'state_province' => t('State or province'),
    ),
    t('Products and Entertainment') => array(
      'album' => t('Album'),
      'book' => t('Book'),
      'drink' => t('Drink'),
      'food' => t('Food'),
      'game' => t('Game'),
      'movie' => t('Movie'),
      'product' => t('Product'),
      'song' => t('Song'),
      'tv_show' => t('TV show'),
    ),
    t('Websites') => array(
      'blog' => t('Blog'),
      'website' => t('Website'),
      'article' => t('Article'),
    ),
  );

  return $options;
}
