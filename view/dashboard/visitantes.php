
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

        <!-- Tabla de Visitantes con DataTables -->
        <div class="table-responsive-premium">
            <table class="table table-premium w-100 dt-responsive nowrap" id="tablaVisitantes">
                <thead>
                    <tr>
                        <th># ID</th>
                        <th>Cédula / Doc.</th>
                        <th>Nombres</th>
                        <th>Apellidos</th>
                        <th>Fecha Nac.</th>
                        <th>Procedencia</th>
                        <th class="no-sort text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody id="tablaVisitantesBody">
                    <tr id="loaderVisitantes">
                        <td colspan="7" class="text-center py-5">
                            <div class="spinner-border text-success" role="status" style="width: 2.5rem; height: 2.5rem;">
                                <span class="visually-hidden">Cargando...</span>
                            </div>
                            <p class="text-muted mt-2 mb-0" style="font-size: 0.9rem;">Cargando listado de visitantes...</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- MODAL: REGISTRAR VISITANTE MANUALMENTE -->
    <div class="modal fade modal-premium" id="modalCrearVisitante" tabindex="-1" aria-labelledby="modalCrearVisitanteLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 20px; border: none; overflow: hidden; box-shadow: 0 15px 35px rgba(0,0,0,0.15);">
                <div class="modal-header" style="background: var(--park-primary); color: #ffffff; padding: 20px 24px;">
                    <h5 class="modal-title text-white" id="modalCrearVisitanteLabel" style="font-family:'Outfit'; font-weight:600; margin:0; color:#ffffff !important;">Registrar Visitante</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <form id="formCrearVisitante" novalidate>
                    <div class="modal-body" style="background:#ffffff; padding: 24px;">
                        <div id="alertCrearVisitanteFeedback" class="alert alert-toast alert-toast-danger" role="alert" style="display:none;"></div>
                        <div class="mb-3">
                            <label for="visitante_cedula" class="form-premium-label">Cédula / Documento <span class="text-muted" style="font-size:0.75rem;">(Dejar vacío para menor sin cédula)</span></label>
                            <input type="text" class="form-control form-premium-control" id="visitante_cedula" name="cedula" placeholder="Ej. 28123456" maxlength="15">
                        </div>
                        <div class="mb-3">
                            <label for="visitante_nombres" class="form-premium-label">Nombres *</label>
                            <input type="text" class="form-control form-premium-control" id="visitante_nombres" name="nombres" placeholder="Ej. Carlos Eduardo" required maxlength="25">
                        </div>
                        <div class="mb-3">
                            <label for="visitante_apellidos" class="form-premium-label">Apellidos *</label>
                            <input type="text" class="form-control form-premium-control" id="visitante_apellidos" name="apellidos" placeholder="Ej. Pérez Gómez" required maxlength="25">
                        </div>
                        <div class="mb-3">
                            <label for="visitante_fecha_nacimiento" class="form-premium-label">Fecha de Nacimiento *</label>
                            <input type="date" class="form-control form-premium-control" id="visitante_fecha_nacimiento" name="fecha_nacimiento" max="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    <div class="modal-footer" style="background:#f8f9fa; border-top: 1px solid var(--park-gray); padding: 16px 24px;">
                        <button type="button" class="btn btn-secondary py-2 px-3" data-bs-dismiss="modal" style="border-radius:10px; font-weight:600; font-size:0.9rem;">Cancelar</button>
                        <button type="submit" class="btn btn-premium-primary" id="btnConfirmarCrearVisitante">Registrar Visitante</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL: EDITAR VISITANTE -->
    <div class="modal fade modal-premium" id="modalEditarVisitante" tabindex="-1" aria-labelledby="modalEditarVisitanteLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 20px; border: none; overflow: hidden; box-shadow: 0 15px 35px rgba(0,0,0,0.15);">
                <div class="modal-header" style="background: var(--park-primary); color: #ffffff; padding: 20px 24px;">
                    <h5 class="modal-title text-white" id="modalEditarVisitanteLabel" style="font-family:'Outfit'; font-weight:600; margin:0; color:#ffffff !important;">Editar Visitante</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <form id="formEditarVisitante" novalidate>
                    <input type="hidden" name="id" id="edit_visitante_id">
                    <div class="modal-body" style="background:#ffffff; padding: 24px;">
                        <div id="alertEditarVisitanteFeedback" class="alert alert-toast alert-toast-danger" role="alert" style="display:none;"></div>
                        <div class="mb-3">
                            <label for="edit_visitante_cedula" class="form-premium-label">Cédula / Documento <span class="text-muted" style="font-size:0.75rem;">(Dejar vacío para menor sin cédula)</span></label>
                            <input type="text" class="form-control form-premium-control" id="edit_visitante_cedula" name="cedula" placeholder="Ej. 28123456" maxlength="15">
                        </div>
                        <div class="mb-3">
                            <label for="edit_visitante_nombres" class="form-premium-label">Nombres *</label>
                            <input type="text" class="form-control form-premium-control" id="edit_visitante_nombres" name="nombres" placeholder="Ej. Carlos Eduardo" required maxlength="25">
                        </div>
                        <div class="mb-3">
                            <label for="edit_visitante_apellidos" class="form-premium-label">Apellidos *</label>
                            <input type="text" class="form-control form-premium-control" id="edit_visitante_apellidos" name="apellidos" placeholder="Ej. Pérez Gómez" required maxlength="25">
                        </div>
                        <div class="mb-3">
                            <label for="edit_visitante_fecha_nacimiento" class="form-premium-label">Fecha de Nacimiento *</label>
                            <input type="date" class="form-control form-premium-control" id="edit_visitante_fecha_nacimiento" name="fecha_nacimiento" max="<?php echo date('Y-m-d'); ?>" required>
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
                    <h5 class="modal-title text-white" id="modalEliminarVisitanteLabel" style="font-family:'Outfit'; font-weight:600; margin:0; color:#ffffff !important;">Confirmar Eliminación</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body" style="background:#ffffff; padding: 24px;">
                    <input type="hidden" id="delete_visitante_id">
                    <p class="mb-0" style="font-size:0.95rem;">¿Está completamente seguro de que desea eliminar al visitante <strong id="deleteVisitanteNombreSpan" class="text-danger"></strong> del sistema?</p>
                    <p class="text-danger mt-2 mb-0" style="font-size:0.85rem; font-weight:600;">Esta acción eliminará al visitante y lo desvinculará de cualquier visita guiada asociada en el cronograma.</p>
                </div>
                <div class="modal-footer" style="background:#f8f9fa; border-top: 1px solid var(--park-gray); padding: 16px 24px;">
                    <button type="button" class="btn btn-secondary py-2 px-3" data-bs-dismiss="modal" style="border-radius:10px; font-weight:600; font-size:0.9rem;">Cancelar</button>
                    <button type="button" class="btn btn-danger py-2 px-3" id="btnConfirmarEliminarVisitante" style="border-radius:10px; font-weight:600; font-size:0.9rem; background:#ef4444; border-color:#ef4444; color:#ffffff;">Eliminar Visitante</button>
                </div>
            </div>
        </div>
    </div>
