/**
 * Lógica del CRUD de Personal - personal.js
 * Parque Zoológico y Botánico Bararida
 */

$(document).ready(function() {
    // Referencias principales
    const $tablaPersonal = $('#tablaPersonalBody');
    const $loader = $('#loaderPersonal');
    const $alertFeedback = $('#alertFeedbackGlobal');
    
    // Cargar listado al iniciar
    listarPersonal();

    // =========================================================================
    // LISTAR PERSONAL (AJAX)
    // =========================================================================
    function listarPersonal() {
        $tablaPersonal.hide();
        $loader.show();

        $.ajax({
            url: 'controllers/PersonalController.php?action=listar',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                $loader.hide();
                $tablaPersonal.empty();

                if (response.status === 'success' && response.data.length > 0) {
                    response.data.forEach(function(u) {
                        // Badge de rol decorado
                        let badgeRol = '';
                        if (u.rol === 'superadmin') {
                            badgeRol = '<span class="badge-premium badge-premium-superadmin">Super Admin</span>';
                        } else if (u.rol === 'admin') {
                            badgeRol = '<span class="badge-premium badge-premium-admin">Administrador</span>';
                        } else {
                            badgeRol = '<span class="badge-premium badge-premium-guia">Guía de Campo</span>';
                        }

                        // El Superadmin no puede ser eliminado del sistema
                        const deleteDisabled = (u.rol === 'superadmin') ? 'disabled' : '';
                        const nameFull = `${u.nombre} ${u.apellido}`;

                        const rowHtml = `
                            <tr class="personal-row" data-search="${u.cedula} ${u.nombre.toLowerCase()} ${u.apellido.toLowerCase()}">
                                <td class="fw-600">#${u.id}</td>
                                <td><strong>${u.tipo_documento}-${u.cedula}</strong></td>
                                <td>${nameFull}</td>
                                <td>${badgeRol}</td>
                                <td>${u.created_at}</td>
                                <td>
                                    <div class="table-actions">
                                        <button class="btn-action btn-action-edit btn-editar-personal" data-id="${u.id}" title="Editar Personal">
                                            <!-- SVG Pencil -->
                                            <svg viewBox="0 0 24 24"><path d="M3 17.25V21H6.75L17.81 9.94L14.06 6.19L3 17.25ZM20.71 7.04C21.1 6.65 21.1 6.02 20.71 5.63L18.37 3.29C17.98 2.9 17.35 2.9 16.96 3.29L15.13 5.12L18.88 8.87L20.71 7.04Z"/></svg>
                                        </button>
                                        <button class="btn-action btn-action-delete btn-eliminar-personal" data-id="${u.id}" data-name="${nameFull}" ${deleteDisabled} title="Eliminar Personal">
                                            <!-- SVG Trash -->
                                            <svg viewBox="0 0 24 24"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        `;
                        $tablaPersonal.append(rowHtml);
                    });
                    $tablaPersonal.fadeIn(300);
                } else {
                    $tablaPersonal.append('<tr><td colspan="6" class="text-center text-muted py-4">No hay personal registrado en el sistema.</td></tr>');
                    $tablaPersonal.fadeIn(300);
                }
            },
            error: function(xhr) {
                $loader.hide();
                showGlobalAlert('Ocurrió un error al cargar la lista de personal. Contacte a soporte.', 'danger');
            }
        });
    }

    // =========================================================================
    // FILTRADO / BUSCADOR EN TIEMPO REAL
    // =========================================================================
    $('#searchPersonal').on('input', function() {
        const query = $(this).val().toLowerCase().trim();
        
        if (query === '') {
            $('.personal-row').show();
            return;
        }

        $('.personal-row').each(function() {
            const searchText = $(this).data('search');
            if (searchText.indexOf(query) > -1) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });

    // =========================================================================
    // CREAR PERSONAL (AJAX)
    // =========================================================================
    $('#formCrearPersonal').on('submit', function(e) {
        e.preventDefault();

        const $form = $(this);
        const $btn = $('#btnGuardarNuevo');
        const $alert = $('#alertCrearFeedback');

        // Validaciones previas cliente
        const password = $('#password').val();
        if (password.length < 6) {
            showAlert($alert, 'La contraseña debe tener al menos 6 caracteres.', 'danger');
            return;
        }

        $btn.prop('disabled', true).text('Guardando...');
        $alert.hide();

        $.ajax({
            url: 'controllers/PersonalController.php?action=crear',
            method: 'POST',
            data: $form.serialize(),
            dataType: 'json',
            success: function(response) {
                $btn.prop('disabled', false).text('Registrar Personal');
                if (response.status === 'success') {
                    $('#modalCrearPersonal').modal('hide');
                    $form[0].reset();
                    showGlobalAlert(response.message, 'success');
                    listarPersonal();
                } else {
                    showAlert($alert, response.message, 'danger');
                }
            },
            error: function(xhr) {
                $btn.prop('disabled', false).text('Registrar Personal');
                let errorMsg = 'Error en el servidor al intentar registrar el personal.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                showAlert($alert, errorMsg, 'danger');
            }
        });
    });

    // =========================================================================
    // OBTENER DATOS PARA EDICIÓN
    // =========================================================================
    $(document).on('click', '.btn-editar-personal', function() {
        const id = $(this).data('id');
        const $modal = $('#modalEditarPersonal');
        const $alert = $('#alertEditarFeedback');
        const $rolSelect = $('#edit_rol');

        $alert.hide();
        $('#edit_password').val(''); // Vaciar campo clave en edición

        $.ajax({
            url: `controllers/PersonalController.php?action=obtener&id=${id}`,
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    const u = response.data;
                    
                    $('#edit_id').val(u.id);
                    $('#edit_tipo_documento').val(u.tipo_documento || 'V');
                    $('#edit_cedula').val(u.cedula);
                    $('#edit_nombre').val(u.nombre);
                    $('#edit_apellido').val(u.apellido);

                    // Lógica Dinámica de Dropdown de Roles (Constraint de Superadmin)
                    $rolSelect.empty();
                    if (u.rol === 'superadmin') {
                        // Si es superadmin, no puede ser degradado
                        $rolSelect.append('<option value="superadmin" selected>Super Administrador</option>');
                        $rolSelect.prop('disabled', true);
                    } else {
                        // Si NO es superadmin, no se puede promover a super (solo roles de la BD)
                        if (typeof DB_ROLES !== 'undefined' && Array.isArray(DB_ROLES)) {
                            DB_ROLES.forEach(function(r) {
                                let label = r;
                                if (r === 'admin') label = 'Administrador';
                                else if (r === 'guia') label = 'Guía de Campo';
                                else label = r.charAt(0).toUpperCase() + r.slice(1);
                                
                                $rolSelect.append(`<option value="${r}">${label}</option>`);
                            });
                        } else {
                            $rolSelect.append('<option value="admin">Administrador</option>');
                            $rolSelect.append('<option value="guia">Guía de Campo</option>');
                        }
                        $rolSelect.val(u.rol);
                        $rolSelect.prop('disabled', false);
                    }

                    $modal.modal('show');
                } else {
                    showGlobalAlert(response.message, 'danger');
                }
            },
            error: function(xhr) {
                showGlobalAlert('No se pudieron recuperar los datos del personal para editar.', 'danger');
            }
        });
    });

    // =========================================================================
    // GUARDAR CAMBIOS DE EDICIÓN (AJAX)
    // =========================================================================
    $('#formEditarPersonal').on('submit', function(e) {
        e.preventDefault();

        const $form = $(this);
        const $btn = $('#btnGuardarEditar');
        const $alert = $('#alertEditarFeedback');

        // Validaciones previas cliente
        const password = $('#edit_password').val();
        if (password.length > 0 && password.length < 6) {
            showAlert($alert, 'La nueva contraseña debe tener al menos 6 caracteres.', 'danger');
            return;
        }

        $btn.prop('disabled', true).text('Guardando...');
        $alert.hide();

        // Si el rol select está deshabilitado (en caso del superadmin),
        // serialize() de jQuery no lo incluye, por lo que agregamos manualmente el valor 'superadmin'
        let formData = $form.serialize();
        if ($('#edit_rol').is(':disabled')) {
            formData += '&rol=superadmin';
        }

        $.ajax({
            url: 'controllers/PersonalController.php?action=actualizar',
            method: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                $btn.prop('disabled', false).text('Guardar Cambios');
                if (response.status === 'success') {
                    $('#modalEditarPersonal').modal('hide');
                    showGlobalAlert(response.message, 'success');
                    
                    // Si el superadmin editó sus propios datos, refrescamos el header superior al instante
                    const currentLoggedId = $('#currentLoggedUserId').val();
                    const editedId = $('#edit_id').val();
                    if (currentLoggedId === editedId) {
                        const newNombre = $('#edit_nombre').val();
                        const newApellido = $('#edit_apellido').val();
                        $('.topbar .user-profile .name').text(`${newNombre} ${newApellido}`);
                        
                        // Actualizar avatar
                        const initials = (newNombre.substring(0,1) + newApellido.substring(0,1)).toUpperCase();
                        $('.topbar .user-profile .avatar-circle').text(initials);
                    }

                    listarPersonal();
                } else {
                    showAlert($alert, response.message, 'danger');
                }
            },
            error: function(xhr) {
                $btn.prop('disabled', false).text('Guardar Cambios');
                let errorMsg = 'Error en el servidor al intentar actualizar el personal.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                showAlert($alert, errorMsg, 'danger');
            }
        });
    });

    // =========================================================================
    // ABRIR CONFIRMACIÓN DE ELIMINACIÓN
    // =========================================================================
    $(document).on('click', '.btn-eliminar-personal', function() {
        const id = $(this).data('id');
        const name = $(this).data('name');

        $('#delete_id').val(id);
        $('#deleteUserNameSpan').text(name);
        $('#modalEliminarPersonal').modal('show');
    });

    // =========================================================================
    // CONFIRMAR ELIMINACIÓN (AJAX)
    // =========================================================================
    $('#btnConfirmarEliminar').on('click', function() {
        const id = $('#delete_id').val();
        const $btn = $(this);
        
        $btn.prop('disabled', true).text('Eliminando...');

        $.ajax({
            url: 'controllers/PersonalController.php?action=eliminar',
            method: 'POST',
            data: { id: id },
            dataType: 'json',
            success: function(response) {
                $btn.prop('disabled', false).text('Eliminar Personal');
                $('#modalEliminarPersonal').modal('hide');
                
                if (response.status === 'success') {
                    showGlobalAlert(response.message, 'success');
                    listarPersonal();
                } else {
                    showGlobalAlert(response.message, 'danger');
                }
            },
            error: function(xhr) {
                $btn.prop('disabled', false).text('Eliminar Personal');
                $('#modalEliminarPersonal').modal('hide');
                let errorMsg = 'Error en el servidor al intentar eliminar el personal.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                showGlobalAlert(errorMsg, 'danger');
            }
        });
    });

    // =========================================================================
    // FUNCIONES AUXILIARES DE ALERTA
    // =========================================================================
    function showAlert($element, message, type) {
        $element.removeClass('alert-success alert-danger')
                .addClass(type === 'success' ? 'alert-success' : 'alert-danger')
                .text(message)
                .fadeIn(200);
    }

    function showGlobalAlert(message, type) {
        $alertFeedback.removeClass('alert-toast-success alert-toast-danger')
                      .addClass(type === 'success' ? 'alert-toast-success' : 'alert-toast-danger')
                      .html(`<strong>${type === 'success' ? 'Éxito: ' : 'Error: '}</strong> ${message}`)
                      .slideDown(250);
        
        // Autoocultar tras 5 segundos
        setTimeout(function() {
            $alertFeedback.slideUp(250);
        }, 5000);
    }
});
