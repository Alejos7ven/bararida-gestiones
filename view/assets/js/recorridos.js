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

    // Variables de control para edición de orden y recolocación de pines
    let recorridoSeleccionadoEdicion = null; // Recorrido seleccionado
    let zonaSeleccionadaEdicion = null;      // Zona seleccionada
    let modoRelocalizarZona = false;          // Flag de modo relocalización
    let modoEditarOrdenRecorrido = false;    // Flag de modo ordenamiento
    let paradasTemporalOrden = [];           // Array temporal de paradas en edición

    
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

        $('#modalCrearZona').on('shown.bs.modal', function () {
            setTimeout(function() {
                $('#nombreZona').trigger('focus');
            }, 100);
        });

        // Al cerrar o cancelar el modal de zona, borramos el pin temporal rojo
        $('#modalCrearZona').on('hidden.bs.modal', function () {
            $pinsContainer.find('.temp-pin').remove();
            if (typeof window.limpiarErroresFormulario === 'function') {
                window.limpiarErroresFormulario('#formCrearZona');
            } else {
                $('#formCrearZona').find('.is-invalid').removeClass('is-valid');
                $('#formCrearZona').find('.invalid-feedback').hide().text('');
            }
        });

        // Guardar la nueva zona vía AJAX
        $('#formCrearZona').on('submit', function(e) {
            e.preventDefault();
            const $form = $(this);

            if (!$form.valid()) {
                return;
            }

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
                        window.mapearErrorServidorAFormulario('#formCrearZona', response.message);
                    }
                },
                error: function(xhr) {
                    $btn.prop('disabled', false).text('Registrar Zona');
                    let errorMsg = 'Error al registrar la zona en el servidor.';
                    let field = null;
                    if (xhr.responseJSON) {
                        if (xhr.responseJSON.message) errorMsg = xhr.responseJSON.message;
                        if (xhr.responseJSON.field) field = xhr.responseJSON.field;
                    }
                    window.mapearErrorServidorAFormulario('#formCrearZona', errorMsg, field);
                }
            });
        });

        // ---------------------------------------------------------------------
        // EVENTO: SELECCIÓN DE PINES DURANTE LA CREACIÓN DE RECORRIDOS
        // ---------------------------------------------------------------------
        $(document).on('click', '.map-pin:not(.temp-pin)', function() {
            const $pin = $(this);
            const zonaId = parseInt($pin.data('id'));
            
            // Caso 1: Si estamos en modo de edición de recorrido inline, permitimos añadir/quitar pines con clics
            if (modoEditarOrdenRecorrido) {
                const idx = paradasTemporalOrden.findIndex(p => parseInt(p.zona_id) === zonaId);
                const $row = $(`.sidebar-list-item[data-type="recorrido"].selected`);
                
                if (idx !== -1) {
                    // Si ya forma parte del recorrido, la removemos
                    paradasTemporalOrden.splice(idx, 1);
                    showMapTooltip("✕ Parada removida de la ruta. Haz clic en ✓ para guardar.");
                } else {
                    // Si no está, la añadimos al final
                    const zona = listaZonas.find(z => parseInt(z.id) === zonaId);
                    if (zona) {
                        paradasTemporalOrden.push({
                            zona_id: zona.id,
                            zona_nombre: zona.nombre,
                            coordenadas: zona.coordenadas
                        });
                        showMapTooltip("✓ Nueva parada añadida al final. Usa ▲/▼ para ordenarla y ✓ para guardar.");
                    }
                }
                
                // Redibujar lista inline y trazo de mapa en tiempo real
                renderizarListaParadasEdicionInline($row);
                actualizarMapaYTrazoEdicionRuta();
                return;
            }

            // Caso 2: Si estamos en modo de visualización normal (no creación), resaltamos y mostramos nombre
            if (!modoCrearRecorrido) {
                if (modoRelocalizarZona) return; // Bloquear selecciones en modo relocalización
                
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

            if (!$form.valid()) {
                return;
            }

            const $btn = $('#btnGuardarRecorrido');

            if (recorridoZonasSeleccionadas.length < 2) {
                window.marcarErrorInput('#nombreRecorrido', 'Debe seleccionar al menos dos zonas sobre el mapa para conformar la ruta del recorrido.', true);
                showGlobalAlert('Debe hacer clic y seleccionar al menos dos zonas sobre el mapa para conformar un recorrido.', 'danger');
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
                        window.mapearErrorServidorAFormulario('#formCrearRecorrido', response.message);
                        showGlobalAlert(response.message, 'danger');
                    }
                },
                error: function(xhr) {
                    $btn.prop('disabled', false).text('Guardar Recorrido');
                    let errorMsg = 'Error al registrar el recorrido en el servidor.';
                    let field = null;
                    if (xhr.responseJSON) {
                        if (xhr.responseJSON.message) errorMsg = xhr.responseJSON.message;
                        if (xhr.responseJSON.field) field = xhr.responseJSON.field;
                    }
                    window.mapearErrorServidorAFormulario('#formCrearRecorrido', errorMsg, field);
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

        // =====================================================================
        // EVENTOS DE GESTIÓN INTERACTIVA INLINE (SIN SCROLL)
        // =====================================================================

        // Variables locales para recordar el HTML original de los botones de acción
        let originalActionsHtml = '';

        // ---------------------------------------------------------------------
        // EVENTO: SELECCIÓN DE RECORRIDO O ZONA EN LISTA ADMINISTRATIVA
        // ---------------------------------------------------------------------
        $(document).on('click', '.sidebar-list-item[data-type]', function(e) {
            // Si hacemos clic en un botón de acción, no alterar selección normal
            if ($(e.target).closest('button').length > 0) return;
            
            // Si estamos en medio de un modo de edición activo, no cambiar selección normal
            if (modoRelocalizarZona || modoEditarOrdenRecorrido) {
                showGlobalAlert('Debe confirmar (✓) o cancelar (✕) la edición actual primero.', 'warning');
                return;
            }

            const $item = $(this);
            const id = parseInt($item.data('id'));
            const type = $item.data('type');

            // Quitar clase selected de otros items
            $(`.sidebar-list-item`).removeClass('selected');
            $item.addClass('selected');

            if (type === 'recorrido') {
                $('.map-pin').removeClass('selected-pin');
                
                // Cargar recorrido
                const rec = listaRecorridos.find(r => parseInt(r.id) === id);
                if (rec) {
                    recorridoSeleccionadoEdicion = rec;
                    // Dibujar pines y recorrido
                    renderizarPinesZonas(listaZonas, rec.paradas);
                    dibujarRutaSVG(rec.paradas);
                }
            } else if (type === 'zona') {
                $svgOverlay.empty();

                // Cargar zona
                const zona = listaZonas.find(z => parseInt(z.id) === id);
                if (zona) {
                    zonaSeleccionadaEdicion = zona;
                    // Renderizar pines normales y destacar el seleccionado
                    renderizarPinesZonas(listaZonas);
                    const $pin = $(`.map-pin[data-id="${zona.id}"]`);
                    if ($pin.length > 0) {
                        $pin.addClass('selected-pin');
                    }
                }
            }
        });

        // ---------------------------------------------------------------------
        // EVENTO: ACTIVAR MODO RELOCALIZAR ZONA (BOTÓN LÁPIZ)
        // ---------------------------------------------------------------------
        $(document).on('click', '.btn-editar-zona-list', function(e) {
            e.stopPropagation();

            if (modoRelocalizarZona || modoEditarOrdenRecorrido) {
                showGlobalAlert('Ya hay un modo de edición activo en el sistema.', 'warning');
                return;
            }

            const $btn = $(this);
            const id = parseInt($btn.data('id'));
            const $row = $btn.closest('.sidebar-list-item');

            // Autoseleccionar la fila
            $row.trigger('click');

            const zona = listaZonas.find(z => parseInt(z.id) === id);
            if (!zona) return;

            zonaSeleccionadaEdicion = zona;
            modoRelocalizarZona = true;

            // Desactivar otros modos de creación generales
            desactivarModoAgregarZona();
            desactivarModoCrearRecorrido();

            // Resaltar contenedor del mapa
            $mapWrapper.addClass('admin-active');

            // Guardar HTML original del contenedor de acciones
            const $actions = $row.find('.sidebar-item-actions');
            originalActionsHtml = $actions.html();

            // Reemplazar botones por Check ✓ y Cruz ✕
            $actions.html(`
                <button class="btn-premium-check btn-confirmar-zona-edit" data-id="${id}" title="Guardar Ubicación">✓</button>
                <button class="btn-premium-cancel btn-cancelar-zona-edit" data-id="${id}" title="Cancelar">✕</button>
            `);

            // Reemplazar el h6 por un input para editar el nombre inline
            const $nameHeader = $row.find('h6');
            if ($nameHeader.length > 0) {
                const originalName = $nameHeader.text().trim();
                $nameHeader.replaceWith(`<input type="text" class="form-control form-control-sm input-edit-nombre" value="${originalName}" style="font-size: 0.85rem; padding: 2px 5px; margin-bottom: 4px; display: inline-block; width: 100%;" />`);
            }

            // Mostrar tooltip flotante en el mapa
            showMapTooltip("📍 Relocalización Activa: Haz clic en el mapa para reposicionar el pin");
        });

        // Evento Clic en el mapa durante relocalización
        $mapWrapper.on('click', function(e) {
            if (!modoRelocalizarZona || !zonaSeleccionadaEdicion) return;
            // Si el clic es en un pin de otra zona, no hacer nada
            if ($(e.target).hasClass('map-pin') && !$(e.target).hasClass('selected-pin')) return;

            const rect = $mapImage[0].getBoundingClientRect();
            const x = ((e.clientX - rect.left) / rect.width) * 100;
            const y = ((e.clientY - rect.top) / rect.height) * 100;
            const coordsString = `${x.toFixed(2)},${y.toFixed(2)}`;

            // Mover visualmente el pin destacado en el mapa
            const $pin = $(`.map-pin[data-id="${zonaSeleccionadaEdicion.id}"]`);
            const $label = $pin.next('.pin-label');
            if ($pin.length > 0) {
                $pin.css({ 'left': x + '%', 'top': y + '%' });
                $pin.attr('data-coords', coordsString);
                $label.css({ 'left': x + '%', 'top': y + '%' });
            }

            // Actualizar tooltip flotante en el mapa indicando coordenadas marcadas
            showMapTooltip(`📍 Posición colocada temporalmente. Presiona ✓ en la lista para confirmar.`);
        });

        // Cancelar relocalización de pin
        $(document).on('click', '.btn-cancelar-zona-edit', function(e) {
            e.stopPropagation();
            const id = parseInt($(this).data('id'));
            const $row = $(this).closest('.sidebar-list-item');
            
            desactivarModoRelocalizarZona();

            // Restaurar botones originales
            $row.find('.sidebar-item-actions').html(originalActionsHtml);

            // Restaurar h6 con el nombre original
            const $input = $row.find('.input-edit-nombre');
            if ($input.length > 0 && zonaSeleccionadaEdicion) {
                $input.replaceWith(`<h6 style="text-overflow: ellipsis; overflow: hidden; white-space: nowrap; margin-bottom: 2px;">${zonaSeleccionadaEdicion.nombre}</h6>`);
            }

            // Restaurar pin al original
            if (zonaSeleccionadaEdicion) {
                const coords = zonaSeleccionadaEdicion.coordenadas.split(',');
                if (coords.length === 2) {
                    const x = parseFloat(coords[0]);
                    const y = parseFloat(coords[1]);
                    const $pin = $(`.map-pin[data-id="${zonaSeleccionadaEdicion.id}"]`);
                    const $label = $pin.next('.pin-label');
                    if ($pin.length > 0) {
                        $pin.css({ 'left': x + '%', 'top': y + '%' });
                        $label.css({ 'left': x + '%', 'top': y + '%' });
                    }
                }
            }
        });

        // Guardar relocalización de pin inline por AJAX
        $(document).on('click', '.btn-confirmar-zona-edit', function(e) {
            e.stopPropagation();
            if (!zonaSeleccionadaEdicion) return;

            const $row = $(this).closest('.sidebar-list-item');
            
            // Obtener las coordenadas del pin del mapa actual
            const $pin = $(`.map-pin[data-id="${zonaSeleccionadaEdicion.id}"]`);
            const nuevasCoords = $pin.attr('data-coords') || zonaSeleccionadaEdicion.coordenadas;

            // Obtener el nuevo nombre del input
            const nuevoNombre = $row.find('.input-edit-nombre').val() || zonaSeleccionadaEdicion.nombre;

            const $btn = $(this);
            $btn.prop('disabled', true).text('...');

            $.ajax({
                url: 'controllers/RecorridoController.php?action=actualizar_zona_coords',
                method: 'POST',
                data: {
                    id: zonaSeleccionadaEdicion.id,
                    coordenadas: nuevasCoords,
                    nombre: nuevoNombre
                },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        showGlobalAlert(response.message, 'success');
                        desactivarModoRelocalizarZona();
                        
                        // Recargar todo
                        cargarZonas(function() {
                            cargarRecorridos();
                        });
                    } else {
                        showGlobalAlert(response.message, 'danger');
                        $btn.prop('disabled', false).text('✓');
                    }
                },
                error: function(xhr) {
                    let errorMsg = 'Error al actualizar la posición de la zona.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    }
                    showGlobalAlert(errorMsg, 'danger');
                    $btn.prop('disabled', false).text('✓');
                }
            });
        });

        // ---------------------------------------------------------------------
        // EVENTO: ACTIVAR MODO REORDENAR RECORRIDO (BOTÓN LÁPIZ)
        // ---------------------------------------------------------------------
        $(document).on('click', '.btn-editar-recorrido-list', function(e) {
            e.stopPropagation();

            if (modoRelocalizarZona || modoEditarOrdenRecorrido) {
                showGlobalAlert('Ya hay un modo de edición activo en el sistema.', 'warning');
                return;
            }

            const $btn = $(this);
            const id = parseInt($btn.data('id'));
            const $row = $btn.closest('.sidebar-list-item');

            // Autoseleccionar la fila
            $row.trigger('click');

            const rec = listaRecorridos.find(r => parseInt(r.id) === id);
            if (!rec) return;

            recorridoSeleccionadoEdicion = rec;
            modoEditarOrdenRecorrido = true;
            paradasTemporalOrden = JSON.parse(JSON.stringify(rec.paradas)); // Clonar paradas

            // Guardar HTML original de los botones
            const $actions = $row.find('.sidebar-item-actions');
            originalActionsHtml = $actions.html();

            // Reemplazar botones por Check ✓ y Cruz ✕
            $actions.html(`
                <button class="btn-premium-check btn-confirmar-recorrido-edit" data-id="${id}" title="Guardar Orden">✓</button>
                <button class="btn-premium-cancel btn-cancelar-recorrido-edit" data-id="${id}" title="Cancelar">✕</button>
            `);

            // Reemplazar el h6 por un input para editar el nombre inline
            const $nameHeader = $row.find('h6');
            if ($nameHeader.length > 0) {
                const originalName = $nameHeader.text().trim();
                $nameHeader.replaceWith(`<input type="text" class="form-control form-control-sm input-edit-nombre" value="${originalName}" style="font-size: 0.85rem; padding: 2px 5px; margin-bottom: 4px; display: inline-block; width: 100%;" />`);
            }

            // Expandir fila y renderizar listado secuencial interactivo
            renderizarListaParadasEdicionInline($row);

            // Mostrar tooltip flotante en el mapa
            showMapTooltip("🔄 Ordenando Recorrido: Usa las flechas ▲/▼ en la barra lateral o arrastra las zonas para ordenar la ruta");
        });

        // Evento para flecha arriba inline
        $(document).on('click', '.inline-reorder-container .btn-arrow-up', function(e) {
            e.stopPropagation();
            const index = parseInt($(this).data('index'));
            const $row = $(this).closest('.sidebar-list-item');
            
            if (index > 0) {
                // Intercambiar elementos en el array temporal
                const temp = paradasTemporalOrden[index];
                paradasTemporalOrden[index] = paradasTemporalOrden[index - 1];
                paradasTemporalOrden[index - 1] = temp;

                // Volver a renderizar lista inline y actualizar mapa en tiempo real
                renderizarListaParadasEdicionInline($row);
                actualizarMapaYTrazoEdicionRuta();
            }
        });

        // Evento para flecha abajo inline
        $(document).on('click', '.inline-reorder-container .btn-arrow-down', function(e) {
            e.stopPropagation();
            const index = parseInt($(this).data('index'));
            const $row = $(this).closest('.sidebar-list-item');
            
            if (index < paradasTemporalOrden.length - 1) {
                // Intercambiar elementos en el array temporal
                const temp = paradasTemporalOrden[index];
                paradasTemporalOrden[index] = paradasTemporalOrden[index + 1];
                paradasTemporalOrden[index + 1] = temp;

                // Volver a renderizar lista inline y actualizar mapa en tiempo real
                renderizarListaParadasEdicionInline($row);
                actualizarMapaYTrazoEdicionRuta();
            }
        });

        // Evento para quitar parada inline
        $(document).on('click', '.inline-reorder-container .btn-remove-stop-inline', function(e) {
            e.stopPropagation();
            const index = parseInt($(this).data('index'));
            const $row = $(this).closest('.sidebar-list-item');

            // Quitar del array temporal
            paradasTemporalOrden.splice(index, 1);

            // Volver a renderizar lista inline y actualizar mapa en tiempo real
            renderizarListaParadasEdicionInline($row);
            actualizarMapaYTrazoEdicionRuta();

            showMapTooltip("✕ Parada removida de la ruta. Haz clic en ✓ para guardar.");
        });

        // Cancelar reordenamiento de paradas
        $(document).on('click', '.btn-cancelar-recorrido-edit', function(e) {
            e.stopPropagation();
            const id = parseInt($(this).data('id'));
            const $row = $(this).closest('.sidebar-list-item');

            desactivarModoEditarOrdenRecorrido();

            // Remover listado expandido inline y restaurar botones
            $row.find('.inline-reorder-container').remove();
            $row.find('.sidebar-item-actions').html(originalActionsHtml);

            // Restaurar h6 con el nombre original
            const $input = $row.find('.input-edit-nombre');
            if ($input.length > 0 && recorridoSeleccionadoEdicion) {
                $input.replaceWith(`<h6 style="text-overflow: ellipsis; overflow: hidden; white-space: nowrap; margin-bottom: 2px;">${recorridoSeleccionadoEdicion.nombre}</h6>`);
            }

            // Restaurar trazo original en el mapa
            if (recorridoSeleccionadoEdicion) {
                renderizarPinesZonas(listaZonas, recorridoSeleccionadoEdicion.paradas);
                dibujarRutaSVG(recorridoSeleccionadoEdicion.paradas);
            }
        });

        // Guardar nuevo orden secuencial inline por AJAX
        $(document).on('click', '.btn-confirmar-recorrido-edit', function(e) {
            e.stopPropagation();
            if (!recorridoSeleccionadoEdicion) return;

            const $row = $(this).closest('.sidebar-list-item');
            const $btn = $(this);
            $btn.prop('disabled', true).text('...');

            // Extraer los IDs de zonas en el nuevo orden temporal
            const idsZonasOrdenadas = paradasTemporalOrden.map(p => p.zona_id);

            if (idsZonasOrdenadas.length < 2) {
                showGlobalAlert('Un recorrido debe incluir al menos dos zonas en su ruta.', 'danger');
                $btn.prop('disabled', false).text('✓');
                return;
            }

            // Obtener el nuevo nombre del input
            const nuevoNombre = $row.find('.input-edit-nombre').val() || recorridoSeleccionadoEdicion.nombre;

            $.ajax({
                url: 'controllers/RecorridoController.php?action=actualizar_recorrido_orden',
                method: 'POST',
                data: {
                    id: recorridoSeleccionadoEdicion.id,
                    zonas: idsZonasOrdenadas,
                    nombre: nuevoNombre
                },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        showGlobalAlert(response.message, 'success');
                        desactivarModoEditarOrdenRecorrido();
                        
                        // Remover listado expandido
                        $row.find('.inline-reorder-container').remove();
                        
                        // Recargar todo
                        cargarRecorridos();
                    } else {
                        showGlobalAlert(response.message, 'danger');
                        $btn.prop('disabled', false).text('✓');
                    }
                },
                error: function(xhr) {
                    let errorMsg = 'Error al guardar la secuencia de paradas.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    }
                    showGlobalAlert(errorMsg, 'danger');
                    $btn.prop('disabled', false).text('✓');
                }
            });
        });

        // ---------------------------------------------------------------------
        // EVENTOS DE DRAG AND DROP PARA REORDENAMIENTO DE PARADAS
        // ---------------------------------------------------------------------
        let dragSrcIndex = null;

        $(document).on('dragstart', '.inline-reorder-list .reorder-item', function(e) {
            dragSrcIndex = $(this).data('index');
            $(this).addClass('dragging');
            e.originalEvent.dataTransfer.effectAllowed = 'move';
            e.originalEvent.dataTransfer.setData('text/plain', dragSrcIndex);
        });

        $(document).on('dragend', '.inline-reorder-list .reorder-item', function() {
            $(this).removeClass('dragging');
            $('.inline-reorder-list .reorder-item').removeClass('drag-over');
        });

        $(document).on('dragover', '.inline-reorder-list .reorder-item', function(e) {
            e.preventDefault();
            e.originalEvent.dataTransfer.dropEffect = 'move';
            return false;
        });

        $(document).on('dragenter', '.inline-reorder-list .reorder-item', function() {
            $(this).addClass('drag-over');
        });

        $(document).on('dragleave', '.inline-reorder-list .reorder-item', function() {
            $(this).removeClass('drag-over');
        });

        $(document).on('drop', '.inline-reorder-list .reorder-item', function(e) {
            e.stopPropagation();
            const dragDestIndex = $(this).data('index');
            if (dragSrcIndex !== null && dragSrcIndex !== dragDestIndex) {
                const $row = $(this).closest('.sidebar-list-item');
                // Intercambiar/mover elemento en el array temporal
                const movedItem = paradasTemporalOrden.splice(dragSrcIndex, 1)[0];
                paradasTemporalOrden.splice(dragDestIndex, 0, movedItem);

                // Volver a renderizar lista inline y actualizar mapa en tiempo real
                renderizarListaParadasEdicionInline($row);
                actualizarMapaYTrazoEdicionRuta();
            }
            return false;
        });
    }

    /**
     * Desactiva el modo de relocalización de pines
     */
    function desactivarModoRelocalizarZona() {
        modoRelocalizarZona = false;
        $mapWrapper.removeClass('admin-active');
        hideMapTooltip();
    }

    /**
     * Desactiva el modo de edición de orden de recorrido
     */
    function desactivarModoEditarOrdenRecorrido() {
        modoEditarOrdenRecorrido = false;
        hideMapTooltip();
        paradasTemporalOrden = [];
    }

    /**
     * Renderiza el listado secuencial de paradas de forma compacta e inline dentro de la fila del recorrido en sidebar
     */
    function renderizarListaParadasEdicionInline($row) {
        $row.find('.inline-reorder-container').remove();

        const containerHtml = `
            <div class="inline-reorder-container w-100">
                <p class="text-muted mb-2" style="font-size:0.7rem; font-weight:700; text-transform:uppercase; margin-bottom: 6px !important;">Timeline del Itinerario:</p>
                <div class="inline-reorder-list">
                </div>
            </div>
        `;
        $row.append(containerHtml);
        const $list = $row.find('.inline-reorder-list');

        if (paradasTemporalOrden.length === 0) {
            $list.append('<p class="text-muted italic text-center py-2" style="font-size:0.75rem;">Este recorrido no posee paradas.</p>');
            return;
        }

        paradasTemporalOrden.forEach(function(p, idx) {
            const isFirst = (idx === 0);
            const isLast = (idx === paradasTemporalOrden.length - 1);

            $list.append(`
                <div class="reorder-item" draggable="true" data-index="${idx}" style="padding: 6px 10px;">
                    <div class="item-details" style="display:flex; align-items:center; gap:8px; min-width: 0; flex-grow: 1; padding-right: 4px;">
                        <div class="drag-handle" title="Arrastrar para reordenar">⠿</div>
                        <div class="item-num" style="background:#cbd5e1; color:#475569; width:20px; height:20px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:10px; font-weight:700; flex-shrink: 0;">${idx + 1}</div>
                        <span class="item-name" style="font-size:0.75rem; font-weight:600; color:#334155; text-overflow:ellipsis; overflow:hidden; white-space:nowrap;" title="${p.zona_nombre}">${p.zona_nombre}</span>
                    </div>
                    <div class="d-flex align-items-center gap-1">
                        <div class="btn-order-arrows">
                            <button class="btn-arrow-up" data-index="${idx}" ${isFirst ? 'disabled' : ''} title="Subir Parada">▲</button>
                            <button class="btn-arrow-down" data-index="${idx}" ${isLast ? 'disabled' : ''} title="Bajar Parada">▼</button>
                        </div>
                        <button class="btn-remove-stop-inline" data-index="${idx}" title="Quitar Parada" style="width:20px; height:20px; border-radius:50%; border:1px solid rgba(239, 68, 68, 0.2); background:rgba(239, 68, 68, 0.06); color:#ef4444; font-size:9px; display:inline-flex; align-items:center; justify-content:center; cursor:pointer; padding:0; outline:none; transition:var(--park-transition); margin-left: 2px;">✕</button>
                    </div>
                </div>
            `);
        });
    }

    /**
     * Redibuja la ruta y números secuenciales en el mapa en base al orden temporal editado
     */
    function actualizarMapaYTrazoEdicionRuta() {
        // Redibujar pines del mapa utilizando la secuencia modificada
        renderizarPinesZonas(listaZonas, paradasTemporalOrden);
        
        // Trazar el conector de recorrido
        dibujarRutaSVG(paradasTemporalOrden);
    }

    /**
     * Muestra el tooltip flotante sobre el mapa indicando el modo de edición activo
     */
    function showMapTooltip(text) {
        const $tooltip = $('#mapEditTooltip');
        const $textSpan = $('#mapEditTooltipText');
        if ($tooltip.length === 0) return;

        $textSpan.text(text);
        $tooltip.fadeIn(250);
    }

    /**
     * Oculta el tooltip flotante del mapa
     */
    function hideMapTooltip() {
        const $tooltip = $('#mapEditTooltip');
        if ($tooltip.length === 0) return;
        $tooltip.fadeOut(200);
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
        if (typeof window.limpiarErroresFormulario === 'function') {
            window.limpiarErroresFormulario('#formCrearRecorrido');
        } else {
            $('#formCrearRecorrido').find('.is-invalid').removeClass('is-valid');
            $('#formCrearRecorrido').find('.invalid-feedback').hide().text('');
        }
        $('#btnModoRecorrido').addClass('active');
        $('#panelCrearRecorridoForm').slideDown(250, function() {
            $('#nombreRecorrido').trigger('focus');
        });

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
            $wrapper.append('<p class="text-muted italic text-center fs-0.85">Haz clic en orden sobre al menos dos zonas del mapa para trazar el recorrido...</p>');
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
                <div class="sidebar-list-item" data-id="${z.id}" data-nombre="${z.nombre}" data-type="zona" style="cursor: pointer;">
                    <div style="flex-grow: 1; min-width: 0; padding-right: 8px;">
                        <h6 style="text-overflow: ellipsis; overflow: hidden; white-space: nowrap; margin-bottom: 2px;">${z.nombre}</h6>
                        <p style="margin: 0; font-size: 0.72rem; color: #64748b;">Coords: ${z.coordenadas}</p>
                    </div>
                    <div class="sidebar-item-actions" data-id="${z.id}">
                        <button class="btn-editar-zona-list" data-id="${z.id}" data-nombre="${z.nombre}" title="Relocalizar Pin">
                            <svg viewBox="0 0 24 24"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
                        </button>
                        <button class="btn-eliminar-zona-list" 
                                data-id="${z.id}" data-nombre="${z.nombre}" title="Eliminar Zona">
                            &times;
                        </button>
                    </div>
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
                <div class="sidebar-list-item" data-id="${r.id}" data-nombre="${r.nombre}" data-type="recorrido" style="cursor: pointer;">
                    <div style="flex-grow: 1; min-width: 0; padding-right: 8px;">
                        <h6 style="text-overflow: ellipsis; overflow: hidden; white-space: nowrap; margin-bottom: 2px;">${r.nombre}</h6>
                        <p style="margin: 0; font-size: 0.72rem; color: #64748b;">${r.paradas.length} zonas en ruta</p>
                    </div>
                    <div class="sidebar-item-actions" data-id="${r.id}">
                        <button class="btn-editar-recorrido-list" data-id="${r.id}" data-nombre="${r.nombre}" title="Editar Orden de Ruta">
                            <svg viewBox="0 0 24 24"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
                        </button>
                        <button class="btn-eliminar-recorrido-list" 
                                data-id="${r.id}" data-nombre="${r.nombre}" title="Eliminar Recorrido">
                            &times;
                        </button>
                    </div>
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
