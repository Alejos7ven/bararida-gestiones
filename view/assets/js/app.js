/**
 * app.js — Módulo de Configuración del Sistema (Gestión de Permisos RBAC)
 * Exclusivo del superadmin. Carga la matriz de permisos y permite modificarlos vía AJAX.
 */

$(document).ready(function () {
    // Etiquetas amigables por nombre de rol
    const ROL_LABELS = {
        superadmin: 'Super Admin',
        admin: 'Administrador',
        guia: 'Guía de Campo'
    };

    // Cargar la matriz de permisos al iniciar
    cargarMatrizPermisos();

    /**
     * Solicita al servidor la matriz completa de roles × módulos y renderiza la tabla.
     */
    function cargarMatrizPermisos() {
        const $tbody  = $('#permisos-tbody');
        const $loader = $('#permisos-loader');
        const $table  = $('#permisos-table');

        $loader.show();
        $table.hide();

        $.ajax({
            url: 'controllers/PermisoController.php?action=listar',
            method: 'GET',
            dataType: 'json',
            success: function (res) {
                $loader.hide();
                if (res.status !== 'success') {
                    mostrarAlerta('error', 'No se pudo cargar la matriz de permisos.');
                    return;
                }

                const { roles, modulos, permisos } = res.data;
                renderizarCabecera(modulos);
                renderizarFilas(roles, modulos, permisos);
                $table.show();
            },
            error: function () {
                $loader.hide();
                mostrarAlerta('error', 'Error de comunicación con el servidor al cargar permisos.');
            }
        });
    }

    /**
     * Renderiza los <th> de la tabla con los nombres de cada módulo.
     */
    function renderizarCabecera(modulos) {
        const $thead = $('#permisos-thead-row');
        $thead.html('<th class="col-rol-label">Rol</th>');
        modulos.forEach(function (mod) {
            $thead.append(`<th class="text-center">${mod.label}</th>`);
        });
    }

    /**
     * Renderiza las filas de la tabla: una por rol con un toggle switch por cada módulo.
     */
    function renderizarFilas(roles, modulos, permisos) {
        const $tbody = $('#permisos-tbody');
        $tbody.empty();

        roles.forEach(function (rol) {
            // El superadmin tiene todos los accesos fijos (no editables)
            const esSuperAdmin = rol.nombre === 'superadmin';
            const $tr = $(`<tr data-rol-id="${rol.id}"></tr>`);

            // Celda de nombre del rol
            const rolLabel = ROL_LABELS[rol.nombre] || rol.nombre;
            const badge    = `<span class="role-badge role-${rol.nombre}">${rolLabel}</span>`;
            $tr.append(`<td>${badge}</td>`);

            // Celda por módulo
            modulos.forEach(function (mod) {
                const tienePermiso = permisos[rol.id] && permisos[rol.id][mod.id] ? true : false;
                const checked      = tienePermiso ? 'checked' : '';
                const disabled     = esSuperAdmin ? 'disabled' : '';
                const title        = esSuperAdmin ? 'El superadmin siempre tiene acceso total' : '';

                const $td = $(`
                    <td class="text-center">
                        <div class="form-check form-switch d-flex justify-content-center" title="${title}">
                            <input class="form-check-input permiso-toggle"
                                   type="checkbox"
                                   role="switch"
                                   data-rol-id="${rol.id}"
                                   data-modulo-id="${mod.id}"
                                   data-modulo-label="${mod.label}"
                                   data-rol-label="${rolLabel}"
                                   ${checked}
                                   ${disabled}
                                   id="perm_${rol.id}_${mod.id}">
                        </div>
                    </td>
                `);

                $tr.append($td);
            });

            $tbody.append($tr);
        });

        // Vincular evento de cambio en los toggles
        bindToggleEvents();
    }

    /**
     * Vincula el evento change en cada toggle para guardar el permiso automáticamente.
     */
    function bindToggleEvents() {
        $(document).off('change', '.permiso-toggle').on('change', '.permiso-toggle', function () {
            const $toggle     = $(this);
            const rol_id      = $toggle.data('rol-id');
            const modulo_id   = $toggle.data('modulo-id');
            const rolLabel    = $toggle.data('rol-label');
            const moduloLabel = $toggle.data('modulo-label');
            const puede       = $toggle.is(':checked') ? 1 : 0;

            // Deshabilitar temporalmente durante la petición
            $toggle.prop('disabled', true);

            $.ajax({
                url: 'controllers/PermisoController.php?action=actualizar',
                method: 'POST',
                dataType: 'json',
                data: {
                    rol_id:        rol_id,
                    modulo_id:     modulo_id,
                    puede_acceder: puede
                },
                success: function (res) {
                    $toggle.prop('disabled', false);
                    if (res.status === 'success') {
                        const accion = puede ? 'habilitado' : 'deshabilitado';
                        mostrarAlerta('success', `Módulo <strong>${moduloLabel}</strong> ${accion} para el rol <strong>${rolLabel}</strong>.`);
                    } else {
                        // Revertir el toggle si falló
                        $toggle.prop('checked', !$toggle.is(':checked'));
                        mostrarAlerta('error', res.message || 'Error al actualizar el permiso.');
                    }
                },
                error: function () {
                    $toggle.prop('disabled', false);
                    $toggle.prop('checked', !$toggle.is(':checked'));
                    mostrarAlerta('error', 'Error de comunicación con el servidor.');
                }
            });
        });
    }

    /**
     * Muestra un toast de alerta global con el mensaje dado.
     * @param {string} tipo    'success' | 'error'
     * @param {string} mensaje HTML del mensaje a mostrar
     */
    function mostrarAlerta(tipo, mensaje) {
        const $alert = $('#alertFeedbackGlobal');
        $alert.removeClass('alert-toast-success alert-toast-danger');
        $alert.addClass(tipo === 'success' ? 'alert-toast-success' : 'alert-toast-danger');
        $alert.html(mensaje).fadeIn(300);
        clearTimeout(window._alertTimer);
        window._alertTimer = setTimeout(function () { $alert.fadeOut(400); }, 3500);
    }
});
