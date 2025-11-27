# Stage File Proxy

Stage File Proxy is a general solution for getting production files
on a development server on demand. It saves you time and disk space
by sending requests to your development environment's files directory
to the production environment and making a copy of the production file
in your development site. You should not need to enable this module
in production.

For a full description of the module, visit the
[project page](https://www.drupal.org/project/stage_file_proxy).

Submit bug reports and feature suggestions, or track changes in the
[issue queue](https://www.drupal.org/project/issues/stage_file_proxy).


## Table of contents

- Requirements
- Installation
- Configuration
- Nginx compatibility
- Maintainers


## Requirements

This module does not require any additional modules outside of Drupal core.


## Installation

Install as you would normally install a contributed Drupal module. For further
information, see
[Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).


## Configuration

1. Enable Stage File Proxy, either via "Extend" (/admin/modules) or via drush:
   $ drush en --yes stage_file_proxy

2. Configure connection to the source. This is available via the UI, at
   Configuration > Stage File Proxy Settings

As this module should only be used on non-production sites, it is preferable to
configure this within your settings.php or settings.local.php file. Detailed
descriptions of each setting, and syntax for defining the configuration in code
is in INSTALL.md


## Nginx compatibility

The recommended NGINX configuration for Drupal includes rewrites that bypass
Drupal for 404's inside certain directories or with certain file extensions.
Make the following change to your NGINX configuration:

Before

```
location ~ ^/sites/.*/files/styles/ {
    try_files $uri @rewrite;
}
```

After

```
location ~ ^/sites/.*/files/ {
    try_files $uri @rewrite;
}
```


## Maintainers

- Stephen Mustgrave - [smustgrave](https://www.drupal.org/u/smustgrave)
- Greg Knaddison - [greggles](https://www.drupal.org/u/greggles)
- Merlin Axel Rutz - [geek-merlin](https://www.drupal.org/u/geek-merlin)
- Mark Dorison - [markdorison](https://www.drupal.org/u/markdorison)
- Baris Wanschers - [BarisW](https://www.drupal.org/u/barisw)
- Baris Wanschers - [BarisW](https://www.drupal.org/u/barisw)
- Moshe Weitzman - [moshe weitzman](https://www.drupal.org/u/moshe-weitzman)
- Mark Sonnabaum - [msonnabaum](https://www.drupal.org/u/msonnabaum)
- netaustin - [netaustin](https://www.drupal.org/user/199298)
- Rob Wilmshurst - [robwilmshurst](https://www.drupal.org/u/robwilmshurst)
