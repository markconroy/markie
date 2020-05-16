# WeatherLab: Pattern Lab + Drupal 8

Component-driven prototyping tool using [Pattern Lab v2](http://patternlab.io/) automated via Gulp/NPM.

## Requirements

1.  [PHP 7.1](http://www.php.net/)
2.  [Node 10 (we recommend NVM)](https://github.com/creationix/nvm)
3.  [Gulp](http://gulpjs.com/)
4.  [Composer](https://getcomposer.org/)
5.  Optional: [Yarn](https://github.com/yarnpkg/yarn)

## Installation

1. Require weatherlab in your project `composer require anrt-tools/weatherlab`
2. Move into the original weatherlab theme `cd web/themes/custom/weatherlab/`
3. Install the theme dependencies `npm install` or `yarn install`
4. Enable Drupal modules `components` and `unified_twig_ext`
5. Proceed to the "Starting Pattern Lab…" section below

## Starting Pattern Lab and watch task

The `start` command spins up a local server, compiles everything (runs all required gulp tasks), and watches for changes.

1.  `npm start` or `yarn start`
2.  See [Troubleshooting](#troubleshooting) if issues

### Commands

* Create new component: `npm run new` or `yarn run new`
* List all gulp commands: `gulp help`
* Reinstall `pattern-lab` to initial state: `npm run reinstall` or `yarn run reinstall` (components are preserved)

### Working with CSS & JS assets

Currently all `scss` partials are compiled into a single `style.css` included in weatherlab core library.  

`js` files are not concatenated into a single file, but should be added as a library item in `weatherlab.libraries.yml`:
* Reference the compiled component `js` file found in `dist/[component-path]`
* For custom vendor/library files (`js`/`css`) add to `components/_custom-vendor/`
* Pattern Lab is unable to load library dependencies, see `components/_meta/_00-head.twig` on symlinking and loading dependencies globally.
* Load library assets in the component twig file using `{{ attach_library('weatherlab/library_name') }}` (works for Drupal and Pattern Lab)

## Troubleshooting

### Issues compiling assets with `npm/yarn start`

First step: 
* Delete `node_modules` folder 
* If using `npm` delete `package-lock.json` or
* `yarn.lock` if using `yarn`
* Run `npm/yarn install`
* Try `npm/yarn start` once more

If that doesn't work, ask in RocketChat


### Fatal error: Allowed memory size of xxx bytes exhausted 

* Run `php -i|grep 'php.ini'`
* Open the relevant `php.ini` file and check `memory_limit` is high (eg 1024M)


---

## Weatherlab is built on FourKitchen's Emulsify theme
Further information can be found on [GitHub project](https://github.com/fourkitchens/emulsify) and the [Wiki](https://github.com/fourkitchens/emulsify/wiki).
