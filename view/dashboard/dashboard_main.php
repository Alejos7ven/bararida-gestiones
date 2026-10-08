
<div class="row g-4 mb-4">
    <!-- Fila Inicial: Accesos Directos y Citas del Día -->
    <div class="col-12">
        <div class="dashboard-map-card shadow-sm p-4" data-user-rol="<?php echo htmlspecialchars($userRol); ?>" id="quickAccessCard" style="border-left: 5px solid var(--park-primary); border-radius: 16px;">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-3">
                <div>
                    <h3 style="font-size: 1.35rem; font-family: 'Outfit'; font-weight: 700; color: var(--park-dark); margin: 0;">
                        👋 ¡Hola, <?php echo htmlspecialchars($_SESSION['personal']['nombre'] ?? ($userName ?? 'Usuario')); ?>!
                    </h3>
                    <p class="text-muted mb-0" style="font-size: 0.88rem;">
                        Panel de accesos rápidos para rol de <strong><?php echo htmlspecialchars($userRolEtiqueta ?? (function_exists('obtenerEtiquetaRol') ? obtenerEtiquetaRol($userRol) : ucfirst($userRol))); ?></strong>.
                    </p>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <?php 
                    $tienePermisoCitas = !empty($userPermissions['citas']); 
                    if ($tienePermisoCitas): 
                    ?>
                        <button class="btn-premium-primary" onclick="window.location.href='index.php?view=citas&agendar=1'">
                            <svg viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                            Agendar Cita
                        </button>
                        <button class="btn-premium-secondary" onclick="window.location.href='index.php?view=citas'">
                            <svg viewBox="0 0 24 24"><path d="M19 4H5c-1.11 0-2 .9-2 2v12c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 14H5V8h14v10z"/></svg>
                            Gestionar Citas
                        </button>
                    <?php else: ?>
                        <button class="btn-premium-primary" onclick="document.getElementById('seccionCitasHoy')?.scrollIntoView({ behavior: 'smooth' });">
                            <svg viewBox="0 0 24 24"><path d="M19 4H5c-1.11 0-2 .9-2 2v12c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 14H5V8h14v10z"/></svg>
                            Ver Mis Citas Asignadas
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <hr class="my-3" style="border-color: #e2e8f0;">

            <!-- Citas del Día de Hoy -->
            <div class="citas-hoy-section" id="seccionCitasHoy">
                <h4 style="font-size: 1.05rem; font-family: 'Outfit'; font-weight: 700; color: #334155; display: flex; align-items: center; gap: 8px; margin-bottom: 12px;">
                    <span style="font-size: 1.25rem;">📅</span> Visitas Guiadas Programadas para Hoy
                </h4>
                
                <!-- Loader local para citas de hoy -->
                <div id="loaderCitasHoy" class="text-center py-4">
                    <div class="spinner-border text-success spinner-border-sm" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                    <p class="text-muted mt-2 mb-0" style="font-size: 0.8rem;">Obteniendo la agenda del día...</p>
                </div>

                <!-- Tabla de visitas de hoy -->
                <div id="citasHoyWrapper" style="display: none;">
                    <div class="table-responsive table-responsive-citas-hoy" style="border: 1px solid #e2e8f0; border-radius: 12px; overflow-x: auto; -webkit-overflow-scrolling: touch; touch-action: pan-y pan-x;">
                        <table class="table align-middle mb-0 text-center table-hover" style="font-size: 0.85rem; min-width: 820px; touch-action: pan-y pan-x;">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 10%;">ID Cita</th>
                                    <th style="width: 10%;">Hora</th>
                                    <th style="width: 22%;">Institución</th>
                                    <th style="width: 14%;">Recorrido</th>
                                    <th style="width: 10%;">Visitantes</th>
                                    <th style="width: 14%;">Guía(s)</th>
                                    <th style="width: 10%;">Estado</th>
                                    <th style="width: 10%;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="tablaCitasHoyBody">
                                <!-- Se poblará vía AJAX -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- WIDGET PREMIUM: MAPA INTERACTIVO Y CALENDARIO -->
<div class="row mt-4 g-4">
    <!-- Columna Izquierda: Mapa con Recorridos -->
    <div class="col-12 <?php echo $tienePermisoCitas ? 'col-lg-6' : 'col-12'; ?>">
        <div class="dashboard-map-card shadow-sm">
            <div class="dashboard-map-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h3 style="font-size: 1.15rem; font-family: 'Outfit'; font-weight: 700; color: var(--park-dark);">
                    <svg viewBox="0 0 24 24" style="width: 24px; height: 24px; fill: var(--park-primary); margin-right: 6px;"><path d="M20.5 3l-.16.03L15 5.1 9 3 3.36 4.9c-.21.07-.36.25-.36.48V20.5c0 .28.22.5.5.5l.16-.03L9 18.9l6 2.1 5.64-1.9c.21-.07.36-.25.36-.48V3.5c0-.28-.22-.5-.5-.5zM15 19l-6-2.11V5l6 2.11V19z"/></svg>
                    Itinerarios y Zonas
                </h3>
                <div class="tour-selector-box">
                    <label for="selectRecorridoDashboard" style="font-size: 0.72rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 2px;">Visualizar Recorrido</label>
                    <select id="selectRecorridoDashboard" class="form-select tour-select-custom" style="min-width: 200px; padding: 4px 8px; font-size: 0.85rem;">
                        <option value="">Cargando recorridos...</option>
                    </select>
                </div>
            </div>
            
            <!-- Mapa responsivo con overlay SVG -->
            <div class="map-wrapper shadow-sm mt-3">
                <img src="view/assets/img/map.jpg" alt="Mapa del Parque Zoológico Bararida" id="mapaParque" class="map-image">
                <svg id="mapSvgOverlay" class="map-svg-overlay" viewBox="0 0 100 100" preserveAspectRatio="none"></svg>
                <div id="mapPinsContainer"></div>
            </div>
            
            <!-- Timeline de paradas (Secuencia del recorrido) -->
            <div id="dashboardTimelineWrapper" class="active-tour-indicator-list mt-3" style="display: none; animation: slideDownFade 0.3s ease; padding: 12px; border-radius: 10px;">
                <h5 style="font-size: 0.88rem; font-weight: 700;">
                    <span>Itinerario Activo:</span> <strong id="nombreRecorridoActivoSpan" class="text-success"></strong>
                </h5>
                <div id="timelineDashboardSteps" class="tour-steps-timeline mt-2">
                    <!-- Se poblará vía JS -->
                </div>
            </div>
        </div>
    </div>
    
    <?php if ($tienePermisoCitas): ?>
    <!-- Columna Derecha: Calendario Premium de Citas Próximas (FullCalendar) -->
    <div class="col-12 col-lg-6">
        <div class="dashboard-map-card shadow-sm d-flex flex-column">
            <div class="dashboard-map-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h3 style="font-size: 1.15rem; font-family: 'Outfit'; font-weight: 700; color: var(--park-dark); margin: 0;">
                    <svg viewBox="0 0 24 24" style="width: 24px; height: 24px; fill: var(--park-primary); margin-right: 6px;"><path d="M19 4H18V2H16V4H8V2H6V4H5C3.89 4 3.01 4.9 3.01 6L3 20C3 21.1 3.89 22 5 22H19C20.1 22 21 21.1 21 20V6C21 4.9 20.1 4 19 4ZM19 20H5V10H19V20ZM9 14H7V12H9V14ZM13 14H11V12H13V14ZM17 14H15V12H15v2h2zm-8 4H7v-2h2v2zm4 0h-11v-2h2v2zm4 0h-15v-2h2v2z"/></svg>
                    Cronograma de Citas
                </h3>
            </div>
            
            <div id="dashboardFullCalendar" class="mt-3 flex-grow-1">
                <!-- Se inicializa vía FullCalendar -->
            </div>

            <div id="dashboardCapacityWarnings" class="mt-3" style="display: none;">
                <!-- Panel de Alertas de Capacidad de la semana actual -->
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- PANEL DE ESTADÍSTICAS (VISITANTES POR DÍA CON GRÁFICA) -->
<div class="row mt-4">
    <div class="col-12">
        <div class="dashboard-map-card shadow-sm">
            <div class="dashboard-map-header mb-3">
                <h3 style="font-size: 1.15rem; font-family: 'Outfit'; font-weight: 700; color: var(--park-dark);">
                    <svg viewBox="0 0 24 24" style="width: 24px; height: 24px; fill: var(--park-primary); margin-right: 6px;"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/></svg>
                    Estadísticas de Afluencia de Visitantes
                </h3>
            </div>
            <div class="chart-container" style="position: relative; height:320px; width:100%;">
                <canvas id="chartVisitantes"></canvas>
                <div id="chartNoDataPlaceholder" style="display: none; height:100%; width:100%; align-items:center; justify-content:center; flex-direction:column; color:#64748b; text-align: center;">
                    <svg viewBox="0 0 24 24" style="width:48px; height:48px; fill:#cbd5e1; margin-bottom:12px;"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V5h14v14zM7 10h2v7H7zm4-3h2v10h-2zm4 6h2v4h-2z"/></svg>
                    <p style="font-family:'Outfit'; font-weight:600; font-size:0.95rem; margin:0;">No hay datos de visitantes registrados aún</p>
                    <p style="font-size:0.8rem; margin:4px 0 0 0;">Los datos aparecerán una vez que se importen visitantes a las citas agendadas.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: DETALLE DE BLOQUE HORARIO (MÚLTIPLES CITAS) -->
<div class="modal fade modal-premium" id="modalDetalleBloqueHorario" tabindex="-1" aria-labelledby="modalDetalleBloqueHorarioLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 16px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.15);">
            <div class="modal-header" style="background: var(--park-primary); color: #ffffff; padding: 16px 20px;">
                <h5 class="modal-title text-white" id="modalDetalleBloqueHorarioLabel" style="font-family:'Outfit'; font-weight:600; margin:0; color:#ffffff !important;">Citas en Bloque Horario</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body" style="padding: 20px; max-height: 400px; overflow-y: auto;">
                <div id="listaCitasBloqueContainer">
                    <!-- Se rellenará dinámicamente con JS -->
                </div>
            </div>
            <div class="modal-footer" style="border-top: 1px solid #e2e8f0; padding: 12px 20px;">
                <button type="button" class="btn btn-secondary btn-sm px-4 py-2" data-bs-dismiss="modal" style="border-radius: 8px; font-weight: 600;">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: ASIGNAR GUÍA DIRECTAMENTE DESDE EL DASHBOARD -->
<div class="modal fade modal-premium" id="modalAsignarGuiaDashboard" tabindex="-1" aria-labelledby="modalAsignarGuiaDashboardLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 16px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.15);">
            <div class="modal-header" style="background: var(--park-primary); color: #ffffff; padding: 16px 20px;">
                <h5 class="modal-title text-white" id="modalAsignarGuiaDashboardLabel" style="font-family:'Outfit'; font-weight:600; margin:0; color:#ffffff !important;">Asignar Guías de Campo</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="formAsignarGuiaDashboard">
                <input type="hidden" name="cita_id" id="assign_guia_cita_id">
                <div class="modal-body" style="padding: 20px;">
                    <!-- Listado de asignados actualmente -->
                    <div class="mb-3">
                        <label class="form-label" style="font-weight:600; font-size:0.88rem; color:var(--park-dark);">Guías Asignados Actualmente:</label>
                        <div id="guiasAsignadosActualesDashboard" class="d-flex flex-column gap-2 p-2.5 border rounded" style="background-color:#f8f9fa; min-height:40px; font-size:0.82rem;">
                            <!-- Dinámico -->
                        </div>
                    </div>

                    <hr class="my-3">

                    <p class="text-muted" style="font-size:0.85rem; line-height:1.4;">Asigne un nuevo guía de campo para acompañar a la institución:</p>
                    <div class="mb-3">
                        <label for="selectGuiaDashboard" class="form-label" style="font-weight:600; font-size:0.88rem;">Seleccione el Guía:</label>
                        <select class="form-select" id="selectGuiaDashboard" name="personal_id" required>
                            <option value="">Cargando guías disponibles...</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="rolGuiaDashboard" class="form-label" style="font-weight:600; font-size:0.88rem;">Rol del Guía:</label>
                        <select class="form-select" id="rolGuiaDashboard" name="rol_guia">
                            <option value="principal">Guía Principal</option>
                            <option value="apoyo">Guía de Apoyo</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid #e2e8f0; padding: 12px 20px;">
                    <button type="button" class="btn btn-secondary btn-sm px-3" data-bs-dismiss="modal" style="border-radius: 8px;">Cerrar</button>
                    <button type="submit" class="btn btn-success btn-sm px-4" style="border-radius: 8px; background-color: var(--park-primary); border-color: var(--park-primary);">Asignar Guía</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: ASIGNAR RECORRIDO A LA VISITA DESDE EL DASHBOARD -->
<div class="modal fade modal-premium" id="modalAsignarRecorridoDashboard" tabindex="-1" aria-labelledby="modalAsignarRecorridoDashboardLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 16px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.15);">
            <div class="modal-header" style="background: var(--park-primary); color: #ffffff; padding: 16px 20px;">
                <h5 class="modal-title text-white" id="modalAsignarRecorridoDashboardLabel" style="font-family:'Outfit'; font-weight:600; margin:0; color:#ffffff !important;">
                    <svg viewBox="0 0 24 24" style="width:20px;height:20px;fill:currentColor;margin-right:6px;vertical-align:text-bottom;"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
                    Asignar Recorrido a la Visita
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="formAsignarRecorridoDashboard">
                <input type="hidden" name="cita_id" id="modal_recorrido_cita_id">
                <div class="modal-body" style="padding: 20px;">
                    <!-- Información de la Cita -->
                    <div class="p-3 mb-3 rounded border bg-light">
                        <div class="text-muted text-uppercase" style="font-size: 0.72rem; font-weight: 700; letter-spacing: 0.5px;">Institución / Visita:</div>
                        <strong id="modalRecorridoInstitucionText" style="font-size: 0.95rem; color: var(--park-dark);"></strong>
                    </div>

                    <div class="mb-3">
                        <label for="selectRecorridoModal" class="form-label" style="font-weight:600; font-size:0.88rem; color:var(--park-dark);">
                            Seleccione el Recorrido o Itinerario:
                        </label>
                        <select class="form-select" id="selectRecorridoModal" name="recorrido_id" required>
                            <option value="">Cargando recorridos disponibles...</option>
                        </select>
                        <div class="form-text" style="font-size: 0.75rem;">
                            Al cambiar de recorrido se actualizará en tiempo real la ruta en el módulo interactivo del mapa.
                        </div>
                    </div>

                    <!-- Caja de Previsualización Dinámica de las Paradas -->
                    <div id="modalRecorridoPreviewBox" class="p-3 border rounded shadow-xs" style="background-color: #f8fafc; display: none;">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted" style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">Detalles del Itinerario:</span>
                            <span class="badge bg-success" id="previewRecorridoParadasCount" style="font-size: 0.72rem;"></span>
                        </div>
                        <div id="previewRecorridoStepsList" class="d-flex flex-column gap-1.5" style="font-size: 0.8rem; max-height: 130px; overflow-y: auto;">
                            <!-- Dinámico -->
                        </div>
                        <div class="mt-2.5 pt-2 border-top d-flex justify-content-end">
                            <button type="button" id="btnPreviewVerEnMapa" class="btn btn-sm btn-outline-success py-1 px-2.5 d-inline-flex align-items-center gap-1" style="font-size: 0.76rem; border-radius: 6px;">
                                <svg viewBox="0 0 24 24" style="width:13px;height:13px;fill:currentColor;"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
                                Ver trazado en el mapa abajo
                            </button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid #e2e8f0; padding: 12px 20px;">
                    <button type="button" class="btn btn-secondary btn-sm px-3" data-bs-dismiss="modal" style="border-radius: 8px;">Cerrar</button>
                    <button type="submit" class="btn btn-success btn-sm px-4" style="border-radius: 8px; background-color: var(--park-primary); border-color: var(--park-primary); font-weight:600;">
                        Guardar Recorrido
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
