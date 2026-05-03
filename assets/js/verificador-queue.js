var VerificadorQueue = (function () {
  var DB_NAME = 'mexquitic_verificador';
  var DB_VERSION = 1;
  var STORE = 'mediciones_pendientes';
  var _db = null;

  function open() {
    return new Promise(function (resolve, reject) {
      if (_db) { resolve(_db); return; }
      var req = indexedDB.open(DB_NAME, DB_VERSION);
      req.onupgradeneeded = function (e) {
        var d = e.target.result;
        if (!d.objectStoreNames.contains(STORE)) {
          d.createObjectStore(STORE, { keyPath: 'id', autoIncrement: true });
        }
      };
      req.onsuccess = function (e) { _db = e.target.result; resolve(_db); };
      req.onerror = function () { reject(req.error); };
    });
  }

  function agregar(datos) {
    return open().then(function (db) {
      return new Promise(function (resolve, reject) {
        var item = {
          usuario_id:      datos.usuario_id,
          domicilio_id:    datos.domicilio_id,
          medidor_id:      datos.medidor_id,
          periodo_id:      datos.periodo_id,
          lectura_anterior: datos.lectura_anterior,
          medicion:        datos.medicion,
          latitud:         datos.latitud,
          longitud:        datos.longitud,
          observaciones:   datos.observaciones || '',
          usuario_nombre:  datos.usuario_nombre || '',
          periodo_nombre:  datos.periodo_nombre || '',
          timestamp_local: new Date().toISOString(),
          intentos:        0
        };
        var tx = db.transaction(STORE, 'readwrite');
        var store = tx.objectStore(STORE);
        var r = store.add(item);
        r.onsuccess = function () { resolve(r.result); };
        r.onerror   = function () { reject(r.error); };
      });
    });
  }

  function obtenerTodos() {
    return open().then(function (db) {
      return new Promise(function (resolve, reject) {
        var tx = db.transaction(STORE, 'readonly');
        var items = [];
        var r = tx.objectStore(STORE).openCursor();
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
        var r = tx.objectStore(STORE).delete(id);
        r.onsuccess = function () { resolve(); };
        r.onerror   = function () { reject(r.error); };
      });
    });
  }

  function contar() {
    return open().then(function (db) {
      return new Promise(function (resolve, reject) {
        var tx = db.transaction(STORE, 'readonly');
        var r = tx.objectStore(STORE).count();
        r.onsuccess = function () { resolve(r.result); };
        r.onerror   = function () { reject(r.error); };
      });
    });
  }

  return { agregar: agregar, obtenerTodos: obtenerTodos, eliminar: eliminar, contar: contar };
})();
