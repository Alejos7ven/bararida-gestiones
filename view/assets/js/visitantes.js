/**
 * Lógica del Módulo de Visitantes - visitantes.js
 * Parque Zoológico y Botánico Bararida
 */

$(document).ready(function() {
    // Referencias DOM
    const $tablaVisitantes = $('#tablaVisitantesBody');
    const $loader = $('#loaderVisitantes');
    const $searchVisitantes = $('#searchVisitantes');

    // Variables y caché de Visitantes
    let cacheVisitantes = [];
    let dtVisitantes = null;

    // Configurar fecha máxima (hoy) en los campos de fecha de nacimiento
    const todayStr = new Date().toISOString().split('T')[0];
    $('#visitante_fecha_nacimiento, #edit_visitante_fecha_nacimiento').attr('max', todayStr);

    // Inicializar
    listarVisitantes();

    // =========================================================================
    // 1. LISTAR VISITANTES (AJAX & DataTables)
    // =========================================================================
    function listarVisitantes() {
        if ($.fn.DataTable.isDataTable('#tablaVisitantes')) {
            $('#tablaVisitantes').DataTable().destroy();
        }

        $tablaVisitantes.html(`
            <tr id="loaderVisitantes">
                <td colspan="7" class="text-center py-5">
                    <div class="spinner-border text-success" role="status" style="width: 2.5rem; height: 2.5rem;">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                    <p class="text-muted mt-2 mb-0" style="font-size: 0.9rem;">Cargando listado de visitantes...</p>
                </td>
            </tr>
        `);

        $.ajax({
            url: 'controllers/VisitanteController.php?action=listar',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    cacheVisitantes = response.data || [];
                    renderVisitantesTable(cacheVisitantes);
                } else {
                    if ($.fn.DataTable.isDataTable('#tablaVisitantes')) {
                        $('#tablaVisitantes').DataTable().destroy();
                    }
                    $tablaVisitantes.empty().append('<tr><td colspan="7" class="text-center text-muted py-4">No se pudo cargar el listado de visitantes.</td></tr>');
                }
            },
            error: function() {
                if ($.fn.DataTable.isDataTable('#tablaVisitantes')) {
                    $('#tablaVisitantes').DataTable().destroy();
                }
                $tablaVisitantes.empty().append('<tr><td colspan="7" class="text-center text-muted py-4">Ocurrió un error al cargar el listado de visitantes.</td></tr>');
                alert('Ocurrió un error al cargar el listado de visitantes.');
            }
        });
    }

    function renderVisitantesTable(data) {
        if ($.fn.DataTable.isDataTable('#tablaVisitantes')) {
            $('#tablaVisitantes').DataTable().destroy();
        }
        $tablaVisitantes.empty();

        if (!data || data.length === 0) {
            $tablaVisitantes.append('<tr><td colspan="7" class="text-center text-muted py-4">No se encontraron visitantes registrados.</td></tr>');
            return;
        }

        data.forEach(function(v) {
            const cedula = v.cedula ? v.cedula : '<span class="text-muted italic">Sin Cédula (Menor)</span>';
            const fechaNac = v.fecha_nacimiento ? formatDate(v.fecha_nacimiento) : '<span class="text-muted">N/E</span>';

            // Formatear procedencia de la última cita
            let procedenciaHtml = '<span class="badge bg-light text-muted border" style="font-size:0.75rem;">Sin citas registradas</span>';
            if (v.procedencia_institucion) {
                const ubicacionArr = [v.procedencia_municipio, v.procedencia_estado].filter(Boolean);
                const ubicacionStr = ubicacionArr.length > 0 ? ` &bull; ${ubicacionArr.join(', ')}` : '';
                const fechaVisitaStr = v.ultima_cita_fecha ? formatDate(v.ultima_cita_fecha) : '';
                procedenciaHtml = `
                    <div>
                        <strong class="text-success" style="font-size:0.88rem;">${v.procedencia_institucion}</strong>
                        <div class="mt-1" style="font-size:0.75rem; color:#6c757d;">
                            <span>#CIT-${v.ultima_cita_id}${fechaVisitaStr ? ' (' + fechaVisitaStr + ')' : ''}</span>${ubicacionStr}
                        </div>
                    </div>
                `;
            }

            const rowHtml = `
                <tr class="visitor-row">
                    <td><strong>#VIS-${v.id}</strong></td>
                    <td><strong>${cedula}</strong></td>
                    <td>${v.nombres}</td>
                    <td>${v.apellidos}</td>
                    <td>${fechaNac}</td>
                    <td>${procedenciaHtml}</td>
                    <td class="text-center">
                        <div class="table-actions justify-content-center">
                            <button class="btn-action btn-action-edit btn-editar-visitor" data-id="${v.id}" title="Editar Visitante">
                                <svg viewBox="0 0 24 24" style="width:20px; height:20px;"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
                            </button>
                            <button class="btn-action btn-action-delete btn-eliminar-visitor" data-id="${v.id}" data-name="${v.nombres} ${v.apellidos}" title="Eliminar Visitante">
                                <svg viewBox="0 0 24 24" style="width:20px; height:20px;"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
            $tablaVisitantes.append(rowHtml);
        });

        dtVisitantes = $('#tablaVisitantes').DataTable({
            order: [[0, 'desc']],
            columnDefs: [
                { targets: 'no-sort', orderable: false, searchable: false }
            ]
        });
    }

    // =========================================================================
    // 2. CREAR VISITANTE (AJAX POST)
    // =========================================================================
    $('#formCrearVisitante').on('submit', function(e) {
        e.preventDefault();
        const $form = $(this);

        if (!$form.valid()) {
            return;
        }

        const $btn = $('#btnConfirmarCrearVisitante');
        const $alert = $('#alertCrearVisitanteFeedback');
        
        $alert.hide().text('');
        $btn.prop('disabled', true).text('Registrando...');

        $.ajax({
            url: 'controllers/VisitanteController.php?action=crear',
            method: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response) {
                $btn.prop('disabled', false).text('Registrar Visitante');
                if (response.status === 'success') {
                    $('#modalCrearVisitante').modal('hide');
                    $('#formCrearVisitante')[0].reset();
                    showSuccessToast(response.message);
                    listarVisitantes();
                } else {
                    window.mapearErrorServidorAFormulario('#formCrearVisitante', response.message, null, $alert);
                }
            },
            error: function(xhr) {
                $btn.prop('disabled', false).text('Registrar Visitante');
                let errorMsg = 'Error al registrar al visitante.';
                let field = null;
                if (xhr.responseJSON) {
                    if (xhr.responseJSON.message) errorMsg = xhr.responseJSON.message;
                    if (xhr.responseJSON.field) field = xhr.responseJSON.field;
                }
                window.mapearErrorServidorAFormulario('#formCrearVisitante', errorMsg, field, $alert);
            }
        });
    });

    $('#modalCrearVisitante').on('shown.bs.modal', function() {
        setTimeout(function() {
            $('#formCrearVisitante input:visible').first().trigger('focus');
        }, 100);
    });

    $('#modalCrearVisitante').on('hidden.bs.modal', function() {
        $('#formCrearVisitante')[0].reset();
        const validator = $('#formCrearVisitante').validate();
        if (typeof window.limpiarErroresFormulario === 'function') {
            window.limpiarErroresFormulario('#formCrearVisitante');
        } else {
            $('#formCrearVisitante').find('.is-invalid').removeClass('is-valid');
            $('#formCrearVisitante').find('.invalid-feedback').hide().text('');
        }
        $('#alertCrearVisitanteFeedback').hide().text('');
    });

    // =========================================================================
    // 3. EDITAR VISITANTE (AJAX GET & POST)
    // =========================================================================
    $(document).on('click', '.btn-editar-visitor', function() {
        const id = $(this).data('id');
        const $alert = $('#alertEditarVisitanteFeedback');
        $alert.hide().text('');
        if (typeof window.limpiarErroresFormulario === 'function') {
            window.limpiarErroresFormulario('#formEditarVisitante');
        } else {
            $('#formEditarVisitante').find('.is-invalid').removeClass('is-valid');
            $('#formEditarVisitante').find('.invalid-feedback').hide().text('');
        }

        $.ajax({
            url: `controllers/VisitanteController.php?action=obtener&id=${id}`,
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    const v = response.data;
                    $('#edit_visitante_id').val(v.id);
                    $('#edit_visitante_cedula').val(v.cedula || '');
                    $('#edit_visitante_nombres').val(v.nombres || '');
                    $('#edit_visitante_apellidos').val(v.apellidos || '');
                    $('#edit_visitante_fecha_nacimiento').val(v.fecha_nacimiento || '');

                    $('#modalEditarVisitante').modal('show');
                } else {
                    showGlobalAlert(response.message, 'danger');
                }
            },
            error: function() {
                showGlobalAlert('No se pudo consultar la información del visitante.', 'danger');
            }
        });
    });

    $('#modalEditarVisitante').on('shown.bs.modal', function() {
        setTimeout(function() {
            $('#edit_visitante_nombres').trigger('focus');
        }, 100);
    });

    $('#modalEditarVisitante').on('hidden.bs.modal', function() {
        $('#formEditarVisitante')[0].reset();
        const validator = $('#formEditarVisitante').validate();
        if (validator) {
            validator.resetForm();
        }
        if (typeof window.limpiarErroresFormulario === 'function') {
            window.limpiarErroresFormulario('#formEditarVisitante');
        } else {
            $('#formEditarVisitante').find('.is-invalid').removeClass('is-valid');
            $('#formEditarVisitante').find('.invalid-feedback').hide().text('');
        }
        $('#alertEditarVisitanteFeedback').hide().text('');
    });

    $('#formEditarVisitante').on('submit', function(e) {
        e.preventDefault();
        const $form = $(this);

        if (!$form.valid()) {
            return;
        }

        const $btn = $('#btnConfirmarEditarVisitante');
        const $alert = $('#alertEditarVisitanteFeedback');

        $alert.hide().text('');
        $btn.prop('disabled', true).text('Guardando...');

        $.ajax({
            url: 'controllers/VisitanteController.php?action=actualizar',
            method: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response) {
                $btn.prop('disabled', false).text('Guardar Cambios');
                if (response.status === 'success') {
                    $('#modalEditarVisitante').modal('hide');
                    showSuccessToast(response.message);
                    listarVisitantes();
                } else {
                    window.mapearErrorServidorAFormulario('#formEditarVisitante', response.message, null, $alert);
                }
            },
            error: function(xhr) {
                $btn.prop('disabled', false).text('Guardar Cambios');
                let errorMsg = 'Error al actualizar visitante.';
                let field = null;
                if (xhr.responseJSON) {
                    if (xhr.responseJSON.message) errorMsg = xhr.responseJSON.message;
                    if (xhr.responseJSON.field) field = xhr.responseJSON.field;
                }
                window.mapearErrorServidorAFormulario('#formEditarVisitante', errorMsg, field, $alert);
            }
        });
    });

    // =========================================================================
    // 4. ELIMINAR VISITANTE
    // =========================================================================
    $(document).on('click', '.btn-eliminar-visitor', function() {
        const id = $(this).data('id');
        const name = $(this).data('name');

        $('#delete_visitante_id').val(id);
        $('#deleteVisitanteNombreSpan').text(name);
        $('#modalEliminarVisitante').modal('show');
    });

    $(document).on('click', '#btnConfirmarEliminarVisitante', function() {
        const id = $('#delete_visitante_id').val();
        const $btn = $(this);

        $btn.prop('disabled', true).text('Eliminando...');

        $.ajax({
            url: 'controllers/VisitanteController.php?action=eliminar',
            method: 'POST',
            data: { id: id },
            dataType: 'json',
            success: function(response) {
                $btn.prop('disabled', false).text('Eliminar Visitante');
                $('#modalEliminarVisitante').modal('hide');

                if (response.status === 'success') {
                    showSuccessToast(response.message);
                    listarVisitantes();
                } else {
                    alert(response.message);
                }
            },
            error: function(xhr) {
                $btn.prop('disabled', false).text('Eliminar Visitante');
                $('#modalEliminarVisitante').modal('hide');
                let errorMsg = 'Error al eliminar el visitante.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                alert(errorMsg);
            }
        });
    });

    // =========================================================================
    // AUXILIARES
    // =========================================================================
    function formatDate(dateString) {
        if (!dateString) return '';
        const parts = dateString.split('-');
        if (parts.length !== 3) return dateString;
        return `${parts[2]}/${parts[1]}/${parts[0]}`;
    }

    function showSuccessToast(message) {
        const $alert = $('#alertFeedbackGlobal');
        $alert.removeClass('alert-toast-success alert-toast-danger')
              .addClass('alert-toast-success')
              .html(`<strong>Éxito:</strong> ${message}`)
              .slideDown(250);
        
        setTimeout(function() {
            $alert.slideUp(250);
        }, 5000);
    }
});
