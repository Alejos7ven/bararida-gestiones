/**
 * Lógica del Módulo de Recorridos y Zonas - recorridos.js
 * Parque Zoológico y Botánico Bararida
 */

$(document).ready(function() {
    // =========================================================================
    // DECLARACIÓN DE VARIABLES DE ESTADO GLOBAL
    // =========================================================================
    let listaZonas = [];          // Almacena todas las zonas cargadas de la BD
    let listaRecorridos = [];     // Almacena todos los recorridos con sus paradas
    
    // Variables de control de administración
    let modoAgregarZona = false;
    let modoCrearRecorrido = false;
    let recorridoZonasSeleccionadas = []; // IDs de zonas seleccionadas para el recorrido actual
    
    // Referencias principales del DOM
    const $mapWrapper = $('#mapWrapper');
    const $mapImage = $('#mapaParque');
    const $pinsContainer = $('#mapPinsContainer');
    const $svgOverlay = $('#mapSvgOverlay');
    const $alertFeedback = $('#alertFeedbackGlobal');

    // =========================================================================
    // CARGAR DATOS AL INICIAR
    // =========================================================================
    inicializarModulo();

    function inicializarModulo() {
        cargarZonas(function() {
            cargarRecorridos();
        });
        
        // Registrar eventos del mapa y botones
        registrarEventos();
    }

    // =========================================================================
    // OPERACIONES AJAX - CAPA DE DATOS
    // =========================================================================

    /**
     * Recupera todas las zonas desde la base de datos
     */
    function cargarZonas(callback) {
        $.ajax({
            url: 'controllers/RecorridoController.php?action=listar_zonas',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    listaZonas = response.data;
                    renderizarPinesZonas(listaZonas);
                    actualizarListasAdminZonas();
                    if (callback) callback();
                } else {
                    showGlobalAlert('No se pudieron cargar las zonas: ' + response.message, 'danger');
                }
            },
            error: function() {
                showGlobalAlert('Error de red al cargar las zonas del mapa.', 'danger');
            }
        });
    }

    /**
     * Recupera todos los recorridos y sus paradas desde la base de datos
     */
    function cargarRecorridos() {
        $.ajax({
            url: 'controllers/RecorridoController.php?action=listar_recorridos',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    listaRecorridos = response.data;
                    actualizarSelectorRecorridos();
                    actualizarListasAdminRecorridos();
                } else {
                    showGlobalAlert('No se pudieron cargar los recorridos: ' + response.message, 'danger');
                }
            },
            error: function() {
                showGlobalAlert('Error de red al cargar los recorridos.', 'danger');
            }
        });
    }

    // =========================================================================
    // RENDERIZADO VISUAL - MAPA Y PINES
    // =========================================================================

    /**
     * Dibuja los pines de las zonas sobre el mapa utilizando coordenadas porcentuales
     */
    function renderizarPinesZonas(zonas, paradasActivas = []) {
        // Limpiamos los pines actuales (sin borrar los temporales de creación)
        $pinsContainer.find('.map-pin:not(.temp-pin)').remove();
        $pinsContainer.find('.pin-label').remove();

        // Determinar si estamos en la vista de administración
        const esVistaAdmin = ($('#adminListaZonas').length > 0);

        zonas.forEach(function(zona) {
            const coords = zona.coordenadas.split(',');
            if (coords.length !== 2) return;

            const x = parseFloat(coords[0]);
            const y = parseFloat(coords[1]);

            // Determinar si la zona actual es parte de una parada activa (recorrido seleccionado)
            let indexParada = -1;
            if (paradasActivas.length > 0) {
                indexParada = paradasActivas.findIndex(p => parseInt(p.zona_id) === parseInt(zona.id));
            }

            let pinContenido = '';
            let claseActiva = '';

            // Si es parte de una ruta seleccionada, colocamos su número de secuencia
            if (indexParada !== -1) {
                pinContenido = indexParada + 1;
                claseActiva = 'active';
            }

            const pinHtml = `
                <div class="map-pin ${claseActiva}" style="left: ${x}%; top: ${y}%;" 
                     data-id="${zona.id}" data-nombre="${zona.nombre}" data-coords="${zona.coordenadas}">
                    ${pinContenido}
                </div>
                <div class="pin-label" style="left: ${x}%; top: ${y}%;">
                    ${zona.nombre}
                </div>
            `;
            $pinsContainer.append(pinHtml);
        });
    }

    /**
     * Dibuja una línea de ruta entre los pines secuencialmente sobre el overlay SVG
     */
    function dibujarRutaSVG(paradas) {
        // Limpiar trazados anteriores
        $svgOverlay.empty();

        if (paradas.length < 2) return;

        let pathCoordinates = [];

        paradas.forEach(function(p) {
            const coords = p.coordenadas.split(',');
            if (coords.length === 2) {
                pathCoordinates.push({
                    x: parseFloat(coords[0]),
                    y: parseFloat(coords[1])
                });
            }
        });

        if (pathCoordinates.length < 2) return;

        // Construir los trazos en SVG utilizando las coordenadas porcentuales exactas (coincide con viewBox 0 0 100 100)
        let dPath = `M ${pathCoordinates[0].x} ${pathCoordinates[0].y}`;
        for (let i = 1; i < pathCoordinates.length; i++) {
            dPath += ` L ${pathCoordinates[i].x} ${pathCoordinates[i].y}`;
        }

        // Trazar línea de sombra blanca (fondo) para mayor contraste en mapas oscuros/coloridos
        const bgPathHtml = document.createElementNS("http://www.w3.org/2000/svg", "path");
        bgPathHtml.setAttribute("d", dPath);
        bgPathHtml.setAttribute("fill", "none");
        bgPathHtml.setAttribute("class", "route-line-bg");
        $svgOverlay[0].appendChild(bgPathHtml);

        // Trazar línea de recorrido principal animada y verde
        const pathHtml = document.createElementNS("http://www.w3.org/2000/svg", "path");
        pathHtml.setAttribute("d", dPath);
        pathHtml.setAttribute("fill", "none");
        pathHtml.setAttribute("class", "route-line");
        $svgOverlay[0].appendChild(pathHtml);
    }

    // =========================================================================
    // REGISTRO DE EVENTOS E INTERACCIONES
    // =========================================================================
    function registrarEventos() {
        
        // ---------------------------------------------------------------------
        // EVENTO: CLIC EN EL MAPA PARA AGREGAR ZONAS (MODO ADMIN)
        // ---------------------------------------------------------------------
        $mapWrapper.on('click', function(e) {
            // Verificar que hagamos clic en el mapa y que estemos en Modo Agregar
            if (!modoAgregarZona) return;

            // Prevenir clics si el objetivo es un pin existente
            if ($(e.target).hasClass('map-pin')) return;

            const rect = $mapImage[0].getBoundingClientRect();
            
            // Calcular las coordenadas exactas basadas en el porcentaje de la imagen
            const x = ((e.clientX - rect.left) / rect.width) * 100;
            const y = ((e.clientY - rect.top) / rect.height) * 100;

            const coordsString = `${x.toFixed(2)},${y.toFixed(2)}`;

            // Dibujar pin temporal de color rojo
            renderizarPinTemporal(x, y);

            // Cargar coordenadas en el modal y abrirlo
            $('#nuevaZonaCoords').val(coordsString);
            $('#modalCrearZona').modal('show');
        });

        // Al cerrar o cancelar el modal de zona, borramos el pin temporal rojo
        $('#modalCrearZona').on('hidden.bs.modal', function () {
            $pinsContainer.find('.temp-pin').remove();
        });

        // Guardar la nueva zona vía AJAX
        $('#formCrearZona').on('submit', function(e) {
            e.preventDefault();
            const $form = $(this);
            const $btn = $('#btnGuardarZona');

            $btn.prop('disabled', true).text('Guardando...');

            $.ajax({
                url: 'controllers/RecorridoController.php?action=crear_zona',
                method: 'POST',
                data: $form.serialize(),
                dataType: 'json',
                success: function(response) {
                    $btn.prop('disabled', false).text('Registrar Zona');
                    if (response.status === 'success') {
                        $('#modalCrearZona').modal('hide');
                        $form[0].reset();
                        showGlobalAlert(response.message, 'success');
                        
                        // Recargar zonas del sistema y redibujar
                        cargarZonas();
                    } else {
                        alert(response.message);
                    }
                },
                error: function(xhr) {
                    $btn.prop('disabled', false).text('Registrar Zona');
                    let errorMsg = 'Error al registrar la zona en el servidor.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    }
                    alert(errorMsg);
                }
            });
        });

        // ---------------------------------------------------------------------
        // EVENTO: SELECCIÓN DE PINES DURANTE LA CREACIÓN DE RECORRIDOS
        // ---------------------------------------------------------------------
        $(document).on('click', '.map-pin:not(.temp-pin)', function() {
            const $pin = $(this);
            const zonaId = parseInt($pin.data('id'));
            
            // Si estamos en modo de visualización normal (no creación), resaltamos y mostramos nombre
            if (!modoCrearRecorrido) {
                $('.map-pin').removeClass('active');
                $pin.addClass('active');
                return;
            }

            // Si estamos en Modo Crear Recorrido, gestionamos la selección secuencial
            const index = recorridoZonasSeleccionadas.indexOf(zonaId);

            if (index === -1) {
                // Si la zona no estaba seleccionada, la añadimos al recorrido
                recorridoZonasSeleccionadas.push(zonaId);
                $pin.addClass('active');
                $pin.text(recorridoZonasSeleccionadas.length);
            } else {
                // Si ya estaba seleccionada, impedimos redundancias o removemos (último elemento)
                if (index === recorridoZonasSeleccionadas.length - 1) {
                    recorridoZonasSeleccionadas.pop();
                    $pin.removeClass('active');
                    $pin.text('');
                } else {
                    showGlobalAlert('Para deseleccionar una zona de la ruta, debe hacerlo en orden inverso (deseleccione la última añadida primero).', 'warning');
                }
            }

            // Trazar el camino dinámico de las zonas seleccionadas en tiempo real
            actualizarRutaSVGEnTiempoReal();
            actualizarTimelineCreacionRecorrido();
        });

        // Guardar el nuevo recorrido creado vía AJAX
        $('#formCrearRecorrido').on('submit', function(e) {
            e.preventDefault();
            const $form = $(this);
            const $btn = $('#btnGuardarRecorrido');

            if (recorridoZonasSeleccionadas.length < 1) {
                showGlobalAlert('Debe hacer clic y seleccionar al menos una zona sobre el mapa para conformar una ruta.', 'danger');
                return;
            }

            $btn.prop('disabled', true).text('Guardando Recorrido...');

            // Formatear los datos incluyendo el listado ordenado de IDs de zonas
            let postData = $form.serialize();
            recorridoZonasSeleccionadas.forEach(function(zId) {
                postData += `&zonas[]=${zId}`;
            });

            $.ajax({
                url: 'controllers/RecorridoController.php?action=crear_recorrido',
                method: 'POST',
                data: postData,
                dataType: 'json',
                success: function(response) {
                    $btn.prop('disabled', false).text('Guardar Recorrido');
                    if (response.status === 'success') {
                        showGlobalAlert(response.message, 'success');
                        
                        // Desactivar modo de creación y vaciar variables
                        desactivarModoCrearRecorrido();
                        
                        // Recargar todo el listado
                        cargarRecorridos();
                    } else {
                        showGlobalAlert(response.message, 'danger');
                    }
                },
                error: function(xhr) {
                    $btn.prop('disabled', false).text('Guardar Recorrido');
                    let errorMsg = 'Error al registrar el recorrido en el servidor.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    }
                    showGlobalAlert(errorMsg, 'danger');
                }
            });
        });

        // ---------------------------------------------------------------------
        // EVENTOS DE CONTROLADORES DE MODOS (BOTONES)
        // ---------------------------------------------------------------------
        
        // Activar / Desactivar Modo Agregar Zona
        $('#btnModoZona').on('click', function() {
            if (modoAgregarZona) {
                desactivarModoAgregarZona();
            } else {
                activarModoAgregarZona();
            }
        });

        $('#btnCancelarModoZona').on('click', desactivarModoAgregarZona);

        // Activar / Desactivar Modo Crear Recorrido
        $('#btnModoRecorrido').on('click', function() {
            if (modoCrearRecorrido) {
                desactivarModoCrearRecorrido();
            } else {
                activarModoCrearRecorrido();
            }
        });

        $('#btnCancelarCrearRecorrido').on('click', desactivarModoCrearRecorrido);

        // ---------------------------------------------------------------------
        // EVENTO: SELECCIÓN DE RECORRIDOS (VISUALIZADOR DEL DASHBOARD)
        // ---------------------------------------------------------------------
        $('#selectRecorridoDashboard').on('change', function() {
            const recorridoId = $(this).val();
            
            if (recorridoId === "") {
                // Si no hay recorrido seleccionado, mostramos todas las zonas normalmente sin trazados
                renderizarPinesZonas(listaZonas);
                $svgOverlay.empty();
                $('#dashboardTimelineWrapper').hide();
                return;
            }

            const rec = listaRecorridos.find(r => parseInt(r.id) === parseInt(recorridoId));
            if (rec) {
                // 1. Dibujar pines resaltados con números de secuencia
                renderizarPinesZonas(listaZonas, rec.paradas);
                
                // 2. Trazar la ruta animada con SVG
                dibujarRutaSVG(rec.paradas);

                // 3. Renderizar el Timeline en el Frontend
                renderizarTimelineDashboard(rec);
            }
        });

        // ---------------------------------------------------------------------
        // EVENTOS: ELIMINAR ZONAS Y RECORRIDOS (MODALES PREMIUM ESTILIZADOS)
        // ---------------------------------------------------------------------
        
        // Abrir Confirmación de Eliminación de Zona (desde lista o desde pin)
        $(document).on('click', '.btn-eliminar-zona-list, .pin-delete-btn', function(e) {
            e.stopPropagation(); // Evitar que el clic en la X del pin seleccione el pin para el recorrido
            
            const id = $(this).data('id');
            const nombre = $(this).data('nombre');
            
            $('#delete_zona_id_modal').val(id);
            $('#deleteZonaNombreSpan').text(nombre);
            $('#modalConfirmarEliminarZona').modal('show');
        });

        // AJAX para confirmar la eliminación de la Zona
        $('#btnConfirmarEliminarZonaModal').on('click', function() {
            const id = $('#delete_zona_id_modal').val();
            const $btn = $(this);
            
            $btn.prop('disabled', true).text('Eliminando...');

            $.ajax({
                url: 'controllers/RecorridoController.php?action=eliminar_zona',
                method: 'POST',
                data: { id: id },
                dataType: 'json',
                success: function(response) {
                    $btn.prop('disabled', false).text('Eliminar Zona');
                    $('#modalConfirmarEliminarZona').modal('hide');
                    
                    if (response.status === 'success') {
                        showGlobalAlert(response.message, 'success');
                        cargarZonas(function() {
                            cargarRecorridos();
                        });
                    } else {
                        showGlobalAlert(response.message, 'danger');
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).text('Eliminar Zona');
                    $('#modalConfirmarEliminarZona').modal('hide');
                    showGlobalAlert('Error al intentar eliminar la zona del sistema.', 'danger');
                }
            });
        });

        // Abrir Confirmación de Eliminación de Recorrido
        $(document).on('click', '.btn-eliminar-recorrido-list', function(e) {
            e.stopPropagation();
            
            const id = $(this).data('id');
            const nombre = $(this).data('nombre');
            
            $('#delete_recorrido_id_modal').val(id);
            $('#deleteRecorridoNombreSpan').text(nombre);
            $('#modalConfirmarEliminarRecorrido').modal('show');
        });

        // AJAX para confirmar la eliminación del Recorrido
        $('#btnConfirmarEliminarRecorridoModal').on('click', function() {
            const id = $('#delete_recorrido_id_modal').val();
            const $btn = $(this);
            
            $btn.prop('disabled', true).text('Eliminando...');

            $.ajax({
                url: 'controllers/RecorridoController.php?action=eliminar_recorrido',
                method: 'POST',
                data: { id: id },
                dataType: 'json',
                success: function(response) {
                    $btn.prop('disabled', false).text('Eliminar Recorrido');
                    $('#modalConfirmarEliminarRecorrido').modal('hide');
                    
                    if (response.status === 'success') {
                        showGlobalAlert(response.message, 'success');
                        cargarRecorridos();
                    } else {
                        showGlobalAlert(response.message, 'danger');
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).text('Eliminar Recorrido');
                    $('#modalConfirmarEliminarRecorrido').modal('hide');
                    showGlobalAlert('Error al intentar eliminar el recorrido.', 'danger');
                }
            });
        });
    }

    // =========================================================================
    // FUNCIONES AUXILIARES Y MANEJO DE ESTADOS
    // =========================================================================

    /**
     * Activa el modo de colocación de pines de zonas
     */
    function activarModoAgregarZona() {
        desactivarModoCrearRecorrido();
        modoAgregarZona = true;
        
        $mapWrapper.addClass('admin-active');
        $('#btnModoZona').addClass('active');
        $('#indicadorModoZona').fadeIn(250);
        
        // Resetear visualización de mapa
        renderizarPinesZonas(listaZonas);
        $svgOverlay.empty();
    }

    /**
     * Desactiva el modo de colocación de pines
     */
    function desactivarModoAgregarZona() {
        modoAgregarZona = false;
        $mapWrapper.removeClass('admin-active');
        $('#btnModoZona').removeClass('active');
        $('#indicadorModoZona').fadeOut(200);
        $pinsContainer.find('.temp-pin').remove();
    }

    /**
     * Activa el modo de trazado y creación de recorridos
     */
    function activarModoCrearRecorrido() {
        desactivarModoAgregarZona();
        modoCrearRecorrido = true;
        recorridoZonasSeleccionadas = [];

        $('#formCrearRecorrido')[0].reset();
        $('#btnModoRecorrido').addClass('active');
        $('#panelCrearRecorridoForm').slideDown(250);

        // Limpiar pines y resetear sus contenidos (permitirá interactuar y numerar)
        renderizarPinesZonas(listaZonas);
        $svgOverlay.empty();
        actualizarTimelineCreacionRecorrido();
    }

    /**
     * Desactiva el modo de creación de recorridos
     */
    function desactivarModoCrearRecorrido() {
        modoCrearRecorrido = false;
        recorridoZonasSeleccionadas = [];

        $('#panelCrearRecorridoForm').slideUp(200);
        $('#btnModoRecorrido').removeClass('active');

        // Restaurar estado de los pines y del mapa
        renderizarPinesZonas(listaZonas);
        $svgOverlay.empty();
    }

    /**
     * Renderiza un marcador temporal de color rojo en las coordenadas clickeadas
     */
    function renderizarPinTemporal(x, y) {
        $pinsContainer.find('.temp-pin').remove();
        const pinHtml = `<div class="map-pin temp-pin" style="left: ${x}%; top: ${y}%;">+</div>`;
        $pinsContainer.append(pinHtml);
    }

    /**
     * Actualiza el overlay de SVG de forma dinámica al seleccionar pines en Modo Creación
     */
    function actualizarRutaSVGEnTiempoReal() {
        const paradasMock = [];
        
        recorridoZonasSeleccionadas.forEach(function(zId) {
            const zona = listaZonas.find(z => parseInt(z.id) === parseInt(zId));
            if (zona) {
                paradasMock.push({
                    zona_id: zona.id,
                    coordenadas: zona.coordenadas
                });
            }
        });

        dibujarRutaSVG(paradasMock);
    }

    /**
     * Actualiza el listado visual secuencial en el creador de recorridos
     */
    function actualizarTimelineCreacionRecorrido() {
        const $wrapper = $('#timelineCreacionWrapper');
        $wrapper.empty();

        if (recorridoZonasSeleccionadas.length === 0) {
            $wrapper.append('<p class="text-muted italic text-center fs-0.85">Haz clic sobre los pines del mapa en orden para ir trazando el recorrido...</p>');
            return;
        }

        const timelineHtml = $('<div class="tour-steps-timeline"></div>');

        recorridoZonasSeleccionadas.forEach(function(zId, idx) {
            const zona = listaZonas.find(z => parseInt(z.id) === parseInt(zId));
            if (zona) {
                if (idx > 0) {
                    timelineHtml.append(`
                        <div class="step-connector-arrow">
                            <svg viewBox="0 0 24 24"><path d="M8.59 16.59L13.17 12 8.59 7.41 10 6l6 6-6 6-1.41-1.41z"/></svg>
                        </div>
                    `);
                }
                
                timelineHtml.append(`
                    <div class="timeline-step active">
                        <div class="step-num">${idx + 1}</div>
                        <div class="step-name">${zona.nombre}</div>
                    </div>
                `);
            }
        });

        $wrapper.append(timelineHtml);
    }

    /**
     * Renderiza el timeline interactivo del recorrido seleccionado en el Dashboard general
     */
    function renderizarTimelineDashboard(recorrido) {
        const $container = $('#dashboardTimelineWrapper');
        const $titleSpan = $('#nombreRecorridoActivoSpan');
        const $stepsTimeline = $('#timelineDashboardSteps');

        $titleSpan.text(recorrido.nombre);
        $stepsTimeline.empty();

        recorrido.paradas.forEach(function(p, idx) {
            if (idx > 0) {
                $stepsTimeline.append(`
                    <div class="step-connector-arrow">
                        <svg viewBox="0 0 24 24"><path d="M8.59 16.59L13.17 12 8.59 7.41 10 6l6 6-6 6-1.41-1.41z"/></svg>
                    </div>
                `);
            }

            $stepsTimeline.append(`
                <div class="timeline-step active">
                    <div class="step-num">${idx + 1}</div>
                    <div class="step-name">${p.zona_nombre}</div>
                </div>
            `);
        });

        $container.fadeIn(300);
    }

    // =========================================================================
    // ACTUALIZACIÓN DE DROPDOWNS Y PANELES COLATERALES (ADMIN)
    // =========================================================================

    /**
     * Actualiza el selector dropdown de recorridos del Dashboard de inicio
     */
    function actualizarSelectorRecorridos() {
        const $select = $('#selectRecorridoDashboard');
        if ($select.length === 0) return;

        // Mantener opción vacía
        $select.empty().append('<option value="">-- Seleccione un Recorrido para visualizar ruta --</option>');

        listaRecorridos.forEach(function(rec) {
            $select.append(`<option value="${rec.id}">${rec.nombre} (${rec.paradas.length} paradas)</option>`);
        });
    }

    /**
     * Actualiza la lista de zonas en la pestaña de administración
     */
    function actualizarListasAdminZonas() {
        const $lista = $('#adminListaZonas');
        if ($lista.length === 0) return;

        $lista.empty();

        if (listaZonas.length === 0) {
            $lista.append('<p class="text-muted italic text-center py-3">No hay zonas pineadas en el mapa.</p>');
            return;
        }

        listaZonas.forEach(function(z) {
            $lista.append(`
                <div class="sidebar-list-item">
                    <div>
                        <h6>${z.nombre}</h6>
                        <p>Coords: ${z.coordenadas}</p>
                    </div>
                    <button class="btn-eliminar-zona-list" 
                            data-id="${z.id}" data-nombre="${z.nombre}" title="Eliminar Zona">
                        &times;
                    </button>
                </div>
            `);
        });
    }

    /**
     * Actualiza la lista de recorridos en la pestaña de administración
     */
    function actualizarListasAdminRecorridos() {
        const $lista = $('#adminListaRecorridos');
        if ($lista.length === 0) return;

        $lista.empty();

        if (listaRecorridos.length === 0) {
            $lista.append('<p class="text-muted italic text-center py-3">No hay recorridos creados en el sistema.</p>');
            return;
        }

        listaRecorridos.forEach(function(r) {
            $lista.append(`
                <div class="sidebar-list-item">
                    <div>
                        <h6>${r.nombre}</h6>
                        <p>${r.paradas.length} zonas en ruta</p>
                    </div>
                    <button class="btn-eliminar-recorrido-list" 
                            data-id="${r.id}" data-nombre="${r.nombre}" title="Eliminar Recorrido">
                        &times;
                    </button>
                </div>
            `);
        });
    }

    /**
     * Muestra una alerta estilo toast que se auto-oculta
     */
    function showGlobalAlert(message, type) {
        if ($alertFeedback.length === 0) return;

        $alertFeedback.removeClass('alert-toast-success alert-toast-danger alert-toast-info alert-toast-warning')
                      .addClass('alert-toast-' + type)
                      .html(`<strong>${type === 'success' ? 'Éxito: ' : 'Aviso: '}</strong> ${message}`)
                      .slideDown(250);
        
        setTimeout(function() {
            $alertFeedback.slideUp(250);
        }, 5000);
    }
});
