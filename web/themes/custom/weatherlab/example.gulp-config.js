// Duplicate this file and rename to local.gulp-config.js
// See weatherlab-gulp for config to override:
// https://gitlab.com/anrt-tools/weatherlab-gulp/blob/develop/gulp-config.js
(() => {
  module.exports = {
    pa11y: {
      includeNotices: false,
      includeWarnings: false,
    },
    browserSync: {
      // Enable UI for optional debugging, settings and history.
      ui: {
        port: 3100,
      },
      // Uncomment to serve specific domain
      // domain: "YOURSITE.docksal",
      defaults: {
        baseDir: "./",
        startPath: "pattern-lab/public"
      }
    },
    cssConfig: {
      lint: {
        enabled: true,
        failOnError: true,
      },
      autoPrefixerBrowsers: ['last 2 versions', 'IE >= 11'],
    }
  }
})();
