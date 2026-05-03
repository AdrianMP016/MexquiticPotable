var VerificadorQueue = (function () {
  var DB_NAME    = 'mexquitic_verificador';
  var DB_VERSION = 2;
  var STORE      = 'mediciones_pendientes';
  var CACHE      = 'datos_cache';
  var _db        = null;

  function open() {
    return new Promise(function (resolve, reject) {
      if (_db) { resolve(_db); return; }
      var req = indexedDB.open(DB_NAME, DB_VERSION);

      req.onupgradeneeded = function (e) {
        var d = e.target.result;
        if (!d.objectStoreNames.contains(STORE)) {
          d.createObjectStore(STORE, { keyPath: 'id', autoIncrement: true });
        }
        if (!d.objectStoreNames.contains(CACHE)) {
          d.createObjectStore(CACHE, { keyPath: 'clave' });
        }
      };

      req.onsuccess = function (e) { _db = e.target.result; resolve(_db); };
      req.onerror   = function () { reject(req.error); };
    });
  }

  // ── Cola de lecturas pendientes ───────────────────────────────────────────

  function agregar(datos) {
    return open().then(function (db) {
      return new Promise(function (resolve, reject) {
        var item = {
          usuario_id:       datos.usuario_id,
          domicilio_id:     datos.domicilio_id,
          medidor_id:       datos.medidor_id,
          periodo_id:       datos.periodo_id,
          lectura_anterior: datos.lectura_anterior,
          medicion:         datos.medicion,
          latitud:          datos.latitud,
          longitud:         datos.longitud,
          observaciones:    datos.observaciones || '',
          usuario_nombre:   datos.usuario_nombre || '',
          periodo_nombre:   datos.periodo_nombre || '',
          timestamp_local:  new Date().toISOString(),
          intentos:         0
        };
        var tx = db.transaction(STORE, 'readwrite');
        var r  = tx.objectStore(STORE).add(item);
        r.onsuccess = function () { resolve(r.result); };
        r.onerror   = function () { reject(r.error); };
      });
    });
  }

  function obtenerTodos() {
    return open().then(function (db) {
      return new Promise(function (resolve, reject) {
        var tx    = db.transaction(STORE, 'readonly');
        var items = [];
        var r     = tx.objectStore(STORE).openCursor();
        r.onsuccess = function (e) {
          var cursor = e.target.result;
          if (cursor) { items.push(cursor.value); cursor.continue(); }
          else { resolve(items); }
        };
        r.onerror = function () { reject(r.error); };
      });
    });
  }

  function eliminar(id) {
    return open().then(function (db) {
      return new Promise(function (resolve, reject) {
        var tx = db.transaction(STORE, 'readwrite');
        var r  = tx.objectStore(STORE).delete(id);
        r.onsuccess = function () { resolve(); };
        r.onerror   = function () { reject(r.error); };
      });
    });
  }

  function contar() {
    return open().then(function (db) {
      return new Promise(function (resolve, reject) {
        var tx = db.transaction(STORE, 'readonly');
        var r  = tx.objectStore(STORE).count();
        r.onsuccess = function () { resolve(r.result); };
        r.onerror   = function () { reject(r.error); };
      });
    });
  }

  // ── Caché de rutas y usuarios ────────────────────────────────────────────

  function cacheGuardar(clave, valor) {
    return open().then(function (db) {
      return new Promise(function (resolve, reject) {
        var tx = db.transaction(CACHE, 'readwrite');
        var r  = tx.objectStore(CACHE).put({ clave: clave, valor: valor, ts: Date.now() });
        r.onsuccess = function () { resolve(); };
        r.onerror   = function () { reject(r.error); };
      });
    });
  }

  function cacheObtener(clave) {
    return open().then(function (db) {
      return new Promise(function (resolve, reject) {
        var tx = db.transaction(CACHE, 'readonly');
        var r  = tx.objectStore(CACHE).get(clave);
        r.onsuccess = function () { resolve(r.result ? r.result.valor : null); };
        r.onerror   = function () { reject(r.error); };
      });
    });
  }

  return {
    agregar:      agregar,
    obtenerTodos: obtenerTodos,
    eliminar:     eliminar,
    contar:       contar,
    cache: {
      guardar:  cacheGuardar,
      obtener:  cacheObtener
    }
  };
})();
