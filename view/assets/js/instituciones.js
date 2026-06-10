/**
 * Lógica del Módulo de Registro de Instituciones - instituciones.js
 * Parque Zoológico y Botánico Bararida
 */

$(document).ready(function() {
    // Referencias DOM principales
    const $tablaInst = $('#tablaInstitucionesBody');
    const $loader = $('#loaderInstituciones');
    const $searchInst = $('#searchInstituciones');

    // Cargar listado al iniciar
    listarInstituciones();

    // =========================================================================
    // 1. LISTAR INSTITUCIONES (AJAX)
    // =========================================================================
    function listarInstituciones() {
        $tablaInst.hide();
        $loader.show();

        $.ajax({
            url: 'controllers/InstitucionController.php?action=listar',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                $loader.hide();
                $tablaInst.empty();

                if (response.status === 'success' && response.data.length > 0) {
                    response.data.forEach(function(inst) {
                        // Construir insignia de tipo de organización
                        let badgeTipo = `<span class="badge-premium badge-premium-info">${inst.tipo_organizacion}</span>`;
                        if (inst.tipo_organizacion.toLowerCase() === 'educativa') {
                            badgeTipo = `<span class="badge-premium badge-premium-admin">Educativa</span>`;
                        } else if (inst.tipo_organizacion.toLowerCase() === 'empresarial') {
                            badgeTipo = `<span class="badge-premium badge-premium-superadmin">Empresarial</span>`;
                        } else if (inst.tipo_organizacion.toLowerCase() === 'comunitaria') {
                            badgeTipo = `<span class="badge-premium badge-premium-guia">Comunitaria</span>`;
                        }

                        // Construir listado corto de contactos
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

                        // Dirección formateada
                        let direccion = '';
                        if (inst.estado) {
                            direccion = `<strong>${inst.estado}</strong>, ${inst.ciudad || ''}<br><small class="text-muted">${inst.detalles_direccion || ''}</small>`;
                        } else {
                            direccion = '<span class="text-muted italic">No definida</span>';
                        }

                        const rowHtml = `
                            <tr class="inst-row" data-search="${inst.nombre.toLowerCase()} ${inst.tipo_documento}-${inst.documento.toLowerCase()} ${inst.tipo_organizacion.toLowerCase()}">
                                <td style="white-space: nowrap;"><strong>${inst.tipo_documento}-${inst.documento}</strong></td>
                                <td>${inst.nombre}</td>
                                <td>${badgeTipo}</td>
                                <td>${contactosHtml}</td>
                                <td>${direccion}</td>
                                <td>
                                    <div class="table-actions">
                                        <button class="btn-action btn-action-edit btn-editar-inst" data-id="${inst.id}" title="Editar Institución">
                                            <!-- SVG Edit -->
                                            <svg viewBox="0 0 24 24" style="width:20px; height:20px;"><path d="M3 17.25V21H6.75L17.81 9.94L14.06 6.19L3 17.25ZM20.71 7.04C21.1 6.65 21.1 6.02 20.71 5.63L18.37 3.29C17.98 2.9 17.35 2.9 16.96 3.29L15.13 5.12L18.88 8.87L20.71 7.04Z"/></svg>
                                        </button>
                                        <button class="btn-action btn-action-delete btn-eliminar-inst" data-id="${inst.id}" data-name="${inst.nombre}" title="Eliminar Institución">
                                            <!-- SVG Trash -->
                                            <svg viewBox="0 0 24 24" style="width:20px; height:20px;"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        `;
                        $tablaInst.append(rowHtml);
                    });
                    $tablaInst.fadeIn(300);
                } else {
                    $tablaInst.append('<tr><td colspan="6" class="text-center text-muted py-4">No hay instituciones registradas en el sistema.</td></tr>');
                    $tablaInst.fadeIn(300);
                }
            },
            error: function() {
                $loader.hide();
                showGlobalAlert('Ocurrió un error al cargar el listado de instituciones.', 'danger');
            }
        });
    }

    // Buscador en tiempo real
    $searchInst.on('input', function() {
        const query = $(this).val().toLowerCase().trim();
        if (query === '') {
            $('.inst-row').show();
            return;
        }
        $('.inst-row').each(function() {
            const searchText = $(this).data('search');
            if (searchText.indexOf(query) > -1) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
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

            // Cargar Municipios
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

            // Cargar Ciudades
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

        $(`#${prefix}_municipio_id`).on('change', function() {
            const municipioId = $(this).val();
            const $selectPar = $(`#${prefix}_parroquia_id`);

            $selectPar.empty().append('<option value="">Seleccione Parroquia</option>').prop('disabled', true);

            if (!municipioId) return;

            // Cargar Parroquias
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

    // =========================================================================
    // 3. REGISTRO DE CONTACTOS DINÁMICOS
    // =========================================================================
    let contactIndex = 0;

    function agregarFilaContacto(containerId, prefix) {
        contactIndex++;
        const isPrincipal = $(`#${containerId} .contact-row`).length === 0;
        const typeSelected = isPrincipal ? 'principal' : 'secundario';
        const deleteButton = isPrincipal 
            ? '<div style="width:28px;"></div>' 
            : `<button type="button" class="btn-remove-contact btn-eliminar-contacto-row" title="Eliminar Contacto">✕</button>`;

        const rowHtml = `
            <div class="contact-row">
                <input type="text" class="form-control form-premium-control contact-input" 
                       name="contactos[${contactIndex}][contacto]" placeholder="Número o correo..." required>
                <select class="form-select form-premium-control" name="contactos[${contactIndex}][tipo]" style="max-width:180px;">
                    <option value="principal" ${typeSelected === 'principal' ? 'selected' : ''}>Principal</option>
                    <option value="secundario" ${typeSelected === 'secundario' ? 'selected' : ''}>Secundario</option>
                    <option value="correo">Correo</option>
                </select>
                ${deleteButton}
            </div>
        `;
        $(`#${containerId}`).append(rowHtml);
    }

    // Listeners para añadir contactos
    $('#btnCreateAddNewContact').on('click', function() {
        agregarFilaContacto('createContactsContainer', 'create');
    });

    $('#btnEditAddNewContact').on('click', function() {
        agregarFilaContacto('editContactsContainer', 'edit');
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
        $('#createContactsContainer').empty();
        agregarFilaContacto('createContactsContainer', 'create');
        $('#alertCrearInstFeedback').hide().text('');
        cargarEstados('create');
        $('#create_municipio_id, #create_ciudad_id, #create_parroquia_id').prop('disabled', true);
    });

    $('#formCrearInstitucion').on('submit', function(e) {
        e.preventDefault();
        const $btn = $('#btnGuardarNuevaInst');
        const $alert = $('#alertCrearInstFeedback');

        // Validaciones
        const RIF = $('#create_documento').val().trim();
        if (RIF === '' || !/^\d+$/.test(RIF)) {
            $alert.text('Por favor, ingrese un número de RIF/Cédula válido (solo dígitos).').show();
            return;
        }

        let contactFilled = false;
        $('#createContactsContainer .contact-input').each(function() {
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
        $('#editContactsContainer').empty();

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
                    $('#edit_tipo_organizacion').val(inst.tipo_organizacion);
                    $('#edit_detalles_direccion').val(inst.detalles_direccion || '');

                    // Pre-poblar contactos
                    if (inst.contactos && inst.contactos.length > 0) {
                        inst.contactos.forEach(function(c, index) {
                            contactIndex++;
                            const isPrincipal = index === 0;
                            const deleteButton = isPrincipal 
                                ? '<div style="width:28px;"></div>' 
                                : `<button type="button" class="btn-remove-contact btn-eliminar-contacto-row" title="Eliminar Contacto">✕</button>`;

                            const rowHtml = `
                                <div class="contact-row">
                                    <input type="text" class="form-control form-premium-control contact-input" 
                                           name="contactos[${contactIndex}][contacto]" value="${c.contacto}" required>
                                    <select class="form-select form-premium-control" name="contactos[${contactIndex}][tipo]" style="max-width:180px;">
                                        <option value="principal" ${c.tipo === 'principal' ? 'selected' : ''}>Principal</option>
                                        <option value="secundario" ${c.tipo === 'secundario' ? 'selected' : ''}>Secundario</option>
                                        <option value="correo" ${c.tipo === 'correo' ? 'selected' : ''}>Correo</option>
                                    </select>
                                    ${deleteButton}
                                </div>
                            `;
                            $('#editContactsContainer').append(rowHtml);
                        });
                    } else {
                        agregarFilaContacto('editContactsContainer', 'edit');
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
        const $btn = $('#btnGuardarEditInst');
        const $alert = $('#alertEditarInstFeedback');

        // Validaciones
        const RIF = $('#edit_documento').val().trim();
        if (RIF === '' || !/^\d+$/.test(RIF)) {
            $alert.text('Por favor, ingrese un número de RIF/Cédula válido (solo dígitos).').show();
            return;
        }

        let contactFilled = false;
        $('#editContactsContainer .contact-input').each(function() {
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
