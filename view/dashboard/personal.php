<div class="panel-container">
        <div class="panel-header-section">
            <div class="panel-header-title">
                <h3>Gestión y Control de Personal</h3>
                <p>Administración del personal administrativo y guías autorizados para el sistema</p>
            </div>
            <div>
                <button class="btn-premium-primary" data-bs-toggle="modal" data-bs-target="#modalCrearPersonal">
                    <svg viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                    Registrar Personal
                </button>
            </div>
        </div>

        <!-- Barra de búsqueda dinámico real-time en front -->
        <div class="search-filter-row">
            <div class="search-input-group">
                <svg class="search-icon-svg" viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                <input type="text" id="searchUser" placeholder="Buscar por cédula, nombre o apellido...">
            </div>
        </div>

        <!-- Cargador Asíncrono AJAX -->
        <div id="loaderPersonal" class="loader-wrapper">
            <div class="spinner-border spinner-premium" role="status">
                <span class="visually-hidden">Cargando registros...</span>
            </div>
        </div>

        <!-- Tabla de Personal -->
        <div class="table-responsive-premium">
            <table class="table-premium">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Cédula</th>
                        <th>Nombre Completo</th>
                        <th>Rol de Acceso</th>
                        <th>Fecha Registro</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="tablaPersonalBody" style="display:none;">
                    <!-- Cargado vía AJAX por personal.js -->
                </tbody>
            </table>
        </div>

        <!-- Controles de Paginación -->
        <div id="paginacionPersonal" class="d-flex justify-content-between align-items-center mt-3 px-2 flex-wrap gap-2" style="display: none !important;">
            <div class="pagination-info-text">
                Mostrando <span id="pagStartPersonal">0</span> - <span id="pagEndPersonal">0</span> de <span id="pagTotalPersonal">0</span> registros
            </div>
            <nav>
                <ul class="pagination pagination-premium mb-0" id="paginationControlsPersonal">
                    <!-- Botones de paginación dinámicos -->
                </ul>
            </nav>
        </div>
    </div>

    <!-- =========================================================================
         MODALES DEL CRUD (CREAR, EDITAR, ELIMINAR)
         ========================================================================= -->

    <!-- Modal Crear Personal -->
    <div class="modal fade modal-premium" id="modalCrearPersonal" tabindex="-1" aria-labelledby="modalCrearPersonalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalCrearPersonalLabel">Registrar Nuevo Personal</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="formCrearPersonal">
                    <div class="modal-body">
                        <div id="alertCrearFeedback" class="alert alert-toast alert-toast-danger" role="alert" style="display:none;"></div>
                        
                        <div class="mb-3">
                            <label for="cedula" class="form-premium-label">Documento de Identidad</label>
                            <div class="input-group">
                                <select class="form-select form-premium-control" id="tipo_documento" name="tipo_documento" style="max-width: 90px; border-top-right-radius: 0; border-bottom-right-radius: 0;" required>
                                    <option value="V" selected>V</option>
                                    <option value="E">E</option>
                                    <option value="J">J</option>
                                    <option value="G">G</option>
                                    <option value="P">P</option>
                                </select>
                                <input type="text" class="form-control form-premium-control" id="cedula" name="cedula" placeholder="Ej. 25123456" style="border-top-left-radius: 0; border-bottom-left-radius: 0;" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="nombre_completo" class="form-premium-label">Nombre Completo</label>
                            <input type="text" class="form-control form-premium-control" id="nombre_completo" name="nombre_completo" placeholder="Ej. Juan Carlos Pérez" required>
                        </div>
                        <div class="mb-3">
                            <label for="rol" class="form-premium-label">Rol del Personal</label>
                            <select class="form-select form-premium-control" id="rol" name="rol" required>
                                <option value="" disabled selected>Cargando roles...</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-premium-label">Contraseña</label>
                            <input type="password" class="form-control form-premium-control" id="password" name="password" placeholder="Mínimo 6 caracteres" required autocomplete="new-password">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary py-2 px-3" data-bs-dismiss="modal" style="border-radius:10px; font-weight:600; font-size:0.9rem;">Cancelar</button>
                        <button type="submit" class="btn btn-premium-primary" id="btnGuardarNuevo">Registrar Personal</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Editar Personal -->
    <div class="modal fade modal-premium" id="modalEditarPersonal" tabindex="-1" aria-labelledby="modalEditarPersonalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalEditarPersonalLabel">Modificar Personal</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="formEditarPersonal">
                    <input type="hidden" id="edit_id" name="id">
                    <div class="modal-body">
                        <div id="alertEditarFeedback" class="alert alert-toast alert-toast-danger" role="alert" style="display:none;"></div>
                        
                        <div class="mb-3">
                            <label for="edit_cedula" class="form-premium-label">Documento de Identidad</label>
                            <div class="input-group">
                                <select class="form-select form-premium-control" id="edit_tipo_documento" name="tipo_documento" style="max-width: 90px; border-top-right-radius: 0; border-bottom-right-radius: 0;" required>
                                    <option value="V">V</option>
                                    <option value="E">E</option>
                                    <option value="J">J</option>
                                    <option value="G">G</option>
                                    <option value="P">P</option>
                                </select>
                                <input type="text" class="form-control form-premium-control" id="edit_cedula" name="cedula" style="border-top-left-radius: 0; border-bottom-left-radius: 0;" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="edit_nombre_completo" class="form-premium-label">Nombre Completo</label>
                            <input type="text" class="form-control form-premium-control" id="edit_nombre_completo" name="nombre_completo" placeholder="Ej. Juan Carlos Pérez" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_rol" class="form-premium-label">Rol del Personal</label>
                            <select class="form-select form-premium-control" id="edit_rol" name="rol" required>
                                <!-- Se inyectará dinámicamente por JS según el personal -->
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="edit_password" class="form-premium-label">Nueva Contraseña (Opcional)</label>
                            <input type="password" class="form-control form-premium-control" id="edit_password" name="password" placeholder="Dejar en blanco para mantener la actual" autocomplete="new-password">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary py-2 px-3" data-bs-dismiss="modal" style="border-radius:10px; font-weight:600; font-size:0.9rem;">Cancelar</button>
                        <button type="submit" class="btn btn-premium-primary" id="btnGuardarEditar">Guardar Cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Confirmar Eliminación -->
    <div class="modal fade modal-premium" id="modalEliminarPersonal" tabindex="-1" aria-labelledby="modalEliminarPersonalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalEliminarPersonalLabel">Confirmar Eliminación</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="delete_id">
                    <p class="mb-0" style="font-size:0.95rem;">¿Está completamente seguro de que desea eliminar al personal <strong id="deleteUserNameSpan"></strong> del sistema?</p>
                    <p class="text-danger mt-2 mb-0" style="font-size:0.85rem; font-weight:600;">Esta acción es irreversible y revocaría todo acceso inmediato del personal al sistema.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary py-2 px-3" data-bs-dismiss="modal" style="border-radius:10px; font-weight:600; font-size:0.9rem;">Cancelar</button>
                    <button type="button" class="btn btn-danger py-2 px-3" id="btnConfirmarEliminar" style="border-radius:10px; font-weight:600; font-size:0.9rem;">Eliminar Personal</button>
                </div>
            </div>
        </div>
    </div>
