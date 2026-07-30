let bombaUsuariosCache = [];

function bombaRenderUsuarios(usuarios) {
  bombaUsuariosCache = usuarios;

  if (!usuarios.length) {
    $("#listaUsuarios").html('<p style="padding:16px; color:var(--agua-muted);">Sin usuarios registrados.</p>');
    return;
  }

  var html = usuarios.map(function (u) {
    var rolTexto = u.rol === "admin" ? "Administrador" : "Operador";
    var estadoBadge = Number(u.activo) === 1
      ? '<span class="badge-rol ' + u.rol + '">' + rolTexto + '</span>'
      : '<span class="badge-rol inactivo">Inactivo</span>';

    return (
      '<div class="lista-fila">' +
        '<div><strong>' + u.nombre + '</strong><br><span style="color:var(--agua-muted);">' + u.usuario + '</span></div>' +
        '<div>' + estadoBadge + '</div>' +
        '<div>' +
          '<button type="button" class="btn-grande secundario btn-editar-usuario" data-id="' + u.id + '" style="padding:10px 14px; font-size:15px;"><i class="fas fa-pen"></i></button> ' +
          '<button type="button" class="btn-grande peligro btn-baja-usuario" data-id="' + u.id + '" style="padding:10px 14px; font-size:15px;"><i class="fas fa-user-slash"></i></button>' +
        '</div>' +
      '</div>'
    );
  }).join("");

  $("#listaUsuarios").html(html);
}

function bombaCargarUsuarios() {
  $.ajax({
    url: bombaAjaxUrl,
    method: "POST",
    dataType: "json",
    data: { accion: "usuarios.listar" },
    success: function (response) {
      bombaRenderUsuarios((response.data || {}).usuarios || []);
    },
    error: function (xhr) {
      $("#usuariosFeedback").removeClass("oculto").text(bombaExtraerMensaje(xhr, "No se pudieron cargar los usuarios."));
    }
  });
}

function bombaAbrirModalUsuario(usuario) {
  $("#modalUsuarioFeedback").addClass("oculto");

  if (usuario) {
    $("#modalUsuarioTitulo").html('<i class="fas fa-user-edit"></i> Editar usuario');
    $("#usuarioId").val(usuario.id);
    $("#usuarioNombre").val(usuario.nombre);
    $("#usuarioUsuario").val(usuario.usuario);
    $("#usuarioPassword").val("");
    $("#usuarioPasswordLabel").text("Nueva contraseña (dejar vacio para no cambiarla)");
    $("#usuarioRol").val(usuario.rol);
    $("#usuarioActivo").prop("checked", Number(usuario.activo) === 1);
  } else {
    $("#modalUsuarioTitulo").html('<i class="fas fa-user-plus"></i> Nuevo usuario');
    $("#usuarioId").val("");
    $("#usuarioNombre").val("");
    $("#usuarioUsuario").val("");
    $("#usuarioPassword").val("");
    $("#usuarioPasswordLabel").text("Contraseña");
    $("#usuarioRol").val("operador");
    $("#usuarioActivo").prop("checked", true);
  }

  $("#modalUsuario").addClass("abierto");
}

$(function () {
  bombaCargarUsuarios();

  $("#btnNuevoUsuario").on("click", function () {
    bombaAbrirModalUsuario(null);
  });

  $(document).on("click", ".btn-editar-usuario", function () {
    var id = $(this).data("id");
    var usuario = bombaUsuariosCache.find(function (u) { return Number(u.id) === Number(id); });
    if (usuario) {
      bombaAbrirModalUsuario(usuario);
    }
  });

  $("#btnCancelarUsuario").on("click", function () {
    $("#modalUsuario").removeClass("abierto");
  });

  $(document).on("click", ".btn-baja-usuario", function () {
    var id = $(this).data("id");
    if (!confirm("¿Dar de baja a este usuario? Ya no podra iniciar sesion.")) {
      return;
    }

    $.ajax({
      url: bombaAjaxUrl,
      method: "POST",
      dataType: "json",
      data: { accion: "usuarios.baja", id: id },
      success: function () {
        bombaCargarUsuarios();
      },
      error: function (xhr) {
        $("#usuariosFeedback").removeClass("oculto").text(bombaExtraerMensaje(xhr, "No se pudo dar de baja al usuario."));
      }
    });
  });

  $("#btnGuardarUsuario").on("click", function () {
    var id = $("#usuarioId").val();
    var $feedback = $("#modalUsuarioFeedback");
    var datosBase = {
      nombre: $("#usuarioNombre").val(),
      usuario: $("#usuarioUsuario").val(),
      rol: $("#usuarioRol").val(),
      activo: $("#usuarioActivo").is(":checked") ? 1 : 0
    };
    var password = $("#usuarioPassword").val();

    var accion = id ? "usuarios.actualizar" : "usuarios.guardar";
    var payload = Object.assign({ accion: accion }, datosBase);

    if (id) {
      payload.id = id;
    } else {
      payload.password = password;
    }

    $.ajax({
      url: bombaAjaxUrl,
      method: "POST",
      dataType: "json",
      data: payload,
      beforeSend: function () {
        $feedback.addClass("oculto");
      },
      success: function () {
        if (id && password) {
          $.ajax({
            url: bombaAjaxUrl,
            method: "POST",
            dataType: "json",
            data: { accion: "usuarios.restablecerPassword", id: id, password: password },
            complete: function () {
              $("#modalUsuario").removeClass("abierto");
              bombaCargarUsuarios();
            }
          });
          return;
        }

        $("#modalUsuario").removeClass("abierto");
        bombaCargarUsuarios();
      },
      error: function (xhr) {
        $feedback.removeClass("oculto").text(bombaExtraerMensaje(xhr, "No se pudo guardar el usuario."));
      }
    });
  });
});
