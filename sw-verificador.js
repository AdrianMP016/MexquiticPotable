var CACHE = 'mexquitic-verificador-v3';

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
      // Clonar de inmediato: si se clona despues de devolver la respuesta,
      // el body ya pudo empezar a leerse y truena "Response body is already used".
      if (response && response.status === 200) {
        var copy = response.clone();
        caches.open(CACHE).then(function (cache) {
          cache.put(e.request, copy);
        });
      }
      return response;
    }).catch(function () {
      return caches.match(e.request);
    })
  );
});
