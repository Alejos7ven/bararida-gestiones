/**
 * Lógica del CRUD de Personal - personal.js
 * Parque Zoológico y Botánico Bararida
 */

$(document).ready(function() {
    // Referencias principales
    const $tablaPersonal = $('#tablaPersonalBody');
    const $loader = $('#loaderPersonal');
    const $alertFeedback = $('#alertFeedbackGlobal');
    
    let DB_ROLES = [];
    
    // Variables de paginación
    let cachePersonal = [];
    let filteredPersonal = [];
    let currentPagePersonal = 1;
    const itemsPerPage = 10;

    // Cargar listado al iniciar
    listarPersonal();
    cargarRoles();

    function cargarRoles() {
        $.ajax({
            url: 'controllers/PersonalController.php?action=listar_roles',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    DB_ROLES = response.data;
                    window.DB_ROLES = DB_ROLES;
                    
                    // Rellenar select de crear personal
                    const $rol = $('#rol');
                    if ($rol.length > 0) {
                        $rol.empty().append('<option value="" disabled selected>Seleccione un rol...</option>');
                        DB_ROLES.forEach(function(r) {
                            let label = r;
                            if (r === 'admin') label = 'Administrador';
                            else if (r === 'guia') label = 'Guía de Campo';
                            else label = r.charAt(0).toUpperCase() + r.slice(1);
                            
                            $rol.append(`<option value="${r}">${label}</option>`);
                        });
                    }
                }
            },
            error: function() {
                // Fallback a los roles por defecto en caso de error
                DB_ROLES = ['admin', 'guia'];
                window.DB_ROLES = DB_ROLES;
                const $rol = $('#rol');
                if ($rol.length > 0) {
                    $rol.empty().append('<option value="" disabled selected>Seleccione un rol...</option>');
                    $rol.append('<option value="admin">Administrador</option>');
                    $rol.append('<option value="guia">Guía de Campo</option>');
                }
            }
        });
    }

    // =========================================================================
    // LISTAR PERSONAL (AJAX)
    // =========================================================================
    function listarPersonal() {
        $tablaPersonal.html(`
            <tr id="loaderPersonal">
                <td colspan="6" class="text-center py-5">
                    <div class="spinner-border text-success" role="status" style="width: 2.5rem; height: 2.5rem;">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                    <p class="text-muted mt-2 mb-0" style="font-size: 0.9rem;">Cargando registros de personal...</p>
                </td>
            </tr>
        `);
        $('#paginacionPersonal').hide();

        $.ajax({
            url: 'controllers/PersonalController.php?action=listar',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    cachePersonal = response.data || [];
                    
                    // Al recargar, intentar mantener la búsqueda actual
                    const query = $('#searchUser').val().toLowerCase().trim();
                    if (query === '') {
                        filteredPersonal = cachePersonal;
                    } else {
                        filteredPersonal = cachePersonal.filter(function(u) {
                            const name = (u.nombre_completo || `${u.nombre || ''} ${u.apellido || ''}`).toLowerCase();
                            const searchString = `${u.cedula} ${name} ${u.tipo_documento.toLowerCase()}`;
                            return searchString.indexOf(query) > -1;
                        });
                    }
                    
                    renderPersonalTable();
                } else {
                    $tablaPersonal.empty().append('<tr><td colspan="6" class="text-center text-muted py-4">No se pudo cargar la lista de personal.</td></tr>');
                }
            },
            error: function(xhr) {
                $tablaPersonal.empty().append('<tr><td colspan="6" class="text-center text-muted py-4">Ocurrió un error al cargar la lista de personal.</td></tr>');
                showGlobalAlert('Ocurrió un error al cargar la lista de personal. Contacte a soporte.', 'danger');
            }
        });
    }

    function renderPersonalTable() {
        $tablaPersonal.empty();
        const totalRecords = filteredPersonal.length;

        if (totalRecords === 0) {
            $tablaPersonal.append('<tr><td colspan="6" class="text-center text-muted py-4">No se encontraron registros de personal.</td></tr>');
            $('#paginacionPersonal').hide();
            $tablaPersonal.fadeIn(300);
            return;
        }

        const totalPages = Math.ceil(totalRecords / itemsPerPage);
        
        if (currentPagePersonal > totalPages) {
            currentPagePersonal = totalPages;
        }
        if (currentPagePersonal < 1) {
            currentPagePersonal = 1;
        }

        const startIdx = (currentPagePersonal - 1) * itemsPerPage;
        const endIdx = Math.min(startIdx + itemsPerPage, totalRecords);
        const pageData = filteredPersonal.slice(startIdx, endIdx);

        pageData.forEach(function(u) {
            let badgeRol = '';
            if (u.rol === 'superadmin') {
                badgeRol = '<span class="badge-premium badge-premium-superadmin">Super Admin</span>';
            } else if (u.rol === 'admin') {
                badgeRol = '<span class="badge-premium badge-premium-admin">Administrador</span>';
            } else {
                badgeRol = '<span class="badge-premium badge-premium-guia">Guía de Campo</span>';
            }

            const deleteDisabled = (u.rol === 'superadmin') ? 'disabled' : '';
            const nameFull = u.nombre_completo || `${u.nombre || ''} ${u.apellido || ''}`.trim();

            const rowHtml = `
                <tr class="personal-row">
                    <td class="fw-600">#${u.id}</td>
                    <td><strong>${u.tipo_documento}-${u.cedula}</strong></td>
                    <td>${nameFull}</td>
                    <td>${badgeRol}</td>
                    <td>${u.created_at}</td>
                    <td>
                        <div class="table-actions">
                            <button class="btn-action btn-action-edit btn-editar-personal" data-id="${u.id}" title="Editar Personal">
                                <svg viewBox="0 0 24 24"><path d="M3 17.25V21H6.75L17.81 9.94L14.06 6.19L3 17.25ZM20.71 7.04C21.1 6.65 21.1 6.02 20.71 5.63L18.37 3.29C17.98 2.9 17.35 2.9 16.96 3.29L15.13 5.12L18.88 8.87L20.71 7.04Z"/></svg>
                            </button>
                            <button class="btn-action btn-action-delete btn-eliminar-personal" data-id="${u.id}" data-name="${nameFull}" ${deleteDisabled} title="Eliminar Personal">
                                <svg viewBox="0 0 24 24"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
            $tablaPersonal.append(rowHtml);
        });

        // Actualizar datos informativos
        $('#pagStartPersonal').text(startIdx + 1);
        $('#pagEndPersonal').text(endIdx);
        $('#pagTotalPersonal').text(totalRecords);

        // Renderizar controles de paginación
        const $controls = $('#paginationControlsPersonal');
        $controls.empty();

        // Botón Anterior
        const prevDisabled = currentPagePersonal === 1 ? 'disabled' : '';
        $controls.append(`
            <li class="page-item ${prevDisabled}">
                <a class="page-link btn-pag-personal" data-page="${currentPagePersonal - 1}" href="javascript:void(0)">&laquo; Anterior</a>
            </li>
        `);

        // Botones de Páginas (Ventana Deslizante de Máximo 10)
        const maxVisible = 10;
        let startPage = Math.max(1, currentPagePersonal - Math.floor(maxVisible / 2));
        let endPage = Math.min(totalPages, startPage + maxVisible - 1);

        if (endPage - startPage + 1 < maxVisible) {
            startPage = Math.max(1, endPage - maxVisible + 1);
        }

        for (let i = startPage; i <= endPage; i++) {
            const activeClass = currentPagePersonal === i ? 'active' : '';
            $controls.append(`
                <li class="page-item ${activeClass}">
                    <a class="page-link btn-pag-personal" data-page="${i}" href="javascript:void(0)">${i}</a>
                </li>
            `);
        }

        // Botón Siguiente
        const nextDisabled = currentPagePersonal === totalPages ? 'disabled' : '';
        $controls.append(`
            <li class="page-item ${nextDisabled}">
                <a class="page-link btn-pag-personal" data-page="${currentPagePersonal + 1}" href="javascript:void(0)">Siguiente &raquo;</a>
            </li>
        `);

        $('#paginacionPersonal').removeClass('d-none').show();
        $tablaPersonal.fadeIn(300);
    }

    // Manejar clicks en paginación
    $(document).on('click', '.btn-pag-personal', function(e) {
        e.preventDefault();
        const page = parseInt($(this).data('page'));
        if (page && page !== currentPagePersonal) {
            currentPagePersonal = page;
            renderPersonalTable();
        }
    });

    // =========================================================================
    // FILTRADO / BUSCADOR EN TIEMPO REAL
    // =========================================================================
    $('#searchUser').on('input', function() {
        const query = $(this).val().toLowerCase().trim();
        
        if (query === '') {
            filteredPersonal = cachePersonal;
        } else {
            filteredPersonal = cachePersonal.filter(function(u) {
                const searchString = `${u.cedula} ${u.nombre.toLowerCase()} ${u.apellido.toLowerCase()} ${u.tipo_documento.toLowerCase()}`;
                return searchString.indexOf(query) > -1;
            });
        }
        
        currentPagePersonal = 1;
        renderPersonalTable();
    });

    // =========================================================================
    // REVELAR / OCULTAR CONTRASEÑA EN FORMULARIOS DE PERSONAL
    // =========================================================================
    const eyeOpenSvg = '<path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>';
    const eyeClosedSvg = '<path d="M12 7c2.76 0 5 2.24 5 5 0 .65-.13 1.26-.36 1.82l2.92 2.92C21.27 15.39 22.4 13.81 23 12c-1.73-4.39-6-7.5-11-7.5-1.4 0-2.74.25-4 .7l2.18 2.18C10.74 7.13 11.35 7 12 7zM2 4.27l2.28 2.28.46.46C3.08 8.3 1.78 10.02 1 12c1.73 4.39 6 7.5 11 7.5 1.55 0 3.03-.3 4.38-.84l.42.42L19.73 22 21 20.73 3.27 3 2 4.27zM7.53 9.8l1.55 1.55c-.05.21-.08.43-.08.65 0 1.66 1.34 3 3 3 .22 0 .44-.03.65-.08l1.55 1.55c-.67.33-1.41.53-2.2.53-2.76 0-5-2.24-5-5 0-.79.2-1.53.53-2.2zm4.31-.78l3.15 3.15.02-.16c0-1.66-1.34-3-3-3l-.17.01z"/>';

    $(document).on('click', '.btn-toggle-password', function() {
        const $btn = $(this);
        const $input = $btn.closest('.input-group').find('input');
        const $svg = $btn.find('svg');
        const isPassword = $input.attr('type') === 'password';

        if (isPassword) {
            $input.attr('type', 'text');
            $svg.html(eyeClosedSvg);
        } else {
            $input.attr('type', 'password');
            $svg.html(eyeOpenSvg);
        }
    });

    function resetPasswordToggle($modal) {
        $modal.find('.btn-toggle-password').each(function() {
            const $btn = $(this);
            const $input = $btn.closest('.input-group').find('input');
            $input.attr('type', 'password');
            $btn.find('svg').html(eyeOpenSvg);
        });
    }

    $('#modalCrearPersonal').on('show.bs.modal', function() {
        resetPasswordToggle($(this));
        $('#alertCrearFeedback').hide();
        if (typeof window.limpiarErroresFormulario === 'function') {
            window.limpiarErroresFormulario('#formCrearPersonal');
        } else {
            $('#formCrearPersonal').find('.is-invalid').removeClass('is-valid');
            $('#formCrearPersonal').find('.invalid-feedback').hide().text('');
        }
    });

    $('#modalCrearPersonal').on('hidden.bs.modal', function() {
        resetPasswordToggle($(this));
    });

    $('#modalCrearPersonal').on('shown.bs.modal', function() {
        setTimeout(function() {
            $('#cedula').trigger('focus');
        }, 100);
    });

    $('#modalEditarPersonal').on('show.bs.modal', function() {
        resetPasswordToggle($(this));
    });

    $('#modalEditarPersonal').on('hidden.bs.modal', function() {
        resetPasswordToggle($(this));
    });

    $('#formCrearPersonal').on('submit', function(e) {
        e.preventDefault();

        const $form = $(this);
        const $btn = $('#btnGuardarNuevo');
        const $alert = $('#alertCrearFeedback');

        if (!$form.valid()) {
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
                    window.mapearErrorServidorAFormulario('#formCrearPersonal', response.message, null, $alert);
                }
            },
            error: function(xhr) {
                $btn.prop('disabled', false).text('Registrar Personal');
                let errorMsg = 'Error en el servidor al intentar registrar el personal.';
                let field = null;
                if (xhr.responseJSON) {
                    if (xhr.responseJSON.message) errorMsg = xhr.responseJSON.message;
                    if (xhr.responseJSON.field) field = xhr.responseJSON.field;
                }
                window.mapearErrorServidorAFormulario('#formCrearPersonal', errorMsg, field, $alert);
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
                    $('#edit_nombre_completo').val(u.nombre_completo || `${u.nombre || ''} ${u.apellido || ''}`.trim());

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
    $('#modalEditarPersonal').on('shown.bs.modal', function() {
        setTimeout(function() {
            $('#edit_cedula').trigger('focus');
        }, 100);
    });

    $('#formEditarPersonal').on('submit', function(e) {
        e.preventDefault();

        const $form = $(this);
        const $btn = $('#btnGuardarEditar');
        const $alert = $('#alertEditarFeedback');

        if (!$form.valid()) {
            return;
        }

        $btn.prop('disabled', true).text('Guardando...');
        $alert.hide();

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
                    
                    const currentLoggedId = $('#currentLoggedUserId').val();
                    const editedId = $('#edit_id').val();
                    if (currentLoggedId === editedId) {
                        const newNombreCompleto = $('#edit_nombre_completo').val();
                        $('.topbar .user-profile .name').text(newNombreCompleto);
                        
                        const initials = newNombreCompleto.substring(0, 2).toUpperCase();
                        $('.topbar .user-profile .avatar-circle').text(initials);
                    }

                    listarPersonal();
                } else {
                    window.mapearErrorServidorAFormulario('#formEditarPersonal', response.message, null, $alert);
                }
            },
            error: function(xhr) {
                $btn.prop('disabled', false).text('Guardar Cambios');
                let errorMsg = 'Error en el servidor al intentar actualizar el personal.';
                let field = null;
                if (xhr.responseJSON) {
                    if (xhr.responseJSON.message) errorMsg = xhr.responseJSON.message;
                    if (xhr.responseJSON.field) field = xhr.responseJSON.field;
                }
                window.mapearErrorServidorAFormulario('#formEditarPersonal', errorMsg, field, $alert);
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
