const bombaAjaxUrl = "ajax/peticiones.php";

function bombaExtraerMensaje(xhr, mensajeDefault) {
  if (xhr && xhr.responseJSON) {
    var errores = xhr.responseJSON.errors;
    if (errores && Object.keys(errores).length) {
      return Object.values(errores).join(" ");
    }
    if (xhr.responseJSON.message) {
      return xhr.responseJSON.message;
    }
  }
  return mensajeDefault;
}

$(function () {
  $("#btnSalir").on("click", function () {
    $.ajax({
      url: bombaAjaxUrl,
      method: "POST",
      dataType: "json",
      data: { accion: "auth.logout" },
      complete: function () {
        window.location.href = "login.php";
      }
    });
  });
});
