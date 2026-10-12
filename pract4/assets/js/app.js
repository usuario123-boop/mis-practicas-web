/* ===========================================================================
   UNACH · Sistema de Gestión de Documentos
   Lógica de interfaz con jQuery.
   =========================================================================== */

$(function () {

    /* -------------------------------------------------------------------
       1. Digitalización: cámara y selección de archivo
       Los dos botones apuntan a inputs distintos. El de cámara usa el
       atributo capture para abrir directamente la cámara en el móvil.
       ------------------------------------------------------------------- */
    $('#btnCamara').on('click', function () { $('#entradaCamara').trigger('click'); });
    $('#btnArchivo').on('click', function () { $('#entradaArchivo').trigger('click'); });

    // Permite activar los botones con el teclado (accesibilidad).
    $('.btn-upload-option').on('keydown', function (ev) {
        if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); $(this).trigger('click'); }
    });

    var MAX_BYTES = 10 * 1024 * 1024;   // debe coincidir con MAX_PDF_BYTES de config.php

    /** Muestra el archivo elegido y lo valida antes de enviarlo. */
    function mostrarArchivo(archivo, origen) {
        if (!archivo) return;

        if (archivo.size > MAX_BYTES) {
            alert('El archivo pesa ' + (archivo.size / 1048576).toFixed(2) +
                  ' MB y el límite es de 10 MB. Selecciona uno más ligero.');
            limpiarArchivo();
            return;
        }

        var esPdf = /\.pdf$/i.test(archivo.name) || archivo.type === 'application/pdf';

        $('#nombreArchivo').text(archivo.name);
        $('#tamanoArchivo').text((archivo.size / 1048576).toFixed(2) + ' MB · ' + origen);
        $('#iconoArchivo')
            .attr('class', esPdf ? 'bi bi-file-earmark-pdf-fill text-danger fs-3'
                                 : 'bi bi-file-earmark-image-fill text-primary fs-3');
        $('#cajaPrevia').removeClass('d-none');
        $('#avisoArchivo').addClass('d-none');
    }

    /** Vacía los dos inputs y oculta la vista previa. */
    function limpiarArchivo() {
        $('#entradaArchivo, #entradaCamara').val('');
        $('#cajaPrevia').addClass('d-none');
    }

    $('#entradaArchivo').on('change', function () {
        $('#entradaCamara').val('');
        mostrarArchivo(this.files[0], 'archivo seleccionado');
    });

    $('#entradaCamara').on('change', function () {
        $('#entradaArchivo').val('');
        mostrarArchivo(this.files[0], 'foto capturada');
    });

    $('#btnQuitarArchivo').on('click', limpiarArchivo);

    /* -------------------------------------------------------------------
       2. Validación del formulario antes de enviarlo
       ------------------------------------------------------------------- */
    $('#formDocumento').on('submit', function (ev) {
        var requiereArchivo = $(this).data('requiere-archivo') === true ||
                              $(this).data('requiere-archivo') === 'true';
        var hayArchivo = ($('#entradaArchivo')[0] && $('#entradaArchivo')[0].files.length) ||
                         ($('#entradaCamara')[0] && $('#entradaCamara')[0].files.length);

        if (requiereArchivo && !hayArchivo) {
            ev.preventDefault();
            $('#avisoArchivo').removeClass('d-none');
            $('html, body').animate({ scrollTop: $('#avisoArchivo').offset().top - 120 }, 250);
            return;
        }

        // Evita envíos duplicados mientras sube el PDF.
        var $boton = $(this).find('button[type="submit"]');
        $boton.prop('disabled', true).html(
            '<span class="spinner-border spinner-border-sm me-2"></span>Guardando…'
        );
    });

    /* -------------------------------------------------------------------
       3. Visor de PDF dentro de la misma página (ventana modal)
       El iframe se carga al abrir y se descarga al cerrar para liberar
       memoria y detener la descarga si el usuario cierra antes de tiempo.
       ------------------------------------------------------------------- */
    var $modalVisor = $('#modalVisor');

    $(document).on('click', '.ver-pdf', function (ev) {
        ev.preventDefault();
        var $btn = $(this);
        $('#visorTitulo').text('Oficio ' + $btn.data('oficio'));
        $('#visorSubtitulo').text($btn.data('asunto') || '');
        $('#visorDescargar').attr('href', 'descargar.php?id=' + $btn.data('id'));
        $('#visorNuevaPestana').attr('href', 'ver_pdf.php?id=' + $btn.data('id'));
        $('#visorMarco').attr('src', 'ver_pdf.php?id=' + $btn.data('id'));
        $modalVisor.modal('show');
    });

    $modalVisor.on('hidden.bs.modal', function () {
        $('#visorMarco').attr('src', 'about:blank');
    });

    /* -------------------------------------------------------------------
       4. Cambio rápido de estatus sin recargar la página (AJAX)
       ------------------------------------------------------------------- */
    $(document).on('click', '.cambiar-estatus', function (ev) {
        ev.preventDefault();
        var $opcion  = $(this);
        var id       = $opcion.data('id');
        var estatus  = $opcion.data('estatus');
        var $badge   = $('#estatus-' + id);
        var textoOrig = $badge.text();

        $badge.text('…');

        $.post('acciones/estatus.php', { id: id, estatus: estatus, csrf: window.CSRF_TOKEN }, null, 'json')
            .done(function (res) {
                if (res.ok) {
                    $badge
                        .removeClass('status-recibido status-proceso status-atendido')
                        .addClass(res.clase)
                        .text(res.estatus);
                    notificar('Estatus actualizado a “' + res.estatus + '”.', 'success');
                } else {
                    $badge.text(textoOrig);
                    notificar(res.mensaje || 'No se pudo actualizar el estatus.', 'danger');
                }
            })
            .fail(function () {
                $badge.text(textoOrig);
                notificar('Error de comunicación con el servidor.', 'danger');
            });
    });

    /* -------------------------------------------------------------------
       5. Confirmación antes de eliminar
       ------------------------------------------------------------------- */
    $(document).on('click', '.eliminar-doc', function (ev) {
        ev.preventDefault();
        var $btn = $(this);
        $('#eliminarOficio').text($btn.data('oficio'));
        $('#formEliminar input[name="id"]').val($btn.data('id'));
        $('#modalEliminar').modal('show');
    });

    /* -------------------------------------------------------------------
       6. Buscador en vivo: filtra las filas ya cargadas mientras se escribe
       ------------------------------------------------------------------- */
    $('#busquedaRapida').on('input', function () {
        var termino = $(this).val().toLowerCase().trim();
        var visibles = 0;

        $('.fila-doc, .doc-card').each(function () {
            var coincide = $(this).text().toLowerCase().indexOf(termino) !== -1;
            $(this).toggle(coincide);
            if (coincide) visibles++;
        });

        $('#sinResultadosFiltro').toggleClass('d-none', visibles > 0 || termino === '');
    });

    /* -------------------------------------------------------------------
       7. Los selectores de filtro envían el formulario automáticamente
       ------------------------------------------------------------------- */
    $('.filtro-auto').on('change', function () { $(this).closest('form').trigger('submit'); });

    /* -------------------------------------------------------------------
       8. Avisos emergentes (toasts)
       ------------------------------------------------------------------- */
    function notificar(mensaje, tipo) {
        var $toast = $(
            '<div class="toast align-items-center text-bg-' + (tipo || 'primary') + ' border-0" role="alert">' +
              '<div class="d-flex">' +
                '<div class="toast-body">' + mensaje + '</div>' +
                '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>' +
              '</div>' +
            '</div>'
        );
        $('#contenedorToasts').append($toast);
        new bootstrap.Toast($toast[0], { delay: 3000 }).show();
        $toast.on('hidden.bs.toast', function () { $(this).remove(); });
    }

    window.notificar = notificar;

    // Cierra solos los mensajes de alerta tras unos segundos.
    setTimeout(function () {
        $('.alert-dismissible').each(function () {
            bootstrap.Alert.getOrCreateInstance(this).close();
        });
    }, 6000);
});
