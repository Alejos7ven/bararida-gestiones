/**
 * Lógica del Módulo de Visitantes - visitantes.js
 * Parque Zoológico y Botánico Bararida
 */

$(document).ready(function() {
    // Referencias DOM
    const $tablaVisitantes = $('#tablaVisitantesBody');
    const $loader = $('#loaderVisitantes');
    const $searchVisitantes = $('#searchVisitantes');

    // Inicializar
    listarVisitantes();

    // =========================================================================
    // 1. LISTAR VISITANTES (AJAX)
    // =========================================================================
    function listarVisitantes() {
        $tablaVisitantes.hide();
        $loader.show();

        $.ajax({
            url: 'controllers/VisitanteController.php?action=listar',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                $loader.hide();
                $tablaVisitantes.empty();

                if (response.status === 'success' && response.data.length > 0) {
                    response.data.forEach(function(v) {
                        const cedula = v.cedula ? v.cedula : '<span class="text-muted italic">Sin Cédula (Menor)</span>';
                        const fechaNac = v.fecha_nacimiento ? formatDate(v.fecha_nacimiento) : '<span class="text-muted">N/E</span>';
                        const searchString = `${(v.cedula || '').toLowerCase()} ${v.nombres.toLowerCase()} ${v.apellidos.toLowerCase()}`;

                        const rowHtml = `
                            <tr class="visitor-row" data-search="${searchString}">
                                <td><strong>#VIS-${v.id}</strong></td>
                                <td><strong>${cedula}</strong></td>
                                <td>${v.nombres}</td>
                                <td>${v.apellidos}</td>
                                <td>${fechaNac}</td>
                                <td>
                                    <div class="table-actions">
                                        <button class="btn-action btn-action-edit btn-editar-visitor" data-id="${v.id}" title="Editar Visitante">
                                            <!-- SVG Edit -->
                                            <svg viewBox="0 0 24 24" style="width:20px; height:20px;"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
                                        </button>
                                        <button class="btn-action btn-action-delete btn-eliminar-visitor" data-id="${v.id}" data-name="${v.nombres} ${v.apellidos}" title="Eliminar Visitante">
                                            <!-- SVG Trash -->
                                            <svg viewBox="0 0 24 24" style="width:20px; height:20px;"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        `;
                        $tablaVisitantes.append(rowHtml);
                    });
                    $tablaVisitantes.fadeIn(300);
                } else {
                    $tablaVisitantes.append('<tr><td colspan="6" class="text-center text-muted py-4">No hay visitantes registrados en el sistema.</td></tr>');
                    $tablaVisitantes.fadeIn(300);
                }
            },
            error: function() {
                $loader.hide();
                alert('Ocurrió un error al cargar el listado de visitantes.');
            }
        });
    }

    // Buscador en tiempo real
    $searchVisitantes.on('input', function() {
        const query = $(this).val().toLowerCase().trim();
        if (query === '') {
            $('.visitor-row').show();
            return;
        }
        $('.visitor-row').each(function() {
            const searchText = $(this).data('search');
            if (searchText.indexOf(query) > -1) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });

    // =========================================================================
    // 2. CREAR VISITANTE (AJAX POST)
    // =========================================================================
    $('#formCrearVisitante').on('submit', function(e) {
        e.preventDefault();
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
                    $alert.text(response.message).fadeIn(200);
                }
            },
            error: function(xhr) {
                $btn.prop('disabled', false).text('Registrar Visitante');
                let errorMsg = 'Error al registrar al visitante.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                $alert.text(errorMsg).fadeIn(200);
            }
        });
    });

    // =========================================================================
    // 3. EDITAR VISITANTE (AJAX GET & POST)
    // =========================================================================
    $(document).on('click', '.btn-editar-visitor', function() {
        const id = $(this).data('id');
        const $alert = $('#alertEditarVisitanteFeedback');
        $alert.hide().text('');

        $.ajax({
            url: `controllers/VisitanteController.php?action=obtener&id=${id}`,
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    const v = response.data;
                    $('#edit_visitante_id').val(v.id);
                    $('#edit_visitante_cedula').val(v.cedula || '');
                    $('#edit_visitante_nombres').val(v.nombres);
                    $('#edit_visitante_apellidos').val(v.apellidos);
                    $('#edit_visitante_fecha_nacimiento').val(v.fecha_nacimiento);

                    $('#modalEditarVisitante').modal('show');
                } else {
                    alert(response.message);
                }
            },
            error: function() {
                alert('No se pudieron obtener los detalles del visitante.');
            }
        });
    });

    $('#formEditarVisitante').on('submit', function(e) {
        e.preventDefault();
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
                    $alert.text(response.message).fadeIn(200);
                }
            },
            error: function(xhr) {
                $btn.prop('disabled', false).text('Guardar Cambios');
                let errorMsg = 'Error al actualizar visitante.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                $alert.text(errorMsg).fadeIn(200);
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
