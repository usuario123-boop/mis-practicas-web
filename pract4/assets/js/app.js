$(function () {

    // Los menús ⋮ viven dentro de .table-responsive, que al tener overflow recorta
    // lo que se sale. Con la estrategia fixed de Popper se posicionan respecto a la
    // ventana y dejan de cortarse.
    $('[data-bs-toggle="dropdown"]').each(function () {
        bootstrap.Dropdown.getOrCreateInstance(this, {
            popperConfig: function (config) {
                config.strategy = 'fixed';
                return config;
            }
        });
    });

    // El botón de cámara abre la captura dentro de la página; el otro, el
    // selector de archivos del sistema.
    $('#btnCamara').on('click', abrirCamara);
    $('#btnArchivo').on('click', function () { $('#entradaArchivo').trigger('click'); });

    // Permite activar los botones con el teclado (accesibilidad).
    $('.btn-upload-option').on('keydown', function (ev) {
        if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); $(this).trigger('click'); }
    });

    // El tope lo fija el servidor: cambia entre XAMPP y Vercel.
    var MAX_BYTES = parseInt($('#formDocumento').data('max-bytes'), 10) || 10 * 1024 * 1024;

    function mostrarArchivo(archivo, origen) {
        if (!archivo) return;

        if (archivo.size > MAX_BYTES) {
            alert('El archivo pesa ' + (archivo.size / 1048576).toFixed(2) +
                  ' MB y el límite es de ' + (MAX_BYTES / 1048576).toFixed(0) +
                  ' MB. Selecciona uno más ligero.');
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

    function limpiarArchivo() {
        $('#entradaArchivo, #entradaCamara').val('');
        $('#cajaPrevia').addClass('d-none');
    }

    // ---- Fotografías ------------------------------------------------------
    //
    // Una foto de teléfono ronda los 12 Mpx y varios MB: arriba del tope de
    // Vercel (4 MB) y un desperdicio para digitalizar una hoja. Antes de subirla
    // se redibuja en un canvas a 1600 px de lado mayor y se recomprime a JPEG,
    // con lo que queda en unos cientos de kilobytes. De paso resuelve el HEIC de
    // iPhone, que el servidor no acepta: el canvas siempre devuelve JPEG.

    var LADO_MAXIMO  = 1600;
    var CALIDAD_JPEG = 0.82;

    function dimensionesReducidas(ancho, alto) {
        var escala = Math.min(1, LADO_MAXIMO / Math.max(ancho, alto));
        return [Math.round(ancho * escala), Math.round(alto * escala)];
    }

    // Deja el JPEG dentro del input de cámara, que es el campo que el servidor
    // trata como fotografía. Asignar un FileList requiere DataTransfer.
    function usarFoto(blob, origen) {
        if (!window.DataTransfer || !window.File) return false;

        var archivo = new File([blob], 'documento_' + Date.now() + '.jpg',
                               { type: 'image/jpeg', lastModified: Date.now() });
        var deposito = new DataTransfer();
        deposito.items.add(archivo);

        $('#entradaArchivo').val('');
        $('#entradaCamara')[0].files = deposito.files;
        mostrarArchivo(archivo, origen);
        return true;
    }

    // Reduce un archivo de imagen ya elegido (galería o app de cámara).
    function reducirImagen(archivo, origen) {
        var url = URL.createObjectURL(archivo);
        var imagen = new Image();

        imagen.onload = function () {
            var medidas = dimensionesReducidas(imagen.width, imagen.height);
            var lienzo  = document.createElement('canvas');
            lienzo.width  = medidas[0];
            lienzo.height = medidas[1];
            lienzo.getContext('2d').drawImage(imagen, 0, 0, medidas[0], medidas[1]);
            URL.revokeObjectURL(url);

            lienzo.toBlob(function (blob) {
                // Si el navegador no pudo recomprimir, se sube tal cual y manda
                // el aviso de tamaño de mostrarArchivo.
                if (!blob || !usarFoto(blob, origen)) mostrarArchivo(archivo, origen);
            }, 'image/jpeg', CALIDAD_JPEG);
        };

        imagen.onerror = function () {
            URL.revokeObjectURL(url);
            mostrarArchivo(archivo, origen);
        };

        imagen.src = url;
    }

    function esImagen(archivo) {
        return /^image\//.test(archivo.type) || /\.(jpe?g|png|heic|heif|webp)$/i.test(archivo.name);
    }

    $('#entradaArchivo').on('change', function () {
        var archivo = this.files[0];
        if (!archivo) return;

        $('#entradaCamara').val('');
        if (esImagen(archivo)) reducirImagen(archivo, 'foto seleccionada');
        else                   mostrarArchivo(archivo, 'archivo seleccionado');
    });

    $('#entradaCamara').on('change', function () {
        var archivo = this.files[0];
        if (!archivo) return;

        $('#entradaArchivo').val('');
        if (esImagen(archivo)) reducirImagen(archivo, 'foto capturada');
        else                   mostrarArchivo(archivo, 'foto capturada');
    });

    // ---- Cámara dentro de la página ---------------------------------------
    //
    // capture="environment" delega la foto a la app de cámara del teléfono, y en
    // equipos con poca memoria libre esa entrega falla con "memoria insuficiente
    // para cumplir con la función anterior". Con getUserMedia la resolución la
    // pide la página, así que ese paso desaparece. Requiere HTTPS: en Vercel ya
    // lo hay y localhost cuenta como origen seguro.

    var $modalCamara = $('#modalCamara');
    var flujoCamara  = null;

    function abrirCamara() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia || !window.DataTransfer) {
            $('#entradaCamara').trigger('click');
            return;
        }

        $('#camaraAviso').addClass('d-none').text('');
        $modalCamara.modal('show');

        navigator.mediaDevices.getUserMedia({
            audio: false,
            video: {
                facingMode: { ideal: 'environment' },
                width:  { ideal: 1920 },
                height: { ideal: 1440 }
            }
        }).then(function (flujo) {
            flujoCamara = flujo;
            $('#camaraVideo')[0].srcObject = flujo;
        }).catch(function () {
            $('#camaraAviso').removeClass('d-none').text(
                'El navegador no pudo abrir la cámara (permiso denegado o conexión sin HTTPS). ' +
                'Usa “Galería o app de cámara”.'
            );
        });
    }

    function cerrarCamara() {
        if (flujoCamara) {
            flujoCamara.getTracks().forEach(function (pista) { pista.stop(); });
            flujoCamara = null;
        }
        var video = $('#camaraVideo')[0];
        if (video) video.srcObject = null;
    }

    $('#btnCamaraDisparar').on('click', function () {
        var video = $('#camaraVideo')[0];
        if (!video || !video.videoWidth) {
            $('#camaraAviso').removeClass('d-none').text('La cámara aún no está lista, espera un momento.');
            return;
        }

        var medidas = dimensionesReducidas(video.videoWidth, video.videoHeight);
        var lienzo  = document.createElement('canvas');
        lienzo.width  = medidas[0];
        lienzo.height = medidas[1];
        lienzo.getContext('2d').drawImage(video, 0, 0, medidas[0], medidas[1]);

        var $boton = $(this).prop('disabled', true);

        lienzo.toBlob(function (blob) {
            $boton.prop('disabled', false);

            if (!blob || !usarFoto(blob, 'foto capturada')) {
                $('#camaraAviso').removeClass('d-none').text(
                    'No se pudo guardar la captura. Usa “Galería o app de cámara”.'
                );
                return;
            }
            $modalCamara.modal('hide');
        }, 'image/jpeg', CALIDAD_JPEG);
    });

    // Salida de emergencia: el selector del sistema, con la foto ya reducida
    // por el manejador de #entradaCamara.
    $('#btnCamaraSistema').on('click', function () {
        $modalCamara.modal('hide');
        $('#entradaCamara').trigger('click');
    });

    // Apagar la cámara al cerrar libera el led y deja de consumir batería.
    $modalCamara.on('hidden.bs.modal', cerrarCamara);

    $('#btnQuitarArchivo').on('click', limpiarArchivo);

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

    // El src del iframe se asigna al abrir y se limpia al cerrar, para no dejar
    // todos los PDF de la sesión cargados en memoria.
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

    // Cambio de estatus sin recargar la página.
    $(document).on('click', '.cambiar-estatus', function (ev) {
        ev.preventDefault();
        var $opcion  = $(this);
        var id       = $opcion.data('id');
        var estatus  = $opcion.data('estatus');
        var $badge   = $('#estatus-' + id + ', #estatus-m-' + id);
        var textoOrig = $badge.first().text();

        $badge.text('…');

        $.post('acciones/estatus.php', { id: id, estatus: estatus, csrf: window.CSRF_TOKEN }, null, 'json')
            .done(function (res) {
                if (res.ok) {
                    $badge
                        .removeClass('status-recibido status-proceso status-atendido')
                        .addClass(res.clase)
                        .text(res.estatus);
                    $opcion.closest('.dropdown-menu').find('.cambiar-estatus')
                        .removeClass('active').filter('[data-estatus="' + res.estatus + '"]').addClass('active');
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

    $(document).on('click', '.eliminar-doc', function (ev) {
        ev.preventDefault();
        var $btn = $(this);
        $('#eliminarOficio').text($btn.data('oficio'));
        $('#formEliminar input[name="id"]').val($btn.data('id'));
        $('#modalEliminar').modal('show');
    });

    // Filtra las filas ya cargadas, sin volver al servidor.
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

    $('.filtro-auto').on('change', function () { $(this).closest('form').trigger('submit'); });

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
