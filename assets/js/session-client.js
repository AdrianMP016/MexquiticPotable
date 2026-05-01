(function (window, $) {
  "use strict";

  const ajaxUrl = "ajax/peticiones.php";
  let currentModuleContext = "plataforma";

  function fallbackDestination(module) {
    if (module === "cobro") {
      return "pago-campo.php";
    }
    if (module === "verificador") {
      return "verificador.php";
    }
    return "index.php";
  }

  function loginDestination(module) {
    if (module === "cobro") {
      return "login-cobro.php";
    }
    if (module === "verificador") {
      return "login-verificador.php";
    }
    return "login-admin.php";
  }

  function ensureModule(module) {
    const normalized = String(module || "").toLowerCase().trim();
    return ["plataforma", "cobro", "verificador"].indexOf(normalized) >= 0 ? normalized : "plataforma";
  }

  function appendModuleContext(payload) {
    if (payload instanceof window.FormData) {
      if (!payload.has("modulo_contexto")) {
        payload.append("modulo_contexto", currentModuleContext);
      }
      return payload;
    }

    if (typeof payload === "string") {
      const hasModule = /(^|&)modulo_contexto=/.test(payload);
      return hasModule
        ? payload
        : payload + (payload ? "&" : "") + "modulo_contexto=" + encodeURIComponent(currentModuleContext);
    }

    const data = $.isPlainObject(payload) ? $.extend({}, payload) : {};
    if (!Object.prototype.hasOwnProperty.call(data, "modulo_contexto")) {
      data.modulo_contexto = currentModuleContext;
    }
    return data;
  }

  $.ajaxPrefilter(function (options, originalOptions) {
    const url = String(options.url || "");
    if (url.indexOf(ajaxUrl) === -1) {
      return;
    }

    const payload = appendModuleContext(originalOptions.data || options.data || {});
    if (
      !(payload instanceof window.FormData) &&
      typeof payload !== "string" &&
      (options.processData === undefined || options.processData)
    ) {
      options.data = $.param(payload);
      return;
    }

    options.data = payload;
  });

  function fetchSession(module) {
    currentModuleContext = ensureModule(module || currentModuleContext);

    return $.ajax({
      url: ajaxUrl,
      method: "POST",
      dataType: "json",
      data: {
        accion: "auth.session",
        modulo_contexto: currentModuleContext
      }
    });
  }

  function redirectForModule(sessionData, module) {
    const data = sessionData && sessionData.data ? sessionData.data : {};
    const modules = data.modules || {};

    if (module === "plataforma" && modules.plataforma) {
      return null;
    }
    if (module === "cobro" && modules.cobro) {
      return null;
    }
    if (module === "verificador" && modules.verificador) {
      return null;
    }

    return loginDestination(module);
  }

  function renderUserBadge(sessionData, config) {
    const data = sessionData && sessionData.data ? sessionData.data : {};
    const user = data.user || {};
    const defaults = config || {};

    const $name = defaults.nameSelector ? $(defaults.nameSelector) : $();
    const $role = defaults.roleSelector ? $(defaults.roleSelector) : $();
    const $user = defaults.userSelector ? $(defaults.userSelector) : $();
    const $panel = defaults.panelSelector ? $(defaults.panelSelector) : $();

    if ($panel.length) {
      $panel.removeClass("d-none");
    }

    if ($name.length) {
      $name.text(user.nombre || "Sin sesion");
    }

    if ($role.length) {
      $role.text(roleLabel(user.rol || ""));
    }

    if ($user.length) {
      $user.text(user.usuario ? "@" + user.usuario : "");
    }
  }

  function roleLabel(role) {
    const labels = {
      admin: "Administrador",
      cobrador: "Cobro",
      verificador: "Verificador",
      capturista: "Capturista",
      solo_lectura: "Solo lectura"
    };

    return labels[String(role || "").toLowerCase()] || "Perfil";
  }

  function bindLogout(selector, module) {
    const logoutModule = ensureModule(module || currentModuleContext);

    $(document).on("click", selector, function (event) {
      event.preventDefault();
      $.ajax({
        url: ajaxUrl,
        method: "POST",
        dataType: "json",
        data: {
          accion: "auth.logout",
          modulo_contexto: logoutModule
        }
      }).always(function () {
        window.location.href = loginDestination(logoutModule);
      });
    });
  }

  window.MexquiticSession = {
    ensure: function (module, config) {
      currentModuleContext = ensureModule(module);

      return fetchSession(currentModuleContext)
        .done(function (response) {
          const redirect = redirectForModule(response, currentModuleContext);
          if (redirect) {
            window.location.href = redirect;
            return;
          }

          renderUserBadge(response, config || {});
        })
        .fail(function () {
          window.location.href = loginDestination(currentModuleContext);
        });
    },
    fetch: fetchSession,
    bindLogout: bindLogout,
    roleLabel: roleLabel,
    currentModule: function () {
      return currentModuleContext;
    }
  };
})(window, window.jQuery);
