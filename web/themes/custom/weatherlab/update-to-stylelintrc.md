## In weatherlab directory:

- Update `.stylelintrc` (copy it from starterkit weatherlab folder)
- _optional_: rename `.stylelintrc` to `.stylelintrc.json`
- To update the `package.json`, run commands:
  - **using** `NPM`:
    - `npm remove emulsify-gulp`
    - `npm install --save-dev https://oauth2:xL4XGnTyQvzp8-NFr2P5@gitlab.com/anrt-tools/weatherlab-gulp.git#5.0.2`
  - **using** `Yarn`:
    - `yarn remove emulsify-gulp`
    - `yarn add --dev https://oauth2:xL4XGnTyQvzp8-NFr2P5@gitlab.com/anrt-tools/weatherlab-gulp.git#5.0.2`
- Copy [`post_install.sh`](https://gitlab.com/anrt-tools/d8-starterkit/blob/master/web/themes/custom/weatherlab/scripts/post_install.sh) to your `weatherlab/scripts` folder
- `package.json`:
    - In `"scripts"` section, replace line: `"postinstall": "./scripts/pattern_lab.sh && ./scripts/twig_functions.sh",` with:
    - ```
      "postinstall": "./scripts/post_install.sh",
      "reinstall": "./scripts/pattern_lab.sh && ./scripts/twig_functions.sh",
      ```
- `gulpfile.js`:
  - Set `require('emulsify-gulp')` to `require('weatherlab-gulp')`
- `example.gulp-config.js`:
  - Add this to top of file:
  - ```
    // Duplicate this file and rename to local.gulp-config.js
    // See weatherlab-gulp for config to override:
    // https://gitlab.com/anrt-tools/weatherlab-gulp/blob/develop/gulp-config.js
    ```
  - Or just replace with [starterkit version](https://gitlab.com/anrt-tools/d8-starterkit/blob/master/web/themes/custom/weatherlab/example.gulp-config.js)
  - Same for `local.gulp-config.js` (if you have one)

## In project root directory

- Update `.stylelintrc.json` (copy it from [starterkit](https://gitlab.com/anrt-tools/d8-starterkit/blob/master/.stylelintrc.json))
- Check `.gitlab-ci.yml` task `test:lint_sass` installs `stylelint-order` (see [starterkit version](https://gitlab.com/anrt-tools/d8-starterkit/blob/master/.gitlab-ci.yml))
- Remove `.csscomb.json`

## Get it to work & troubleshooting tips

- Test linting with `gulp validate:css`
- IF errors about `max-empty-space`, edit files and remove double empty lines.
- IF errors about `@return` after at-rule block, append `/* stylelint-disable-line */` to `@return $string` line.
- Otherwise, enjoy the automatic file formatting by
- Running `npm/yarn start`
- All working?

### Improvements

- Rename folder `00-base/global/02-fonts` to `00-base/00-fonts`
- `style.scss`:
  - Replace: 
    ```
    @import "00-base/global/01-colors/_color-vars.scss";  
    @import "00-base/global/02-fonts/_fonts.scss";  
    @import "**/*.scss";  
    ```
  - With:
    ```
    @import "00-base/**/*.scss";
    @import "01-basic-elements/**/*.scss";
    @import "02-site-components/**/*.scss";
    @import "03-building-blocks/**/*.scss";
    @import "04-content/**/*.scss";
    @import "05-page-sections/**/*.scss";
    @import "06-sample-pages/**/*.scss";
    ```
  - This reduces `style.css` file size by not including `wysiwyg.scss` content
- `wysiwyg.scss` (if exists)
  - Update path to `_fonts.scss`

