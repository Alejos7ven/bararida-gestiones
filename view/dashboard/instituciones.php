<div class="panel-container animate__animated animate__fadeIn">
        <div class="panel-header-section">
            <div class="panel-header-title">
                <h3>Registro de Instituciones</h3>
                <p>Historial y registro de colegios, liceos y empresas externas con control de sedes</p>
            </div>
            <div>
                <button class="btn-premium-primary" data-bs-toggle="modal" data-bs-target="#modalCrearInstitucion">
                    <svg viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                    Registrar Institución
                </button>
            </div>
        </div>

        <div class="search-filter-row">
            <div class="search-input-group">
                <svg class="search-icon-svg" viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                <input type="text" id="searchInstituciones" placeholder="Filtrar por RIF, Razón Social, Sede o Tipo...">
            </div>
        </div>

        <div id="loaderInstituciones" class="text-center py-5">
            <div class="spinner-border text-success" role="status" style="width: 3rem; height: 3rem;">
                <span class="visually-hidden">Cargando...</span>
            </div>
            <p class="text-muted mt-2">Cargando instituciones registradas...</p>
        </div>

        <div class="table-responsive-premium">
            <table class="table-premium">
                <thead>
                    <tr>
                        <th>Código RIF</th>
                        <th>Razón Social</th>
                        <th>Tipo Org.</th>
                        <th>Contactos</th>
                        <th>Sedes Registradas</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="tablaInstitucionesBody" style="display: none;">
                    <!-- Se poblará vía AJAX -->
                </tbody>
            </table>
        </div>

        <!-- Controles de Paginación -->
        <div id="paginacionInstituciones" class="d-flex justify-content-between align-items-center mt-3 px-2 flex-wrap gap-2" style="display: none !important;">
            <div class="pagination-info-text">
                Mostrando <span id="pagStartInstituciones">0</span> - <span id="pagEndInstituciones">0</span> de <span id="pagTotalInstituciones">0</span> registros
            </div>
            <nav>
                <ul class="pagination pagination-premium mb-0" id="paginationControlsInstituciones">
                    <!-- Botones de paginación dinámicos -->
                </ul>
            </nav>
        </div>
    </div>

    <!-- MODAL: REGISTRAR INSTITUCIÓN STANDALONE -->
    <div class="modal fade modal-premium" id="modalCrearInstitucion" tabindex="-1" aria-labelledby="modalCrearInstitucionLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content" style="border-radius: 20px; border: none; overflow: hidden; box-shadow: 0 15px 35px rgba(0,0,0,0.15);">
                <div class="modal-header" style="background: var(--park-primary); color: #ffffff; padding: 20px 24px;">
                    <h5 class="modal-title text-white" id="modalCrearInstitucionLabel" style="font-family:'Outfit'; font-weight:600; margin:0; color:#ffffff !important;">Registrar Nueva Institución</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <form id="formCrearInstitucion" novalidate style="display: flex; flex-direction: column; flex: 1 1 auto; overflow: hidden; min-height: 0;">
                    <div class="modal-body" style="background:#ffffff; padding: 30px; overflow-y: auto; max-height: calc(88vh - 140px); flex: 1 1 auto;">
                        <div id="alertCrearInstFeedback" class="alert alert-toast alert-toast-danger" role="alert" style="display:none;"></div>
                        
                        <!-- Sección 1: Datos Básicos -->
                        <h5 class="mb-3" style="font-family:'Outfit'; font-weight:700; color:var(--park-dark);">1. Datos Identificativos</h5>
                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <label for="create_tipo_documento" class="form-premium-label">Tipo RIF</label>
                                <select class="form-select form-premium-control" id="create_tipo_documento" name="tipo_documento" required>
                                    <option value="J" selected>J</option>
                                    <option value="G">G</option>
                                    <option value="V">V</option>
                                    <option value="E">E</option>
                                    <option value="P">P</option>
                                </select>
                            </div>
                            <div class="col-md-8">
                                <label for="create_documento" class="form-premium-label">Número RIF / Cédula</label>
                                <input type="text" class="form-control form-premium-control" id="create_documento" name="documento" placeholder="Ej. 123456789 (solo números)" required>
                            </div>
                            <div class="col-md-5">
                                 <label for="create_nombre_completo" class="form-premium-label">Razón Social / Nombre Oficial</label>
                                 <input type="text" class="form-control form-premium-control" id="create_nombre_completo" name="nombre_completo" placeholder="Ej. Colegio San Vicente" required>
                             </div>
                             <div class="col-md-4">
                                 <label for="create_tipo_organizacion_select" class="form-premium-label">Tipo de Organización</label>
                                 <select class="form-select form-premium-control select-tipo-organizacion" id="create_tipo_organizacion_select" name="tipo_organizacion" required>
                                     <option value="">Seleccione Tipo</option>
                                     <option value="Educativa">Educativa</option>
                                     <option value="Empresarial">Empresarial</option>
                                     <option value="Gubernamental">Gubernamental</option>
                                     <option value="Fundación / ONG">Fundación / ONG</option>
                                     <option value="Comunitaria">Comunitaria</option>
                                     <option value="Religiosa">Religiosa</option>
                                     <option value="Otro">Otro (Especificar)</option>
                                 </select>
                                 <div class="mt-2 custom-tipo-organizacion-container" id="create_custom_tipo_org_container" style="display: none;">
                                     <input type="text" class="form-control form-premium-control" id="create_tipo_organizacion" placeholder="Escriba el tipo de organización personalizado...">
                                 </div>
                             </div>
                             <div class="col-md-3">
                                 <label for="create_tipo_gestion" class="form-premium-label">Tipo de Gestión</label>
                                 <select class="form-select form-premium-control" id="create_tipo_gestion" name="tipo_gestion" required>
                                     <option value="publica" selected>Pública</option>
                                     <option value="privada">Privada</option>
                                 </select>
                             </div>
                        </div>

                        <!-- Sección 2: Sedes de la Institución (Multi-Sede) -->
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0" style="font-family:'Outfit'; font-weight:700; color:var(--park-dark);">2. Sedes de la Institución</h5>
                            <button type="button" class="btn btn-sm btn-outline-success" id="btnCreateAddSede" style="border-radius: 8px; font-weight: 600;">
                                + Agregar Otra Sede
                            </button>
                        </div>
                        <p class="text-muted" style="font-size:0.85rem; margin-top:-5px; margin-bottom:15px;">
                            Registre una o más sedes (localidades) donde opera la institución.
                        </p>

                        <div id="createSedesContainer" class="d-flex flex-column gap-3 mb-4">
                            <!-- Filas de sedes dinámicas inyectadas por JS -->
                        </div>

                        <!-- Sección 3: Directorio de Contactos -->
                        <h5 style="font-family:'Outfit'; font-weight:700; color:var(--park-dark);" class="mb-3">3. Directorio de Contactos</h5>
                        
                        <div class="row g-3 mb-4">
                            <!-- Columna Teléfonos -->
                            <div class="col-md-6">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <label class="form-premium-label mb-0" style="font-weight: 700;">Teléfonos</label>
                                    <button type="button" class="btn btn-sm btn-outline-success p-1 py-0.5" id="btnCreateAddPhone" style="font-size: 0.75rem; border-radius: 6px;">+ Teléfono</button>
                                </div>
                                <div id="createPhonesContainer" class="d-flex flex-column gap-2">
                                    <!-- Teléfonos dinámicos -->
                                </div>
                            </div>
                            
                            <!-- Columna Correos -->
                            <div class="col-md-6">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <label class="form-premium-label mb-0" style="font-weight: 700;">Correos Electrónicos</label>
                                    <button type="button" class="btn btn-sm btn-outline-success p-1 py-0.5" id="btnCreateAddEmail" style="font-size: 0.75rem; border-radius: 6px;">+ Correo</button>
                                </div>
                                <div id="createEmailsContainer" class="d-flex flex-column gap-2">
                                    <!-- Correos dinámicos -->
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer" style="background: #fdfdfd; border-top: 1px solid #eeeeee; padding: 20px 24px;">
                        <button type="button" class="btn btn-secondary btn-premium-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success btn-premium-primary" id="btnGuardarNuevaInst">Registrar Institución</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL: EDITAR INSTITUCIÓN -->
    <div class="modal fade modal-premium" id="modalEditarInstitucion" tabindex="-1" aria-labelledby="modalEditarInstitucionLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content" style="border-radius: 20px; border: none; overflow: hidden; box-shadow: 0 15px 35px rgba(0,0,0,0.15);">
                <div class="modal-header" style="background: var(--park-primary); color: #ffffff; padding: 20px 24px;">
                    <h5 class="modal-title text-white" id="modalEditarInstitucionLabel" style="font-family:'Outfit'; font-weight:600; margin:0; color:#ffffff !important;">Editar Información de Institución</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <form id="formEditarInstitucion" novalidate style="display: flex; flex-direction: column; flex: 1 1 auto; overflow: hidden; min-height: 0;">
                    <input type="hidden" id="edit_id" name="id">
                    <input type="hidden" id="edit_entidad_id" name="entidad_id">
                    
                    <div class="modal-body" style="background:#ffffff; padding: 30px; overflow-y: auto; max-height: calc(88vh - 140px); flex: 1 1 auto;">
                        <div id="alertEditarInstFeedback" class="alert alert-toast alert-toast-danger" role="alert" style="display:none;"></div>
                        
                        <!-- Sección 1: Datos Básicos -->
                        <h5 class="mb-3" style="font-family:'Outfit'; font-weight:700; color:var(--park-dark);">1. Datos Identificativos</h5>
                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <label for="edit_tipo_documento" class="form-premium-label">Tipo RIF</label>
                                <select class="form-select form-premium-control" id="edit_tipo_documento" name="tipo_documento" required>
                                    <option value="J">J</option>
                                    <option value="G">G</option>
                                    <option value="V">V</option>
                                    <option value="E">E</option>
                                    <option value="P">P</option>
                                </select>
                            </div>
                            <div class="col-md-8">
                                <label for="edit_documento" class="form-premium-label">Número RIF / Cédula</label>
                                <input type="text" class="form-control form-premium-control" id="edit_documento" name="documento" placeholder="Ej. 123456789" required>
                            </div>
                            <div class="col-md-5">
                                 <label for="edit_nombre_completo" class="form-premium-label">Razón Social / Nombre Oficial</label>
                                 <input type="text" class="form-control form-premium-control" id="edit_nombre_completo" name="nombre_completo" placeholder="Ej. Colegio San Vicente" required>
                             </div>
                             <div class="col-md-4">
                                 <label for="edit_tipo_organizacion_select" class="form-premium-label">Tipo de Organización</label>
                                 <select class="form-select form-premium-control select-tipo-organizacion" id="edit_tipo_organizacion_select" name="tipo_organizacion" required>
                                     <option value="">Seleccione Tipo</option>
                                     <option value="Educativa">Educativa</option>
                                     <option value="Empresarial">Empresarial</option>
                                     <option value="Gubernamental">Gubernamental</option>
                                     <option value="Fundación / ONG">Fundación / ONG</option>
                                     <option value="Comunitaria">Comunitaria</option>
                                     <option value="Religiosa">Religiosa</option>
                                     <option value="Otro">Otro (Especificar)</option>
                                 </select>
                                 <div class="mt-2 custom-tipo-organizacion-container" id="edit_custom_tipo_org_container" style="display: none;">
                                     <input type="text" class="form-control form-premium-control" id="edit_tipo_organizacion" placeholder="Escriba el tipo de organización personalizado...">
                                 </div>
                             </div>
                             <div class="col-md-3">
                                 <label for="edit_tipo_gestion" class="form-premium-label">Tipo de Gestión</label>
                                 <select class="form-select form-premium-control" id="edit_tipo_gestion" name="tipo_gestion" required>
                                     <option value="publica">Pública</option>
                                     <option value="privada">Privada</option>
                                 </select>
                             </div>
                        </div>

                        <!-- Sección 2: Sedes de la Institución (Multi-Sede) -->
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0" style="font-family:'Outfit'; font-weight:700; color:var(--park-dark);">2. Sedes de la Institución</h5>
                            <button type="button" class="btn btn-sm btn-outline-success" id="btnEditAddSede" style="border-radius: 8px; font-weight: 600;">
                                + Agregar Sede
                            </button>
                        </div>
                        <p class="text-muted" style="font-size:0.85rem; margin-top:-5px; margin-bottom:15px;">
                            Administre las sedes asociadas a esta institución.
                        </p>

                        <div id="editSedesContainer" class="d-flex flex-column gap-3 mb-4">
                            <!-- Filas de sedes dinámicas inyectadas por JS -->
                        </div>

                        <!-- Sección 3: Directorio de Contactos -->
                        <h5 style="font-family:'Outfit'; font-weight:700; color:var(--park-dark);" class="mb-3">3. Directorio de Contactos</h5>
                        
                        <div class="row g-3 mb-4">
                            <!-- Columna Teléfonos -->
                            <div class="col-md-6">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <label class="form-premium-label mb-0" style="font-weight: 700;">Teléfonos</label>
                                    <button type="button" class="btn btn-sm btn-outline-success p-1 py-0.5" id="btnEditAddPhone" style="font-size: 0.75rem; border-radius: 6px;">+ Teléfono</button>
                                </div>
                                <div id="editPhonesContainer" class="d-flex flex-column gap-2">
                                    <!-- Teléfonos dinámicos -->
                                </div>
                            </div>
                            
                            <!-- Columna Correos -->
                            <div class="col-md-6">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <label class="form-premium-label mb-0" style="font-weight: 700;">Correos Electrónicos</label>
                                    <button type="button" class="btn btn-sm btn-outline-success p-1 py-0.5" id="btnEditAddEmail" style="font-size: 0.75rem; border-radius: 6px;">+ Correo</button>
                                </div>
                                <div id="editEmailsContainer" class="d-flex flex-column gap-2">
                                    <!-- Correos dinámicos -->
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer" style="background: #fdfdfd; border-top: 1px solid #eeeeee; padding: 20px 24px;">
                        <button type="button" class="btn btn-secondary btn-premium-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success btn-premium-primary" id="btnGuardarEditInst">Guardar Cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL: ELIMINAR INSTITUCIÓN -->
    <div class="modal fade modal-premium" id="modalEliminarInstitucion" tabindex="-1" aria-labelledby="modalEliminarInstitucionLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 20px; border: none; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.15);">
                <div class="modal-header" style="background: #ef4444; color: #ffffff; padding: 20px 24px;">
                    <h5 class="modal-title" id="modalEliminarInstitucionLabel" style="font-family:'Outfit'; font-weight:600; margin:0;">Confirmar Eliminación</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body" style="background:#ffffff; padding: 24px;">
                    <input type="hidden" id="delete_institucion_id">
                    <p class="mb-0" style="font-size:0.95rem;">¿Está completamente seguro de que desea eliminar la institución <strong id="deleteInstNameSpan" class="text-danger"></strong> del sistema?</p>
                    <p class="text-danger mt-2 mb-0" style="font-size:0.85rem; font-weight:600;">Esta acción es irreversible y borrará permanentemente sus sedes y contactos asociados.</p>
                </div>
                <div class="modal-footer" style="background:#f8f9fa; border-top: 1px solid #e2e8f0; padding: 16px 24px;">
                    <button type="button" class="btn btn-secondary py-2 px-3" data-bs-dismiss="modal" style="border-radius:10px; font-weight:600; font-size:0.9rem;">Cancelar</button>
                    <button type="button" class="btn btn-danger py-2 px-3" id="btnConfirmarEliminarInstitucion" style="border-radius:10px; font-weight:600; font-size:0.9rem; background:#ef4444; border-color:#ef4444; color:#ffffff;">Eliminar Institución</button>
                </div>
            </div>
        </div>
    </div>
