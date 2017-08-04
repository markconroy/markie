var cacheName = 'markie';

var item_1 = '/node/34';

self.addEventListener('install', e => {
  // once the SW is installed, go ahead and fetch the resources
  // to make this work offline
  e.waitUntil(
    caches.open(markie).then(cache => {
      return cache.addAll([
        '/',
        '/about',
        '/articles',
        '/articles/drupal/simple-plan-everyone-get-free-t-shirt-drupalcon',
        item_1
      ]).then(() => self.skipWaiting());
    })
  );
});

// when the browser fetches a url, either respond with
// the cached object or go ahead and fetch the actual url
self.addEventListener('fetch', event => {
  event.respondWith(
    // ensure we check the *right* cache to match against
    caches.open(markie).then(cache => {
      return cache.match(event.request).then(res => {
        return res || fetch(event.request)
      });
    })
  );
});
//
// var CACHE = 'markie';
//
// var item_1 = '/node/34';
//
// self.addEventListener('install', function(evt) {
//   console.log('The service worker is being installed.');
//   evt.waitUntil(precache());
// });
//
// self.addEventListener('fetch', function(evt) {
//   console.log('The service worker is serving the asset.');
//   evt.respondWith(fromNetwork(evt.request, 400).catch(function () {
//     return fromCache(evt.request);
//   }));
// });
//
// function precache() {
//   return caches.open(CACHE).then(function (cache) {
//     return cache.addAll([
//       '/',
//       '/about',
//       '/articles',
//       '/articles/drupal/simple-plan-everyone-get-free-t-shirt-drupalcon',
//       '/articles/nevermind-25-years-old-today',
//       item_1
//     ]);
//   });
// }
//
// function fromNetwork(request, timeout) {
//   return new Promise(function (fulfill, reject) {
//     var timeoutId = setTimeout(reject, timeout);
//     fetch(request).then(function (response) {
//       clearTimeout(timeoutId);
//       fulfill(response);
//     }, reject);
//   });
// }
//
// function fromCache(request) {
//   return caches.open(CACHE).then(function (cache) {
//     return cache.match(request).then(function (matching) {
//       return matching || Promise.reject('no-match');
//     });
//   });
// }
