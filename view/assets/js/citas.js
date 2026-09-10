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

            const nombreInst = c.nombre_completo || c.razon_social || '';
            let sedeInfo = '';
            if (c.sede_ciudad || c.sede_estado) {
                sedeInfo = `<div class="mt-1"><span class="badge bg-light text-dark border" style="font-size:0.7rem;">Sede: ${c.sede_ciudad || c.sede_estado}</span> <small class="text-muted">${c.sede_detalles || ''}</small></div>`;
            } else if (c.estado) {
                sedeInfo = `<div class="mt-1"><small class="text-muted">${c.estado}, ${c.ciudad || ''}</small></div>`;
            }

            const rowHtml = `
                <tr class="cita-row">
                    <td><strong>#CIT-${c.id}</strong></td>
                    <td>
                        <span style="white-space: nowrap;"><strong>${c.tipo_documento}-${c.RIF}</strong></span><br>
                        <strong class="text-success" style="font-size:0.88rem;">${nombreInst}</strong>
                        ${sedeInfo}
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
                            <button class="btn-action btn-action-delete btn-eliminar-cita" data-id="${c.id}" data-name="${nombreInst}" title="Eliminar Cita">
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
        if (typeof window.limpiarErroresFormulario === 'function') {
            window.limpiarErroresFormulario('#formCrearCita');
        } else {
            $('#formCrearCita').find('.is-invalid').removeClass('is-valid');
            $('#formCrearCita').find('.invalid-feedback').hide().text('');
        }
    });

    $('#modalCrearCita').on('shown.bs.modal', function () {
        setTimeout(function() {
            $('#buscar_institucion_autocomplete').trigger('focus');
        }, 100);
    });

    function resetWizard() {
        currentStep = 1;
        selectedInstitutionId = null;
        $('#formCrearCita')[0].reset();
        
        // Limpiar campos ocultos
        $('#final_institucion_id').val('');
        $('#final_sede_id').val('');
        $('#final_crear_nueva_sede').val('0');
        $('#final_nueva_sede_ciudad_id').val('');
        $('#final_nueva_sede_parroquia_id').val('');
        $('#final_nueva_sede_detalles').val('');
        $('#final_tipo_documento').val('');
        $('#final_documento').val('');

        // Ocultar selectores y formularios secundarios
        $('#wizardSedesSelectionBox').hide();
        $('#wizardSedesRadioList').empty();
        $('#wizardNuevaSedeForm').hide();
        $('#nueva_institucion_rif_row').hide();
        
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
        $('#btnWizardNext').prop('disabled', true).hide();

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
            
            // Si ya hay una institución seleccionada, registrada o en proceso de búsqueda válida, mantener Siguiente visible y habilitado
            const instId = $('#final_institucion_id').val() || selectedInstitutionId;
            const isNuevaInst = $('#nueva_institucion_rif_row').is(':visible');
            const hasQuery = $.trim($('#buscar_institucion_autocomplete').val()).length >= 2;

            if (instId || isNuevaInst || hasQuery) {
                $('#btnWizardNext').text('Siguiente').prop('disabled', false).show();
            } else {
                $('#btnWizardNext').text('Siguiente').prop('disabled', true).hide();
            }
        } else {
            $('#btnWizardPrev').show();
            $('#btnWizardNext').prop('disabled', false).show();
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

    // Asegurar que el contenedor de sugerencias resida directamente en el body para evitar overflow del modal
    if ($('#autocomplete_suggestions').parent().is(':not(body)')) {
        $('body').append($('#autocomplete_suggestions'));
    }

    // Función para posicionar dinámicamente las sugerencias fuera de las restricciones del modal
    function posicionarAutocompleteSuggestions() {
        const $input = $('#buscar_institucion_autocomplete');
        const $suggestions = $('#autocomplete_suggestions');
        if (!$input.length || !$suggestions.length) return;

        const rect = $input[0].getBoundingClientRect();
        if (rect.width === 0 && rect.height === 0) return;

        $suggestions.css({
            position: 'fixed',
            top: (rect.bottom + 4) + 'px',
            left: rect.left + 'px',
            width: rect.width + 'px',
            zIndex: 9999999
        });
    }

    $(window).on('resize scroll', function() {
        if ($('#autocomplete_suggestions').is(':visible')) {
            posicionarAutocompleteSuggestions();
        }
    });

    $(document).on('scroll', '#modalCrearCita, #modalCrearCita .modal-body', function() {
        if ($('#autocomplete_suggestions').is(':visible')) {
            posicionarAutocompleteSuggestions();
        }
    });

    $('#modalCrearCita').on('hidden.bs.modal', function() {
        $('#autocomplete_suggestions').hide().empty();
    });

    $('#buscar_institucion_autocomplete').on('input', function() {
        const query = $(this).val().trim();
        const $suggestions = $('#autocomplete_suggestions');
        const $statusBox = $('#rifCheckStatus');

        clearTimeout(searchTimeout);
        $statusBox.hide();

        if (query.length < 2) {
            $suggestions.hide().empty();
            selectedInstitutionId = null;
            $('#final_institucion_id').val('');
            $('#wizardSedesSelectionBox').hide();
            $('#wizardNuevaSedeForm').hide();
            $('#nueva_institucion_rif_row').hide();
            $('#btnWizardNext').prop('disabled', true).hide();
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
                                        <div class="fw-bold text-success">${inst.nombre_completo || inst.nombre}</div>
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
                        posicionarAutocompleteSuggestions();
                        $suggestions.show();
                        posicionarAutocompleteSuggestions();
                    }
                },
                error: function() {
                    console.error('Error al realizar búsqueda de autocompletado');
                }
            });
        }, 300);
    });

    $('#buscar_institucion_autocomplete').on('focus', function() {
        if ($(this).val().trim().length >= 2 && $('#autocomplete_suggestions').children().length > 0) {
            posicionarAutocompleteSuggestions();
            $('#autocomplete_suggestions').show();
            posicionarAutocompleteSuggestions();
        }
    });

    // Manejar selección de sugerencia
    $(document).on('click', '.autocomplete-item', function() {
        const inst = $(this).data('json');
        selectedInstitutionId = inst.id;
        const nombreInst = inst.nombre_completo || inst.nombre || '';
        
        $('#buscar_institucion_autocomplete').val(`${inst.tipo_documento}-${inst.documento} - ${nombreInst}`);
        $('#autocomplete_suggestions').hide().empty();

        // Rellenar datos
        $('#final_tipo_documento').val(inst.tipo_documento);
        $('#final_documento').val(inst.documento);
        $('#final_institucion_id').val(selectedInstitutionId);

        prellenarDatosInstitucion(inst);
        $('#nueva_institucion_rif_row').hide();

        // Cargar y mostrar selector de Sedes en Paso 1
        cargarSedesParaInstitucion(inst.id);

        $('#rifCheckStatus').removeClass('alert-danger alert-warning')
                            .addClass('alert-success')
                            .html(`<strong>✓ Institución Seleccionada:</strong> ${nombreInst}. Verifique o seleccione la sede deseada y presione Siguiente.`)
                            .fadeIn(200);

        $('#btnWizardNext').prop('disabled', false).show();
    });

    // Cargar sedes para institución existente
    function cargarSedesParaInstitucion(institucionId) {
        const $box = $('#wizardSedesSelectionBox');
        const $list = $('#wizardSedesRadioList');
        $box.show();
        $list.empty().append('<div class="text-muted"><small>Cargando sedes registradas...</small></div>');
        $('#wizardNuevaSedeForm').hide();
        $('#final_crear_nueva_sede').val('0');

        $.ajax({
            url: `controllers/InstitucionController.php?action=obtener_sedes&institucion_id=${institucionId}`,
            method: 'GET',
            dataType: 'json',
            success: function(resp) {
                $list.empty();
                if (resp.status === 'success' && resp.data && resp.data.length > 0) {
                    resp.data.forEach(function(s, idx) {
                        const isChecked = idx === 0 ? 'checked' : '';
                        if (idx === 0) {
                            $('#final_sede_id').val(s.id);
                        }
                        const num = idx + 1;
                        const labelDetails = s.detalles ? ` - <small class="text-muted">${s.detalles}</small>` : '';
                        const itemHtml = `
                            <div class="form-check p-2 bg-white border rounded">
                                <input class="form-check-input radio-sede-item" type="radio" name="wizard_selected_sede" id="sede_opt_${s.id}" value="${s.id}" data-sede='${JSON.stringify(s).replace(/'/g, "&apos;")}' ${isChecked}>
                                <label class="form-check-label w-100 cursor-pointer" for="sede_opt_${s.id}">
                                    <span class="badge bg-success me-1">Sede #${num}</span>
                                    <strong>${s.estado}, ${s.ciudad} (${s.parroquia})</strong>
                                    ${labelDetails}
                                </label>
                            </div>
                        `;
                        $list.append(itemHtml);
                    });
                } else {
                    $list.append('<div class="text-muted"><small>No hay sedes registradas para esta institución. Puede agregar una nueva sede a continuación.</small></div>');
                    $('#final_sede_id').val('');
                }
            }
        });
    }

    $(document).on('change', '.radio-sede-item', function() {
        const sedeId = $(this).val();
        $('#final_sede_id').val(sedeId);
        const sedeData = $(this).data('sede');
        if (sedeData) {
            $('#estado_id').empty().append(`<option value="${sedeData.id_estado || ''}" selected>${sedeData.estado || 'Estado Pre-cargado'}</option>`).prop('disabled', true);
            $('#municipio_id').empty().append(`<option value="${sedeData.id_municipio || ''}" selected>${sedeData.municipio || 'Municipio Pre-cargado'}</option>`).prop('disabled', true);
            $('#ciudad_id').empty().append(`<option value="${sedeData.ciudad_id || ''}" selected>${sedeData.ciudad || 'Ciudad Pre-cargada'}</option>`).prop('disabled', true);
            $('#parroquia_id').empty().append(`<option value="${sedeData.parroquia_id || ''}" selected>${sedeData.parroquia || 'Parroquia Pre-cargada'}</option>`).prop('disabled', true);
            $('#detalles_direccion').val(sedeData.detalles || sedeData.detalles_direccion || '').prop('disabled', true);
        }
    });

    // Toggle para agregar nueva sede en cita
    $('#btnToggleNuevaSedeCita').on('click', function() {
        const $form = $('#wizardNuevaSedeForm');
        if ($form.is(':visible')) {
            $form.slideUp(200);
            $('#final_crear_nueva_sede').val('0');
            $('.radio-sede-item').prop('disabled', false);
            const firstRadio = $('.radio-sede-item:first');
            if (firstRadio.length) {
                firstRadio.prop('checked', true);
                $('#final_sede_id').val(firstRadio.val());
            }
        } else {
            $form.slideDown(200);
            $('#final_crear_nueva_sede').val('1');
            $('.radio-sede-item').prop('checked', false).prop('disabled', true);
            $('#final_sede_id').val('');
            cargarEstadosNuevaSedeCita();
        }
    });

    function cargarEstadosNuevaSedeCita() {
        const $selectEstado = $('#nueva_sede_cita_estado');
        $.ajax({
            url: 'controllers/LocationController.php?action=estados',
            method: 'GET',
            dataType: 'json',
            success: function(resp) {
                if (resp.status === 'success') {
                    $selectEstado.empty().append('<option value="">Seleccione Estado</option>');
                    resp.data.forEach(function(est) {
                        $selectEstado.append(`<option value="${est.id_estado}">${est.estado}</option>`);
                    });
                }
            }
        });
    }

    $('#nueva_sede_cita_estado').on('change', function() {
        const estadoId = $(this).val();
        const $selectMun = $('#nueva_sede_cita_municipio');
        const $selectCiu = $('#nueva_sede_cita_ciudad');
        const $selectPar = $('#nueva_sede_cita_parroquia');

        $selectMun.empty().append('<option value="">Seleccione Municipio</option>').prop('disabled', true);
        $selectCiu.empty().append('<option value="">Seleccione Ciudad</option>').prop('disabled', true);
        $selectPar.empty().append('<option value="">Seleccione Parroquia</option>').prop('disabled', true);

        if (!estadoId) return;

        $.ajax({
            url: `controllers/LocationController.php?action=ciudades&estado_id=${estadoId}`,
            method: 'GET',
            dataType: 'json',
            success: function(resp) {
                if (resp.status === 'success') {
                    $selectCiu.empty().append('<option value="">Seleccione Ciudad</option>');
                    resp.data.forEach(function(c) {
                        $selectCiu.append(`<option value="${c.id_ciudad}">${c.ciudad}</option>`);
                    });
                    $selectCiu.prop('disabled', false);
                }
            }
        });

        $.ajax({
            url: `controllers/LocationController.php?action=municipios&estado_id=${estadoId}`,
            method: 'GET',
            dataType: 'json',
            success: function(resp) {
                if (resp.status === 'success') {
                    $selectMun.empty().append('<option value="">Seleccione Municipio</option>');
                    resp.data.forEach(function(m) {
                        $selectMun.append(`<option value="${m.id_municipio}">${m.municipio}</option>`);
                    });
                    $selectMun.prop('disabled', false);
                }
            }
        });
    });

    $('#nueva_sede_cita_municipio').on('change', function() {
        const munId = $(this).val();
        const $selectPar = $('#nueva_sede_cita_parroquia');
        $selectPar.empty().append('<option value="">Seleccione Parroquia</option>').prop('disabled', true);

        if (!munId) return;

        $.ajax({
            url: `controllers/LocationController.php?action=parroquias&municipio_id=${munId}`,
            method: 'GET',
            dataType: 'json',
            success: function(resp) {
                if (resp.status === 'success') {
                    $selectPar.empty().append('<option value="">Seleccione Parroquia</option>');
                    resp.data.forEach(function(p) {
                        $selectPar.append(`<option value="${p.id_parroquia}">${p.parroquia}</option>`);
                    });
                    $selectPar.prop('disabled', false);
                }
            }
        });
    });

    $('#nueva_sede_cita_ciudad').on('change', function() {
        $('#final_nueva_sede_ciudad_id').val($(this).val());
    });
    $('#nueva_sede_cita_parroquia').on('change', function() {
        $('#final_nueva_sede_parroquia_id').val($(this).val());
    });
    $('#nueva_sede_cita_detalles').on('input', function() {
        $('#final_nueva_sede_detalles').val($(this).val());
    });

    // Función modular para iniciar el registro de una nueva institución al vuelo
    function iniciarRegistroNuevaInstitucion(customQuery) {
        const query = (customQuery || $('#buscar_institucion_autocomplete').val()).trim();
        $('#autocomplete_suggestions').hide().empty();
        $('#wizardSedesSelectionBox').hide();

        selectedInstitutionId = null;
        $('#final_institucion_id').val('');
        $('#final_sede_id').val('');
        $('#final_crear_nueva_sede').val('0');
        
        let docPart = '';
        let tipoDocPart = 'J';
        let nombrePart = '';

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

        $('#tipo_documento_inst').val(tipoDocPart);
        $('#documento_inst').val(docPart);
        $('#nueva_institucion_rif_row').show();

        $('#tipo_documento_inst').off('change').on('change', function() {
            $('#final_tipo_documento').val($(this).val());
        });
        $('#documento_inst').off('input').on('input', function() {
            $('#final_documento').val($(this).val().replace(/[^0-9]/g, ''));
        });

        if (nombrePart) {
            $('#nombre_institucion').val(nombrePart);
        }

        $('#rifCheckStatus').removeClass('alert-danger alert-success')
                            .addClass('alert-warning')
                            .html('<strong>⚠ Institución no registrada:</strong> Ingrese los datos y la sede de la nueva institución en el siguiente paso.')
                            .fadeIn(200);

        $('#btnWizardNext').prop('disabled', false).show();

        setTimeout(function() {
            currentStep = 2;
            showStep(currentStep);
        }, 800);
    }

    // Manejar registrar nueva institución desde autocompletado
    $(document).on('click', '#btn_new_inst_autocomplete', function() {
        iniciarRegistroNuevaInstitucion();
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
            const query = $.trim($('#buscar_institucion_autocomplete').val());
            const instId = $('#final_institucion_id').val();
            const isNuevaInst = $('#nueva_institucion_rif_row').is(':visible');
            
            if (!instId && !isNuevaInst && !query) {
                window.marcarErrorInput('#buscar_institucion_autocomplete', 'Por favor busque una institución existente o ingrese el nombre para registrarla.', true);
                return;
            }

            // Si escribió un nombre o RIF pero no ha seleccionado nada del autocompletado ni está en modo nueva
            if (!instId && !isNuevaInst && query) {
                if ($('#btn_new_inst_autocomplete').length) {
                    $('#btn_new_inst_autocomplete').trigger('click');
                } else {
                    iniciarRegistroNuevaInstitucion(query);
                }
                return;
            }

            // Ir al paso 2
            currentStep = 2;
            showStep(currentStep);
        } else if (currentStep === 2) {
            // Validaciones Paso 2 usando jquery-validate
            if (!$('#formCrearCita').valid()) {
                return;
            }

            // Validar contactos si es nueva institución
            if ($('#nueva_institucion_rif_row').is(':visible')) {
                let contactError = false;
                let $contactInputErr = null;
                let contactErrorMsg = '';

                const $contactRows = $('#contactsContainer .contact-row');
                if ($contactRows.length === 0) {
                    window.marcarErrorInput('#btnAddNewContact', 'Debe agregar al menos un número o correo de contacto.', true);
                    return;
                }

                $contactRows.each(function() {
                    const $input = $(this).find('.contact-input');
                    const val = $.trim($input.val());
                    const tipo = $(this).find('select').val();

                    if (!val) {
                        contactError = true;
                        $contactInputErr = $input;
                        contactErrorMsg = 'Debe ingresar el número de teléfono o correo de contacto.';
                        return false;
                    }

                    if (tipo === 'correo') {
                        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                        if (!emailRegex.test(val)) {
                            contactError = true;
                            $contactInputErr = $input;
                            contactErrorMsg = 'El correo electrónico no tiene un formato válido.';
                            return false;
                        }
                    } else {
                        const cleanDigits = val.replace(/[^0-9]/g, '');
                        if (/[a-zA-Z]/.test(val) || cleanDigits.length < 7 || cleanDigits.length > 15) {
                            contactError = true;
                            $contactInputErr = $input;
                            contactErrorMsg = 'El teléfono debe contener entre 7 y 15 dígitos numéricos.';
                            return false;
                        }
                    }
                });

                if (contactError && $contactInputErr) {
                    window.marcarErrorInput($contactInputErr, contactErrorMsg, true);
                    return;
                }
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

    // Permitir clic en los indicadores circulares ya completados para retroceder directamente
    $(document).on('click', '.step-wizard-item.completed', function() {
        const targetStep = $(this).index() + 1;
        if (targetStep < currentStep) {
            currentStep = targetStep;
            showStep(currentStep);
        }
    });

    function prellenarDatosInstitucion(data) {
        const nombreInst = data.nombre_completo || data.nombre || '';
        $('#nombre_institucion').val(nombreInst).prop('disabled', true);
        
        // Agregar dinámicamente la opción si no existe en el select
        if (data.tipo_organizacion && $('#tipo_organizacion option[value="' + data.tipo_organizacion + '"]').length === 0) {
            $('#tipo_organizacion').append(`<option value="${data.tipo_organizacion}">${data.tipo_organizacion}</option>`);
        }
        $('#tipo_organizacion').val(data.tipo_organizacion).prop('disabled', true);
        $('#tipo_gestion').val(data.tipo_gestion || 'publica').prop('disabled', true);
        
        // Si tiene sedes, prellenar la primera sede
        const primerSede = (data.sedes && data.sedes.length > 0) ? data.sedes[0] : data;
        $('#estado_id').empty().append(`<option value="${primerSede.id_estado || ''}" selected>${primerSede.estado || 'Estado Pre-cargado'}</option>`).prop('disabled', true);
        $('#municipio_id').empty().append(`<option value="${primerSede.id_municipio || ''}" selected>${primerSede.municipio || 'Municipio Pre-cargado'}</option>`).prop('disabled', true);
        $('#ciudad_id').empty().append(`<option value="${primerSede.ciudad_id || ''}" selected>${primerSede.ciudad || 'Ciudad Pre-cargada'}</option>`).prop('disabled', true);
        $('#parroquia_id').empty().append(`<option value="${primerSede.parroquia_id || ''}" selected>${primerSede.parroquia || 'Parroquia Pre-cargada'}</option>`).prop('disabled', true);
        
        $('#detalles_direccion').val(primerSede.detalles || primerSede.detalles_direccion || '').prop('disabled', true);

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
        agregarFilaContacto('correo');
        $('#btnAddNewContact').show();
    }

    // =========================================================================
    // 4. DIRECCIÓN DE CONTACTOS (FILAS DINÁMICAS EN WIZARD)
    // =========================================================================
    let contactCount = 0;
    
    function agregarFilaContacto(forcedType = '') {
        contactCount++;
        const isPrincipal = forcedType === 'principal' || $('#contactsContainer .contact-row').length === 0;
        const typeSelected = forcedType ? forcedType : (isPrincipal ? 'principal' : 'secundario');
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
                    window.mapearErrorServidorAFormulario('#formCrearCita', response.message);
                }
            },
            error: function(xhr) {
                $btn.prop('disabled', false).text('Agendar Cita');
                let errorMsg = 'Error en el servidor al registrar la cita.';
                let field = null;
                if (xhr.responseJSON) {
                    if (xhr.responseJSON.message) errorMsg = xhr.responseJSON.message;
                    if (xhr.responseJSON.field) field = xhr.responseJSON.field;
                }
                window.mapearErrorServidorAFormulario('#formCrearCita', errorMsg, field);
            }
        });
    }

    // =========================================================================
    // 5.5 EDICIÓN DE CITA (GET + POST)
    // =========================================================================
    $(document).on('click', '.btn-editar-cita', function() {
        const id = $(this).data('id');
        
        $('#formEditarCita')[0].reset();
        const $sedeSelect = $('#edit_sede_id');
        $sedeSelect.empty().append('<option value="">Cargando sedes...</option>');

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
                    
                    let hora = cita.hora_inicio;
                    if (hora && hora.length > 5) {
                        hora = hora.substring(0, 5);
                    }
                    $('#edit_hora_inicio').val(hora);
                    
                    $('#edit_metodo_pago').val(cita.metodo_pago);
                    $('#edit_pago_estado').val(cita.pago_estado);
                    $('#edit_estatus').val(cita.estatus);

                    // Cargar sedes de la institución
                    if (cita.institucion_id) {
                        $.ajax({
                            url: `controllers/InstitucionController.php?action=obtener_sedes&institucion_id=${cita.institucion_id}`,
                            method: 'GET',
                            dataType: 'json',
                            success: function(sResp) {
                                $sedeSelect.empty().append('<option value="">Seleccione Sede</option>');
                                if (sResp.status === 'success' && sResp.data) {
                                    sResp.data.forEach(function(s, idx) {
                                        const sel = (cita.sede_id && cita.sede_id == s.id) ? 'selected' : '';
                                        const det = s.detalles ? ' - ' + s.detalles : '';
                                        $sedeSelect.append(`<option value="${s.id}" ${sel}>Sede #${idx+1}: ${s.estado}, ${s.ciudad} (${s.parroquia})${det}</option>`);
                                    });
                                }
                            }
                        });
                    }

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
                    window.mapearErrorServidorAFormulario('#formEditarCita', response.message);
                }
            },
            error: function(xhr) {
                $btn.prop('disabled', false).text('Guardar Cambios');
                let errorMsg = 'Error en el servidor al intentar actualizar la cita.';
                let field = null;
                if (xhr.responseJSON) {
                    if (xhr.responseJSON.message) errorMsg = xhr.responseJSON.message;
                    if (xhr.responseJSON.field) field = xhr.responseJSON.field;
                }
                window.mapearErrorServidorAFormulario('#formEditarCita', errorMsg, field);
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
        $('#alertImportarVisitantes').hide().text('');

        // Aceptar .xlsx y .csv
        const name = file.name;
        const extension = name.split('.').pop().toLowerCase();

        if (extension !== 'csv' && extension !== 'xlsx') {
            $('#alertImportarVisitantes').html('<strong>Formato no válido:</strong> Por favor seleccione un archivo Excel (.xlsx) o CSV (.csv).').slideDown(200);
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
        $('#alertImportarVisitantes').hide().text('');

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
                $('#alertImportarVisitantes').html('<strong>Error:</strong> La librería de lectura Excel no está disponible. Intente recargar la página.').slideDown(200);
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
                        if (!row || row.length === 0) continue;

                        const ced  = String(row[0] !== undefined && row[0] !== null ? row[0] : '').trim();
                        const nom  = String(row[1] !== undefined && row[1] !== null ? row[1] : '').trim();
                        const ape  = String(row[2] !== undefined && row[2] !== null ? row[2] : '').trim();

                        // Omitir cabecera si se repite o fila de ejemplo
                        if (nom.toLowerCase() === 'nombres' || nom.toLowerCase() === 'nombre') continue;
                        if (ced === '12345678' && nom === 'Pedro' && ape === 'Pérez') continue;
                        if (nom === '' && ape === '') continue;

                        const col3 = row[3] !== undefined ? row[3] : '';
                        const col4 = row[4] !== undefined ? row[4] : '';
                        const col5 = row[5] !== undefined ? row[5] : '';
                        const fNac = resolveBirthDate(col3, col4, col5);

                        visitors.push({ cedula: ced, nombres: nom, apellidos: ape, fecha_nacimiento: fNac });
                    }
                    renderPreview(visitors);
                } catch (err) {
                    $('#alertImportarVisitantes').html('<strong>Error al leer el archivo Excel:</strong> Asegúrese de que no esté dañado o protegido.<br><small>' + err.message + '</small>').slideDown(200);
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

    // =========================================================================
    // UTILIDADES DE PARSEO Y NORMALIZACIÓN DE FECHAS DE NACIMIENTO
    // =========================================================================
    const MESES_MAP = {
        'enero': 1, 'ene': 1, 'january': 1, 'jan': 1,
        'febrero': 2, 'feb': 2, 'febr': 2, 'february': 2,
        'marzo': 3, 'mar': 3, 'march': 3,
        'abril': 4, 'abr': 4, 'april': 4, 'apr': 4,
        'mayo': 5, 'may': 5,
        'junio': 6, 'jun': 6, 'june': 6,
        'julio': 7, 'jul': 7, 'july': 7,
        'agosto': 8, 'ago': 8, 'august': 8, 'aug': 8,
        'septiembre': 9, 'setiembre': 9, 'sep': 9, 'sept': 9, 'september': 9,
        'octubre': 10, 'oct': 10, 'october': 10,
        'noviembre': 11, 'nov': 11, 'november': 11,
        'diciembre': 12, 'dic': 12, 'december': 12, 'dec': 12
    };

    function parseMonthVal(val) {
        if (val === undefined || val === null) return 0;
        const s = String(val).trim().toLowerCase();
        if (!s) return 0;
        if (MESES_MAP[s]) return MESES_MAP[s];
        const num = parseInt(s.replace(/[^0-9]/g, ''), 10);
        if (!isNaN(num) && num >= 1 && num <= 31) return num;
        return 0;
    }

    function parseYearVal(val) {
        if (val === undefined || val === null) return 0;
        let s = String(val).trim();
        if (!s) return 0;
        let num = parseInt(s.replace(/[^0-9]/g, ''), 10);
        if (isNaN(num) || num <= 0) return 0;

        // Si pusieron 2 dígitos (ej. 98 o 05)
        if (num < 100) {
            num = num <= 30 ? 2000 + num : 1900 + num;
        }
        // Si pusieron un cero de más por error tipográfico (ej. 20000 -> 2000, 19950 -> 1995)
        else if (num > 2100) {
            const numStr = String(num);
            if (numStr.startsWith('19') || numStr.startsWith('20')) {
                num = parseInt(numStr.substring(0, 4), 10);
            }
        }
        return (num >= 1900 && num <= 2100) ? num : 0;
    }

    function parseDayVal(val) {
        if (val === undefined || val === null) return 0;
        const num = parseInt(String(val).replace(/[^0-9]/g, ''), 10);
        if (!isNaN(num) && num >= 1 && num <= 31) return num;
        return 0;
    }

    function parseExcelSerialDate(serial) {
        const num = parseFloat(serial);
        if (isNaN(num) || num < 1 || num > 100000) return '';
        const epoch = new Date(1899, 11, 30);
        const date = new Date(epoch.getTime() + num * 86400000);
        if (!isNaN(date.getTime())) {
            const y = date.getUTCFullYear();
            const m = String(date.getUTCMonth() + 1).padStart(2, '0');
            const d = String(date.getUTCDate()).padStart(2, '0');
            if (y >= 1900 && y <= 2100) {
                return `${y}-${m}-${d}`;
            }
        }
        return '';
    }

    // Resuelve flexiblemente fechas desde 3 columnas (Día, Mes, Año) o fecha combinada
    function resolveBirthDate(valCol3, valCol4, valCol5) {
        const s3 = String(valCol3 !== undefined && valCol3 !== null ? valCol3 : '').trim();
        const s4 = String(valCol4 !== undefined && valCol4 !== null ? valCol4 : '').trim();
        const s5 = String(valCol5 !== undefined && valCol5 !== null ? valCol5 : '').trim();

        // 1. Columnas separadas Día, Mes, Año (cuando hay al menos 2 columnas o las 3 con datos)
        if (s3 !== '' && (s4 !== '' || s5 !== '')) {
            let d = parseDayVal(s3);
            let m = parseMonthVal(s4);
            let y = parseYearVal(s5);

            // Si día está en rango de mes (1-12) y mes está en rango de día (>12 y <=31), se detecta inversión
            if (m > 12 && m <= 31 && d >= 1 && d <= 12) {
                const temp = d;
                d = m;
                m = temp;
            }

            if (d >= 1 && d <= 31 && m >= 1 && m <= 12 && y >= 1900 && y <= 2100) {
                const dateObj = new Date(y, m - 1, d);
                if (dateObj.getFullYear() === y && dateObj.getMonth() === m - 1 && dateObj.getDate() === d) {
                    return `${y}-${String(m).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
                }
            }
            // Si vino en formato de 3 columnas pero los valores no son válidos, no inventar seriales
            return '';
        }

        // 2. Fecha combinada en una sola celda
        const testStr = s3 || s5 || s4;
        if (!testStr) return '';

        // ISO YYYY-MM-DD (soporta año con 5 dígitos corregido a 4)
        const isoMatch = testStr.match(/^(\d{4,5})-(\d{1,2})-(\d{1,2})$/);
        if (isoMatch) {
            const y = parseYearVal(isoMatch[1]);
            let m = parseInt(isoMatch[2], 10);
            let d = parseInt(isoMatch[3], 10);
            if (m > 12 && m <= 31 && d >= 1 && d <= 12) {
                const temp = d; d = m; m = temp;
            }
            if (y && m >= 1 && m <= 12 && d >= 1 && d <= 31) {
                const dateObj = new Date(y, m - 1, d);
                if (dateObj.getFullYear() === y && dateObj.getMonth() === m - 1 && dateObj.getDate() === d) {
                    return `${y}-${String(m).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
                }
            }
        }

        // DD/MM/YYYY o YYYY/MM/DD
        const slashParts = testStr.split(/[\/\-\.]/);
        if (slashParts.length === 3) {
            if (slashParts[0].length >= 4) {
                const y = parseYearVal(slashParts[0]);
                let m = parseMonthVal(slashParts[1]);
                let d = parseDayVal(slashParts[2]);
                if (m > 12 && m <= 31 && d >= 1 && d <= 12) {
                    const temp = d; d = m; m = temp;
                }
                if (y && m >= 1 && m <= 12 && d >= 1 && d <= 31) {
                    const dateObj = new Date(y, m - 1, d);
                    if (dateObj.getFullYear() === y && dateObj.getMonth() === m - 1 && dateObj.getDate() === d) {
                        return `${y}-${String(m).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
                    }
                }
            } else {
                let d = parseDayVal(slashParts[0]);
                let m = parseMonthVal(slashParts[1]);
                const y = parseYearVal(slashParts[2]);
                if (m > 12 && m <= 31 && d >= 1 && d <= 12) {
                    const temp = d; d = m; m = temp;
                }
                if (y && m >= 1 && m <= 12 && d >= 1 && d <= 31) {
                    const dateObj = new Date(y, m - 1, d);
                    if (dateObj.getFullYear() === y && dateObj.getMonth() === m - 1 && dateObj.getDate() === d) {
                        return `${y}-${String(m).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
                    }
                }
            }
        }

        // Número serial de Excel (únicamente en celda individual y en rango válido de fecha serial > 10000)
        if (/^\d{4,5}(\.\d+)?$/.test(testStr)) {
            const numVal = parseFloat(testStr);
            if (numVal >= 10000 && numVal <= 60000) {
                const parsedSerial = parseExcelSerialDate(testStr);
                if (parsedSerial) return parsedSerial;
            }
        }

        return '';
    }

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

            // Omitir cabecera
            if (nom.toLowerCase() === 'nombres' || nom.toLowerCase() === 'nombre') continue;
            // Omitir fila de ejemplo
            if (ced === '12345678' && nom === 'Pedro' && ape === 'Pérez') continue;
            // Omitir vacíos
            if (nom === '' && ape === '') continue;

            const col3 = fields[3] || '';
            const col4 = fields[4] || '';
            const col5 = fields[5] || '';
            const fNac = resolveBirthDate(col3, col4, col5);

            visitors.push({
                cedula: ced,
                nombres: nom,
                apellidos: ape,
                fecha_nacimiento: fNac
            });
        }
        return visitors;
    }

    function sanitizeCsvDate(dateStr) {
        return resolveBirthDate(dateStr, '', '');
    }

    // Renderiza una fila en la tabla de previsualización con soporte de feedback de error por input
    function renderPreviewRow(v) {
        const todayStr = new Date().toISOString().split('T')[0];
        const dateVal = v.fecha_nacimiento ? sanitizeCsvDate(v.fecha_nacimiento) : '';
        const cedVal = v.cedula !== undefined && v.cedula !== null ? String(v.cedula).trim() : '';
        const nomVal = v.nombres !== undefined && v.nombres !== null ? String(v.nombres).trim() : '';
        const apeVal = v.apellidos !== undefined && v.apellidos !== null ? String(v.apellidos).trim() : '';

        // Validación Cédula (opcional para menores, pero si se coloca debe ser estrictamente numérica de 5 a 12 dígitos)
        let isCedInvalid = false;
        let cedFeedback = '';
        if (cedVal !== '') {
            if (!/^\d+$/.test(cedVal)) {
                isCedInvalid = true;
                cedFeedback = 'Solo números';
            } else if (cedVal.length < 5 || cedVal.length > 12) {
                isCedInvalid = true;
                cedFeedback = 'Mín. 5 dígitos';
            }
        }

        // Validación Nombres (obligatorio, solo letras, sin números)
        let isNomInvalid = false;
        let nomFeedback = '';
        if (!nomVal) {
            isNomInvalid = true;
            nomFeedback = 'Nombre requerido';
        } else if (/\d/.test(nomVal)) {
            isNomInvalid = true;
            nomFeedback = 'Sin números';
        } else if (!/^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s\.\'\-]+$/.test(nomVal) || nomVal.length < 2) {
            isNomInvalid = true;
            nomFeedback = 'Solo letras';
        }

        // Validación Apellidos (obligatorio, solo letras, sin números)
        let isApeInvalid = false;
        let apeFeedback = '';
        if (!apeVal) {
            isApeInvalid = true;
            apeFeedback = 'Apellido requerido';
        } else if (/\d/.test(apeVal)) {
            isApeInvalid = true;
            apeFeedback = 'Sin números';
        } else if (!/^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s\.\'\-]+$/.test(apeVal) || apeVal.length < 2) {
            isApeInvalid = true;
            apeFeedback = 'Solo letras';
        }

        // Validación Fecha
        const isFechaInvalid = !dateVal || dateVal > todayStr;
        const fechaFeedback = !dateVal ? 'Fecha requerida' : (dateVal > todayStr ? 'No puede ser futura' : '');

        return `
            <tr class="preview-visitor-row">
                <td style="padding-left: 8px; vertical-align: top;">
                    <input type="text" class="form-control form-premium-control p-1 text-center input-preview-cedula ${isCedInvalid ? 'is-invalid' : ''}" 
                           value="${cedVal}" placeholder="Ej. 12345678" maxlength="12" style="font-size:0.8rem; border-radius: 6px;">
                    <div class="invalid-feedback text-start" style="font-size:0.7rem; line-height:1.1; margin-top:2px; ${isCedInvalid ? 'display:block;' : ''}">${cedFeedback}</div>
                </td>
                <td style="vertical-align: top;">
                    <input type="text" class="form-control form-premium-control p-1 input-preview-nombres ${isNomInvalid ? 'is-invalid' : ''}" 
                           value="${nomVal}" placeholder="Ej. Carlos Eduardo" required style="font-size:0.8rem; border-radius: 6px;">
                    <div class="invalid-feedback text-start" style="font-size:0.7rem; line-height:1.1; margin-top:2px; ${isNomInvalid ? 'display:block;' : ''}">${nomFeedback}</div>
                </td>
                <td style="vertical-align: top;">
                    <input type="text" class="form-control form-premium-control p-1 input-preview-apellidos ${isApeInvalid ? 'is-invalid' : ''}" 
                           value="${apeVal}" placeholder="Ej. Pérez Gómez" required style="font-size:0.8rem; border-radius: 6px;">
                    <div class="invalid-feedback text-start" style="font-size:0.7rem; line-height:1.1; margin-top:2px; ${isApeInvalid ? 'display:block;' : ''}">${apeFeedback}</div>
                </td>
                <td style="vertical-align: top;">
                    <input type="date" class="form-control form-premium-control p-1 input-preview-fecha ${isFechaInvalid ? 'is-invalid' : ''}" 
                           value="${dateVal}" max="${todayStr}" required style="font-size:0.8rem; border-radius: 6px;">
                    <div class="invalid-feedback text-start" style="font-size:0.7rem; line-height:1.1; margin-top:2px; ${isFechaInvalid ? 'display:block;' : ''}">${fechaFeedback}</div>
                </td>
                <td style="vertical-align: middle; text-align: center; width: 35px;">
                    <button type="button" class="btn-remove-preview-visitor btn-remove-contact" title="Quitar de la lista" style="width:24px; height:24px; font-size:10px; padding:0; display:inline-flex; align-items:center; justify-content:center;">✕</button>
                </td>
            </tr>
        `;
    }

    // Eventos en tiempo real para inputs de la tabla de previsualización
    $(document).on('input change', '.input-preview-cedula', function() {
        const val = $(this).val().trim();
        const $fb = $(this).siblings('.invalid-feedback');
        if (val !== '') {
            if (!/^\d+$/.test(val)) {
                $(this).addClass('is-invalid');
                $fb.text('Solo números permitidos').show();
            } else if (val.length < 5 || val.length > 12) {
                $(this).addClass('is-invalid');
                $fb.text('Mínimo 5 y máx. 12 dígitos').show();
            } else {
                $(this).removeClass('is-invalid');
                $fb.hide().text('');
            }
        } else {
            $(this).removeClass('is-invalid');
            $fb.hide().text('');
        }
        $('#alertImportarVisitantes').slideUp(150);
    });

    $(document).on('input change', '.input-preview-nombres', function() {
        const val = $(this).val().trim();
        const $fb = $(this).siblings('.invalid-feedback');
        if (!val) {
            $(this).addClass('is-invalid');
            $fb.text('Nombre requerido').show();
        } else if (/\d/.test(val)) {
            $(this).addClass('is-invalid');
            $fb.text('No puede contener números').show();
        } else if (!/^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s\.\'\-]+$/.test(val) || val.length < 2) {
            $(this).addClass('is-invalid');
            $fb.text('Solo letras permitidas').show();
        } else {
            $(this).removeClass('is-invalid');
            $fb.hide().text('');
        }
        $('#alertImportarVisitantes').slideUp(150);
    });

    $(document).on('input change', '.input-preview-apellidos', function() {
        const val = $(this).val().trim();
        const $fb = $(this).siblings('.invalid-feedback');
        if (!val) {
            $(this).addClass('is-invalid');
            $fb.text('Apellido requerido').show();
        } else if (/\d/.test(val)) {
            $(this).addClass('is-invalid');
            $fb.text('No puede contener números').show();
        } else if (!/^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s\.\'\-]+$/.test(val) || val.length < 2) {
            $(this).addClass('is-invalid');
            $fb.text('Solo letras permitidas').show();
        } else {
            $(this).removeClass('is-invalid');
            $fb.hide().text('');
        }
        $('#alertImportarVisitantes').slideUp(150);
    });

    $(document).on('input change', '.input-preview-fecha', function() {
        const val = $(this).val().trim();
        const todayStr = new Date().toISOString().split('T')[0];
        const $fb = $(this).siblings('.invalid-feedback');
        if (!val) {
            $(this).addClass('is-invalid');
            $fb.text('Fecha requerida').show();
        } else if (val > todayStr) {
            $(this).addClass('is-invalid');
            $fb.text('No puede ser fecha futura').show();
        } else {
            $(this).removeClass('is-invalid');
            $fb.hide().text('');
        }
        $('#alertImportarVisitantes').slideUp(150);
    });

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

        $('#alertImportarVisitantes').hide().text('');
        const citaId = $('#import_cita_id').val();
        const visitorsList = [];
        let isValid = true;
        let $firstInvalid = null;
        let errorCount = 0;
        const todayStr = new Date().toISOString().split('T')[0];

        $('.preview-visitor-row').each(function() {
            const $row = $(this);
            const $inputCed = $row.find('.input-preview-cedula');
            const $inputNom = $row.find('.input-preview-nombres');
            const $inputApe = $row.find('.input-preview-apellidos');
            const $inputFecha = $row.find('.input-preview-fecha');

            const ced = $inputCed.val().trim();
            const nom = $inputNom.val().trim();
            const ape = $inputApe.val().trim();
            const fNac = $inputFecha.val().trim();

            if (ced !== '') {
                if (!/^\d+$/.test(ced)) {
                    $inputCed.addClass('is-invalid');
                    $inputCed.siblings('.invalid-feedback').text('Solo números permitidos').show();
                    isValid = false;
                    errorCount++;
                    if (!$firstInvalid) $firstInvalid = $inputCed;
                } else if (ced.length < 5 || ced.length > 12) {
                    $inputCed.addClass('is-invalid');
                    $inputCed.siblings('.invalid-feedback').text('Mínimo 5 y máx. 12 dígitos').show();
                    isValid = false;
                    errorCount++;
                    if (!$firstInvalid) $firstInvalid = $inputCed;
                }
            }

            if (!nom) {
                $inputNom.addClass('is-invalid');
                $inputNom.siblings('.invalid-feedback').text('Nombre requerido').show();
                isValid = false;
                errorCount++;
                if (!$firstInvalid) $firstInvalid = $inputNom;
            } else if (/\d/.test(nom)) {
                $inputNom.addClass('is-invalid');
                $inputNom.siblings('.invalid-feedback').text('No puede contener números').show();
                isValid = false;
                errorCount++;
                if (!$firstInvalid) $firstInvalid = $inputNom;
            } else if (!/^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s\.\'\-]+$/.test(nom) || nom.length < 2) {
                $inputNom.addClass('is-invalid');
                $inputNom.siblings('.invalid-feedback').text('Solo letras permitidas').show();
                isValid = false;
                errorCount++;
                if (!$firstInvalid) $firstInvalid = $inputNom;
            }

            if (!ape) {
                $inputApe.addClass('is-invalid');
                $inputApe.siblings('.invalid-feedback').text('Apellido requerido').show();
                isValid = false;
                errorCount++;
                if (!$firstInvalid) $firstInvalid = $inputApe;
            } else if (/\d/.test(ape)) {
                $inputApe.addClass('is-invalid');
                $inputApe.siblings('.invalid-feedback').text('No puede contener números').show();
                isValid = false;
                errorCount++;
                if (!$firstInvalid) $firstInvalid = $inputApe;
            } else if (!/^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s\.\'\-]+$/.test(ape) || ape.length < 2) {
                $inputApe.addClass('is-invalid');
                $inputApe.siblings('.invalid-feedback').text('Solo letras permitidas').show();
                isValid = false;
                errorCount++;
                if (!$firstInvalid) $firstInvalid = $inputApe;
            }

            if (!fNac) {
                $inputFecha.addClass('is-invalid');
                $inputFecha.siblings('.invalid-feedback').text('Fecha requerida').show();
                isValid = false;
                errorCount++;
                if (!$firstInvalid) $firstInvalid = $inputFecha;
            } else if (fNac > todayStr) {
                $inputFecha.addClass('is-invalid');
                $inputFecha.siblings('.invalid-feedback').text('No puede ser fecha futura').show();
                isValid = false;
                errorCount++;
                if (!$firstInvalid) $firstInvalid = $inputFecha;
            }

            visitorsList.push({
                cedula: ced,
                nombres: nom,
                apellidos: ape,
                fecha_nacimiento: fNac
            });
        });

        if (!isValid) {
            $('#alertImportarVisitantes').html(`<strong>Datos incompletos o inválidos:</strong> Se detectaron ${errorCount} campo(s) con error. Por favor, corríjalos en la tabla para continuar.`).slideDown(200);
            if ($firstInvalid) {
                $firstInvalid.trigger('focus');
                if ($firstInvalid[0] && typeof $firstInvalid[0].scrollIntoView === 'function') {
                    $firstInvalid[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
            return;
        }

        if (visitorsList.length === 0) {
            $('#alertImportarVisitantes').html('<strong>Atención:</strong> Debe añadir al menos un visitante para importar.').slideDown(200);
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

                if (response.status === 'success') {
                    $('#modalImportarVisitantes').modal('hide');
                    showGlobalAlert(response.message, 'success');
                    listarCitas();
                } else {
                    $('#alertImportarVisitantes').html(`<strong>Error:</strong> ${response.message}`).slideDown(200);
                }
            },
            error: function(xhr) {
                $btnImport.prop('disabled', false).text('Confirmar y Guardar');
                let errorMsg = 'Error en el servidor al guardar el listado de visitantes.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                $('#alertImportarVisitantes').html(`<strong>Error:</strong> ${errorMsg}`).slideDown(200);
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
            showGlobalAlert('Debe seleccionar al menos un visitante para agregar.', 'danger');
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
                    showGlobalAlert(response.message, 'danger');
                }
            },
            error: function(xhr) {
                $btn.prop('disabled', false).text('Agregar Seleccionados');
                let errorMsg = 'Error al intentar vincular visitantes.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                showGlobalAlert(errorMsg, 'danger');
            }
        });
    });

    // Registrar nuevo visitante y agregar a la asistencia
    $(document).on('submit', '#formRegistrarYAgregarAsistencia', function(e) {
        e.preventDefault();
        
        const citaId = $('#modalVerVisitantes').data('cita-id');
        const $cedula = $('#reg_visitante_cedula');
        const $nombres = $('#reg_visitante_nombres');
        const $apellidos = $('#reg_visitante_apellidos');
        const $fechaNac = $('#reg_visitante_fecha_nac');

        const cedula = $cedula.val().trim();
        const nombres = $nombres.val().trim();
        const apellidos = $apellidos.val().trim();
        const fechaNac = $fechaNac.val().trim();
        const todayStr = new Date().toISOString().split('T')[0];

        let valid = true;

        if (cedula !== '') {
            const cleanCed = cedula.replace(/[^0-9]/g, '');
            if (cleanCed.length < 5 || cleanCed.length > 12) {
                if (typeof marcarErrorInput === 'function') {
                    marcarErrorInput($cedula, 'La cédula debe tener al menos 5 dígitos numéricos.');
                } else {
                    $cedula.addClass('is-invalid');
                }
                valid = false;
            }
        }

        if (!fechaNac) {
            if (typeof marcarErrorInput === 'function') {
                marcarErrorInput($fechaNac, 'La fecha de nacimiento es obligatoria.');
            } else {
                $fechaNac.addClass('is-invalid');
            }
            valid = false;
        } else if (fechaNac > todayStr) {
            if (typeof marcarErrorInput === 'function') {
                marcarErrorInput($fechaNac, 'La fecha de nacimiento no puede ser futura.');
            } else {
                $fechaNac.addClass('is-invalid');
            }
            valid = false;
        }

        if (!apellidos) {
            if (typeof marcarErrorInput === 'function') {
                marcarErrorInput($apellidos, 'El apellido es obligatorio.');
            } else {
                $apellidos.addClass('is-invalid');
            }
            valid = false;
        }

        if (!nombres) {
            if (typeof marcarErrorInput === 'function') {
                marcarErrorInput($nombres, 'El nombre es obligatorio.');
            } else {
                $nombres.addClass('is-invalid');
            }
            valid = false;
        }

        if (!valid) return;

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
                    if (typeof limpiarErroresFormulario === 'function') {
                        limpiarErroresFormulario('#formRegistrarYAgregarAsistencia');
                    }
                    $('#panelAgregarAsistencia').slideUp(250);
                    cargarVisitantesCita(citaId);
                    listarCitas(); // Refrescar tabla principal
                } else {
                    showGlobalAlert(response.message, 'danger');
                }
            },
            error: function(xhr) {
                $btn.prop('disabled', false).text('Registrar y Agregar');
                let errorMsg = 'Error al registrar y agregar al visitante.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                showGlobalAlert(errorMsg, 'danger');
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
                    showGlobalAlert(response.message, 'danger');
                }
            },
            error: function(xhr) {
                $btn.prop('disabled', false).text('Remover Asistencia');
                $('#modalConfirmarEliminarAsistente').modal('hide');
                let errorMsg = 'Error al intentar remover al visitante de la asistencia.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                showGlobalAlert(errorMsg, 'danger');
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
