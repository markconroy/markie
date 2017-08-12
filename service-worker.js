var cacheName = 'markie_cache';

// TODO create variables for the first 5 articles, get them on install, then
// check for updates when you next view the app.
// e.g. var item_1 = '/articles/web-design/conversation-i-have-nearly-every-designer';

self.addEventListener('install', e => {
  // once the SW is installed, go ahead and fetch the resources
  // to make this work offline
  e.waitUntil(
    caches.open(cacheName).then(cache => {
      return cache.addAll([
        '/',
        '/about',
        '/articles',
        '/articles/drupal/simple-plan-everyone-get-free-t-shirt-drupalcon',
        '/pwa/offline.css'
      ]).then(() => self.skipWaiting());
    })
  );
});

// when the browser fetches a url, either respond with
// the cached object or go ahead and fetch the actual url
self.addEventListener('fetch', event => {
  event.respondWith(
    // ensure we check the *right* cache to match against
    caches.open(cacheName).then(cache => {
      return cache.match(event.request).then(res => {
        return res || fetch(event.request)
      });
    })
  );
});
