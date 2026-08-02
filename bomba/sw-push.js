self.addEventListener("push", function (event) {
  var datos = {};

  try {
    datos = event.data ? event.data.json() : {};
  } catch (e) {
    datos = { titulo: "Control de Bomba", cuerpo: event.data ? event.data.text() : "" };
  }

  var titulo = datos.titulo || "Control de Bomba";
  var opciones = {
    body: datos.cuerpo || "",
    icon: "../assets/img/logo-recibo.png",
    badge: "../assets/img/logo-recibo.png",
    data: datos.datos || {}
  };

  event.waitUntil(self.registration.showNotification(titulo, opciones));
});

self.addEventListener("notificationclick", function (event) {
  event.notification.close();

  event.waitUntil(
    clients.matchAll({ type: "window", includeUncontrolled: true }).then(function (clientList) {
      for (var i = 0; i < clientList.length; i++) {
        if ("focus" in clientList[i]) {
          return clientList[i].focus();
        }
      }
      if (clients.openWindow) {
        return clients.openWindow("index.php");
      }
    })
  );
});
