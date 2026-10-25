<div class="recorridos-panel animate__animated animate__fadeIn">
        <div class="recorridos-title-bar">
            <div>
                <h3>Gestión de Recorridos y Zonas en el Mapa</h3>
                <p>Establece pines en el mapa para delimitar zonas y agrúpalos para definir recorridos</p>
            </div>
            <div class="d-flex gap-2 flex-nowrap">
                <button class="btn-park-action" id="btnModoZona">
                    <svg viewBox="0 0 24 24" style="width:20px; height:20px; fill:currentColor; margin-right:4px;"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
                    Agregar Zona
                </button>
                <button class="btn-park-action btn-park-secondary" id="btnModoRecorrido">
                    <svg viewBox="0 0 24 24" style="width:20px; height:20px; fill:currentColor; margin-right:4px;"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                    Crear Recorrido
                </button>
            </div>
        </div>

        <!-- Indicador de Modo Agregar Activo -->
        <div class="admin-mode-indicator animate__animated animate__slideInDown" id="indicadorModoZona" style="display: none;">
            <span>
                <span class="spinner-grow spinner-grow-sm text-danger" role="status" style="width: 12px; height: 12px;"></span>
                <strong>MODO AGREGAR ZONA ACTIVO:</strong> Haz clic en cualquier parte del mapa para marcar y registrar una zona.
            </span>
            <button class="btn btn-sm btn-cancel-modo" id="btnCancelarModoZona">Cancelar Modo</button>
        </div>

        <!-- Formulario para Crear Recorrido (Inicialmente Oculto, arriba del mapa) -->
        <div id="panelCrearRecorridoForm" class="card mb-4 animate__animated animate__slideInDown" style="display: none; border-radius: 16px; border: 1.5px dashed var(--park-primary); box-shadow: 0 4px 12px rgba(0,0,0,0.02); overflow: hidden;">
            <div class="card-body" style="background: #ffffff; padding: 20px;">
                <h5 class="card-title font-weight-bold" style="font-family:'Outfit'; font-weight: 700; color: var(--park-primary);">Nuevo Recorrido</h5>
                <p class="text-muted" style="font-size:0.8rem; line-height: 1.35;">Escribe el nombre del recorrido y haz clic en orden sobre al menos dos zonas del mapa para crear la secuencia.</p>
                
                <form id="formCrearRecorrido">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="nombreRecorrido" class="form-label" style="font-weight: 600; font-size: 0.85rem; color: var(--park-dark);">Nombre del Recorrido</label>
                            <input type="text" class="form-control form-premium-control" id="nombreRecorrido" name="nombre" placeholder="Ej. Ruta de los Primates" required style="border-radius:10px;">
                        </div>
                        <div class="col-md-8 mb-3">
                            <label class="form-label" style="font-weight: 600; font-size: 0.85rem; color: var(--park-dark);">Ruta Trazada</label>
                            <div id="timelineCreacionWrapper" style="background:#f8f9fa; border-radius:10px; padding:10px; border: 1px solid var(--park-gray); min-height: 50px;">
                                <!-- Timeline dinámico -->
                            </div>
                        </div>
                    </div>
                    <div class="d-flex gap-2 justify-content-end mt-2">
                        <button type="button" class="btn btn-secondary btn-sm px-3 py-2" id="btnCancelarCrearRecorrido" style="border-radius:8px; font-weight:600;">Cancelar</button>
                        <button type="submit" class="btn btn-success btn-sm px-3 py-2" id="btnGuardarRecorrido" style="border-radius:8px; font-weight:600; background: var(--park-primary); border-color: var(--park-primary);">Guardar Recorrido</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="row g-4">
            <!-- Columna Izquierda: El Mapa Interactivo Responsivo -->
            <div class="col-lg-8">
                <div class="map-wrapper shadow-sm" id="mapWrapper">
                    <img src="view/assets/img/map.jpg" alt="Mapa del Parque Zoológico Bararida" id="mapaParque" class="map-image">
                    <!-- Overlay de SVG -->
                    <svg id="mapSvgOverlay" class="map-svg-overlay" viewBox="0 0 100 100" preserveAspectRatio="none"></svg>
                    <!-- Contenedor de Pines -->
                    <div id="mapPinsContainer"></div>
                    <!-- Tooltip Flotante de Edición Activa (Premium) -->
                    <div class="map-edit-tooltip" id="mapEditTooltip" style="display: none;">
                        <span class="pulse-indicator"></span>
                        <span class="tooltip-text" id="mapEditTooltipText">📍 Modo Edición Activo</span>
                    </div>
                </div>
            </div>

            <!-- Columna Derecha: Paneles de Control Lateral -->
            <div class="col-lg-4">
                
                <!-- Lista de Recorridos Existentes -->
                <div class="card mb-4" style="border-radius: 16px; border:1px solid var(--park-gray); overflow: hidden;">
                    <div class="card-body" style="padding: 20px;">
                        <h5 class="card-title font-weight-bold mb-3" style="font-family:'Outfit'; font-weight: 700; color: var(--park-dark);">Recorridos Existentes</h5>
                        <div class="sidebar-panel-list" id="adminListaRecorridos">
                            <!-- Se poblará vía AJAX -->
                        </div>
                    </div>
                </div>


                <!-- Lista de Zonas Pineadas -->
                <div class="card mb-4" style="border-radius: 16px; border:1px solid var(--park-gray); overflow: hidden;">
                    <div class="card-body" style="padding: 20px;">
                        <h5 class="card-title font-weight-bold mb-3" style="font-family:'Outfit'; font-weight: 700; color: var(--park-dark);">Zonas Registradas</h5>
                        <div class="sidebar-panel-list" id="adminListaZonas">
                            <!-- Se poblará vía AJAX -->
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Modal para Crear Nueva Zona -->
    <div class="modal fade modal-premium" id="modalCrearZona" tabindex="-1" aria-labelledby="modalCrearZonaLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 20px; overflow: hidden; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.15);">
                <div class="modal-header" style="background: var(--park-primary); color: #ffffff; border-bottom: none; padding: 20px 24px;">
                    <h5 class="modal-title text-white" id="modalCrearZonaLabel" style="font-family:'Outfit'; font-weight:600; margin: 0; color:#ffffff !important;">Nueva Zona en el Mapa</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <form id="formCrearZona">
                    <div class="modal-body" style="background:#ffffff; padding: 24px;">
                        <div class="mb-3">
                            <label for="nombreZona" class="form-premium-label" style="font-weight: 600; color: var(--park-dark);">Nombre de la Zona</label>
                            <input type="text" class="form-control form-premium-control" id="nombreZona" name="nombre" placeholder="Ej. Área de Serpentario" required style="border-radius:10px;">
                        </div>
                        <div class="mb-3">
                            <label for="nuevaZonaCoords" class="form-premium-label" style="font-weight: 600; color: var(--park-dark);">Coordenadas del Pin (%)</label>
                            <input type="text" class="form-control form-premium-control" id="nuevaZonaCoords" name="coordenadas" readonly required style="background:#f8f9fa; border-radius:10px; cursor:not-allowed;">
                        </div>
                    </div>
                    <div class="modal-footer" style="background:#f8f9fa; border-top: 1px solid var(--park-gray); padding: 16px 24px;">
                        <button type="button" class="btn btn-secondary py-2 px-3" data-bs-dismiss="modal" style="border-radius:10px; font-weight:600; font-size:0.9rem;">Cancelar</button>
                        <button type="submit" class="btn btn-premium-primary" id="btnGuardarZona" style="background: var(--park-primary); border-color: var(--park-primary);">Registrar Zona</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Confirmar Eliminación de Zona -->
    <div class="modal fade modal-premium" id="modalConfirmarEliminarZona" tabindex="-1" aria-labelledby="modalConfirmarEliminarZonaLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 20px; overflow: hidden; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.15);">
                <div class="modal-header" style="background: #ef4444; color: #ffffff; border-bottom: none; padding: 20px 24px;">
                    <h5 class="modal-title text-white" id="modalConfirmarEliminarZonaLabel" style="font-family:'Outfit'; font-weight:600; margin: 0; color:#ffffff !important;">Confirmar Eliminación de Zona</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body" style="background:#ffffff; padding: 24px;">
                    <input type="hidden" id="delete_zona_id_modal">
                    <p class="mb-0" style="font-size:0.95rem;">¿Está completamente seguro de que desea eliminar la zona <strong id="deleteZonaNombreSpan" class="text-danger"></strong> del mapa?</p>
                    <p class="text-danger mt-2 mb-0" style="font-size:0.85rem; font-weight:600;">Esta acción es irreversible y también eliminará las paradas asociadas de cualquier recorrido activo.</p>
                </div>
                <div class="modal-footer" style="background:#f8f9fa; border-top: 1px solid var(--park-gray); padding: 16px 24px;">
                    <button type="button" class="btn btn-secondary py-2 px-3" data-bs-dismiss="modal" style="border-radius:10px; font-weight:600; font-size:0.9rem;">Cancelar</button>
                    <button type="button" class="btn btn-danger py-2 px-3" id="btnConfirmarEliminarZonaModal" style="border-radius:10px; font-weight:600; font-size:0.9rem; background: #ef4444; border-color: #ef4444; color:#ffffff;">Eliminar Zona</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Confirmar Eliminación de Recorrido -->
    <div class="modal fade modal-premium" id="modalConfirmarEliminarRecorrido" tabindex="-1" aria-labelledby="modalConfirmarEliminarRecorridoLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 20px; overflow: hidden; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.15);">
                <div class="modal-header" style="background: #ef4444; color: #ffffff; border-bottom: none; padding: 20px 24px;">
                    <h5 class="modal-title text-white" id="modalConfirmarEliminarRecorridoLabel" style="font-family:'Outfit'; font-weight:600; margin: 0; color:#ffffff !important;">Confirmar Eliminación de Recorrido</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body" style="background:#ffffff; padding: 24px;">
                    <input type="hidden" id="delete_recorrido_id_modal">
                    <p class="mb-0" style="font-size:0.95rem;">¿Está completamente seguro de que desea eliminar el recorrido <strong id="deleteRecorridoNombreSpan" class="text-danger"></strong> del sistema?</p>
                    <p class="text-danger mt-2 mb-0" style="font-size:0.85rem; font-weight:600;">Esta acción es irreversible y revocaría el itinerario de forma permanente.</p>
                </div>
                <div class="modal-footer" style="background:#f8f9fa; border-top: 1px solid var(--park-gray); padding: 16px 24px;">
                    <button type="button" class="btn btn-secondary py-2 px-3" data-bs-dismiss="modal" style="border-radius:10px; font-weight:600; font-size:0.9rem;">Cancelar</button>
                    <button type="button" class="btn btn-danger py-2 px-3" id="btnConfirmarEliminarRecorridoModal" style="border-radius:10px; font-weight:600; font-size:0.9rem; background: #ef4444; border-color: #ef4444;">Eliminar Recorrido</button>
                </div>
            </div>
        </div>
    </div>
