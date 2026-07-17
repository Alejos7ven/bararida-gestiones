/**
 * Lógica del Módulo de Registro de Instituciones - instituciones.js
 * Parque Zoológico y Botánico Bararida
 */

$(document).ready(function() {
    // Referencias DOM principales
    const $tablaInst = $('#tablaInstitucionesBody');
    const $loader = $('#loaderInstituciones');
    const $searchInst = $('#searchInstituciones');

    // Variables de paginación
    let cacheInstituciones = [];
    let filteredInstituciones = [];
    let currentPageInstituciones = 1;
    const itemsPerPage = 10;

    // Cargar listado al iniciar
    listarInstituciones();

    // =========================================================================
    // 1. LISTAR INSTITUCIONES (AJAX)
    // =========================================================================
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
                    
                    // Mantener búsqueda si la hay
                    const query = $searchInst.val().toLowerCase().trim();
                    if (query === '') {
                        filteredInstituciones = cacheInstituciones;
                    } else {
                        filteredInstituciones = cacheInstituciones.filter(function(inst) {
                            const searchStr = `${inst.nombre.toLowerCase()} ${inst.tipo_documento.toLowerCase()}-${inst.documento.toLowerCase()} ${inst.tipo_organizacion.toLowerCase()} ${inst.tipo_gestion || ''}`;
                            return searchStr.indexOf(query) > -1;
                        });
                    }

                    renderInstitucionesTable();
                } else {
                    $tablaInst.empty().append('<tr><td colspan="7" class="text-center text-muted py-4">No se pudo cargar el listado de instituciones.</td></tr>');
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
            $tablaInst.append('<tr><td colspan="7" class="text-center text-muted py-4">No se encontraron instituciones registradas.</td></tr>');
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

            let direccion = '';
            if (inst.estado) {
                direccion = `<strong>${inst.estado}</strong>, ${inst.ciudad || ''}<br><small class="text-muted">${inst.detalles_direccion || ''}</small>`;
            } else {
                direccion = '<span class="text-muted italic">No definida</span>';
            }

            const badgeGestion = inst.tipo_gestion === 'privada'
                ? `<span class="badge-premium badge-premium-danger">Privada</span>`
                : `<span class="badge-premium badge-premium-superadmin">Pública</span>`;

            const rowHtml = `
                <tr class="inst-row">
                    <td style="white-space: nowrap;"><strong>${inst.tipo_documento}-${inst.documento}</strong></td>
                    <td>${inst.nombre}</td>
                    <td>${badgeTipo}</td>
                    <td>${badgeGestion}</td>
                    <td>${contactosHtml}</td>
                    <td>${direccion}</td>
                    <td>
                        <div class="table-actions">
                            <button class="btn-action btn-action-edit btn-editar-inst" data-id="${inst.id}" title="Editar Institución">
                                <svg viewBox="0 0 24 24" style="width:20px; height:20px;"><path d="M3 17.25V21H6.75L17.81 9.94L14.06 6.19L3 17.25ZM20.71 7.04C21.1 6.65 21.1 6.02 20.71 5.63L18.37 3.29C17.98 2.9 17.35 2.9 16.96 3.29L15.13 5.12L18.88 8.87L20.71 7.04Z"/></svg>
                            </button>
                            <button class="btn-action btn-action-delete btn-eliminar-inst" data-id="${inst.id}" data-name="${inst.nombre}" title="Eliminar Institución">
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

        // Botones de Páginas (Ventana Deslizante de Máximo 10)
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
                const searchStr = `${inst.nombre.toLowerCase()} ${inst.tipo_documento.toLowerCase()}-${inst.documento.toLowerCase()} ${inst.tipo_organizacion.toLowerCase()} ${inst.tipo_gestion || ''}`;
                return searchStr.indexOf(query) > -1;
            });
        }
        currentPageInstituciones = 1;
        renderInstitucionesTable();
    });

    // =========================================================================
    // 2. GESTIÓN DE DIRECCIONES GEOGRÁFICAS EN MODALES (CREATE/EDIT)
    // =========================================================================
    function cargarEstados(prefix, callback = null) {
        const $selectEstado = $(`#${prefix}_estado_id`);
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
                    if (callback) callback();
                }
            }
        });
    }

    function bindingUbicaciones(prefix) {
        $(`#${prefix}_estado_id`).on('change', function() {
            const estadoId = $(this).val();
            const $selectMun = $(`#${prefix}_municipio_id`);
            const $selectCiu = $(`#${prefix}_ciudad_id`);
            const $selectPar = $(`#${prefix}_parroquia_id`);

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

        $(`#${prefix}_ciudad_id`).on('change', function() {
            const ciudadId = $(this).val();
            const estadoId = $(`#${prefix}_estado_id`).val();
            const $selectMun = $(`#${prefix}_municipio_id`);
            const $selectPar = $(`#${prefix}_parroquia_id`);

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

        $(`#${prefix}_municipio_id`).on('change', function() {
            const municipioId = $(this).val();
            const $selectPar = $(`#${prefix}_parroquia_id`);

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
    }

    // Inicializar bindings geográficos
    bindingUbicaciones('create');
    bindingUbicaciones('edit');

    // Alternar tipo de organización personalizado
    $(document).on('change', '.select-tipo-organizacion', function() {
        const value = $(this).val();
        const prefix = $(this).attr('id').startsWith('create') ? 'create' : 'edit';
        const $customContainer = $(`#${prefix}_custom_tipo_org_container`);
        const $customInput = $(`#${prefix}_tipo_organizacion`);

        if (value === 'Otro') {
            $customContainer.show();
            $customInput.prop('required', true).prop('disabled', false).val('');
            $(this).removeAttr('name');
            $customInput.attr('name', 'tipo_organizacion');
        } else {
            $customContainer.hide();
            $customInput.prop('required', false).prop('disabled', true).val('');
            $(this).attr('name', 'tipo_organizacion');
            $customInput.removeAttr('name');
        }
    });

    // =========================================================================
    // 3. REGISTRO DE CONTACTOS DINÁMICOS DIVIDIDOS
    // =========================================================================
    let contactIndex = 0;

    function agregarContactoDividido(containerId, type, initialVal = '') {
        contactIndex++;
        const isEmail = type === 'correo';
        
        let subType = 'correo';
        if (!isEmail) {
            const phoneCount = $(`#${containerId} .contact-row`).length;
            subType = phoneCount === 0 ? 'principal' : 'secundario';
        }

        const placeholder = isEmail ? 'Ej. contacto@empresa.com' : 'Ej. 04121234567';
        const inputType = isEmail ? 'email' : 'tel';
        const labelClass = isEmail ? 'badge bg-info text-white' : 'badge bg-success text-white';
        const labelText = isEmail ? 'Correo' : (subType === 'principal' ? 'Tlf Principal' : 'Tlf Adicional');
        
        const isDeletable = $(`#${containerId} .contact-row`).length > 0;
        const deleteButton = isDeletable 
            ? `<button type="button" class="btn-remove-contact btn-eliminar-contacto-row" title="Eliminar Contacto" style="border:none; background:none; color:#ef4444; font-weight:bold; margin-left:8px;">✕</button>` 
            : `<div style="width: 22px;"></div>`;

        const rowHtml = `
            <div class="contact-row d-flex align-items-center mb-2">
                <span class="${labelClass} me-2" style="font-size:0.75rem; min-width:95px; text-align:center; padding: 6px 4px; border-radius: 6px;">${labelText}</span>
                <input type="${inputType}" class="form-control form-premium-control contact-input flex-grow-1" 
                       name="contactos[${contactIndex}][contacto]" value="${initialVal}" placeholder="${placeholder}" required>
                <input type="hidden" name="contactos[${contactIndex}][tipo]" value="${subType}">
                ${deleteButton}
            </div>
        `;
        $(`#${containerId}`).append(rowHtml);
    }

    // Listeners para añadir contactos
    $('#btnCreateAddPhone').on('click', function() {
        agregarContactoDividido('createPhonesContainer', 'telefono');
    });
    $('#btnCreateAddEmail').on('click', function() {
        agregarContactoDividido('createEmailsContainer', 'correo');
    });
    $('#btnEditAddPhone').on('click', function() {
        agregarContactoDividido('editPhonesContainer', 'telefono');
    });
    $('#btnEditAddEmail').on('click', function() {
        agregarContactoDividido('editEmailsContainer', 'correo');
    });

    // Delegar eliminación de contactos
    $(document).on('click', '.btn-eliminar-contacto-row', function() {
        $(this).closest('.contact-row').remove();
    });

    // =========================================================================
    // 4. ALTA DE NUEVA INSTITUCIÓN (POST)
    // =========================================================================
    $('#modalCrearInstitucion').on('show.bs.modal', function () {
        $('#formCrearInstitucion')[0].reset();
        
        // Reset tipo organizacion select & custom input
        $('#create_tipo_organizacion_select').val('').attr('name', 'tipo_organizacion');
        $('#create_custom_tipo_org_container').hide();
        $('#create_tipo_organizacion').val('').removeAttr('name');

        // Reset contactos
        $('#createPhonesContainer').empty();
        $('#createEmailsContainer').empty();
        agregarContactoDividido('createPhonesContainer', 'telefono');
        agregarContactoDividido('createEmailsContainer', 'correo');

        $('#alertCrearInstFeedback').hide().text('');
        cargarEstados('create');
        $('#create_municipio_id, #create_ciudad_id, #create_parroquia_id').prop('disabled', true);
    });

    $('#formCrearInstitucion').on('submit', function(e) {
        e.preventDefault();
        const $form = $(this);

        if (!$form.valid()) {
            return;
        }

        const $btn = $('#btnGuardarNuevaInst');
        const $alert = $('#alertCrearInstFeedback');

        let contactFilled = false;
        $('#createPhonesContainer .contact-input, #createEmailsContainer .contact-input').each(function() {
            if ($(this).val().trim() !== '') contactFilled = true;
        });

        if (!contactFilled) {
            $alert.text('Debe registrar al menos un número o correo en el directorio de contactos.').show();
            return;
        }

        $btn.prop('disabled', true).text('Registrando...');
        $alert.hide();

        $.ajax({
            url: 'controllers/InstitucionController.php?action=crear',
            method: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response) {
                $btn.prop('disabled', false).text('Registrar');
                if (response.status === 'success') {
                    $('#modalCrearInstitucion').modal('hide');
                    showGlobalAlert(response.message, 'success');
                    listarInstituciones();
                } else {
                    $alert.text(response.message).show();
                }
            },
            error: function(xhr) {
                $btn.prop('disabled', false).text('Registrar');
                let errorMsg = 'Error en el servidor al intentar registrar la institución.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                $alert.text(errorMsg).show();
            }
        });
    });

    // =========================================================================
    // 5. EDICIÓN DE INSTITUCIÓN (GET + POST)
    // =========================================================================
    $(document).on('click', '.btn-editar-inst', function() {
        const id = $(this).data('id');
        const $alert = $('#alertEditarInstFeedback');
        
        $alert.hide().text('');
        $('#formEditarInstitucion')[0].reset();
        $('#editPhonesContainer').empty();
        $('#editEmailsContainer').empty();

        // Cargar datos vía AJAX
        $.ajax({
            url: `controllers/InstitucionController.php?action=obtener&id=${id}`,
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    const inst = response.data;
                    $('#edit_id').val(inst.institucion_id);
                    $('#edit_entidad_id').val(inst.entidad_id);
                    $('#edit_tipo_documento').val(inst.tipo_documento);
                    $('#edit_documento').val(inst.documento);
                    $('#edit_nombre').val(inst.nombre);
                    // Pre-poblar tipo de organización
                    const predefTypes = ['Educativa', 'Empresarial', 'Gubernamental', 'Fundación / ONG', 'Comunitaria', 'Religiosa'];
                    if (predefTypes.includes(inst.tipo_organizacion)) {
                        $('#edit_tipo_organizacion_select').val(inst.tipo_organizacion).attr('name', 'tipo_organizacion');
                        $('#edit_custom_tipo_org_container').hide();
                        $('#edit_tipo_organizacion').val('').removeAttr('name').prop('disabled', true).prop('required', false);
                    } else {
                        $('#edit_tipo_organizacion_select').val('Otro').removeAttr('name');
                        $('#edit_custom_tipo_org_container').show();
                        $('#edit_tipo_organizacion').val(inst.tipo_organizacion).attr('name', 'tipo_organizacion').prop('disabled', false).prop('required', true);
                    }

                    $('#edit_tipo_gestion').val(inst.tipo_gestion || 'publica');
                    $('#edit_detalles_direccion').val(inst.detalles_direccion || '');

                    // Pre-poblar contactos divididos
                    $('#editPhonesContainer').empty();
                    $('#editEmailsContainer').empty();
                    if (inst.contactos && inst.contactos.length > 0) {
                        inst.contactos.forEach(function(c) {
                            if (c.tipo === 'correo') {
                                agregarContactoDividido('editEmailsContainer', 'correo', c.contacto);
                            } else {
                                agregarContactoDividido('editPhonesContainer', 'telefono', c.contacto);
                            }
                        });
                    }
                    // Garantizar que haya al menos una fila de cada uno por si no tiene
                    if ($('#editPhonesContainer .contact-row').length === 0) {
                        agregarContactoDividido('editPhonesContainer', 'telefono');
                    }
                    if ($('#editEmailsContainer .contact-row').length === 0) {
                        agregarContactoDividido('editEmailsContainer', 'correo');
                    }

                    // Cargar Ubicaciones en cascada de forma controlada
                    cargarEstados('edit', function() {
                        $('#edit_estado_id').val(inst.id_estado);

                        // Cargar Municipios y Ciudades
                        $.ajax({
                            url: `controllers/LocationController.php?action=municipios&estado_id=${inst.id_estado}`,
                            method: 'GET',
                            dataType: 'json',
                            success: function(munRes) {
                                if (munRes.status === 'success') {
                                    const $selectMun = $('#edit_municipio_id');
                                    $selectMun.empty().append('<option value="">Seleccione Municipio</option>');
                                    munRes.data.forEach(function(m) {
                                        $selectMun.append(`<option value="${m.id_municipio}">${m.municipio}</option>`);
                                    });
                                    $selectMun.val(inst.id_municipio);
                                }
                            }
                        });

                        $.ajax({
                            url: `controllers/LocationController.php?action=ciudades&estado_id=${inst.id_estado}`,
                            method: 'GET',
                            dataType: 'json',
                            success: function(ciuRes) {
                                if (ciuRes.status === 'success') {
                                    const $selectCiu = $('#edit_ciudad_id');
                                    $selectCiu.empty().append('<option value="">Seleccione Ciudad</option>');
                                    ciuRes.data.forEach(function(c) {
                                        $selectCiu.append(`<option value="${c.id_ciudad}">${c.ciudad}</option>`);
                                    });
                                    $selectCiu.val(inst.ciudad_id);
                                }
                            }
                        });

                        // Cargar Parroquias
                        $.ajax({
                            url: `controllers/LocationController.php?action=parroquias&municipio_id=${inst.id_municipio}`,
                            method: 'GET',
                            dataType: 'json',
                            success: function(parRes) {
                                if (parRes.status === 'success') {
                                    const $selectPar = $('#edit_parroquia_id');
                                    $selectPar.empty().append('<option value="">Seleccione Parroquia</option>');
                                    parRes.data.forEach(function(p) {
                                        $selectPar.append(`<option value="${p.id_parroquia}">${p.parroquia}</option>`);
                                    });
                                    $selectPar.val(inst.parroquia_id);
                                }
                            }
                        });
                    });

                    // Mostrar modal
                    $('#modalEditarInstitucion').modal('show');
                } else {
                    showGlobalAlert(response.message, 'danger');
                }
            },
            error: function() {
                showGlobalAlert('Error al conectar con el servidor para obtener los datos de la institución.', 'danger');
            }
        });
    });

    $('#formEditarInstitucion').on('submit', function(e) {
        e.preventDefault();
        const $form = $(this);

        if (!$form.valid()) {
            return;
        }

        const $btn = $('#btnGuardarEditInst');
        const $alert = $('#alertEditarInstFeedback');

        let contactFilled = false;
        $('#editPhonesContainer .contact-input, #editEmailsContainer .contact-input').each(function() {
            if ($(this).val().trim() !== '') contactFilled = true;
        });

        if (!contactFilled) {
            $alert.text('Debe registrar al menos un número o correo en el directorio de contactos.').show();
            return;
        }

        $btn.prop('disabled', true).text('Guardando...');
        $alert.hide();

        $.ajax({
            url: 'controllers/InstitucionController.php?action=actualizar',
            method: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response) {
                $btn.prop('disabled', false).text('Guardar Cambios');
                if (response.status === 'success') {
                    $('#modalEditarInstitucion').modal('hide');
                    showGlobalAlert(response.message, 'success');
                    listarInstituciones();
                } else {
                    $alert.text(response.message).show();
                }
            },
            error: function(xhr) {
                $btn.prop('disabled', false).text('Guardar Cambios');
                let errorMsg = 'Error en el servidor al intentar actualizar la institución.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                $alert.text(errorMsg).show();
            }
        });
    });

    // =========================================================================
    // 6. ELIMINAR INSTITUCIÓN (DELETE)
    // =========================================================================
    $(document).on('click', '.btn-eliminar-inst', function() {
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
                $btn.prop('disabled', false).text('Eliminar');
                $('#modalEliminarInstitucion').modal('hide');

                if (response.status === 'success') {
                    showGlobalAlert(response.message, 'success');
                    listarInstituciones();
                } else {
                    showGlobalAlert(response.message, 'danger');
                }
            },
            error: function(xhr) {
                $btn.prop('disabled', false).text('Eliminar');
                $('#modalEliminarInstitucion').modal('hide');
                let errorMsg = 'Ocurrió un error al intentar eliminar la institución.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                showGlobalAlert(errorMsg, 'danger');
            }
        });
    });

    // =========================================================================
    // 7. TOAST GLOBAL
    // =========================================================================
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
});
