<?php
// Evitar acceso directo
if (!isset($userPermissions)) {
    exit;
}
?>
<?php if ($userPermissions['visitantes']): ?>
    <div class="panel-container animate__animated animate__fadeIn">
        <div class="panel-header-section">
            <div class="panel-header-title">
                <h3>Gestión de Visitantes</h3>
                <p>Historial, registro y control de visitantes en el sistema administrativo</p>
            </div>
            <div>
                <button class="btn-premium-primary" data-bs-toggle="modal" data-bs-target="#modalCrearVisitante">
                    <svg viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                    Registrar Visitante
                </button>
            </div>
        </div>

        <div class="search-filter-row">
            <div class="search-input-group">
                <svg class="search-icon-svg" viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                <input type="text" id="searchVisitantes" placeholder="Filtrar por Cédula, Nombres o Apellidos...">
            </div>
        </div>

        <div id="loaderVisitantes" class="text-center py-5">
            <div class="spinner-border text-success" role="status" style="width: 3rem; height: 3rem;">
                <span class="visually-hidden">Cargando...</span>
            </div>
            <p class="text-muted mt-2">Cargando listado de visitantes...</p>
        </div>

        <div class="table-responsive-premium">
            <table class="table-premium">
                <thead>
                    <tr>
                        <th># ID</th>
                        <th>Cédula / Documento</th>
                        <th>Nombres</th>
                        <th>Apellidos</th>
                        <th>Fecha de Nacimiento</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="tablaVisitantesBody" style="display: none;">
                    <!-- Se poblará vía AJAX -->
                </tbody>
            </table>
        </div>

        <!-- Controles de Paginación -->
        <div id="paginacionVisitantes" class="d-flex justify-content-between align-items-center mt-3 px-2 flex-wrap gap-2" style="display: none !important;">
            <div class="pagination-info-text">
                Mostrando <span id="pagStartVisitantes">0</span> - <span id="pagEndVisitantes">0</span> de <span id="pagTotalVisitantes">0</span> registros
            </div>
            <nav>
                <ul class="pagination pagination-premium mb-0" id="paginationControlsVisitantes">
                    <!-- Botones de paginación dinámicos -->
                </ul>
            </nav>
        </div>
    </div>

    <!-- MODAL: REGISTRAR VISITANTE MANUALMENTE -->
    <div class="modal fade modal-premium" id="modalCrearVisitante" tabindex="-1" aria-labelledby="modalCrearVisitanteLabel" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 20px; border: none; overflow: hidden; box-shadow: 0 15px 35px rgba(0,0,0,0.15);">
                <div class="modal-header" style="background: var(--park-primary); color: #ffffff; padding: 20px 24px;">
                    <h5 class="modal-title" id="modalCrearVisitanteLabel" style="font-family:'Outfit'; font-weight:600; margin:0;">Registrar Visitante</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <form id="formCrearVisitante" novalidate>
                    <div class="modal-body" style="background:#ffffff; padding: 24px;">
                        <div id="alertCrearVisitanteFeedback" class="alert alert-toast alert-toast-danger" role="alert" style="display:none;"></div>
                        <div class="mb-3">
                            <label for="visitante_cedula" class="form-premium-label">Cédula / Documento <span class="text-muted" style="font-size:0.75rem;">(Dejar vacío para menor sin cédula)</span></label>
                            <input type="text" class="form-control form-premium-control" id="visitante_cedula" name="cedula" placeholder="Ej. 28123456">
                        </div>
                        <div class="mb-3">
                            <label for="visitante_nombres" class="form-premium-label">Nombres *</label>
                            <input type="text" class="form-control form-premium-control" id="visitante_nombres" name="nombres" placeholder="Nombres del visitante" required>
                        </div>
                        <div class="mb-3">
                            <label for="visitante_apellidos" class="form-premium-label">Apellidos *</label>
                            <input type="text" class="form-control form-premium-control" id="visitante_apellidos" name="apellidos" placeholder="Apellidos del visitante" required>
                        </div>
                        <div class="mb-3">
                            <label for="visitante_fecha_nacimiento" class="form-premium-label">Fecha de Nacimiento *</label>
                            <input type="date" class="form-control form-premium-control" id="visitante_fecha_nacimiento" name="fecha_nacimiento" required>
                        </div>
                    </div>
                    <div class="modal-footer" style="background:#f8f9fa; border-top: 1px solid var(--park-gray); padding: 16px 24px;">
                        <button type="button" class="btn btn-secondary py-2 px-3" data-bs-dismiss="modal" style="border-radius:10px; font-weight:600; font-size:0.9rem;">Cancelar</button>
                        <submit type="submit" class="btn btn-premium-primary" id="btnConfirmarCrearVisitante">Registrar Visitante</submit>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL: EDITAR VISITANTE -->
    <div class="modal fade modal-premium" id="modalEditarVisitante" tabindex="-1" aria-labelledby="modalEditarVisitanteLabel" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 20px; border: none; overflow: hidden; box-shadow: 0 15px 35px rgba(0,0,0,0.15);">
                <div class="modal-header" style="background: var(--park-primary); color: #ffffff; padding: 20px 24px;">
                    <h5 class="modal-title" id="modalEditarVisitanteLabel" style="font-family:'Outfit'; font-weight:600; margin:0;">Editar Visitante</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <form id="formEditarVisitante" novalidate>
                    <input type="hidden" name="id" id="edit_visitante_id">
                    <div class="modal-body" style="background:#ffffff; padding: 24px;">
                        <div id="alertEditarVisitanteFeedback" class="alert alert-toast alert-toast-danger" role="alert" style="display:none;"></div>
                        <div class="mb-3">
                            <label for="edit_visitante_cedula" class="form-premium-label">Cédula / Documento <span class="text-muted" style="font-size:0.75rem;">(Dejar vacío para menor sin cédula)</span></label>
                            <input type="text" class="form-control form-premium-control" id="edit_visitante_cedula" name="cedula">
                        </div>
                        <div class="mb-3">
                            <label for="edit_visitante_nombres" class="form-premium-label">Nombres *</label>
                            <input type="text" class="form-control form-premium-control" id="edit_visitante_nombres" name="nombres" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_visitante_apellidos" class="form-premium-label">Apellidos *</label>
                            <input type="text" class="form-control form-premium-control" id="edit_visitante_apellidos" name="apellidos" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_visitante_fecha_nacimiento" class="form-premium-label">Fecha de Nacimiento *</label>
                            <input type="date" class="form-control form-premium-control" id="edit_visitante_fecha_nacimiento" name="fecha_nacimiento" required>
                        </div>
                    </div>
                    <div class="modal-footer" style="background:#f8f9fa; border-top: 1px solid var(--park-gray); padding: 16px 24px;">
                        <button type="button" class="btn btn-secondary py-2 px-3" data-bs-dismiss="modal" style="border-radius:10px; font-weight:600; font-size:0.9rem;">Cancelar</button>
                        <button type="submit" class="btn btn-premium-primary" id="btnConfirmarEditarVisitante">Guardar Cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL: CONFIRMAR ELIMINAR VISITANTE -->
    <div class="modal fade modal-premium" id="modalEliminarVisitante" tabindex="-1" aria-labelledby="modalEliminarVisitanteLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 20px; border: none; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.15);">
                <div class="modal-header" style="background: #ef4444; color: #ffffff; padding: 20px 24px;">
                    <h5 class="modal-title" id="modalEliminarVisitanteLabel" style="font-family:'Outfit'; font-weight:600; margin:0;">Confirmar Eliminación</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body" style="background:#ffffff; padding: 24px;">
                    <input type="hidden" id="delete_visitante_id">
                    <p class="mb-0" style="font-size:0.95rem;">¿Está completamente seguro de que desea eliminar al visitante <strong id="deleteVisitanteNombreSpan" class="text-danger"></strong> del sistema?</p>
                    <p class="text-danger mt-2 mb-0" style="font-size:0.85rem; font-weight:600;">Esta acción eliminará al visitante y lo desvinculará de cualquier visita guiada asociada en el cronograma.</p>
                </div>
                <div class="modal-footer" style="background:#f8f9fa; border-top: 1px solid var(--park-gray); padding: 16px 24px;">
                    <button type="button" class="btn btn-secondary py-2 px-3" data-bs-dismiss="modal" style="border-radius:10px; font-weight:600; font-size:0.9rem;">Cancelar</button>
                    <button type="button" class="btn btn-danger py-2 px-3" id="btnConfirmarEliminarVisitante" style="border-radius:10px; font-weight:600; font-size:0.9rem; background:#ef4444; border-color:#ef4444;">Eliminar Visitante</button>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="restricted-access-container">
        <div class="restricted-card">
            <div class="restricted-shield-wrapper">
                <svg viewBox="0 0 24 24"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11v8.8z"/></svg>
            </div>
            <h4>Acceso Restringido</h4>
            <p>Su rol actual no cuenta con permisos autorizados para interactuar con este módulo. Si requiere visualizar esta sección, solicítelo al Super Administrador.</p>
            <a href="index.php?view=dashboard" class="btn-premium-danger">Volver al Dashboard</a>
        </div>
    </div>
<?php endif; ?>
