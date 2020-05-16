<?php

/**
 * @file
 * Add "attach_library" function for Pattern Lab.
 */

use Symfony\Component\Yaml\Yaml;
use PatternLab\Template;

$function = new Twig_SimpleFunction('attach_library', function ($string) {
  // Get Library Name from string.
  $libraryName = substr($string, strpos($string, "/") + 1);

  // Find Library in libraries.yml file.
  $yamlFile = glob('*.libraries.yml');
  $yamlOutput = Yaml::parseFile($yamlFile[0]);
  $scriptTags = [];
  $styleTags = [];

  // For each item in .libraries.yml file.
  foreach ($yamlOutput as $key => $value) {

    // If the library exists.
    if ($key === $libraryName) {
      if (isset($yamlOutput[$key]['js'])) {
        $js_files = $yamlOutput[$key]['js'];
      }
      // Check if CSS files are defined.
      if (isset($yamlOutput[$key]['css'])) {
        // Create a single array from stylesheets groups (base, theme).
        $css_files = array_reduce($yamlOutput[$key]['css'], 'array_merge', []);
      }

      // For each file, create an async script to insert to the Twig component.
      if (isset($js_files)) {
        foreach ($js_files as $key => $file) {
          // By default prefix paths with relative path to dist folder,
          // but remove this for external JS as it would break URLs.
          $path_prefix = '../../../../';
          if (isset($file['type']) && $file['type'] === 'external') {
            $path_prefix = '';
          }
          $scriptString = '<script data-name="reload" data-src="' . $path_prefix . $key . '"></script>';
          $stringLoader = Template::getStringLoader();
          $scriptTags[$key] = $stringLoader->render(["string" => $scriptString, "data" => []]);
        }
      }

      // For each css files, create a link to insert to the Twig component.
      if (isset($css_files)) {
        foreach ($css_files as $key => $file) {
          // By default prefix paths with relative path to dist folder,
          // but remove this for external CSS as it would break URLs.
          $path_prefix = '../../../../';
          if (isset($file['type']) && $file['type'] === 'external') {
            $path_prefix = '';
          }
          $linkString = '<link rel="stylesheet" href="' . $path_prefix . $key . '">';
          $stringLoader = Template::getStringLoader();
          $styleTags[$key] = $stringLoader->render(["string" => $linkString, "data" => []]);
        }
      }
    }
  }
  echo implode($styleTags);
  echo implode($scriptTags);
}, ['is_safe' => ['html']]);
