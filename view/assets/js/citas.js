/**
 * Lógica del Módulo de Citas - citas.js
 * Parque Zoológico y Botánico Bararida
 */

$(document).ready(function() {
    // Referencias DOM principales
    const $tablaCitas = $('#tablaCitasBody');
    const $loader = $('#loaderCitas');
    const $alertFeedback = $('#alertFeedbackGlobal');
    
    // Variables de estado del Wizard (Modal de registro)
    let currentStep = 1;
    let selectedInstitutionId = null; // null si no está registrada, entero si existe
    let cacheCitas = [];
    let bookingCalendar = null;

    // Variables de paginación de citas
    let filteredCitas = [];
    let currentPageCitas = 1;
    const itemsPerPage = 10;

    // Variables de paginación para el modal de visitantes
    let cacheModalVisitantes = [];
    let filteredModalVisitantes = [];
    let currentPageModalVisitantes = 1;
    const itemsPerPageModal = 10;

    // Cargar citas al iniciar
    listarCitas();
    cargarEstados();
    cargarRecorridosDropdown();

    // Abrir modal de agendar si viene del dashboard
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('agendar') === '1') {
        setTimeout(function() {
            $('#modalCrearCita').modal('show');
        }, 300);
    }

    // =========================================================================
    // 1. LISTAR CITAS (AJAX)
    // =========================================================================
    function listarCitas() {
        $tablaCitas.hide();
        $loader.show();
        $('#paginacionCitas').hide();

        $.ajax({
            url: 'controllers/CitaController.php?action=listar',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                $loader.hide();
                if (response.status === 'success') {
                    cacheCitas = response.data || [];
                    
                    // Al recargar, intentar mantener la búsqueda actual
                    const query = $('#searchCitas').val().toLowerCase().trim();
                    if (query === '') {
                        filteredCitas = cacheCitas;
                    } else {
                        filteredCitas = cacheCitas.filter(function(c) {
                            const searchStr = `${c.razon_social.toLowerCase()} ${c.RIF.toLowerCase()} ${c.id} ${c.tipo_documento.toLowerCase()}`;
                            return searchStr.indexOf(query) > -1;
                        });
                    }

                    renderCitasTable();
                } else {
                    $tablaCitas.empty().append('<tr><td colspan="8" class="text-center text-muted py-4">No se pudo cargar el listado de citas.</td></tr>');
                    $tablaCitas.fadeIn(300);
                }
            },
            error: function(xhr) {
                $loader.hide();
                showGlobalAlert('Ocurrió un error al cargar el listado de citas.', 'danger');
            }
        });
    }

    function renderCitasTable() {
        $tablaCitas.empty();
        const totalRecords = filteredCitas.length;

        if (totalRecords === 0) {
            $tablaCitas.append('<tr><td colspan="8" class="text-center text-muted py-4">No se encontraron citas registradas.</td></tr>');
            $('#paginacionCitas').hide();
            $tablaCitas.fadeIn(300);
            return;
        }

        const totalPages = Math.ceil(totalRecords / itemsPerPage);

        if (currentPageCitas > totalPages) {
            currentPageCitas = totalPages;
        }
        if (currentPageCitas < 1) {
            currentPageCitas = 1;
        }

        const startIdx = (currentPageCitas - 1) * itemsPerPage;
        const endIdx = Math.min(startIdx + itemsPerPage, totalRecords);
        const pageData = filteredCitas.slice(startIdx, endIdx);

        pageData.forEach(function(c) {
            let badgeEstatus = '';
            if (c.estatus === 'aprobado') {
                badgeEstatus = '<span class="badge-premium badge-premium-admin">Aprobada</span>';
            } else if (c.estatus === 'completado') {
                badgeEstatus = '<span class="badge-premium badge-premium-superadmin">Completada</span>';
            } else if (c.estatus === 'cancelado') {
                badgeEstatus = '<span class="badge-premium badge-premium-danger">Cancelada</span>';
            } else {
                badgeEstatus = '<span class="badge-premium badge-premium-guia">Pendiente</span>';
            }

            const badgePago = c.pago_estado === 'pagado' 
                ? '<span class="badge-premium badge-payment-paid">Pagado</span>'
                : '<span class="badge-premium badge-payment-pending">Pendiente</span>';

            const badgeVisitantes = `<span class="badge-premium btn-ver-visitantes ${parseInt(c.total_visitantes) > 0 ? 'badge-premium-info' : 'bg-secondary'}" data-id="${c.id}" data-name="${c.razon_social}" style="cursor: pointer;">${c.total_visitantes} visitantes</span>`;

            const btnImportarHtml = `
                <button class="btn-action btn-action-edit btn-importar-visitantes" data-id="${c.id}" data-name="${c.razon_social}" title="Importar Visitantes (CSV)">
                    <svg viewBox="0 0 24 24" style="width:20px; height:20px;"><path d="M19.35 10.04C18.67 6.59 15.64 4 12 4 9.11 4 6.6 5.64 5.35 8.04 2.34 8.36 0 10.91 0 14c0 3.31 2.69 6 6 6h13c2.76 0 5-2.24 5-5 0-2.64-2.05-4.78-4.65-4.96zM14 13v4h-4v-4H7l5-5 5 5h-3z"/></svg>
                </button>
            `;

            // Guía asignado o pendiente
            const guiaAsignado = c.guia_nombre ? `${c.guia_nombre} ${c.guia_apellido || ''}` : '<span class="text-muted italic">No asignado</span>';

            const rowHtml = `
                <tr class="cita-row">
                    <td><strong>#CIT-${c.id}</strong></td>
                    <td>
                        <span style="white-space: nowrap;"><strong>${c.tipo_documento}-${c.RIF}</strong></span><br>
                        <small class="text-muted" style="font-size:0.8rem;">${c.razon_social}</small>
                    </td>
                    <td>
                        ${formatDate(c.fecha_visita)}<br>
                        <small class="text-success fw-600">${formatTime(c.hora_inicio)}</small>
                    </td>
                    <td>${badgeVisitantes}</td>
                    <td><span class="text-muted italic">${guiaAsignado}</span></td>
                    <td>${badgeEstatus}</td>
                    <td>${badgePago}</td>
                    <td>
                        <div class="table-actions">
                            ${btnImportarHtml}
                            <button class="btn-action btn-action-edit btn-editar-cita" data-id="${c.id}" title="Editar Cita">
                                <svg viewBox="0 0 24 24" style="width:20px; height:20px;"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
                            </button>
                            <a href="controllers/CitaController.php?action=descargar_plantilla&cita_id=${c.id}" class="btn-action btn-action-edit" title="Descargar Plantilla Excel (.xlsx)" target="_blank">
                                <svg viewBox="0 0 24 24" style="width:20px; height:20px;"><path d="M19.35 10.04C18.67 6.59 15.64 4 12 4 9.11 4 6.6 5.64 5.35 8.04 2.34 8.36 0 10.91 0 14c0 3.31 2.69 6 6 6h13c2.76 0 5-2.24 5-5 0-2.64-2.05-4.78-4.65-4.96zM17 13l-5 5-5-5h3V9h4v4h3z"/></svg>
                            </a>
                            <button class="btn-action btn-action-delete btn-eliminar-cita" data-id="${c.id}" data-name="${c.razon_social}" title="Eliminar Cita">
                                <svg viewBox="0 0 24 24" style="width:20px; height:20px;"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
            $tablaCitas.append(rowHtml);
        });

        // Actualizar datos informativos
        $('#pagStartCitas').text(startIdx + 1);
        $('#pagEndCitas').text(endIdx);
        $('#pagTotalCitas').text(totalRecords);

        // Renderizar controles de paginación
        const $controls = $('#paginationControlsCitas');
        $controls.empty();

        const prevDisabled = currentPageCitas === 1 ? 'disabled' : '';
        $controls.append(`
            <li class="page-item ${prevDisabled}">
                <a class="page-link btn-pag-citas" data-page="${currentPageCitas - 1}" href="javascript:void(0)">&laquo; Anterior</a>
            </li>
        `);

        // Botones de Páginas (Ventana Deslizante de Máximo 10)
        const maxVisibleCitas = 10;
        let startPageCitas = Math.max(1, currentPageCitas - Math.floor(maxVisibleCitas / 2));
        let endPageCitas = Math.min(totalPages, startPageCitas + maxVisibleCitas - 1);

        if (endPageCitas - startPageCitas + 1 < maxVisibleCitas) {
            startPageCitas = Math.max(1, endPageCitas - maxVisibleCitas + 1);
        }

        for (let i = startPageCitas; i <= endPageCitas; i++) {
            const activeClass = currentPageCitas === i ? 'active' : '';
            $controls.append(`
                <li class="page-item ${activeClass}">
                    <a class="page-link btn-pag-citas" data-page="${i}" href="javascript:void(0)">${i}</a>
                </li>
            `);
        }

        const nextDisabled = currentPageCitas === totalPages ? 'disabled' : '';
        $controls.append(`
            <li class="page-item ${nextDisabled}">
                <a class="page-link btn-pag-citas" data-page="${currentPageCitas + 1}" href="javascript:void(0)">Siguiente &raquo;</a>
            </li>
        `);

        $('#paginacionCitas').removeClass('d-none').show();
        $tablaCitas.fadeIn(300);
    }

    // Clicks en paginación de citas
    $(document).on('click', '.btn-pag-citas', function(e) {
        e.preventDefault();
        const page = parseInt($(this).data('page'));
        if (page && page !== currentPageCitas) {
            currentPageCitas = page;
            renderCitasTable();
        }
    });

    // Buscador en tiempo real de citas
    $('#searchCitas').on('input', function() {
        const query = $(this).val().toLowerCase().trim();
        if (query === '') {
            filteredCitas = cacheCitas;
        } else {
            filteredCitas = cacheCitas.filter(function(c) {
                const searchStr = `${c.razon_social.toLowerCase()} ${c.RIF.toLowerCase()} ${c.id} ${c.tipo_documento.toLowerCase()}`;
                return searchStr.indexOf(query) > -1;
            });
        }
        currentPageCitas = 1;
        renderCitasTable();
    });

    // =========================================================================
    // 1.5. CARGAR RECORRIDOS DINÁMICAMENTE PARA SELECTORES (AJAX)
    // =========================================================================
    function cargarRecorridosDropdown() {
        const $recorridoSelect = $('#recorrido_id');
        const $editRecorridoSelect = $('#edit_recorrido_id');
        if ($recorridoSelect.length === 0 && $editRecorridoSelect.length === 0) return;

        $.ajax({
            url: 'controllers/RecorridoController.php?action=listar_recorridos_simple',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    const recorridos = response.data || [];
                    
                    // Rellenar select de crear cita
                    $recorridoSelect.empty().append('<option value="">Ninguno / Recorrido Libre</option>');
                    // Rellenar select de editar cita
                    $editRecorridoSelect.empty().append('<option value="">Ninguno / Recorrido Libre</option>');
                    
                    recorridos.forEach(function(rec) {
                        $recorridoSelect.append(`<option value="${rec.id}">${rec.nombre}</option>`);
                        $editRecorridoSelect.append(`<option value="${rec.id}">${rec.nombre}</option>`);
                    });
                }
            },
            error: function() {
                $recorridoSelect.empty().append('<option value="">Error al cargar recorridos</option>');
                $editRecorridoSelect.empty().append('<option value="">Error al cargar recorridos</option>');
            }
        });
    }

    // =========================================================================
    // 2. CARGA DE UBICACIONES EN CASCADA
    // =========================================================================
    function cargarEstados() {
        const $selectEstado = $('#estado_id');
        $.ajax({
            url: 'controllers/LocationController.php?action=estados',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    $selectEstado.empty().append('<option value="">Seleccione Estado</option>');
                    response.data.forEach(function(est) {
                        $selectEstado.append(`<option value="${est.id_estado}">${est.estado}</option>`);
                    });
                }
            }
        });
    }

    $('#estado_id').on('change', function() {
        const estadoId = $(this).val();
        const $selectMun = $('#municipio_id');
        const $selectCiu = $('#ciudad_id');
        const $selectPar = $('#parroquia_id');

        $selectMun.empty().append('<option value="">Seleccione Municipio</option>').prop('disabled', true);
        $selectCiu.empty().append('<option value="">Seleccione Ciudad</option>').prop('disabled', true);
        $selectPar.empty().append('<option value="">Seleccione Parroquia</option>').prop('disabled', true);

        if (!estadoId) return;

        // Cargar Ciudades al seleccionar Estado
        $.ajax({
            url: `controllers/LocationController.php?action=ciudades&estado_id=${estadoId}`,
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    $selectCiu.prop('disabled', false);
                    response.data.forEach(function(c) {
                        $selectCiu.append(`<option value="${c.id_ciudad}">${c.ciudad}</option>`);
                    });
                }
            }
        });
    });

    $('#ciudad_id').on('change', function() {
        const ciudadId = $(this).val();
        const estadoId = $('#estado_id').val();
        const $selectMun = $('#municipio_id');
        const $selectPar = $('#parroquia_id');

        $selectMun.empty().append('<option value="">Seleccione Municipio</option>').prop('disabled', true);
        $selectPar.empty().append('<option value="">Seleccione Parroquia</option>').prop('disabled', true);

        if (!ciudadId || !estadoId) return;

        // Cargar Municipios al seleccionar Ciudad (usando el estadoId actual)
        $.ajax({
            url: `controllers/LocationController.php?action=municipios&estado_id=${estadoId}`,
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    $selectMun.prop('disabled', false);
                    response.data.forEach(function(m) {
                        $selectMun.append(`<option value="${m.id_municipio}">${m.municipio}</option>`);
                    });
                }
            }
        });
    });

    $('#municipio_id').on('change', function() {
        const municipioId = $(this).val();
        const $selectPar = $('#parroquia_id');

        $selectPar.empty().append('<option value="">Seleccione Parroquia</option>').prop('disabled', true);

        if (!municipioId) return;

        // Cargar Parroquias al seleccionar Municipio
        $.ajax({
            url: `controllers/LocationController.php?action=parroquias&municipio_id=${municipioId}`,
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    $selectPar.prop('disabled', false);
                    response.data.forEach(function(p) {
                        $selectPar.append(`<option value="${p.id_parroquia}">${p.parroquia}</option>`);
                    });
                }
            }
        });
    });

    // =========================================================================
    // 3. WIZARD / REGISTRO MULTI-PASO
    // =========================================================================
    
    // Al abrir el modal, resetear variables e ir al paso 1
    $('#modalCrearCita').on('show.bs.modal', function () {
        resetWizard();
    });

    function resetWizard() {
        currentStep = 1;
        selectedInstitutionId = null;
        $('#formCrearCita')[0].reset();
        
        // Ocultar alerta de capacidad
        $('#bookingCapacityAlert').hide().empty();
        
        // Limpiar contenedor de contactos e insertar el campo obligatorio inicial
        $('#contactsContainer').empty();
        agregarFilaContacto('principal');

        // Deshabilitar selectores geográficos subordinados
        $('#municipio_id, #ciudad_id, #parroquia_id').prop('disabled', true);

        // Limpiar cualquier tipo de organización no predefinido
        $('#tipo_organizacion option').each(function() {
            const val = $(this).val();
            const predefined = ['', 'Colegio Privado', 'Escuela Pública', 'Liceo Público', 'Liceo Privado', 'Fundación / ONG', 'Institución Gubernamental', 'Empresa Privada', 'Comunidad Organizada', 'Otro'];
            if (predefined.indexOf(val) === -1) {
                $(this).remove();
            }
        });

        // Ocultar alerta de RIF
        $('#rifCheckStatus').hide().removeClass('alert-success alert-warning alert-danger').text('');
        
        // Resetear botones
        $('#btnWizardPrev').hide();
        $('#btnWizardNext').text('Siguiente');
        $('#btnWizardNext').prop('disabled', true); // Se habilita al verificar el RIF exitosamente

        showStep(currentStep);
    }

    function showStep(step) {
        $('.wizard-step-panel').removeClass('active');
        $(`#wizardStep${step}`).addClass('active');

        // Actualizar barra de progreso del Step Wizard
        const percentage = ((step - 1) / 2) * 100;
        $('.step-wizard-progress').css('width', `${percentage}%`);

        // Actualizar indicadores circulares
        $('.step-wizard-item').each(function(index) {
            const stepNum = index + 1;
            $(this).removeClass('active completed');
            if (stepNum === step) {
                $(this).addClass('active');
            } else if (stepNum < step) {
                $(this).addClass('completed');
            }
        });

        // Configuración de botones de navegación
        if (step === 1) {
            $('#btnWizardPrev').hide();
            $('#btnWizardNext').hide();
        } else {
            $('#btnWizardPrev').show();
            $('#btnWizardNext').show();
        }

        if (step === 3) {
            $('#btnWizardNext').text('Agendar Cita');
            // Inicializar o redibujar FullCalendar en el modal
            setTimeout(inicializarCalendarioReserva, 150);
        } else {
            $('#btnWizardNext').text('Siguiente');
        }
    }

    // BOTÓN: VERIFICAR RIF (PASO 1)
    // AUTOCUMPLETADO DE INSTITUCIONES (PASO 1)
    let searchTimeout = null;

    $('#buscar_institucion_autocomplete').on('input', function() {
        const query = $(this).val().trim();
        const $suggestions = $('#autocomplete_suggestions');
        const $statusBox = $('#rifCheckStatus');

        clearTimeout(searchTimeout);
        $statusBox.hide();

        if (query.length < 2) {
            $suggestions.hide().empty();
            $('#btnWizardNext').prop('disabled', true);
            return;
        }

        searchTimeout = setTimeout(function() {
            $.ajax({
                url: `controllers/InstitucionController.php?action=buscar_autocomplete&q=${encodeURIComponent(query)}`,
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    $suggestions.empty();
                    if (response.status === 'success') {
                        const results = response.data;
                        if (results.length > 0) {
                            results.forEach(function(inst) {
                                const itemHtml = `
                                    <button type="button" class="dropdown-item py-2 px-3 autocomplete-item" style="display: block; width: 100%; text-align: left;" data-json='${JSON.stringify(inst)}'>
                                        <div class="fw-bold text-success">${inst.nombre}</div>
                                        <small class="text-muted">${inst.tipo_documento}-${inst.documento} • ${inst.tipo_organizacion} (${inst.tipo_gestion})</small>
                                    </button>
                                `;
                                $suggestions.append(itemHtml);
                            });
                        }

                        // Agregar opción para registrar como nueva institución
                        const queryIsDoc = /^[JGVjgvEPep]?\d+$/.test(query.replace(/[-]/g, ''));
                        const optionText = queryIsDoc ? `Registrar nueva con RIF: ${query}` : `Registrar nueva institución: "${query}"`;
                        const addNewHtml = `
                            <div class="dropdown-divider"></div>
                            <button type="button" class="dropdown-item py-2 px-3 text-primary fw-bold" id="btn_new_inst_autocomplete" style="display: block; width: 100%; text-align: left;">
                                <span class="me-1">+</span> ${optionText}
                            </button>
                        `;
                        $suggestions.append(addNewHtml);
                        $suggestions.show();
                    }
                },
                error: function() {
                    console.error('Error al realizar búsqueda de autocompletado');
                }
            });
        }, 300);
    });

    // Manejar selección de sugerencia
    $(document).on('click', '.autocomplete-item', function() {
        const inst = $(this).data('json');
        selectedInstitutionId = inst.id;
        
        $('#buscar_institucion_autocomplete').val(`${inst.tipo_documento}-${inst.documento} - ${inst.nombre}`);
        $('#autocomplete_suggestions').hide().empty();

        // Rellenar datos
        $('#final_tipo_documento').val(inst.tipo_documento);
        $('#final_documento').val(inst.documento);
        $('#final_institucion_id').val(selectedInstitutionId);

        prellenarDatosInstitucion(inst);
        $('#nueva_institucion_rif_row').hide();

        $('#rifCheckStatus').removeClass('alert-danger alert-warning')
                            .addClass('alert-success')
                            .html(`<strong>✓ Institución Seleccionada:</strong> ${inst.nombre}. Redirigiendo...`)
                            .fadeIn(200);

        $('#btnWizardNext').prop('disabled', false);

        // Transición automática al paso 2
        setTimeout(function() {
            currentStep = 2;
            showStep(currentStep);
        }, 800);
    });

    // Manejar registrar nueva institución desde autocompletado
    $(document).on('click', '#btn_new_inst_autocomplete', function() {
        const query = $('#buscar_institucion_autocomplete').val().trim();
        $('#autocomplete_suggestions').hide().empty();

        selectedInstitutionId = null;
        $('#final_institucion_id').val('');
        
        // Intentar parsear el query para ver si es RIF o Nombre
        let docPart = '';
        let tipoDocPart = 'J';
        let nombrePart = '';

        // Ejemplos: J-12345678, J12345678, 12345678
        const cleanQuery = query.replace(/[-]/g, '');
        const docMatch = cleanQuery.match(/^([JGVjgvEPep])?(\d+)$/);
        if (docMatch) {
            tipoDocPart = docMatch[1] ? docMatch[1].toUpperCase() : 'J';
            docPart = docMatch[2];
        } else {
            nombrePart = query;
        }

        limpiarYEditarDatosInstitucion();

        $('#final_tipo_documento').val(tipoDocPart);
        $('#final_documento').val(docPart);

        // Mostrar y precargar campos RIF en Paso 2
        $('#tipo_documento_inst').val(tipoDocPart);
        $('#documento_inst').val(docPart);
        $('#nueva_institucion_rif_row').show();

        // Sincronizar inputs de RIF en tiempo real
        $('#tipo_documento_inst').off('change').on('change', function() {
            $('#final_tipo_documento').val($(this).val());
        });
        $('#documento_inst').off('input').on('input', function() {
            $('#final_documento').val($(this).val().replace(/[^0-9]/g, ''));
        });

        // Si se extraxo nombre, precargarlo en el formulario de paso 2
        if (nombrePart) {
            $('#nombre_institucion').val(nombrePart);
        }

        // Mostrar warning toast indicando que es nueva
        $('#rifCheckStatus').removeClass('alert-danger alert-success')
                            .addClass('alert-warning')
                            .html('<strong>⚠ Institución no registrada:</strong> Redirigiendo para registrar...')
                            .fadeIn(200);

        $('#btnWizardNext').prop('disabled', false);

        setTimeout(function() {
            currentStep = 2;
            showStep(currentStep);
        }, 800);
    });

    // Cerrar sugerencias al hacer clic afuera
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#buscar_institucion_autocomplete, #autocomplete_suggestions').length) {
            $('#autocomplete_suggestions').hide();
        }
    });

    // BOTÓN: SIGUIENTE / GUARDAR
    $('#btnWizardNext').on('click', function() {
        if (currentStep === 1) {
            // Ir al paso 2
            currentStep = 2;
            showStep(currentStep);
        } else if (currentStep === 2) {
            // Validaciones Paso 2 usando jquery-validate
            if (!$('#formCrearCita').valid()) {
                return;
            }

            // Ir al paso 3
            currentStep = 3;
            showStep(currentStep);
        } else if (currentStep === 3) {
            // Validaciones Paso 3 usando jquery-validate
            if (!$('#formCrearCita').valid()) {
                return;
            }

            // Procesar y enviar cita mediante AJAX
            guardarCita();
        }
    });

    // BOTÓN: ATRÁS
    $('#btnWizardPrev').on('click', function() {
        if (currentStep > 1) {
            currentStep--;
            showStep(currentStep);
        }
    });

    function prellenarDatosInstitucion(data) {
        $('#nombre_institucion').val(data.nombre).prop('disabled', true);
        
        // Agregar dinámicamente la opción si no existe en el select
        if (data.tipo_organizacion && $('#tipo_organizacion option[value="' + data.tipo_organizacion + '"]').length === 0) {
            $('#tipo_organizacion').append(`<option value="${data.tipo_organizacion}">${data.tipo_organizacion}</option>`);
        }
        $('#tipo_organizacion').val(data.tipo_organizacion).prop('disabled', true);
        $('#tipo_gestion').val(data.tipo_gestion || 'publica').prop('disabled', true);
        
        // Limpiar selects geográficos y forzar a mostrar los nombres de forma simple
        $('#estado_id').empty().append(`<option value="${data.id_estado}" selected>${data.estado || 'Estado Pre-cargado'}</option>`).prop('disabled', true);
        $('#municipio_id').empty().append(`<option value="${data.id_municipio}" selected>${data.municipio || 'Municipio Pre-cargado'}</option>`).prop('disabled', true);
        $('#ciudad_id').empty().append(`<option value="${data.ciudad_id}" selected>${data.ciudad || 'Ciudad Pre-cargada'}</option>`).prop('disabled', true);
        $('#parroquia_id').empty().append(`<option value="${data.parroquia_id}" selected>${data.parroquia || 'Parroquia Pre-cargada'}</option>`).prop('disabled', true);
        
        $('#detalles_direccion').val(data.detalles_direccion || '').prop('disabled', true);

        // Prellenar contactos
        $('#contactsContainer').empty();
        if (data.contactos && data.contactos.length > 0) {
            data.contactos.forEach(function(c) {
                let labelTipo = c.tipo === 'principal' ? 'Teléfono Principal' : (c.tipo === 'secundario' ? 'Teléfono Secundario' : 'Correo Electrónico');
                const contactHtml = `
                    <div class="contact-row">
                        <input type="text" class="form-control form-premium-control" value="${c.contacto}" disabled>
                        <span class="badge bg-secondary p-2.5" style="border-radius:10px; font-size:0.75rem;">${labelTipo}</span>
                    </div>
                `;
                $('#contactsContainer').append(contactHtml);
            });
        }
        $('#btnAddNewContact').hide();
    }

    // Limpiar campos y habilitar edición
    function limpiarYEditarDatosInstitucion() {
        $('#nombre_institucion').val('').prop('disabled', false);
        $('#tipo_organizacion').val('').prop('disabled', false);
        $('#tipo_gestion').val('publica').prop('disabled', false);
        
        // Recargar estados
        cargarEstados();
        $('#estado_id').prop('disabled', false);
        
        $('#municipio_id').empty().append('<option value="">Seleccione Municipio</option>').prop('disabled', true);
        $('#ciudad_id').empty().append('<option value="">Seleccione Ciudad</option>').prop('disabled', true);
        $('#parroquia_id').empty().append('<option value="">Seleccione Parroquia</option>').prop('disabled', true);
        
        $('#detalles_direccion').val('').prop('disabled', false);

        $('#contactsContainer').empty();
        agregarFilaContacto('principal');
        $('#btnAddNewContact').show();
    }

    // =========================================================================
    // 4. DIRECCIÓN DE CONTACTOS (FILAS DINÁMICAS EN WIZARD)
    // =========================================================================
    let contactCount = 0;
    
    function agregarFilaContacto(forcedType = '') {
        contactCount++;
        const isPrincipal = forcedType === 'principal' || $('#contactsContainer .contact-row').length === 0;
        const typeSelected = isPrincipal ? 'principal' : 'secundario';
        const deleteButton = isPrincipal 
            ? '<div style="width:28px;"></div>' 
            : `<button type="button" class="btn-remove-contact btn-eliminar-contacto-row" title="Eliminar Contacto">✕</button>`;

        const rowHtml = `
            <div class="contact-row" id="contactRow_${contactCount}">
                <input type="text" class="form-control form-premium-control contact-input" 
                       name="contactos[${contactCount}][contacto]" placeholder="Número o correo..." required>
                <select class="form-select form-premium-control" name="contactos[${contactCount}][tipo]" style="max-width:180px;">
                    <option value="principal" ${typeSelected === 'principal' ? 'selected' : ''}>Principal</option>
                    <option value="secundario" ${typeSelected === 'secundario' ? 'selected' : ''}>Secundario</option>
                    <option value="correo">Correo</option>
                </select>
                ${deleteButton}
            </div>
        `;
        $('#contactsContainer').append(rowHtml);
    }

    $('#btnAddNewContact').on('click', function() {
        agregarFilaContacto();
    });

    $(document).on('click', '.btn-eliminar-contacto-row', function() {
        $(this).closest('.contact-row').remove();
    });

    // =========================================================================
    // 5. GUARDAR CITA (AJAX POST)
    // =========================================================================
    function guardarCita() {
        const $btn = $('#btnWizardNext');
        
        // Habilitar campos temporalmente para serializar RIF y Razón Social pre-cargados
        $('#formCrearCita :disabled').prop('disabled', false);
        const formData = $('#formCrearCita').serialize();
        
        // Deshabilitar de nuevo para evitar desconfiguración si falla
        if (selectedInstitutionId !== null) {
            $('#nombre_institucion, #tipo_organizacion, #tipo_gestion, #estado_id, #municipio_id, #ciudad_id, #parroquia_id, #detalles_direccion').prop('disabled', true);
        }

        $btn.prop('disabled', true).text('Guardando cita...');

        $.ajax({
            url: 'controllers/CitaController.php?action=crear',
            method: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                $btn.prop('disabled', false).text('Agendar Cita');
                
                if (response.status === 'success') {
                    $('#modalCrearCita').modal('hide');
                    showGlobalAlert(response.message, 'success');
                    listarCitas();
                } else {
                    alert(response.message);
                }
            },
            error: function(xhr) {
                $btn.prop('disabled', false).text('Agendar Cita');
                let errorMsg = 'Error en el servidor al registrar la cita.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                alert(errorMsg);
            }
        });
    }

    // =========================================================================
    // 5.5 EDICIÓN DE CITA (GET + POST)
    // =========================================================================
    $(document).on('click', '.btn-editar-cita', function() {
        const id = $(this).data('id');
        
        $('#formEditarCita')[0].reset();

        $.ajax({
            url: `controllers/CitaController.php?action=obtener&id=${id}`,
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    const cita = response.data;
                    $('#edit_cita_id').val(cita.id);
                    $('#edit_recorrido_id').val(cita.recorrido_id || '');
                    $('#edit_fecha_visita').val(cita.fecha_visita);
                    
                    // Asegurar que la hora tenga el formato HH:MM (por ejemplo '09:00:00' -> '09:00')
                    let hora = cita.hora_inicio;
                    if (hora && hora.length > 5) {
                        hora = hora.substring(0, 5);
                    }
                    $('#edit_hora_inicio').val(hora);
                    
                    $('#edit_metodo_pago').val(cita.metodo_pago);
                    $('#edit_pago_estado').val(cita.pago_estado);
                    $('#edit_estatus').val(cita.estatus);

                    $('#modalEditarCita').modal('show');
                } else {
                    showGlobalAlert(response.message, 'danger');
                }
            },
            error: function() {
                showGlobalAlert('Error al conectar con el servidor para obtener los datos de la cita.', 'danger');
            }
        });
    });

    $('#formEditarCita').on('submit', function(e) {
        e.preventDefault();
        const $form = $(this);

        if (!$form.valid()) {
            return;
        }

        const $btn = $('#btnGuardarEditCita');
        $btn.prop('disabled', true).text('Guardando...');

        $.ajax({
            url: 'controllers/CitaController.php?action=editar',
            method: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response) {
                $btn.prop('disabled', false).text('Guardar Cambios');
                if (response.status === 'success') {
                    $('#modalEditarCita').modal('hide');
                    showGlobalAlert(response.message, 'success');
                    listarCitas();
                } else {
                    alert(response.message);
                }
            },
            error: function(xhr) {
                $btn.prop('disabled', false).text('Guardar Cambios');
                let errorMsg = 'Error en el servidor al intentar actualizar la cita.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                alert(errorMsg);
            }
        });
    });

    // =========================================================================
    // 6. ELIMINAR CITA
    // =========================================================================
    $(document).on('click', '.btn-eliminar-cita', function() {
        const id = $(this).data('id');
        const name = $(this).data('name');

        $('#delete_cita_id').val(id);
        $('#deleteCitaNameSpan').text(`#CIT-${id} de ${name}`);
        $('#modalEliminarCita').modal('show');
    });

    $('#btnConfirmarEliminarCita').on('click', function() {
        const id = $('#delete_cita_id').val();
        const $btn = $(this);

        $btn.prop('disabled', true).text('Eliminando...');

        $.ajax({
            url: 'controllers/CitaController.php?action=eliminar',
            method: 'POST',
            data: { id: id },
            dataType: 'json',
            success: function(response) {
                $btn.prop('disabled', false).text('Eliminar Cita');
                $('#modalEliminarCita').modal('hide');

                if (response.status === 'success') {
                    showGlobalAlert(response.message, 'success');
                    listarCitas();
                } else {
                    showGlobalAlert(response.message, 'danger');
                }
            },
            error: function(xhr) {
                $btn.prop('disabled', false).text('Eliminar Cita');
                $('#modalEliminarCita').modal('hide');
                let errorMsg = 'Error en el servidor al intentar eliminar la cita.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                showGlobalAlert(errorMsg, 'danger');
            }
        });
    });

    // =========================================================================
    // 7. IMPORTACIÓN DE VISITANTES DESDE CSV (DRAG & DROP)
    // =========================================================================
    const $dropZone = $('#csvDropZone');
    const $fileInput = $('#csvFileInput');
    const $fileInfo = $('#csvFileInfo');
    const $btnImport = $('#btnConfirmarImportarCSV');
    let selectedCsvFile = null;

    $(document).on('click', '.btn-importar-visitantes', function() {
        const id = $(this).data('id');
        const name = $(this).data('name');

        $('#import_cita_id').val(id);
        $('#importCitaNombreSpan').text(`#CIT-${id} - ${name}`);
        
        // Resetear formulario de archivo
        $fileInput.val('');
        selectedCsvFile = null;
        $fileInfo.hide().text('');
        $('#btnSiguientePrevisualizar').prop('disabled', true);
        
        // Resetear paneles a estado inicial (Cargar Archivo)
        $('#importPreviewPanel').hide();
        $('#importPreviewFooter').attr('style', 'display: none !important;');
        $('#importUploadPanel').show();
        $('#importUploadFooter').attr('style', 'display: flex !important;');
        $('#importPreviewTableBody').empty();

        $('#modalImportarVisitantes').modal('show');
    });

    // Clic en la zona de drop abre el selector de archivos
    $dropZone.on('click', function() {
        $fileInput.click();
    });

    $fileInput.on('change', function() {
        handleFileSelect(this.files[0]);
    });

    // Drag & Drop handlers
    $dropZone.on('dragover', function(e) {
        e.preventDefault();
        $(this).addClass('dragover');
    });

    $dropZone.on('dragleave', function() {
        $(this).removeClass('dragover');
    });

    $dropZone.on('drop', function(e) {
        e.preventDefault();
        $(this).removeClass('dragover');
        const file = e.originalEvent.dataTransfer.files[0];
        handleFileSelect(file);
    });

    function handleFileSelect(file) {
        if (!file) return;

        // Aceptar .xlsx y .csv
        const name = file.name;
        const extension = name.split('.').pop().toLowerCase();

        if (extension !== 'csv' && extension !== 'xlsx') {
            alert('Por favor, seleccione un archivo Excel (.xlsx) o CSV (.csv).');
            $fileInput.val('');
            selectedCsvFile = null;
            $fileInfo.hide();
            $('#btnSiguientePrevisualizar').prop('disabled', true);
            return;
        }

        // Guardar archivo en memoria
        selectedCsvFile = file;

        // Mostrar detalles del archivo
        const sizeKB = (file.size / 1024).toFixed(2);
        const icon = extension === 'xlsx' ? '📊' : '📂';
        $fileInfo.html(`${icon} Archivo listo: <strong>${name}</strong> (${sizeKB} KB)`).fadeIn(200);
        $('#btnSiguientePrevisualizar').prop('disabled', false);
    }

    // Botón: Siguiente (Cargar y Previsualizar)
    $('#btnSiguientePrevisualizar').on('click', function() {
        if (!selectedCsvFile) return;

        const extension = selectedCsvFile.name.split('.').pop().toLowerCase();

        function renderPreview(visitors) {
            $('#importPreviewTableBody').empty();
            if (visitors.length > 0) {
                visitors.forEach(function(v) {
                    $('#importPreviewTableBody').append(renderPreviewRow(v));
                });
            } else {
                $('#importPreviewTableBody').append(renderPreviewRow({cedula: '', nombres: '', apellidos: '', fecha_nacimiento: ''}));
            }
            // Cambiar visibilidad de paneles
            $('#importUploadPanel').hide();
            $('#importUploadFooter').attr('style', 'display: none !important;');
            $('#importPreviewPanel').show();
            $('#importPreviewFooter').attr('style', 'display: flex !important;');
        }

        if (extension === 'xlsx') {
            // ── Parsear con SheetJS ──────────────────────────────────────────
            if (typeof XLSX === 'undefined') {
                alert('La librería de lectura Excel no está disponible. Intente recargar la página.');
                return;
            }
            const reader = new FileReader();
            reader.onload = function(e) {
                try {
                    const workbook  = XLSX.read(e.target.result, { type: 'array', cellDates: false });
                    const sheetName = workbook.SheetNames[0];
                    const sheet     = workbook.Sheets[sheetName];
                    // Convertir a array de arrays (sin encabezado, raw strings)
                    const rawRows = XLSX.utils.sheet_to_json(sheet, { header: 1, defval: '', raw: false });

                    const visitors = [];
                    for (let i = 1; i < rawRows.length; i++) { // i=1 salta la fila de cabecera
                        const row = rawRows[i];
                        const ced  = String(row[0] || '').trim();
                        const nom  = String(row[1] || '').trim();
                        const ape  = String(row[2] || '').trim();
                        const fNac = String(row[3] || '').trim();

                        // Omitir fila de ejemplo y filas vacías
                        if (ced === '12345678' && nom === 'Pedro' && ape === 'Pérez') continue;
                        if (nom === '' && ape === '') continue;

                        visitors.push({ cedula: ced, nombres: nom, apellidos: ape, fecha_nacimiento: sanitizeCsvDate(fNac) });
                    }
                    renderPreview(visitors);
                } catch (err) {
                    alert('No se pudo leer el archivo Excel. Asegúrese de que no esté dañado o protegido.\n\nDetalle: ' + err.message);
                }
            };
            reader.readAsArrayBuffer(selectedCsvFile);

        } else {
            // ── Parsear CSV (comportamiento existente) ───────────────────────
            const reader = new FileReader();
            reader.onload = function(e) {
                const visitors = parseCsvToJSON(e.target.result);
                renderPreview(visitors);
            };
            reader.readAsText(selectedCsvFile);
        }
    });

    // Botón: Atrás (Volver a carga de archivo)
    $('#btnAtrasUpload').on('click', function() {
        $('#importPreviewPanel').hide();
        $('#importPreviewFooter').attr('style', 'display: none !important;');
        $('#importUploadPanel').show();
        $('#importUploadFooter').attr('style', 'display: flex !important;');
    });

    // Botón: Añadir fila en blanco en la previsualización
    $('#btnAgregarVisitantePreview').on('click', function() {
        $('#importPreviewTableBody').append(renderPreviewRow({cedula: '', nombres: '', apellidos: '', fecha_nacimiento: ''}));
        const $tableContainer = $('#importPreviewTableBody').parent().parent();
        $tableContainer.scrollTop($tableContainer[0].scrollHeight);
    });

    // Eliminar fila de previsualización
    $(document).on('click', '.btn-remove-preview-visitor', function() {
        $(this).closest('tr').remove();
    });

    // Parsear texto del archivo CSV a JSON Array
    function parseCsvToJSON(text) {
        const lines = text.split(/\r?\n/);
        if (lines.length === 0) return [];

        let delimiter = ';';
        const firstLine = lines[0];
        if (firstLine.includes(',')) {
            const countSemicolon = (firstLine.match(/;/g) || []).length;
            const countComma = (firstLine.match(/,/g) || []).length;
            if (countComma > countSemicolon) {
                delimiter = ',';
            }
        }

        const visitors = [];

        for (let i = 0; i < lines.length; i++) {
            const line = lines[i].trim();
            if (line === '') continue;

            const fields = line.split(delimiter).map(f => f.trim().replace(/^["']|["']$/g, ''));
            if (fields.length < 3) continue;

            const ced = fields[0] || '';
            const nom = fields[1] || '';
            const ape = fields[2] || '';
            const fNac = fields[3] || '';

            // Omitir cabecera
            if (nom.toLowerCase() === 'nombres' || nom.toLowerCase() === 'nombre') continue;
            // Omitir fila de ejemplo
            if (ced === '12345678' && nom === 'Pedro' && ape === 'Pérez') continue;
            // Omitir vacíos
            if (nom === '' && ape === '') continue;

            visitors.push({
                cedula: ced,
                nombres: nom,
                apellidos: ape,
                fecha_nacimiento: fNac
            });
        }
        return visitors;
    }

    // Normalizar fechas de la plantilla para el control input[type=date]
    function sanitizeCsvDate(dateStr) {
        if (!dateStr) return '';
        dateStr = dateStr.trim();
        if (/^\d{4}-\d{2}-\d{2}$/.test(dateStr)) return dateStr;

        const parts = dateStr.split(/[\/-]/);
        if (parts.length === 3) {
            if (parts[0].length === 4) {
                return `${parts[0]}-${parts[1].padStart(2, '0')}-${parts[2].padStart(2, '0')}`;
            }
            if (parts[2].length === 4) {
                return `${parts[2]}-${parts[1].padStart(2, '0')}-${parts[0].padStart(2, '0')}`;
            }
        }
        return dateStr;
    }

    // Renderiza una fila en la tabla de previsualización
    function renderPreviewRow(v) {
        const dateVal = sanitizeCsvDate(v.fecha_nacimiento);
        return `
            <tr class="preview-visitor-row">
                <td style="padding-left: 10px;">
                    <input type="text" class="form-control form-premium-control p-1 text-center input-preview-cedula" 
                           value="${v.cedula || ''}" placeholder="Opcional" style="font-size:0.8rem; border-radius: 6px;">
                </td>
                <td>
                    <input type="text" class="form-control form-premium-control p-1 input-preview-nombres" 
                           value="${v.nombres || ''}" placeholder="Nombres" required style="font-size:0.8rem; border-radius: 6px;">
                </td>
                <td>
                    <input type="text" class="form-control form-premium-control p-1 input-preview-apellidos" 
                           value="${v.apellidos || ''}" placeholder="Apellidos" required style="font-size:0.8rem; border-radius: 6px;">
                </td>
                <td>
                    <input type="date" class="form-control form-premium-control p-1 input-preview-fecha" 
                           value="${dateVal}" required style="font-size:0.8rem; border-radius: 6px;">
                </td>
                <td>
                    <button type="button" class="btn-remove-preview-visitor btn-remove-contact" title="Quitar de la lista" style="width:24px; height:24px; font-size:10px; padding:0; display:flex; align-items:center; justify-content:center;">✕</button>
                </td>
            </tr>
        `;
    }

    // Submit del formulario de importación (envío final de datos previsualizados)
    $('#formImportarVisitantes').on('submit', function(e) {
        e.preventDefault();
        
        // Evitar submit si estamos en la pestaña 1
        if ($('#importPreviewPanel').is(':hidden')) {
            if (selectedCsvFile) {
                $('#btnSiguientePrevisualizar').click();
            }
            return;
        }

        const citaId = $('#import_cita_id').val();
        const visitorsList = [];
        let isValid = true;

        $('.preview-visitor-row').each(function() {
            const ced = $(this).find('.input-preview-cedula').val().trim();
            const nom = $(this).find('.input-preview-nombres').val().trim();
            const ape = $(this).find('.input-preview-apellidos').val().trim();
            const fNac = $(this).find('.input-preview-fecha').val().trim();

            if (nom === '' || ape === '' || fNac === '') {
                isValid = false;
                $(this).css('background-color', '#fff5f5');
            } else {
                $(this).css('background-color', '');
            }

            visitorsList.push({
                cedula: ced,
                nombres: nom,
                apellidos: ape,
                fecha_nacimiento: fNac
            });
        });

        if (!isValid) {
            alert('Por favor, complete todos los campos obligatorios (*) marcados en rojo.');
            return;
        }

        if (visitorsList.length === 0) {
            alert('Debe añadir al menos un visitante para importar.');
            return;
        }

        $btnImport.prop('disabled', true).text('Guardando listado...');

        $.ajax({
            url: 'controllers/CitaController.php?action=importar_visitantes',
            method: 'POST',
            data: {
                cita_id: citaId,
                visitantes_json: JSON.stringify(visitorsList)
            },
            dataType: 'json',
            success: function(response) {
                $btnImport.prop('disabled', false).text('Confirmar y Guardar');
                $('#modalImportarVisitantes').modal('hide');

                if (response.status === 'success') {
                    showGlobalAlert(response.message, 'success');
                    listarCitas();
                } else {
                    alert(response.message);
                }
            },
            error: function(xhr) {
                $btnImport.prop('disabled', false).text('Confirmar y Guardar');
                let errorMsg = 'Error en el servidor al guardar el listado de visitantes.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                alert(errorMsg);
            }
        });
    });

    // =========================================================================
    // 8. AUXILIARES
    // =========================================================================
    // BOTÓN: VER VISITANTES (Abre modal con listado de asistentes)
    let currentVisitorIds = [];

    function cargarVisitantesCita(id) {
        $('#tablaVerVisitantesBody').empty().append('<tr><td colspan="5" class="text-center py-4"><div class="spinner-border text-success" role="status"></div><br><small class="text-muted mt-2 d-block">Cargando listado de visitantes...</small></td></tr>');
        $('#paginacionModalVisitantes').hide();

        return $.ajax({
            url: `controllers/CitaController.php?action=listar_visitantes&cita_id=${id}`,
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                $('#tablaVerVisitantesBody').empty();
                currentVisitorIds = [];
                if (response.status === 'success') {
                    cacheModalVisitantes = response.data || [];
                    
                    cacheModalVisitantes.forEach(function(v) {
                        currentVisitorIds.push(parseInt(v.id));
                    });

                    filteredModalVisitantes = cacheModalVisitantes;
                    // Mantener en la misma página si es posible o ir a la 1
                    if (currentPageModalVisitantes < 1) {
                        currentPageModalVisitantes = 1;
                    }
                    
                    renderModalVisitantesTable();
                } else {
                    $('#tablaVerVisitantesBody').append('<tr><td colspan="5" class="text-center text-muted py-4">No se encontraron visitantes registrados para esta cita.</td></tr>');
                }
            },
            error: function(xhr) {
                let errorMsg = 'Error al cargar los visitantes.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                $('#tablaVerVisitantesBody').html(`<tr><td colspan="5" class="text-center text-danger py-4"><strong>✕ Error:</strong> ${errorMsg}</td></tr>`);
            }
        });
    }

    function renderModalVisitantesTable() {
        $('#tablaVerVisitantesBody').empty();
        const totalRecords = filteredModalVisitantes.length;

        if (totalRecords === 0) {
            $('#tablaVerVisitantesBody').append('<tr><td colspan="5" class="text-center text-muted py-4">No se encontraron visitantes registrados para esta cita.</td></tr>');
            $('#paginacionModalVisitantes').hide();
            return;
        }

        const totalPages = Math.ceil(totalRecords / itemsPerPageModal);

        if (currentPageModalVisitantes > totalPages) {
            currentPageModalVisitantes = totalPages;
        }
        if (currentPageModalVisitantes < 1) {
            currentPageModalVisitantes = 1;
        }

        const startIdx = (currentPageModalVisitantes - 1) * itemsPerPageModal;
        const endIdx = Math.min(startIdx + itemsPerPageModal, totalRecords);
        const pageData = filteredModalVisitantes.slice(startIdx, endIdx);

        pageData.forEach(function(v, idx) {
            const actualIdx = startIdx + idx + 1;
            const cedula = v.cedula ? v.cedula : '<span class="text-muted italic">Sin Cédula (Menor)</span>';
            const fechaNac = v.fecha_nacimiento ? formatDate(v.fecha_nacimiento) : '<span class="text-muted italic">N/E</span>';
            $('#tablaVerVisitantesBody').append(`
                <tr>
                    <td class="text-center fw-600">${actualIdx}</td>
                    <td><strong>${cedula}</strong></td>
                    <td>${v.nombres} ${v.apellidos}</td>
                    <td class="text-center">${fechaNac}</td>
                    <td class="text-center">
                        <button type="button" class="btn-quitar-visitante-asistencia btn-remove-contact" data-visitante-id="${v.id}" title="Remover de la asistencia" style="width:24px; height:24px; font-size:10px; padding:0; display:inline-flex; align-items:center; justify-content:center;">✕</button>
                    </td>
                </tr>
            `);
        });

        // Actualizar datos informativos
        $('#pagStartModalVisitantes').text(startIdx + 1);
        $('#pagEndModalVisitantes').text(endIdx);
        $('#pagTotalModalVisitantes').text(totalRecords);

        // Renderizar controles de paginación
        const $controls = $('#paginationControlsModalVisitantes');
        $controls.empty();

        const prevDisabled = currentPageModalVisitantes === 1 ? 'disabled' : '';
        $controls.append(`
            <li class="page-item ${prevDisabled}">
                <a class="page-link btn-pag-modal-visitantes" data-page="${currentPageModalVisitantes - 1}" href="javascript:void(0)">&laquo; Anterior</a>
            </li>
        `);

        // Botones de Páginas (Ventana Deslizante de Máximo 10)
        const maxVisibleModal = 10;
        let startPageModal = Math.max(1, currentPageModalVisitantes - Math.floor(maxVisibleModal / 2));
        let endPageModal = Math.min(totalPages, startPageModal + maxVisibleModal - 1);

        if (endPageModal - startPageModal + 1 < maxVisibleModal) {
            startPageModal = Math.max(1, endPageModal - maxVisibleModal + 1);
        }

        for (let i = startPageModal; i <= endPageModal; i++) {
            const activeClass = currentPageModalVisitantes === i ? 'active' : '';
            $controls.append(`
                <li class="page-item ${activeClass}">
                    <a class="page-link btn-pag-modal-visitantes" data-page="${i}" href="javascript:void(0)">${i}</a>
                </li>
            `);
        }

        const nextDisabled = currentPageModalVisitantes === totalPages ? 'disabled' : '';
        $controls.append(`
            <li class="page-item ${nextDisabled}">
                <a class="page-link btn-pag-modal-visitantes" data-page="${currentPageModalVisitantes + 1}" href="javascript:void(0)">Siguiente &raquo;</a>
            </li>
        `);

        $('#paginacionModalVisitantes').removeClass('d-none').show();
    }

    // Clicks en paginación del modal
    $(document).on('click', '.btn-pag-modal-visitantes', function(e) {
        e.preventDefault();
        const page = parseInt($(this).data('page'));
        if (page && page !== currentPageModalVisitantes) {
            currentPageModalVisitantes = page;
            renderModalVisitantesTable();
        }
    });

    $(document).on('click', '.btn-ver-visitantes', function() {
        const id = $(this).data('id');
        const name = $(this).data('name');
        
        $('#modalVerVisitantes').data('cita-id', id);
        $('#verVisitantesCitaNombreSpan').text(`#CIT-${id} - ${name}`);
        
        // Resetear y ocultar panel de asistencia
        $('#panelAgregarAsistencia').hide();
        if ($('#formRegistrarYAgregarAsistencia').length) {
            $('#formRegistrarYAgregarAsistencia')[0].reset();
        }
        $('#inputBuscarVisitantesSistema').val('');
        
        // Cargar
        cargarVisitantesCita(id);
        
        $('#modalVerVisitantes').modal('show');
    });

    // Alternar visibilidad de panelAgregarAsistencia
    $(document).on('click', '#btnToggleAgregarAsistencia', function() {
        const $panel = $('#panelAgregarAsistencia');
        if ($panel.is(':hidden')) {
            $panel.slideDown(250);
            
            // Forzar volver a la primera pestaña (Registrados)
            const firstTab = document.querySelector('#pills-existente-tab');
            if (firstTab) {
                bootstrap.Tab.getInstance(firstTab)?.show() || new bootstrap.Tab(firstTab).show();
            }
            
            cargarVisitantesSistemaChecklist();
        } else {
            $panel.slideUp(250);
        }
    });

    // Ocultar panelAgregarAsistencia
    $(document).on('click', '#btnCancelarAgregarAsistencia, #btnCancelarAgregarNuevoAsistencia', function() {
        $('#panelAgregarAsistencia').slideUp(250);
    });

    // Cargar todos los visitantes del sistema para el checklist
    function cargarVisitantesSistemaChecklist() {
        const $tbody = $('#listaVisitantesSistemaChecklist');
        $tbody.empty().append('<tr><td colspan="3" class="text-center py-3"><div class="spinner-border spinner-border-sm text-success" role="status"></div> Cargando visitantes...</td></tr>');

        $.ajax({
            url: 'controllers/VisitanteController.php?action=listar',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                $tbody.empty();
                if (response.status === 'success' && response.data.length > 0) {
                    response.data.forEach(function(v) {
                        const isAttending = currentVisitorIds.includes(parseInt(v.id));
                        const checkbox = isAttending 
                            ? `<input type="checkbox" checked disabled class="form-check-input">`
                            : `<input type="checkbox" value="${v.id}" class="form-check-input check-visitante-sistema">`;
                        
                        const badge = isAttending 
                            ? `<span class="badge badge-asistencia ms-2">En asistencia</span>`
                            : '';

                        const cedula = v.cedula ? v.cedula : '<span class="text-muted">Sin Cédula</span>';

                        $tbody.append(`
                            <tr class="fila-visitante-sistema" data-buscar="${v.cedula} ${v.nombres.toLowerCase()} ${v.apellidos.toLowerCase()}">
                                <td class="text-center">${checkbox}</td>
                                <td><strong>${cedula}</strong></td>
                                <td>${v.nombres} ${v.apellidos} ${badge}</td>
                            </tr>
                        `);
                    });
                } else {
                    $tbody.append('<tr><td colspan="3" class="text-center text-muted py-3">No hay visitantes registrados en el sistema.</td></tr>');
                }
            },
            error: function() {
                $tbody.html('<tr><td colspan="3" class="text-center text-danger py-3">Error al cargar visitantes del sistema.</td></tr>');
            }
        });
    }

    // Buscador en tiempo real de visitantes del sistema
    $(document).on('keyup', '#inputBuscarVisitantesSistema', function() {
        const term = $(this).val().toLowerCase().trim();
        $('.fila-visitante-sistema').each(function() {
            const searchData = $(this).data('buscar');
            if (searchData.includes(term)) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });

    // Guardar selección de asistencia
    $(document).on('click', '#btnGuardarSeleccionAsistencia', function() {
        const citaId = $('#modalVerVisitantes').data('cita-id');
        const selectedIds = [];
        
        $('.check-visitante-sistema:checked').each(function() {
            selectedIds.push($(this).val());
        });

        if (selectedIds.length === 0) {
            alert('Debe seleccionar al menos un visitante para agregar.');
            return;
        }

        const $btn = $(this);
        $btn.prop('disabled', true).text('Guardando...');

        $.ajax({
            url: 'controllers/CitaController.php?action=vincular_visitantes',
            method: 'POST',
            data: {
                cita_id: citaId,
                visitante_ids: selectedIds
            },
            dataType: 'json',
            success: function(response) {
                $btn.prop('disabled', false).text('Agregar Seleccionados');
                if (response.status === 'success') {
                    showGlobalAlert(response.message, 'success');
                    $('#panelAgregarAsistencia').slideUp(250);
                    cargarVisitantesCita(citaId);
                    listarCitas(); // Refrescar tabla del dashboard principal
                } else {
                    alert(response.message);
                }
            },
            error: function(xhr) {
                $btn.prop('disabled', false).text('Agregar Seleccionados');
                let errorMsg = 'Error al intentar vincular visitantes.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                alert(errorMsg);
            }
        });
    });

    // Registrar nuevo visitante y agregar a la asistencia
    $(document).on('submit', '#formRegistrarYAgregarAsistencia', function(e) {
        e.preventDefault();
        
        const citaId = $('#modalVerVisitantes').data('cita-id');
        const cedula = $('#reg_visitante_cedula').val().trim();
        const nombres = $('#reg_visitante_nombres').val().trim();
        const apellidos = $('#reg_visitante_apellidos').val().trim();
        const fechaNac = $('#reg_visitante_fecha_nac').val().trim();

        if (!nombres || !apellidos || !fechaNac) {
            alert('Por favor, rellene todos los campos obligatorios (*).');
            return;
        }

        const newVisitor = {
            cedula: cedula,
            nombres: nombres,
            apellidos: apellidos,
            fecha_nacimiento: fechaNac
        };

        const $btn = $('#btnRegistrarYAgregarAsistencia');
        $btn.prop('disabled', true).text('Registrando...');

        $.ajax({
            url: 'controllers/CitaController.php?action=importar_visitantes',
            method: 'POST',
            data: {
                cita_id: citaId,
                visitantes_json: JSON.stringify([newVisitor])
            },
            dataType: 'json',
            success: function(response) {
                $btn.prop('disabled', false).text('Registrar y Agregar');
                if (response.status === 'success') {
                    showGlobalAlert(response.message, 'success');
                    $('#formRegistrarYAgregarAsistencia')[0].reset();
                    $('#panelAgregarAsistencia').slideUp(250);
                    cargarVisitantesCita(citaId);
                    listarCitas(); // Refrescar tabla principal
                } else {
                    alert(response.message);
                }
            },
            error: function(xhr) {
                $btn.prop('disabled', false).text('Registrar y Agregar');
                let errorMsg = 'Error al registrar y agregar al visitante.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                alert(errorMsg);
            }
        });
    });

    // Remover visitante de la asistencia
    $(document).on('click', '.btn-quitar-visitante-asistencia', function() {
        const visitanteId = $(this).data('visitante-id');
        const $row = $(this).closest('tr');
        const name = $row.find('td').eq(2).text().trim();
        
        $('#quitar_asistente_visitante_id').val(visitanteId);
        $('#quitarAsistenteNombreSpan').text(name);
        $('#modalConfirmarEliminarAsistente').modal('show');
    });

    $(document).on('click', '#btnConfirmarQuitarAsistenteModal', function() {
        const citaId = $('#modalVerVisitantes').data('cita-id');
        const visitanteId = $('#quitar_asistente_visitante_id').val();
        const $btn = $(this);

        $btn.prop('disabled', true).text('Removiendo...');

        $.ajax({
            url: 'controllers/CitaController.php?action=desvincular_visitante',
            method: 'POST',
            data: {
                cita_id: citaId,
                visitante_id: visitanteId
            },
            dataType: 'json',
            success: function(response) {
                $btn.prop('disabled', false).text('Remover Asistencia');
                $('#modalConfirmarEliminarAsistente').modal('hide');
                
                if (response.status === 'success') {
                    showGlobalAlert(response.message, 'success');
                    cargarVisitantesCita(citaId);
                    listarCitas(); // Refrescar tabla principal
                } else {
                    alert(response.message);
                }
            },
            error: function(xhr) {
                $btn.prop('disabled', false).text('Remover Asistencia');
                $('#modalConfirmarEliminarAsistente').modal('hide');
                let errorMsg = 'Error al intentar remover al visitante de la asistencia.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                alert(errorMsg);
            }
        });
    });




    function showGlobalAlert(message, type) {
        const $alert = $('#alertFeedbackGlobal');
        $alert.removeClass('alert-toast-success alert-toast-danger')
              .addClass(type === 'success' ? 'alert-toast-success' : 'alert-toast-danger')
              .html(`<strong>${type === 'success' ? 'Éxito: ' : 'Error: '}</strong> ${message}`)
              .slideDown(250);
        
        setTimeout(function() {
            $alert.slideUp(250);
        }, 5000);
    }

    function formatDate(dateString) {
        if (!dateString) return '';
        const parts = dateString.split('-');
        if (parts.length !== 3) return dateString;
        return `${parts[2]}/${parts[1]}/${parts[0]}`;
    }

    function formatTime(timeString) {
        if (!timeString) return '';
        const parts = timeString.split(':');
        if (parts.length < 2) return timeString;
        
        let hour = parseInt(parts[0]);
        const minutes = parts[1];
        const ampm = hour >= 12 ? 'PM' : 'AM';
        
        hour = hour % 12;
        hour = hour ? hour : 12; // 0 debe ser 12
        
        return `${hour}:${minutes} ${ampm}`;
    }

    // =========================================================================
    // LÓGICA DE CAPACIDAD Y SUGERENCIAS DE FECHAS ALTERNATIVAS
    // =========================================================================
    function inicializarCalendarioReserva() {
        const calendarEl = document.getElementById('calendarReserva');
        if (!calendarEl) return;

        // Agrupar citas por fecha para calcular la densidad
        const citasPorFecha = {};
        cacheCitas.forEach(function(c) {
            citasPorFecha[c.fecha_visita] = (citasPorFecha[c.fecha_visita] || 0) + 1;
        });

        // Eventos para el calendario de reserva
        const events = cacheCitas.map(function(c) {
            return {
                title: `${c.razon_social}`,
                start: `${c.fecha_visita}T${c.hora_inicio}`,
                className: `fc-event-status-${c.estatus || 'pendiente'}`
            };
        });

        if (bookingCalendar) {
            bookingCalendar.destroy();
        }

        bookingCalendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            locale: 'es',
            firstDay: 1, // Lunes
            headerToolbar: {
                left: 'prev,next',
                center: 'title',
                right: 'today'
            },
            buttonText: {
                today: 'Hoy'
            },
            dayMaxEvents: 3, // límite de eventos por celda (máximo 3, si hay más muestra link)
            slotEventOverlap: false, // evitar superposición en vistas de tiempo
            height: 'auto',
            moreLinkText: 'Ver más',
            events: events,
            selectable: true,
            dateClick: function(info) {
                // Rellenar fecha al hacer clic en un día
                $('#fecha_visita').val(info.dateStr).trigger('change');
            },
            dayCellDidMount: function(info) {
                const dateStr = info.date.toLocaleDateString('sv-SE');
                const count = citasPorFecha[dateStr] || 0;
                
                if (count === 1) {
                    info.el.classList.add('fc-day-density-low');
                } else if (count === 2) {
                    info.el.classList.add('fc-day-density-medium');
                } else if (count >= 3) {
                    info.el.classList.add('fc-day-density-high');
                }
            }
        });

        bookingCalendar.render();

        // Validar capacidad de la fecha precargada si existe
        const currentVal = $('#fecha_visita').val();
        if (currentVal) {
            validarCapacidadFecha(currentVal);
        }
    }

    $(document).on('change', '#fecha_visita', function() {
        const fechaSeleccionada = $(this).val();
        validarCapacidadFecha(fechaSeleccionada);
    });

    $(document).on('click', '.booking-suggestion-btn', function() {
        const fecha = $(this).data('fecha');
        $('#fecha_visita').val(fecha).trigger('change');
        if (bookingCalendar) {
            bookingCalendar.gotoDate(fecha);
        }
    });

    function validarCapacidadFecha(fecha) {
        const $alertDiv = $('#bookingCapacityAlert');
        if (!fecha) {
            $alertDiv.hide().empty();
            return;
        }

        // Buscar citas agendadas para este día
        const citasDelDia = cacheCitas.filter(function(c) {
            return c.fecha_visita === fecha;
        });

        const count = citasDelDia.length;
        let alertHtml = '';

        if (count === 0) {
            alertHtml = `
                <div class="booking-capacity-warning info">
                    <div class="booking-capacity-title">
                        <svg viewBox="0 0 24 24" style="width:16px; height:16px; fill: currentColor; margin-right: 4px; vertical-align: text-bottom;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                        Día Disponible para Agendar
                    </div>
                    <span>No hay citas programadas para el <strong>${formatLongDate(fecha)}</strong>. Capacidad libre.</span>
                </div>
            `;
            $alertDiv.html(alertHtml).fadeIn(200);
        } else {
            // Generar listado de citas existentes
            let citasHtml = `
                <div class="booking-existing-appointments">
                    <strong style="display: block; margin-bottom: 4px;">Citas existentes para este día:</strong>
            `;
            citasDelDia.forEach(function(c) {
                citasHtml += `
                    <div class="booking-existing-item">
                        • <strong>${formatTime(c.hora_inicio)}</strong> - ${c.razon_social} (${c.recorrido_nombre || 'Libre'})
                    </div>
                `;
            });
            citasHtml += `</div>`;

            if (count < 3) {
                alertHtml = `
                    <div class="booking-capacity-warning warning" style="background-color: #F0FDF4; border-color: #BBF7D0; color: #166534;">
                        <div class="booking-capacity-title" style="color: #166534;">
                            <svg viewBox="0 0 24 24" style="width:16px; height:16px; fill: currentColor; margin-right: 4px; vertical-align: text-bottom;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                            Ocupación Moderada (${count} de 3 citas diarias)
                        </div>
                        <span>El día seleccionado tiene disponibilidad. Puede agendar cuidando no colisionar las horas.</span>
                        ${citasHtml}
                    </div>
                `;
                $alertDiv.html(alertHtml).fadeIn(200);
            } else {
                // Advertencia de sobrecarga: count >= 3
                const sugerencias = obtenerSugerenciasFechas(fecha, cacheCitas);
                let sugerenciasHtml = '';

                if (sugerencias.length > 0) {
                    sugerenciasHtml = `
                        <div style="margin-top: 10px;">
                            <strong style="display: block; margin-bottom: 6px;">Fechas alternativas recomendadas (con menor ocupación):</strong>
                            <div class="booking-suggestions-list">
                    `;
                    sugerencias.forEach(function(sug) {
                        const labelOcupacion = sug.cantidad === 0 ? 'Libre' : (sug.cantidad === 1 ? '1 cita' : `${sug.cantidad} citas`);
                        const colorClass = sug.cantidad === 0 ? 'text-success' : 'text-warning';
                        sugerenciasHtml += `
                            <button type="button" class="booking-suggestion-btn ${colorClass}" data-fecha="${sug.fecha}">
                                📅 ${formatShortDate(sug.fecha)} (${labelOcupacion})
                            </button>
                        `;
                    });
                    sugerenciasHtml += `
                            </div>
                        </div>
                    `;
                } else {
                    sugerenciasHtml = `
                        <div style="margin-top: 10px; font-style: italic; font-size: 0.8rem;">
                            No se encontraron días hábiles alternativos con disponibilidad en las próximas semanas.
                        </div>
                    `;
                }

                alertHtml = `
                    <div class="booking-capacity-warning warning">
                        <div class="booking-capacity-title">
                            <svg viewBox="0 0 24 24" style="width:16px; height:16px; fill: currentColor; margin-right: 4px; vertical-align: text-bottom;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                            Capacidad Recomendada Excedida (${count} citas)
                        </div>
                        <span style="display: block; margin-bottom: 8px;">Se sugiere evitar agendar más de 3 citas al día para no sobrecargar al personal. Sin embargo, puede proceder si lo considera conveniente.</span>
                        ${sugerenciasHtml}
                        ${citasHtml}
                    </div>
                `;
                $alertDiv.html(alertHtml).fadeIn(200);
            }
        }
    }

    function obtenerSugerenciasFechas(fechaSeleccionadaStr, citas) {
        const selectDate = new Date(fechaSeleccionadaStr + 'T00:00:00');
        const hoy = new Date();
        hoy.setHours(0,0,0,0);

        // Agrupar citas por fecha
        const citasPorFecha = {};
        citas.forEach(function(c) {
            citasPorFecha[c.fecha_visita] = (citasPorFecha[c.fecha_visita] || 0) + 1;
        });

        function obtenerCandidatosParaRango(limiteDias) {
            const candidatos = [];
            const offsets = [];
            // Generar offsets intercalados: 1, -1, 2, -2, etc.
            for (let i = 1; i <= limiteDias; i++) {
                offsets.push(i);
                offsets.push(-i);
            }

            offsets.forEach(function(offset) {
                const tempDate = new Date(selectDate);
                tempDate.setDate(selectDate.getDate() + offset);
                tempDate.setHours(0,0,0,0);

                // No recomendar fechas pasadas
                if (tempDate < hoy) return;

                // Excluir fines de semana (0 = Domingo, 6 = Sábado)
                const dayOfWeek = tempDate.getDay();
                if (dayOfWeek === 0 || dayOfWeek === 6) return;

                const tempDateStr = tempDate.toLocaleDateString('sv-SE');
                const count = citasPorFecha[tempDateStr] || 0;

                if (count < 3) {
                    candidatos.push({
                        fecha: tempDateStr,
                        cantidad: count,
                        distancia: Math.abs(offset)
                    });
                }
            });

            // Ordenar por cercanía (distancia absoluta)
            candidatos.sort(function(a, b) {
                if (a.distancia !== b.distancia) {
                    return a.distancia - b.distancia;
                }
                return new Date(a.fecha) - new Date(b.fecha);
            });

            return candidatos;
        }

        // Buscar primero en ±3 días hábiles
        let sugerencias = obtenerCandidatosParaRango(3);

        // Si no hay candidatos, ampliar a ±7 días hábiles
        if (sugerencias.length === 0) {
            sugerencias = obtenerCandidatosParaRango(7);
        }

        return sugerencias.slice(0, 3);
    }

    function formatShortDate(dateStr) {
        const parts = dateStr.split('-');
        if (parts.length !== 3) return dateStr;
        const d = new Date(parts[0], parts[1] - 1, parts[2]);
        const diasSemana = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
        const dayName = diasSemana[d.getDay()];
        return `${dayName} ${parts[2]}/${parts[1]}`;
    }

    function formatLongDate(dateStr) {
        const parts = dateStr.split('-');
        if (parts.length !== 3) return dateStr;
        const d = new Date(parts[0], parts[1] - 1, parts[2]);
        const opciones = { weekday: 'long', day: 'numeric', month: 'long' };
        let formatted = d.toLocaleDateString('es-ES', opciones);
        return formatted.charAt(0).toUpperCase() + formatted.slice(1);
    }

    function formatTime(timeString) {
        if (!timeString) return '';
        const parts = timeString.split(':');
        if (parts.length < 2) return timeString;
        
        let hour = parseInt(parts[0]);
        const minutes = parts[1];
        const ampm = hour >= 12 ? 'PM' : 'AM';
        
        hour = hour % 12;
        hour = hour ? hour : 12;
        
        return `${hour}:${minutes} ${ampm}`;
    }
});
