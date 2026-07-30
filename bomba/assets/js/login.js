const bombaAjaxUrl = "ajax/peticiones.php";

$(function () {
  $("#formLogin").on("submit", function (event) {
    event.preventDefault();

    var $feedback = $("#loginFeedback");
    var $btn = $("#btnEntrar");
    var usuario = $("#loginUsuario").val();
    var password = $("#loginPassword").val();

    if (!usuario || !password) {
      $feedback.removeClass("oculto").text("Captura usuario y contraseña.");
      return;
    }

    $.ajax({
      url: bombaAjaxUrl,
      method: "POST",
      dataType: "json",
      data: { accion: "auth.login", usuario: usuario, password: password },
      beforeSend: function () {
        $feedback.addClass("oculto");
        $btn.prop("disabled", true).html('<i class="fas fa-spinner fa-spin"></i> Entrando...');
      },
      success: function () {
        var next = $("#loginNext").val() || "index.php";
        window.location.href = next;
      },
      error: function (xhr) {
        var mensaje = (xhr.responseJSON && xhr.responseJSON.message) || "No se pudo iniciar sesion.";
        $feedback.removeClass("oculto").text(mensaje);
      },
      complete: function () {
        $btn.prop("disabled", false).html('<i class="fas fa-sign-in-alt"></i> Entrar');
      }
    });
  });
});
