<?php
// Evitar acceso directo
if (!isset($userPermissions)) {
    exit;
}
?>
<?php if ($userPermissions['citas']): ?>
    <div class="panel-container animate__animated animate__fadeIn">
        <div class="panel-header-section">
            <div class="panel-header-title">
                <h3>Gestión de Citas y Visitas Guiadas</h3>
                <p>Control de solicitudes de visitas guiadas, registro de instituciones y carga de visitantes</p>
            </div>
            <div>
                <button class="btn-premium-primary" data-bs-toggle="modal" data-bs-target="#modalCrearCita">
                    <svg viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                    Agendar Cita
                </button>
            </div>
        </div>

        <!-- Barra de Búsqueda y Filtrado -->
        <div class="search-filter-row">
            <div class="search-input-group">
                <svg class="search-icon-svg" viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                <input type="text" id="searchCitas" placeholder="Buscar citas por código, institución o RIF...">
            </div>
        </div>

        <!-- Loader Animado -->
        <div id="loaderCitas" class="text-center py-5">
            <div class="spinner-border text-success" role="status" style="width: 3rem; height: 3rem;">
                <span class="visually-hidden">Cargando...</span>
            </div>
            <p class="text-muted mt-2">Cargando cronograma de citas del parque...</p>
        </div>

        <!-- Tabla de Citas Agendadas -->
        <div class="table-responsive-premium">
            <table class="table-premium">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Institución</th>
                        <th>Fecha y Hora</th>
                        <th>Visitantes</th>
                        <th>Guía Asignado</th>
                        <th>Estado Cita</th>
                        <th>Estado Pago</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="tablaCitasBody" style="display: none;">
                    <!-- Se poblará vía AJAX -->
                </tbody>
            </table>
        </div>

        <!-- Controles de Paginación -->
        <div id="paginacionCitas" class="d-flex justify-content-between align-items-center mt-3 px-2 flex-wrap gap-2" style="display: none !important;">
            <div class="pagination-info-text">
                Mostrando <span id="pagStartCitas">0</span> - <span id="pagEndCitas">0</span> de <span id="pagTotalCitas">0</span> registros
            </div>
            <nav>
                <ul class="pagination pagination-premium mb-0" id="paginationControlsCitas">
                    <!-- Botones de paginación dinámicos -->
                </ul>
            </nav>
        </div>
    </div>

    <!-- MODAL: REGISTRAR CITA (WIZARD MULTI-PASO) -->
    <div class="modal fade modal-premium" id="modalCrearCita" tabindex="-1" aria-labelledby="modalCrearCitaLabel" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content" style="border-radius: 20px; border: none; overflow: visible !important; box-shadow: 0 15px 35px rgba(0,0,0,0.15);">
                <div class="modal-header" style="background: var(--park-primary); color: #ffffff; padding: 20px 24px; border-top-left-radius: 20px; border-top-right-radius: 20px;">
                    <h5 class="modal-title" id="modalCrearCitaLabel" style="font-family:'Outfit'; font-weight:600; margin:0;">Agendar Nueva Visita Guiada</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <form id="formCrearCita" novalidate>
                    <input type="hidden" name="institucion_id" id="final_institucion_id">
                    <input type="hidden" name="tipo_documento" id="final_tipo_documento">
                    <input type="hidden" name="documento" id="final_documento">

                    <div class="modal-body" style="background:#ffffff; padding: 30px;">
                        <!-- Indicadores de Pasos -->
                        <div class="step-wizard">
                            <div class="step-wizard-progress"></div>
                            <div class="step-wizard-item active">
                                <div class="step-wizard-circle">1</div>
                                <span class="step-wizard-label">Comprobar RIF</span>
                            </div>
                            <div class="step-wizard-item">
                                <div class="step-wizard-circle">2</div>
                                <span class="step-wizard-label">Datos Institución</span>
                            </div>
                            <div class="step-wizard-item">
                                <div class="step-wizard-circle">3</div>
                                <span class="step-wizard-label">Agendar Cita</span>
                            </div>
                        </div>

                        <!-- PASO 1: VERIFICAR INSTITUCIÓN -->
                        <div class="wizard-step-panel active" id="wizardStep1">
                            <h5 class="mb-3" style="font-family:'Outfit'; font-weight:700; color:var(--park-dark);">Búsqueda de la Institución</h5>
                            <p class="text-muted mb-4" style="font-size:0.85rem;">Busque la institución externa por su Razón Social o RIF. Si no se encuentra registrada, elija registrar una nueva.</p>
                            
                            <div class="row g-3">
                                <div class="col-12 position-relative">
                                    <label for="buscar_institucion_autocomplete" class="form-premium-label">Buscar Institución (Nombre o RIF)</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-white border-end-0" style="border-radius: 12px 0 0 12px; border-color: var(--park-gray);">
                                            <svg viewBox="0 0 24 24" style="width:20px; height:20px; fill:var(--park-muted);"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                                        </span>
                                        <input type="text" class="form-control form-premium-control border-start-0" id="buscar_institucion_autocomplete" name="buscar_institucion_autocomplete_field" placeholder="Ej. Colegio La Salle o J-123456789..." autocomplete="new-password" style="border-radius: 0 12px 12px 0;">
                                    </div>
                                    <!-- Contenedor flotante para sugerencias -->
                                    <div id="autocomplete_suggestions" class="dropdown-menu shadow-lg w-100" style="display: none; position: absolute; max-height: 250px; overflow-y: auto; z-index: 1100; border-radius: 12px; margin-top: 5px; border: 1px solid var(--park-gray);"></div>
                                </div>
                            </div>

                            <div class="rif-status-box alert alert-success mt-4 shadow-sm" id="rifCheckStatus" style="display:none;">
                                <!-- Info de verificación dinámica -->
                            </div>
                        </div>

                        <!-- PASO 2: DATOS DE LA INSTITUCIÓN -->
                        <div class="wizard-step-panel" id="wizardStep2">
                            <h5 class="mb-3" style="font-family:'Outfit'; font-weight:700; color:var(--park-dark);">Información de la Institución</h5>
                            <p class="text-muted mb-4" style="font-size:0.85rem;">Si la institución es nueva, registre sus datos fiscales, dirección geográfica e información de contacto.</p>

                             <!-- Campos de RIF para Nueva Institución -->
                             <div class="row g-3 mb-3" id="nueva_institucion_rif_row" style="display: none;">
                                 <div class="col-md-4">
                                     <label for="tipo_documento_inst" class="form-premium-label">Tipo RIF</label>
                                     <select class="form-select form-premium-control" id="tipo_documento_inst">
                                         <option value="J" selected>J</option>
                                         <option value="G">G</option>
                                         <option value="V">V</option>
                                         <option value="E">E</option>
                                         <option value="P">P</option>
                                     </select>
                                 </div>
                                 <div class="col-md-8">
                                     <label for="documento_inst" class="form-premium-label">Número RIF / Cédula</label>
                                     <input type="text" class="form-control form-premium-control" id="documento_inst" placeholder="Ej. 123456789 (solo números)">
                                 </div>
                             </div>

                             <div class="row g-3">
                                <div class="col-md-5">
                                    <label for="nombre_institucion" class="form-premium-label">Razón Social / Nombre Oficial</label>
                                    <input type="text" class="form-control form-premium-control" id="nombre_institucion" name="nombre" placeholder="Ej. Colegio La Salle" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="tipo_organizacion" class="form-premium-label">Tipo de Organización</label>
                                    <select class="form-select form-premium-control" id="tipo_organizacion" name="tipo_organizacion" required>
                                        <option value="">Seleccione Tipo</option>
                                        <option value="Colegio Privado">Colegio Privado</option>
                                        <option value="Escuela Pública">Escuela Pública</option>
                                        <option value="Liceo Público">Liceo Público</option>
                                        <option value="Liceo Privado">Liceo Privado</option>
                                        <option value="Fundación / ONG">Fundación / ONG</option>
                                        <option value="Institución Gubernamental">Institución Gubernamental</option>
                                        <option value="Empresa Privada">Empresa Privada</option>
                                        <option value="Comunidad Organizada">Comunidad Organizada</option>
                                        <option value="Otro">Otro</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="tipo_gestion" class="form-premium-label">Tipo de Gestión</label>
                                    <select class="form-select form-premium-control" id="tipo_gestion" name="tipo_gestion" required>
                                        <option value="publica" selected>Pública</option>
                                        <option value="privada">Privada</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row g-3 mt-2">
                                <div class="col-md-3">
                                    <label for="estado_id" class="form-premium-label">Estado</label>
                                    <select class="form-select form-premium-control" id="estado_id" required>
                                        <option value="">Cargando estados...</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="municipio_id" class="form-premium-label">Municipio</label>
                                    <select class="form-select form-premium-control" id="municipio_id" required disabled>
                                        <option value="">Seleccione Municipio</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="parroquia_id" class="form-premium-label">Parroquia</label>
                                    <select class="form-select form-premium-control" id="parroquia_id" name="parroquia_id" required disabled>
                                        <option value="">Seleccione Parroquia</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="ciudad_id" class="form-premium-label">Ciudad</label>
                                    <select class="form-select form-premium-control" id="ciudad_id" name="ciudad_id" required disabled>
                                        <option value="">Seleccione Ciudad</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-3 mt-3">
                                <label for="detalles_direccion" class="form-premium-label">Detalles de la Dirección (Calle, Av, Sector)</label>
                                <input type="text" class="form-control form-premium-control" id="detalles_direccion" name="detalles_direccion" placeholder="Ej. Calle 12 entre carreras 3 y 4, Galpón Nro 20">
                            </div>

                            <!-- Contactos del Directorio -->
                            <div class="mt-4">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <label class="form-premium-label mb-0" style="font-weight:700;">Directorio de Contactos</label>
                                    <button type="button" class="btn-add-contact" id="btnAddNewContact">
                                        <span>+</span> Agregar Otro Contacto
                                    </button>
                                </div>
                                <div id="contactsContainer">
                                    <!-- Filas dinámicas vía JS -->
                                </div>
                            </div>
                        </div>

                        <!-- PASO 3: DETALLES DE LA CITA -->
                        <div class="wizard-step-panel" id="wizardStep3">
                            <h5 class="mb-3" style="font-family:'Outfit'; font-weight:700; color:var(--park-dark);">Agendación de la Visita Guiada</h5>
                            <p class="text-muted mb-4" style="font-size:0.85rem;">Defina la fecha, hora, recorrido y estado de facturación de la visita guiada agendada.</p>

                            <div class="row g-3">
                                <div class="col-md-12">
                                    <label for="recorrido_id" class="form-premium-label">Recorrido del Parque Asignado (Opcional)</label>
                                    <select class="form-select form-premium-control" id="recorrido_id" name="recorrido_id">
                                        <option value="">Cargando recorridos...</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row g-3 mt-2">
                                <div class="col-md-6">
                                    <label for="fecha_visita" class="form-premium-label">Fecha Planificada de Visita</label>
                                    <input type="date" class="form-control form-premium-control" id="fecha_visita" name="fecha_visita" min="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="hora_inicio" class="form-premium-label">Hora de Inicio</label>
                                    <input type="time" class="form-control form-premium-control" id="hora_inicio" name="hora_inicio" required>
                                </div>
                            </div>

                            <div class="row g-3 mt-2">
                                <div class="col-md-6">
                                    <label for="metodo_pago" class="form-premium-label">Método de Facturación / Pago</label>
                                    <select class="form-select form-premium-control" id="metodo_pago" name="metodo_pago" required>
                                        <option value="">Seleccione Método</option>
                                        <option value="Transferencia Bancaria">Transferencia Bancaria</option>
                                        <option value="Pago Móvil">Pago Móvil</option>
                                        <option value="Efectivo Divisas">Efectivo Divisas (USD)</option>
                                        <option value="Efectivo Bolívares">Efectivo Bolívares (VES)</option>
                                        <option value="Exento / Donativo">Exento / Donativo del Parque</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="pago_estado" class="form-premium-label">Estado del Pago</label>
                                    <select class="form-select form-premium-control" id="pago_estado" name="pago_estado" required>
                                        <option value="pendiente" selected>Pendiente</option>
                                        <option value="pagado">Pagado / Verificado</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Contenedor dinámico de alertas de capacidad y recomendaciones -->
                            <div id="bookingCapacityAlert" class="mt-3" style="display: none;"></div>

                            <!-- Calendario interactivo para selección visual de la fecha -->
                            <div class="mt-3">
                                <label class="form-premium-label" style="font-weight: 700;">Calendario de Disponibilidad (Haga clic en un día para seleccionar la fecha)</label>
                                <div id="calendarReserva"></div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer" style="background:#f8f9fa; border-top: 1px solid var(--park-gray); padding: 18px 30px;">
                        <button type="button" class="btn-step-prev" id="btnWizardPrev" style="display: none;">Atrás</button>
                        <button type="button" class="btn-step-next" id="btnWizardNext">Siguiente</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL: ELIMINAR CITA -->
    <div class="modal fade modal-premium" id="modalEliminarCita" tabindex="-1" aria-labelledby="modalEliminarCitaLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 20px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.15);">
                <div class="modal-header" style="background: #ef4444; color: #ffffff; padding: 20px 24px;">
                    <h5 class="modal-title" id="modalEliminarCitaLabel" style="font-family:'Outfit'; font-weight:600; margin:0;">Confirmar Eliminación de Cita</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body" style="padding: 24px;">
                    <input type="hidden" id="delete_cita_id">
                    <p class="mb-0" style="font-size:0.95rem;">¿Está completamente seguro de que desea eliminar la cita <strong id="deleteCitaNameSpan" class="text-danger"></strong> del sistema?</p>
                    <p class="text-danger mt-2 mb-0" style="font-size:0.85rem; font-weight:600;">Esta acción es irreversible y revocaría el agendamiento y los registros de visitantes asociados.</p>
                </div>
                <div class="modal-footer" style="background:#f8f9fa; border-top: 1px solid var(--park-gray); padding: 16px 24px;">
                    <button type="button" class="btn btn-secondary py-2 px-3" data-bs-dismiss="modal" style="border-radius:10px; font-weight:600; font-size:0.9rem;">Cancelar</button>
                    <button type="button" class="btn btn-danger py-2 px-3" id="btnConfirmarEliminarCita" style="border-radius:10px; font-weight:600; font-size:0.9rem; background:#ef4444; border-color:#ef4444;">Eliminar Cita</button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL: EDITAR CITA -->
    <div class="modal fade modal-premium" id="modalEditarCita" tabindex="-1" aria-labelledby="modalEditarCitaLabel" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-md modal-dialog-centered">
            <div class="modal-content" style="border-radius: 20px; border: none; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.15);">
                <div class="modal-header" style="background: var(--park-primary); color: #ffffff; padding: 20px 24px;">
                    <h5 class="modal-title" id="modalEditarCitaLabel" style="font-family:'Outfit'; font-weight:600; margin:0;">Editar Cita</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <form id="formEditarCita" novalidate>
                    <input type="hidden" name="id" id="edit_cita_id">
                    <div class="modal-body" style="background:#ffffff; padding: 30px;">
                        
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label for="edit_recorrido_id" class="form-premium-label">Recorrido del Parque Asignado (Opcional)</label>
                                <select class="form-select form-premium-control" id="edit_recorrido_id" name="recorrido_id">
                                    <option value="">Cargando recorridos...</option>
                                </select>
                            </div>
                        </div>

                        <div class="row g-3 mt-2">
                            <div class="col-md-6">
                                <label for="edit_fecha_visita" class="form-premium-label">Fecha Planificada de Visita</label>
                                <input type="date" class="form-control form-premium-control" id="edit_fecha_visita" name="fecha_visita" required>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_hora_inicio" class="form-premium-label">Hora de Inicio</label>
                                <input type="time" class="form-control form-premium-control" id="edit_hora_inicio" name="hora_inicio" required>
                            </div>
                        </div>

                        <div class="row g-3 mt-2">
                            <div class="col-md-6">
                                <label for="edit_metodo_pago" class="form-premium-label">Método de Facturación / Pago</label>
                                <select class="form-select form-premium-control" id="edit_metodo_pago" name="metodo_pago" required>
                                    <option value="">Seleccione Método</option>
                                    <option value="Transferencia Bancaria">Transferencia Bancaria</option>
                                    <option value="Pago Móvil">Pago Móvil</option>
                                    <option value="Efectivo Divisas">Efectivo Divisas (USD)</option>
                                    <option value="Efectivo Bolívares">Efectivo Bolívares (VES)</option>
                                    <option value="Exento / Donativo">Exento / Donativo del Parque</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_pago_estado" class="form-premium-label">Estado del Pago</label>
                                <select class="form-select form-premium-control" id="edit_pago_estado" name="pago_estado" required>
                                    <option value="pendiente">Pendiente</option>
                                    <option value="pagado">Pagado / Verificado</option>
                                </select>
                            </div>
                        </div>

                        <div class="row g-3 mt-2">
                            <div class="col-md-12">
                                <label for="edit_estatus" class="form-premium-label">Estado de la Cita</label>
                                <select class="form-select form-premium-control" id="edit_estatus" name="estatus" required>
                                    <option value="pendiente">Pendiente</option>
                                    <option value="aprobado">Aprobada</option>
                                    <option value="completado">Completada</option>
                                    <option value="cancelado">Cancelada</option>
                                </select>
                            </div>
                        </div>
                        
                    </div>

                    <div class="modal-footer" style="background:#f8f9fa; border-top: 1px solid var(--park-gray); padding: 18px 30px;">
                        <button type="button" class="btn btn-secondary py-2 px-3" data-bs-dismiss="modal" style="border-radius:10px; font-weight:600; font-size:0.9rem;">Cancelar</button>
                        <button type="submit" class="btn btn-success py-2 px-3" id="btnGuardarEditCita" style="border-radius:10px; font-weight:600; font-size:0.9rem; background:var(--park-primary); border-color:var(--park-primary);">Guardar Cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL: IMPORTAR VISITANTES (CSV) -->
    <div class="modal fade modal-premium" id="modalImportarVisitantes" tabindex="-1" aria-labelledby="modalImportarVisitantesLabel" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-lg modal-dialog-centered" id="importModalDialog">
            <div class="modal-content" style="border-radius: 20px; border: none; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.15);">
                <div class="modal-header" style="background: var(--park-primary); color: #ffffff; padding: 20px 24px;">
                    <h5 class="modal-title" id="modalImportarVisitantesLabel" style="font-family:'Outfit'; font-weight:600; margin:0;">Importar Listado de Visitantes</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <form id="formImportarVisitantes" enctype="multipart/form-data" novalidate>
                    <input type="hidden" name="cita_id" id="import_cita_id">
                    <div class="modal-body" style="padding: 24px;">
                        <!-- Panel 1: Carga de Archivo -->
                        <div id="importUploadPanel">
                            <p class="text-muted" style="font-size:0.85rem; line-height:1.45;">Cargue la plantilla Excel o CSV con el listado de visitantes de la cita: <strong id="importCitaNombreSpan" class="text-success"></strong>.</p>
                            
                            <!-- Input de archivo (fuera del drop zone para evitar bucle de eventos click) -->
                            <input type="file" id="csvFileInput" accept=".xlsx,.csv" style="display: none;">
                            <!-- Zona de Drag & Drop -->
                            <div class="csv-upload-zone" id="csvDropZone">
                                <span class="csv-upload-icon">📥</span>
                                <p class="csv-upload-text">Arrastra el archivo Excel o CSV aquí o <strong>haz clic para examinar</strong></p>
                                <div class="csv-upload-file-info" id="csvFileInfo"></div>
                            </div>

                            <div class="alert alert-info mt-3 p-2.5 mb-0" style="border-radius:10px; font-size:0.75rem; line-height:1.35;">
                                <strong>Instrucciones:</strong> Descarga la plantilla Excel (.xlsx) y llénala con los datos de los visitantes. Las columnas son: Cédula (opcional), Nombres, Apellidos y Fecha Nacimiento en formato <code>AAAA-MM-DD</code>. Puedes subir <strong>.xlsx</strong> o <strong>.csv</strong>.
                            </div>
                        </div>

                        <!-- Panel 2: Previsualización y Edición -->
                        <div id="importPreviewPanel" style="display: none;">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0" style="font-family:'Outfit'; font-weight:700; color:var(--park-dark);">Previsualización de Visitantes a Registrar</h6>
                                <button type="button" class="btn btn-sm btn-outline-success" id="btnAgregarVisitantePreview" style="border-radius:8px; font-weight:600; font-size:0.8rem;">＋ Añadir Fila</button>
                            </div>
                            <div class="table-responsive" style="max-height: 300px; border: 1px solid var(--park-gray); border-radius: 12px; overflow-y: auto;">
                                <table class="table table-sm align-middle mb-0 text-center" style="font-size: 0.82rem;">
                                    <thead class="table-light" style="position: sticky; top: 0; z-index: 10;">
                                        <tr>
                                            <th style="width: 25%; text-align: left; padding-left: 10px;">Cédula (Opcional)</th>
                                            <th style="width: 25%; text-align: left;">Nombres *</th>
                                            <th style="width: 25%; text-align: left;">Apellidos *</th>
                                            <th style="width: 20%; text-align: left;">F. Nacimiento *</th>
                                            <th style="width: 5%;"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="importPreviewTableBody">
                                        <!-- Filas dinámicas -->
                                    </tbody>
                                </table>
                            </div>
                            <p class="text-muted mt-2 mb-0" style="font-size:0.75rem;">Puedes editar los datos directamente en las cajas de texto de la tabla o remover registros antes de confirmar.</p>
                        </div>
                    </div>
                    <div class="modal-footer" style="background:#f8f9fa; border-top: 1px solid var(--park-gray); padding: 16px 24px;">
                        <!-- Botones para Panel 1 -->
                        <div id="importUploadFooter" class="d-flex w-100 justify-content-end gap-2">
                            <button type="button" class="btn btn-secondary py-2 px-3" data-bs-dismiss="modal" style="border-radius:10px; font-weight:600; font-size:0.9rem;">Cancelar</button>
                            <button type="button" class="btn btn-premium-primary" id="btnSiguientePrevisualizar" style="background: var(--park-primary); border-color: var(--park-primary);" disabled>Cargar y Previsualizar</button>
                        </div>
                        <!-- Botones para Panel 2 -->
                        <div id="importPreviewFooter" class="d-flex w-100 justify-content-between gap-2" style="display: none !important;">
                            <button type="button" class="btn btn-outline-secondary py-2 px-3" id="btnAtrasUpload" style="border-radius:10px; font-weight:600; font-size:0.9rem;">Atrás</button>
                            <button type="submit" class="btn btn-premium-primary" id="btnConfirmarImportarCSV" style="background: var(--park-primary); border-color: var(--park-primary);">Confirmar y Guardar</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL: VER VISITANTES DE CITA -->
    <div class="modal fade modal-premium" id="modalVerVisitantes" tabindex="-1" aria-labelledby="modalVerVisitantesLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content" style="border-radius: 20px; border: none; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.15);">
                <div class="modal-header" style="background: var(--park-primary); color: #ffffff; padding: 20px 24px;">
                    <h5 class="modal-title" id="modalVerVisitantesLabel" style="font-family:'Outfit'; font-weight:600; margin:0;">Visitantes de la Cita</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body" style="padding: 24px;">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <p class="text-muted mb-0" style="font-size:0.85rem; line-height:1.45;">Listado de visitantes registrados para la cita: <strong id="verVisitantesCitaNombreSpan" class="text-success"></strong>.</p>
                        <button type="button" class="btn btn-sm btn-outline-success" id="btnToggleAgregarAsistencia" style="border-radius:8px; font-weight:600; font-size:0.8rem; display: flex; align-items: center; gap: 4px;">
                            <span>＋</span> Agregar asistencia de visitantes
                        </button>
                    </div>

                    <!-- Panel Oculto: Gestión/Agregar Asistencia de Visitantes -->
                    <div id="panelAgregarAsistencia" style="display: none; background-color: #F8FAFC; border: 1.5px solid var(--park-gray); border-radius: 14px; padding: 18px; margin-bottom: 20px; box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);">
                        <h6 style="font-family:'Outfit'; font-weight:700; color:var(--park-dark); margin-bottom: 12px; font-size: 0.9rem;">Agregar Visitantes a la Asistencia</h6>
                        
                        <ul class="nav nav-pills nav-pills-premium mb-3" id="pills-tab-visitantes" role="tablist" style="gap: 8px;">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="pills-existente-tab" data-bs-toggle="pill" data-bs-target="#pills-existente" type="button" role="tab" aria-controls="pills-existente" aria-selected="true" style="font-size: 0.78rem; font-weight: 600; border-radius: 8px; padding: 6px 12px;">Registrados en el Sistema</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="pills-nuevo-tab" data-bs-toggle="pill" data-bs-target="#pills-nuevo" type="button" role="tab" aria-controls="pills-nuevo" aria-selected="false" style="font-size: 0.78rem; font-weight: 600; border-radius: 8px; padding: 6px 12px;">Nuevo Registro</button>
                            </li>
                        </ul>
                        
                        <div class="tab-content" id="pills-tabContent-visitantes">
                            <!-- Pestaña 1: Buscar/Agregar Visitantes Existentes -->
                            <div class="tab-pane fade show active" id="pills-existente" role="tabpanel" aria-labelledby="pills-existente-tab">
                                <div class="mb-3">
                                    <input type="text" id="inputBuscarVisitantesSistema" class="form-control form-premium-control" placeholder="Buscar por Cédula, Nombres o Apellidos..." style="font-size: 0.85rem; border-radius: 8px; padding: 8px 12px;">
                                </div>
                                
                                <div class="table-responsive" style="max-height: 200px; border: 1px solid var(--park-gray); border-radius: 10px; overflow-y: auto; background-color: #ffffff;">
                                    <table class="table table-sm align-middle mb-0" style="font-size: 0.82rem;">
                                        <thead class="table-light" style="position: sticky; top: 0; z-index: 5;">
                                            <tr>
                                                <th class="text-center" style="width: 12%;">Seleccionar</th>
                                                <th style="width: 28%;">Cédula</th>
                                                <th style="width: 60%;">Nombre Completo</th>
                                            </tr>
                                        </thead>
                                        <tbody id="listaVisitantesSistemaChecklist">
                                            <!-- Inyectado dinámicamente con JS (todos los visitantes del sistema) -->
                                        </tbody>
                                    </table>
                                </div>
                                
                                <div class="d-flex justify-content-end mt-3 gap-2">
                                    <button type="button" class="btn btn-sm btn-secondary" id="btnCancelarAgregarAsistencia" style="border-radius: 8px; font-weight:600; font-size:0.8rem; padding: 6px 14px;">Ocultar</button>
                                    <button type="button" class="btn btn-sm btn-success" id="btnGuardarSeleccionAsistencia" style="border-radius: 8px; font-weight:600; font-size:0.8rem; padding: 6px 14px; background: var(--park-primary); border-color: var(--park-primary);">Agregar Seleccionados</button>
                                </div>
                            </div>
                            
                            <!-- Pestaña 2: Crear Nuevo Visitante y Añadir a Asistencia -->
                            <div class="tab-pane fade" id="pills-nuevo" role="tabpanel" aria-labelledby="pills-nuevo-tab">
                                <form id="formRegistrarYAgregarAsistencia" novalidate>
                                    <div class="row g-2">
                                        <div class="col-md-6">
                                            <label for="reg_visitante_cedula" class="form-premium-label" style="font-size: 0.75rem;">Cédula / Documento <span class="text-muted" style="font-size:0.7rem;">(Opcional)</span></label>
                                            <input type="text" class="form-control form-premium-control" id="reg_visitante_cedula" name="cedula" placeholder="Ej. 28123456" style="font-size: 0.8rem; border-radius: 8px; padding: 6px 10px;">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="reg_visitante_fecha_nac" class="form-premium-label" style="font-size: 0.75rem;">F. Nacimiento *</label>
                                            <input type="date" class="form-control form-premium-control" id="reg_visitante_fecha_nac" name="fecha_nacimiento" required style="font-size: 0.8rem; border-radius: 8px; padding: 6px 10px;">
                                        </div>
                                    </div>
                                    <div class="row g-2 mt-2">
                                        <div class="col-md-6">
                                            <label for="reg_visitante_nombres" class="form-premium-label" style="font-size: 0.75rem;">Nombres *</label>
                                            <input type="text" class="form-control form-premium-control" id="reg_visitante_nombres" name="nombres" placeholder="Nombres del visitante" required style="font-size: 0.8rem; border-radius: 8px; padding: 6px 10px;">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="reg_visitante_apellidos" class="form-premium-label" style="font-size: 0.75rem;">Apellidos *</label>
                                            <input type="text" class="form-control form-premium-control" id="reg_visitante_apellidos" name="apellidos" placeholder="Apellidos del visitante" required style="font-size: 0.8rem; border-radius: 8px; padding: 6px 10px;">
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-end mt-3 gap-2">
                                        <button type="button" class="btn btn-sm btn-secondary" id="btnCancelarAgregarNuevoAsistencia" style="border-radius: 8px; font-weight:600; font-size:0.8rem; padding: 6px 14px;">Ocultar</button>
                                        <button type="submit" class="btn btn-sm btn-success" id="btnRegistrarYAgregarAsistencia" style="border-radius: 8px; font-weight:600; font-size:0.8rem; padding: 6px 14px; background: var(--park-primary); border-color: var(--park-primary);">Registrar y Agregar</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Tabla de Asistentes Actuales -->
                    <div class="table-responsive" style="max-height: 350px; border: 1px solid var(--park-gray); border-radius: 12px; overflow-y: auto;">
                        <table class="table table-sm table-striped align-middle mb-0" style="font-size: 0.85rem;">
                            <thead class="table-light" style="position: sticky; top: 0; z-index: 10;">
                                <tr>
                                    <th class="text-center" style="width: 8%;">#</th>
                                    <th style="width: 25%;">Cédula</th>
                                    <th style="width: 42%;">Nombre Completo</th>
                                    <th class="text-center" style="width: 17%;">F. Nacimiento</th>
                                    <th class="text-center" style="width: 8%;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="tablaVerVisitantesBody">
                                <!-- Inyectado por JS -->
                            </tbody>
                        </table>
                    </div>

                    <!-- Controles de Paginación Modal Visitantes -->
                    <div id="paginacionModalVisitantes" class="d-flex justify-content-between align-items-center mt-3 px-1 flex-wrap gap-2" style="display: none !important;">
                        <div class="pagination-info-text">
                            Mostrando <span id="pagStartModalVisitantes">0</span> - <span id="pagEndModalVisitantes">0</span> de <span id="pagTotalModalVisitantes">0</span> visitantes
                        </div>
                        <nav>
                            <ul class="pagination pagination-premium pagination-sm mb-0" id="paginationControlsModalVisitantes">
                                <!-- Botones de paginación dinámicos -->
                            </ul>
                        </nav>
                    </div>
                </div>
                <div class="modal-footer" style="background:#f8f9fa; border-top: 1px solid var(--park-gray); padding: 16px 24px;">
                    <button type="button" class="btn btn-secondary py-2 px-4" data-bs-dismiss="modal" style="border-radius:10px; font-weight:600; font-size:0.9rem;">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL: CONFIRMAR QUITAR VISITANTE DE ASISTENCIA -->
    <div class="modal fade modal-premium" id="modalConfirmarEliminarAsistente" tabindex="-1" aria-labelledby="modalConfirmarEliminarAsistenteLabel" aria-hidden="true" style="z-index: 1070;">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 20px; overflow: hidden; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.15);">
                <div class="modal-header" style="background: #ef4444; color: #ffffff; border-bottom: none; padding: 20px 24px;">
                    <h5 class="modal-title" id="modalConfirmarEliminarAsistenteLabel" style="font-family:'Outfit'; font-weight:600; margin: 0;">Confirmar Remoción de Asistente</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body" style="background:#ffffff; padding: 24px;">
                    <input type="hidden" id="quitar_asistente_visitante_id">
                    <p class="mb-0" style="font-size:0.95rem;">¿Está completamente seguro de que desea remover al asistente <strong id="quitarAsistenteNombreSpan" class="text-danger"></strong> de esta cita?</p>
                    <p class="text-danger mt-2 mb-0" style="font-size:0.85rem; font-weight:600;">Esta acción afectará los listados de asistencia asociados a esta visita.</p>
                </div>
                <div class="modal-footer" style="background:#f8f9fa; border-top: 1px solid var(--park-gray); padding: 16px 24px;">
                    <button type="button" class="btn btn-secondary py-2 px-3" data-bs-dismiss="modal" style="border-radius:10px; font-weight:600; font-size:0.9rem;">Cancelar</button>
                    <button type="button" class="btn btn-danger py-2 px-3" id="btnConfirmarQuitarAsistenteModal" style="border-radius:10px; font-weight:600; font-size:0.9rem; background: #ef4444; border-color: #ef4444;">Remover Asistencia</button>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
    <!-- Escudo de acceso denegado premium -->
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
