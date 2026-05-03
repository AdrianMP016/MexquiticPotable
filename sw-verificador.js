var CACHE = 'mexquitic-verificador-v1';

self.addEventListener('install', function (e) {
  self.skipWaiting();
});

self.addEventListener('activate', function (e) {
  e.waitUntil(
    caches.keys().then(function (keys) {
      return Promise.all(
        keys
          .filter(function (k) { return k !== CACHE; })
          .map(function (k) { return caches.delete(k); })
      );
    })
  );
  self.clients.claim();
});

self.addEventListener('fetch', function (e) {
  // Solo interceptar GET
  if (e.request.method !== 'GET') { return; }

  var url = new URL(e.request.url);

  // Las llamadas AJAX las maneja la cola IndexedDB, no las cacheamos
  if (url.pathname.indexOf('peticiones.php') !== -1) { return; }

  // Todo lo demás: red primero, caché como respaldo
  e.respondWith(
    fetch(e.request).then(function (response) {
      // Cachear solo respuestas exitosas
      if (response && response.status === 200) {
        caches.open(CACHE).then(function (cache) {
          cache.put(e.request, response.clone());
        });
      }
      return response;
    }).catch(function () {
      return caches.match(e.request);
    })
  );
});
