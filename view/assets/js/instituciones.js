/**
 * Lógica del Módulo de Registro de Instituciones - instituciones.js
 * Parque Zoológico y Botánico Bararida
 * Manejo modular de instituciones y sedes múltiples
 */

$(document).ready(function() {
    // Referencias DOM principales
    const $tablaInst = $('#tablaInstitucionesBody');
    const $loader = $('#loaderInstituciones');
    const $searchInst = $('#searchInstituciones');
    const $alertFeedback = $('#alertFeedbackGlobal');

    // Variables de paginación y caché
    let cacheInstituciones = [];
    let filteredInstituciones = [];
    let currentPageInstituciones = 1;
    const itemsPerPage = 10;
    let cacheEstados = [];

    // Cargar listado y estados al iniciar
    cargarEstadosCache();
    listarInstituciones();

    // =========================================================================
    // 1. CARGA INICIAL Y LISTAR INSTITUCIONES (AJAX)
    // =========================================================================
    function cargarEstadosCache() {
        $.ajax({
            url: 'controllers/LocationController.php?action=estados',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    cacheEstados = response.data || [];
                }
            }
        });
    }

    function listarInstituciones() {
        $tablaInst.hide();
        $loader.show();
        $('#paginacionInstituciones').hide();

        $.ajax({
            url: 'controllers/InstitucionController.php?action=listar',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                $loader.hide();
                if (response.status === 'success') {
                    cacheInstituciones = response.data || [];
                    
                    const query = $searchInst.val().toLowerCase().trim();
                    if (query === '') {
                        filteredInstituciones = cacheInstituciones;
                    } else {
                        filteredInstituciones = cacheInstituciones.filter(function(inst) {
                            const name = (inst.nombre_completo || inst.nombre || '').toLowerCase();
                            const doc = `${(inst.tipo_documento || '').toLowerCase()}-${(inst.documento || '').toLowerCase()}`;
                            const tipo = (inst.tipo_organizacion || '').toLowerCase();
                            const sedesText = (inst.sedes || []).map(s => `${s.estado || ''} ${s.ciudad || ''} ${s.parroquia || ''} ${s.detalles || ''}`).join(' ').toLowerCase();
                            const searchStr = `${name} ${doc} ${tipo} ${inst.tipo_gestion || ''} ${sedesText}`;
                            return searchStr.indexOf(query) > -1;
                        });
                    }

                    renderInstitucionesTable();
                } else {
                    $tablaInst.empty().append('<tr><td colspan="6" class="text-center text-muted py-4">No se pudo cargar el listado de instituciones.</td></tr>');
                    $tablaInst.fadeIn(300);
                }
            },
            error: function() {
                $loader.hide();
                showGlobalAlert('Ocurrió un error al cargar el listado de instituciones.', 'danger');
            }
        });
    }

    function renderInstitucionesTable() {
        $tablaInst.empty();
        const totalRecords = filteredInstituciones.length;

        if (totalRecords === 0) {
            $tablaInst.append('<tr><td colspan="6" class="text-center text-muted py-4">No se encontraron instituciones registradas.</td></tr>');
            $('#paginacionInstituciones').hide();
            $tablaInst.fadeIn(300);
            return;
        }

        const totalPages = Math.ceil(totalRecords / itemsPerPage);

        if (currentPageInstituciones > totalPages) {
            currentPageInstituciones = totalPages;
        }
        if (currentPageInstituciones < 1) {
            currentPageInstituciones = 1;
        }

        const startIdx = (currentPageInstituciones - 1) * itemsPerPage;
        const endIdx = Math.min(startIdx + itemsPerPage, totalRecords);
        const pageData = filteredInstituciones.slice(startIdx, endIdx);

        pageData.forEach(function(inst) {
            let badgeTipo = `<span class="badge-premium badge-premium-info">${inst.tipo_organizacion}</span>`;
            if (inst.tipo_organizacion.toLowerCase() === 'educativa') {
                badgeTipo = `<span class="badge-premium badge-premium-admin">Educativa</span>`;
            } else if (inst.tipo_organizacion.toLowerCase() === 'empresarial') {
                badgeTipo = `<span class="badge-premium badge-premium-superadmin">Empresarial</span>`;
            } else if (inst.tipo_organizacion.toLowerCase() === 'comunitaria') {
                badgeTipo = `<span class="badge-premium badge-premium-guia">Comunitaria</span>`;
            }

            // Contactos
            let contactosHtml = '';
            if (inst.contactos && inst.contactos.length > 0) {
                inst.contactos.forEach(function(c) {
                    let icon = c.tipo === 'correo' ? '✉' : '📞';
                    let displayContact = c.contacto;
                    if (displayContact.length > 24) {
                        displayContact = displayContact.substring(0, 21) + '...';
                    }
                    contactosHtml += `<div style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 190px;" title="${c.contacto}">` +
                                     `<small class="text-muted">${icon}&nbsp;${displayContact}</small>` +
                                     `</div>`;
                });
            } else {
                contactosHtml = '<span class="text-muted italic">Sin contactos</span>';
            }

            // Sedes múltiples
            let sedesHtml = '';
            const sedesList = inst.sedes && inst.sedes.length > 0 ? inst.sedes : [];
            if (sedesList.length > 0) {
                sedesList.forEach(function(s, idx) {
                    const sedeNum = idx + 1;
                    const estado = s.estado || '';
                    const ciudad = s.ciudad || '';
                    const parroquia = s.parroquia || '';
                    const detalles = s.detalles ? `<br><small class="text-muted">${s.detalles}</small>` : '';
                    
                    sedesHtml += `<div class="mb-1 pb-1 ${idx < sedesList.length - 1 ? 'border-bottom border-light' : ''}">
                        <span class="badge bg-light text-dark border me-1" style="font-size:0.7rem;">Sede #${sedeNum}</span>
                        <strong>${estado}</strong>${ciudad ? `, ${ciudad}` : ''}${parroquia ? ` (${parroquia})` : ''}
                        ${detalles}
                    </div>`;
                });
            } else {
                sedesHtml = '<span class="text-muted italic">Sin sedes asignadas</span>';
            }

            const nombreInst = inst.nombre_completo || inst.nombre || '';
            const totalCitas = parseInt(inst.total_citas || 0, 10);
            const hasCitas = totalCitas > 0;
            const deleteDisabledAttr = hasCitas ? 'disabled' : '';
            const deleteTitle = hasCitas 
                ? `No se puede eliminar: posee ${totalCitas} cita(s) registrada(s) (pasadas, activas o futuras)` 
                : 'Eliminar Institución';

            const rowHtml = `
                <tr class="inst-row">
                    <td style="white-space: nowrap;"><strong>${inst.tipo_documento}-${inst.documento}</strong></td>
                    <td><strong>${nombreInst}</strong></td>
                    <td>
                        ${badgeTipo}
                        <small class="text-muted d-block mt-0.5" style="font-size:0.75rem; text-transform:capitalize;">${inst.tipo_gestion || 'Pública'}</small>
                    </td>
                    <td>${contactosHtml}</td>
                    <td style="max-width: 260px;">${sedesHtml}</td>
                    <td>
                        <div class="table-actions">
                            <button class="btn-action btn-action-edit btn-editar-inst" data-id="${inst.id}" title="Editar Institución">
                                <svg viewBox="0 0 24 24" style="width:20px; height:20px;"><path d="M3 17.25V21H6.75L17.81 9.94L14.06 6.19L3 17.25ZM20.71 7.04C21.1 6.65 21.1 6.02 20.71 5.63L18.37 3.29C17.98 2.9 17.35 2.9 16.96 3.29L15.13 5.12L18.88 8.87L20.71 7.04Z"/></svg>
                            </button>
                            <button class="btn-action btn-action-delete btn-eliminar-inst" data-id="${inst.id}" data-name="${nombreInst}" ${deleteDisabledAttr} title="${deleteTitle}">
                                <svg viewBox="0 0 24 24" style="width:20px; height:20px;"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
            $tablaInst.append(rowHtml);
        });

        // Actualizar datos informativos
        $('#pagStartInstituciones').text(startIdx + 1);
        $('#pagEndInstituciones').text(endIdx);
        $('#pagTotalInstituciones').text(totalRecords);

        // Renderizar controles de paginación
        const $controls = $('#paginationControlsInstituciones');
        $controls.empty();

        const prevDisabled = currentPageInstituciones === 1 ? 'disabled' : '';
        $controls.append(`
            <li class="page-item ${prevDisabled}">
                <a class="page-link btn-pag-instituciones" data-page="${currentPageInstituciones - 1}" href="javascript:void(0)">&laquo; Anterior</a>
            </li>
        `);

        const maxVisibleInst = 10;
        let startPageInst = Math.max(1, currentPageInstituciones - Math.floor(maxVisibleInst / 2));
        let endPageInst = Math.min(totalPages, startPageInst + maxVisibleInst - 1);

        if (endPageInst - startPageInst + 1 < maxVisibleInst) {
            startPageInst = Math.max(1, endPageInst - maxVisibleInst + 1);
        }

        for (let i = startPageInst; i <= endPageInst; i++) {
            const activeClass = currentPageInstituciones === i ? 'active' : '';
            $controls.append(`
                <li class="page-item ${activeClass}">
                    <a class="page-link btn-pag-instituciones" data-page="${i}" href="javascript:void(0)">${i}</a>
                </li>
            `);
        }

        const nextDisabled = currentPageInstituciones === totalPages ? 'disabled' : '';
        $controls.append(`
            <li class="page-item ${nextDisabled}">
                <a class="page-link btn-pag-instituciones" data-page="${currentPageInstituciones + 1}" href="javascript:void(0)">Siguiente &raquo;</a>
            </li>
        `);

        $('#paginacionInstituciones').removeClass('d-none').show();
        $tablaInst.fadeIn(300);
    }

    // Clicks en paginación
    $(document).on('click', '.btn-pag-instituciones', function(e) {
        e.preventDefault();
        const page = parseInt($(this).data('page'));
        if (page && page !== currentPageInstituciones) {
            currentPageInstituciones = page;
            renderInstitucionesTable();
        }
    });

    // Buscador en tiempo real
    $searchInst.on('input', function() {
        const query = $(this).val().toLowerCase().trim();
        if (query === '') {
            filteredInstituciones = cacheInstituciones;
        } else {
            filteredInstituciones = cacheInstituciones.filter(function(inst) {
                const name = (inst.nombre_completo || inst.nombre || '').toLowerCase();
                const doc = `${(inst.tipo_documento || '').toLowerCase()}-${(inst.documento || '').toLowerCase()}`;
                const tipo = (inst.tipo_organizacion || '').toLowerCase();
                const sedesText = (inst.sedes || []).map(s => `${s.estado || ''} ${s.ciudad || ''} ${s.parroquia || ''} ${s.detalles || ''}`).join(' ').toLowerCase();
                const searchStr = `${name} ${doc} ${tipo} ${inst.tipo_gestion || ''} ${sedesText}`;
                return searchStr.indexOf(query) > -1;
            });
        }
        currentPageInstituciones = 1;
        renderInstitucionesTable();
    });

    // =========================================================================
    // 2. GESTIÓN DINÁMICA DE SEDES MÚLTIPLES
    // =========================================================================

    /**
     * Construye y añade una tarjeta de Sede a un contenedor dado
     */
    function agregarSedeCard(containerSelector, prefix, sedeData = null) {
        const $container = $(containerSelector);
        const index = $container.children('.sede-card').length;
        const sedeNum = index + 1;
        const uniqueId = `${prefix}_sede_${Date.now()}_${Math.floor(Math.random() * 1000)}`;

        const sId = sedeData && sedeData.id ? sedeData.id : '';
        const detallesVal = sedeData && sedeData.detalles ? sedeData.detalles : '';

        const cardHtml = `
            <div class="sede-card p-3 border rounded-3 bg-light position-relative" id="${uniqueId}">
                <input type="hidden" class="sede-id-input" value="${sId}">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="fw-bold text-success sede-title" style="font-size: 0.9rem;">
                        <span class="badge bg-success me-1">Sede #${sedeNum}</span>
                        ${sedeNum === 1 ? '<span class="text-muted" style="font-size:0.8rem;">(Principal)</span>' : ''}
                    </span>
                    <button type="button" class="btn btn-sm btn-outline-danger btn-remove-sede px-2 py-0" title="Eliminar Sede" style="font-size: 0.75rem; border-radius: 6px;">
                        &times; Eliminar
                    </button>
                </div>
                <div class="row g-2">
                    <div class="col-md-6">
                        <label class="form-label mb-1" style="font-size:0.8rem; font-weight:600;">Estado</label>
                        <select class="form-select form-select-sm select-estado" required>
                            <option value="">Seleccione Estado</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label mb-1" style="font-size:0.8rem; font-weight:600;">Municipio</label>
                        <select class="form-select form-select-sm select-municipio" required disabled>
                            <option value="">Seleccione Municipio</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label mb-1" style="font-size:0.8rem; font-weight:600;">Ciudad</label>
                        <select class="form-select form-select-sm select-ciudad" required disabled>
                            <option value="">Seleccione Ciudad</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label mb-1" style="font-size:0.8rem; font-weight:600;">Parroquia</label>
                        <select class="form-select form-select-sm select-parroquia" required disabled>
                            <option value="">Seleccione Parroquia</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label mb-1" style="font-size:0.8rem; font-weight:600;">Detalles de Dirección</label>
                        <input type="text" class="form-control form-control-sm input-detalles" placeholder="Ej. Calle 10 con Av. Libertador, Edif. Principal" value="${detallesVal}">
                    </div>
                </div>
            </div>
        `;

        $container.append(cardHtml);
        const $card = $(`#${uniqueId}`);

        // Poblar Estados
        const $selectEstado = $card.find('.select-estado');
        if (cacheEstados.length > 0) {
            poblarEstadosSelect($selectEstado);
            if (sedeData && sedeData.id_estado) {
                $selectEstado.val(sedeData.id_estado);
                cargarCascadaSede($card, sedeData.id_estado, sedeData.id_municipio, sedeData.ciudad_id, sedeData.parroquia_id);
            }
        } else {
            $.ajax({
                url: 'controllers/LocationController.php?action=estados',
                method: 'GET',
                dataType: 'json',
                success: function(resp) {
                    if (resp.status === 'success') {
                        cacheEstados = resp.data || [];
                        poblarEstadosSelect($selectEstado);
                        if (sedeData && sedeData.id_estado) {
                            $selectEstado.val(sedeData.id_estado);
                            cargarCascadaSede($card, sedeData.id_estado, sedeData.id_municipio, sedeData.ciudad_id, sedeData.parroquia_id);
                        }
                    }
                }
            });
        }

        // Event listener: cambio de estado en esta tarjeta
        $selectEstado.on('change', function() {
            const estadoId = $(this).val();
            const $selectMun = $card.find('.select-municipio');
            const $selectCiu = $card.find('.select-ciudad');
            const $selectPar = $card.find('.select-parroquia');

            $selectMun.empty().append('<option value="">Seleccione Municipio</option>').prop('disabled', true);
            $selectCiu.empty().append('<option value="">Seleccione Ciudad</option>').prop('disabled', true);
            $selectPar.empty().append('<option value="">Seleccione Parroquia</option>').prop('disabled', true);

            if (!estadoId) return;

            // Cargar ciudades
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

            // Cargar municipios
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

        // Event listener: cambio de municipio en esta tarjeta
        $card.find('.select-municipio').on('change', function() {
            const munId = $(this).val();
            const $selectPar = $card.find('.select-parroquia');
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

        actualizarBotonesEliminarSedes(containerSelector);
    }

    function poblarEstadosSelect($select) {
        $select.empty().append('<option value="">Seleccione Estado</option>');
        cacheEstados.forEach(function(est) {
            $select.append(`<option value="${est.id_estado}">${est.estado}</option>`);
        });
    }

    function cargarCascadaSede($card, estadoId, selectedMunId, selectedCiuId, selectedParId) {
        const $selectMun = $card.find('.select-municipio');
        const $selectCiu = $card.find('.select-ciudad');
        const $selectPar = $card.find('.select-parroquia');

        // Ciudades
        $.ajax({
            url: `controllers/LocationController.php?action=ciudades&estado_id=${estadoId}`,
            method: 'GET',
            dataType: 'json',
            success: function(resp) {
                if (resp.status === 'success') {
                    $selectCiu.empty().append('<option value="">Seleccione Ciudad</option>');
                    resp.data.forEach(function(c) {
                        const sel = (selectedCiuId && c.id_ciudad == selectedCiuId) ? 'selected' : '';
                        $selectCiu.append(`<option value="${c.id_ciudad}" ${sel}>${c.ciudad}</option>`);
                    });
                    $selectCiu.prop('disabled', false);
                }
            }
        });

        // Municipios y Parroquias
        $.ajax({
            url: `controllers/LocationController.php?action=municipios&estado_id=${estadoId}`,
            method: 'GET',
            dataType: 'json',
            success: function(resp) {
                if (resp.status === 'success') {
                    $selectMun.empty().append('<option value="">Seleccione Municipio</option>');
                    resp.data.forEach(function(m) {
                        const sel = (selectedMunId && m.id_municipio == selectedMunId) ? 'selected' : '';
                        $selectMun.append(`<option value="${m.id_municipio}" ${sel}>${m.municipio}</option>`);
                    });
                    $selectMun.prop('disabled', false);

                    if (selectedMunId) {
                        $.ajax({
                            url: `controllers/LocationController.php?action=parroquias&municipio_id=${selectedMunId}`,
                            method: 'GET',
                            dataType: 'json',
                            success: function(pResp) {
                                if (pResp.status === 'success') {
                                    $selectPar.empty().append('<option value="">Seleccione Parroquia</option>');
                                    pResp.data.forEach(function(p) {
                                        const selP = (selectedParId && p.id_parroquia == selectedParId) ? 'selected' : '';
                                        $selectPar.append(`<option value="${p.id_parroquia}" ${selP}>${p.parroquia}</option>`);
                                    });
                                    $selectPar.prop('disabled', false);
                                }
                            }
                        });
                    }
                }
            }
        });
    }

    function actualizarBotonesEliminarSedes(containerSelector) {
        const $container = $(containerSelector);
        const cards = $container.children('.sede-card');
        cards.each(function(idx) {
            const num = idx + 1;
            $(this).find('.sede-title').html(`
                <span class="badge bg-success me-1">Sede #${num}</span>
                ${num === 1 ? '<span class="text-muted" style="font-size:0.8rem;">(Principal)</span>' : ''}
            `);
            // Si solo hay 1 sede, ocultamos el botón eliminar
            if (cards.length === 1) {
                $(this).find('.btn-remove-sede').hide();
            } else {
                $(this).find('.btn-remove-sede').show();
            }
        });
    }

    // Botones para agregar sedes (delegación robusta)
    $(document).on('click', '#btnCreateAddSede', function(e) {
        e.preventDefault();
        agregarSedeCard('#createSedesContainer', 'create');
    });

    $(document).on('click', '#btnEditAddSede', function(e) {
        e.preventDefault();
        agregarSedeCard('#editSedesContainer', 'edit');
    });

    // Eliminar sede de la lista
    $(document).on('click', '.btn-remove-sede', function(e) {
        e.preventDefault();
        const $card = $(this).closest('.sede-card');
        const containerSelector = '#' + $card.parent().attr('id');
        $card.remove();
        actualizarBotonesEliminarSedes(containerSelector);
    });

    // =========================================================================
    // 3. GESTIÓN DE CONTACTOS (TELÉFONOS Y CORREOS)
    // =========================================================================
    function agregarContacto(containerId, tipo, valor = '') {
        const placeholder = tipo === 'telefono' ? 'Ej. 0414-1234567' : 'Ej. contacto@colegio.com';
        const inputType = tipo === 'correo' ? 'email' : 'tel';
        const inputMode = tipo === 'telefono' ? 'inputmode="numeric"' : '';
        const extraClass = tipo === 'telefono' ? 'input-telefono-val' : 'input-correo-val';
        const contactHtml = `
            <div class="input-group input-group-sm mb-1 contact-row" data-tipo="${tipo}">
                <span class="input-group-text bg-white">${tipo === 'telefono' ? '📞' : '✉'}</span>
                <input type="${inputType}" ${inputMode} class="form-control input-contacto-val ${extraClass}" placeholder="${placeholder}" value="${valor}" required>
                <button type="button" class="btn btn-outline-danger btn-remove-contact" title="Eliminar Contacto">&times;</button>
            </div>
        `;
        $(`#${containerId}`).append(contactHtml);
    }

    // Restringir ingreso de letras en los campos de teléfono en tiempo real (solo números, +, -, (), espacios)
    $(document).on('input', '.contact-row[data-tipo="telefono"] .input-contacto-val', function() {
        this.value = this.value.replace(/[^0-9+\-\s()]/g, '');
    });

    $(document).on('click', '#btnCreateAddPhone', function(e) { e.preventDefault(); agregarContacto('createPhonesContainer', 'telefono'); });
    $(document).on('click', '#btnCreateAddEmail', function(e) { e.preventDefault(); agregarContacto('createEmailsContainer', 'correo'); });
    $(document).on('click', '#btnEditAddPhone', function(e) { e.preventDefault(); agregarContacto('editPhonesContainer', 'telefono'); });
    $(document).on('click', '#btnEditAddEmail', function(e) { e.preventDefault(); agregarContacto('editEmailsContainer', 'correo'); });

    $(document).on('click', '.btn-remove-contact', function() {
        $(this).closest('.contact-row').remove();
    });

    // Manejo de tipo de organización personalizada "Otro"
    $(document).on('change', '.select-tipo-organizacion', function() {
        const val = $(this).val();
        const $customContainer = $(this).siblings('.custom-tipo-organizacion-container');
        if (val === 'Otro') {
            $customContainer.slideDown(200);
            $customContainer.find('input').prop('required', true);
        } else {
            $customContainer.slideUp(200);
            $customContainer.find('input').prop('required', false).val('');
        }
    });

    // =========================================================================
    // 4. CREAR INSTITUCIÓN (AJAX)
    // =========================================================================
    // Al abrir el modal de crear, inicializar con 1 sede y 1 teléfono por defecto
    $('#modalCrearInstitucion').on('show.bs.modal', function() {
        $('#alertCrearInstFeedback').hide();
        $('#createSedesContainer').empty();
        $('#createPhonesContainer').empty();
        $('#createEmailsContainer').empty();

        // 1 sede por defecto
        agregarSedeCard('#createSedesContainer', 'create');
        // 1 teléfono por defecto
        agregarContacto('createPhonesContainer', 'telefono');
    });

    $('#formCrearInstitucion').on('submit', function(e) {
        e.preventDefault();

        const $btn = $('#btnGuardarNuevaInst');
        const $alert = $('#alertCrearInstFeedback');
        $alert.hide();

        const tipo_documento = $('#create_tipo_documento').val();
        const documento = $.trim($('#create_documento').val());
        const nombre_completo = $.trim($('#create_nombre_completo').val());
        let tipo_organizacion = $('#create_tipo_organizacion_select').val();
        if (tipo_organizacion === 'Otro') {
            tipo_organizacion = $.trim($('#create_tipo_organizacion').val());
        }
        const tipo_gestion = $('#create_tipo_gestion').val();

        if (!documento || !nombre_completo || !tipo_organizacion) {
            showAlert($alert, 'Por favor complete todos los datos identificativos requeridos.', 'danger');
            return;
        }

        // Recolectar sedes
        const sedes = [];
        let sedeError = false;
        $('#createSedesContainer .sede-card').each(function() {
            const ciudadId = $(this).find('.select-ciudad').val();
            const parroquiaId = $(this).find('.select-parroquia').val();
            const detalles = $.trim($(this).find('.input-detalles').val());

            if (!ciudadId || !parroquiaId) {
                sedeError = true;
                return false;
            }
            sedes.push({
                ciudad_id: ciudadId,
                parroquia_id: parroquiaId,
                detalles: detalles
            });
        });

        if (sedeError || sedes.length === 0) {
            showAlert($alert, 'Debe completar la ubicación geográfica de todas las sedes registradas (Estado, Municipio, Parroquia y Ciudad).', 'danger');
            return;
        }

        // Recolectar contactos y validar formatos
        const contactos = [];
        let contactError = false;
        let contactErrorMsg = '';

        $('#createPhonesContainer .contact-row, #createEmailsContainer .contact-row').each(function() {
            const tipo = $(this).data('tipo');
            const $input = $(this).find('.input-contacto-val');
            const val = $.trim($input.val());
            if (val) {
                if (tipo === 'telefono') {
                    const cleanDigits = val.replace(/[^0-9]/g, '');
                    if (/[a-zA-Z]/.test(val) || cleanDigits.length < 7 || cleanDigits.length > 15) {
                        contactError = true;
                        contactErrorMsg = `El número de teléfono "${val}" no es válido. No debe contener letras y debe tener entre 7 y 15 dígitos.`;
                        $input.addClass('is-invalid');
                        return false;
                    } else {
                        $input.removeClass('is-invalid');
                    }
                } else if (tipo === 'correo') {
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailRegex.test(val)) {
                        contactError = true;
                        contactErrorMsg = `El correo electrónico "${val}" no tiene un formato válido.`;
                        $input.addClass('is-invalid');
                        return false;
                    } else {
                        $input.removeClass('is-invalid');
                    }
                }
                contactos.push({ tipo: tipo, contacto: val });
            }
        });

        if (contactError) {
            showAlert($alert, contactErrorMsg, 'danger');
            return;
        }

        if (contactos.length === 0) {
            showAlert($alert, 'Debe registrar al menos un número de teléfono o correo de contacto.', 'danger');
            return;
        }

        $btn.prop('disabled', true).text('Guardando...');

        $.ajax({
            url: 'controllers/InstitucionController.php?action=crear',
            method: 'POST',
            data: {
                tipo_documento: tipo_documento,
                documento: documento,
                nombre_completo: nombre_completo,
                tipo_organizacion: tipo_organizacion,
                tipo_gestion: tipo_gestion,
                sedes: sedes,
                contactos: contactos
            },
            dataType: 'json',
            success: function(response) {
                $btn.prop('disabled', false).text('Registrar Institución');
                if (response.status === 'success') {
                    $('#modalCrearInstitucion').modal('hide');
                    $('#formCrearInstitucion')[0].reset();
                    showGlobalAlert(response.message, 'success');
                    currentPageInstituciones = 1;
                    $searchInst.val('');
                    listarInstituciones();
                } else {
                    showAlert($alert, response.message, 'danger');
                }
            },
            error: function(xhr) {
                $btn.prop('disabled', false).text('Registrar Institución');
                let msg = 'Error en el servidor al registrar la institución.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                showAlert($alert, msg, 'danger');
            }
        });
    });

    // =========================================================================
    // 5. OBTENER Y EDITAR INSTITUCIÓN (AJAX)
    // =========================================================================
    $(document).on('click', '.btn-editar-inst', function() {
        const id = $(this).data('id');
        const $alert = $('#alertEditarInstFeedback');
        $alert.hide();
        $('#editSedesContainer').empty();
        $('#editPhonesContainer').empty();
        $('#editEmailsContainer').empty();

        $.ajax({
            url: `controllers/InstitucionController.php?action=obtener&id=${id}`,
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    const inst = response.data;
                    $('#edit_id').val(inst.institucion_id || inst.id);
                    $('#edit_entidad_id').val(inst.entidad_id);
                    $('#edit_tipo_documento').val(inst.tipo_documento || 'J');
                    $('#edit_documento').val(inst.documento);
                    $('#edit_nombre_completo').val(inst.nombre_completo || inst.nombre || '');
                    $('#edit_tipo_gestion').val(inst.tipo_gestion || 'publica');

                    // Tipo de organización
                    const tiposEstandar = ['Educativa', 'Empresarial', 'Gubernamental', 'Fundación / ONG', 'Comunitaria', 'Religiosa'];
                    if (tiposEstandar.includes(inst.tipo_organizacion)) {
                        $('#edit_tipo_organizacion_select').val(inst.tipo_organizacion);
                        $('#edit_custom_tipo_org_container').hide();
                    } else {
                        $('#edit_tipo_organizacion_select').val('Otro');
                        $('#edit_tipo_organizacion').val(inst.tipo_organizacion);
                        $('#edit_custom_tipo_org_container').show();
                    }

                    // Poblar Sedes
                    const sedesList = inst.sedes && inst.sedes.length > 0 ? inst.sedes : [];
                    if (sedesList.length > 0) {
                        sedesList.forEach(function(s) {
                            agregarSedeCard('#editSedesContainer', 'edit', s);
                        });
                    } else {
                        agregarSedeCard('#editSedesContainer', 'edit');
                    }

                    // Poblar Contactos
                    if (inst.contactos && inst.contactos.length > 0) {
                        inst.contactos.forEach(function(c) {
                            if (c.tipo === 'correo') {
                                agregarContacto('editEmailsContainer', 'correo', c.contacto);
                            } else {
                                agregarContacto('editPhonesContainer', 'telefono', c.contacto);
                            }
                        });
                    } else {
                        agregarContacto('editPhonesContainer', 'telefono');
                    }

                    $('#modalEditarInstitucion').modal('show');
                } else {
                    showGlobalAlert(response.message, 'danger');
                }
            },
            error: function() {
                showGlobalAlert('No se pudo recuperar la información de la institución.', 'danger');
            }
        });
    });

    $('#formEditarInstitucion').on('submit', function(e) {
        e.preventDefault();

        const $btn = $('#btnGuardarEditInst');
        const $alert = $('#alertEditarInstFeedback');
        $alert.hide();

        const id = $('#edit_id').val();
        const entidad_id = $('#edit_entidad_id').val();
        const tipo_documento = $('#edit_tipo_documento').val();
        const documento = $.trim($('#edit_documento').val());
        const nombre_completo = $.trim($('#edit_nombre_completo').val());
        let tipo_organizacion = $('#edit_tipo_organizacion_select').val();
        if (tipo_organizacion === 'Otro') {
            tipo_organizacion = $.trim($('#edit_tipo_organizacion').val());
        }
        const tipo_gestion = $('#edit_tipo_gestion').val();

        if (!documento || !nombre_completo || !tipo_organizacion) {
            showAlert($alert, 'Por favor complete todos los datos identificativos requeridos.', 'danger');
            return;
        }

        // Recolectar sedes
        const sedes = [];
        let sedeError = false;
        $('#editSedesContainer .sede-card').each(function() {
            const sId = $(this).find('.sede-id-input').val();
            const ciudadId = $(this).find('.select-ciudad').val();
            const parroquiaId = $(this).find('.select-parroquia').val();
            const detalles = $.trim($(this).find('.input-detalles').val());

            if (!ciudadId || !parroquiaId) {
                sedeError = true;
                return false;
            }
            sedes.push({
                id: sId || null,
                ciudad_id: ciudadId,
                parroquia_id: parroquiaId,
                detalles: detalles
            });
        });

        if (sedeError || sedes.length === 0) {
            showAlert($alert, 'Debe completar la ubicación geográfica de todas las sedes registradas.', 'danger');
            return;
        }

        // Recolectar contactos y validar formatos
        const contactos = [];
        let contactError = false;
        let contactErrorMsg = '';

        $('#editPhonesContainer .contact-row, #editEmailsContainer .contact-row').each(function() {
            const tipo = $(this).data('tipo');
            const $input = $(this).find('.input-contacto-val');
            const val = $.trim($input.val());
            if (val) {
                if (tipo === 'telefono') {
                    const cleanDigits = val.replace(/[^0-9]/g, '');
                    if (/[a-zA-Z]/.test(val) || cleanDigits.length < 7 || cleanDigits.length > 15) {
                        contactError = true;
                        contactErrorMsg = `El número de teléfono "${val}" no es válido. No debe contener letras y debe tener entre 7 y 15 dígitos.`;
                        $input.addClass('is-invalid');
                        return false;
                    } else {
                        $input.removeClass('is-invalid');
                    }
                } else if (tipo === 'correo') {
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailRegex.test(val)) {
                        contactError = true;
                        contactErrorMsg = `El correo electrónico "${val}" no tiene un formato válido.`;
                        $input.addClass('is-invalid');
                        return false;
                    } else {
                        $input.removeClass('is-invalid');
                    }
                }
                contactos.push({ tipo: tipo, contacto: val });
            }
        });

        if (contactError) {
            showAlert($alert, contactErrorMsg, 'danger');
            return;
        }

        if (contactos.length === 0) {
            showAlert($alert, 'Debe registrar al menos un número de teléfono o correo de contacto.', 'danger');
            return;
        }

        $btn.prop('disabled', true).text('Guardando...');

        $.ajax({
            url: 'controllers/InstitucionController.php?action=actualizar',
            method: 'POST',
            data: {
                id: id,
                entidad_id: entidad_id,
                tipo_documento: tipo_documento,
                documento: documento,
                nombre_completo: nombre_completo,
                tipo_organizacion: tipo_organizacion,
                tipo_gestion: tipo_gestion,
                sedes: sedes,
                contactos: contactos
            },
            dataType: 'json',
            success: function(response) {
                $btn.prop('disabled', false).text('Guardar Cambios');
                if (response.status === 'success') {
                    $('#modalEditarInstitucion').modal('hide');
                    showGlobalAlert(response.message, 'success');
                    listarInstituciones();
                } else {
                    showAlert($alert, response.message, 'danger');
                }
            },
            error: function(xhr) {
                $btn.prop('disabled', false).text('Guardar Cambios');
                let msg = 'Error en el servidor al actualizar la institución.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                showAlert($alert, msg, 'danger');
            }
        });
    });

    // =========================================================================
    // 6. ELIMINAR INSTITUCIÓN
    // =========================================================================
    $(document).on('click', '.btn-eliminar-inst', function(e) {
        if ($(this).is(':disabled') || $(this).prop('disabled') || $(this).attr('disabled')) {
            e.preventDefault();
            e.stopPropagation();
            return false;
        }
        const id = $(this).data('id');
        const name = $(this).data('name');
        $('#delete_institucion_id').val(id);
        $('#deleteInstNameSpan').text(name);
        $('#modalEliminarInstitucion').modal('show');
    });

    $('#btnConfirmarEliminarInstitucion').on('click', function() {
        const id = $('#delete_institucion_id').val();
        const $btn = $(this);
        $btn.prop('disabled', true).text('Eliminando...');

        $.ajax({
            url: 'controllers/InstitucionController.php?action=eliminar',
            method: 'POST',
            data: { id: id },
            dataType: 'json',
            success: function(response) {
                $btn.prop('disabled', false).text('Eliminar Institución');
                $('#modalEliminarInstitucion').modal('hide');
                if (response.status === 'success') {
                    showGlobalAlert(response.message, 'success');
                    listarInstituciones();
                } else {
                    showGlobalAlert(response.message, 'danger');
                }
            },
            error: function(xhr) {
                $btn.prop('disabled', false).text('Eliminar Institución');
                $('#modalEliminarInstitucion').modal('hide');
                let msg = 'Error en el servidor al eliminar la institución.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                showGlobalAlert(msg, 'danger');
            }
        });
    });

    function showAlert($alertElem, message, type = 'danger') {
        $alertElem.removeClass('alert-toast-danger alert-toast-success alert-toast-warning')
                  .addClass(`alert-toast-${type}`)
                  .text(message)
                  .fadeIn(200);
    }

    function showGlobalAlert(message, type = 'success') {
        const $alert = $('#alertFeedbackGlobal');
        if ($alert.length) {
            $alert.removeClass('alert-toast-success alert-toast-danger')
                  .addClass(type === 'success' ? 'alert-toast-success' : 'alert-toast-danger')
                  .html(`<strong>${type === 'success' ? 'Éxito: ' : 'Error: '}</strong> ${message}`)
                  .slideDown(250);
            
            setTimeout(function() {
                $alert.slideUp(250);
            }, 5000);
        }
    }
});
