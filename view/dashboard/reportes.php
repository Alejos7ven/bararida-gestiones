
<div class="panel-container animate__animated animate__fadeIn">
    <!-- Encabezado del Módulo -->
    <div class="panel-header-section mb-4">
        <div class="panel-header-title">
            <div class="d-flex align-items-center gap-2">
                <span class="badge-module-icon">
                    <svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/></svg>
                </span>
                <div>
                    <h3 class="m-0">Informes y Estadísticas Generales</h3>
                    <p class="text-muted m-0" style="font-size: 0.9rem;">
                        Análisis métrico, cálculo de afluencia, control de zonas y exportación de informes institucionales
                    </p>
                </div>
            </div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button id="btnDescargarPdf" class="btn-premium-primary" title="Exportar informe a documento PDF oficial">
                <svg viewBox="0 0 24 24" style="width: 18px; height: 18px; fill: currentColor; margin-right: 6px;"><path d="M20 2H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-8.5 7.5c0 .83-.67 1.5-1.5 1.5H9v2H7.5V7H10c.83 0 1.5.67 1.5 1.5v1zm5 2c0 .83-.67 1.5-1.5 1.5h-2.5V7H15c.83 0 1.5.67 1.5 1.5v3zm4-3H19v1h1.5V11H19v2h-1.5V7h3v1.5zM9 9.5h1v-1H9v1zm5 2h1v-3h-1v3zM4 6H2v14c0 1.1.9 2 2 2h14v-2H4V6z"/></svg>
                Descargar Informe PDF
            </button>
        </div>
    </div>

    <!-- Barra de Filtros Interactiva -->
    <div class="card card-reportes-filtros mb-4 shadow-sm">
        <div class="card-body p-4">
            <form id="formFiltroReportes" novalidate>
                <div class="row g-3 align-items-end">
                    <!-- Sección de Reporte -->
                    <div class="col-lg-3 col-md-6">
                        <label for="filtroSeccion" class="form-label-filter">
                            <span class="label-icon">📊</span> Tipo de Informe
                        </label>
                        <select id="filtroSeccion" name="seccion" class="form-select form-select-custom">
                            <option value="general" selected>Resumen Ejecutivo General</option>
                            <option value="citas">Citas Realizadas</option>
                            <option value="zonas">Visitas por Zona</option>
                            <option value="exoneraciones">Exoneraciones Otorgadas</option>
                            <option value="visitantes">Afluencia de Visitantes</option>
                            <option value="instituciones">Instituciones Atendidas</option>
                        </select>
                    </div>

                    <!-- Modalidad de Período -->
                    <div class="col-lg-2 col-md-6">
                        <label for="filtroTiempo" class="form-label-filter">
                            <span class="label-icon">⏱️</span> Modalidad de Tiempo
                        </label>
                        <select id="filtroTiempo" name="filtro_tiempo" class="form-select form-select-custom">
                            <option value="mes" selected>Por Mes</option>
                            <option value="dia">Día Específico</option>
                            <option value="anio">Año Completo</option>
                        </select>
                    </div>

                    <!-- Contenedor Dinámico de Selector de Fecha -->
                    <!-- 1. Por Día -->
                    <div class="col-lg-3 col-md-6 contenedor-filtro-tiempo d-none" id="colFiltroDia">
                        <label for="fechaDia" class="form-label-filter">
                            <span class="label-icon">📅</span> Seleccionar Día
                        </label>
                        <div class="input-group">
                            <input type="date" id="fechaDia" name="fecha_dia" class="form-control form-control-custom" value="<?php echo date('Y-m-d'); ?>">
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="btnDiaHoy" title="Fijar día de hoy">Hoy</button>
                        </div>
                    </div>

                    <!-- 2. Por Mes (Mes y Año separados) -->
                    <div class="col-lg-4 col-md-6 contenedor-filtro-tiempo" id="colFiltroMes">
                        <div class="row g-2">
                            <div class="col-7">
                                <label for="filtroMesMes" class="form-label-filter">
                                    <span class="label-icon">🗓️</span> Mes
                                </label>
                                <select id="filtroMesMes" name="mes" class="form-select form-select-custom">
                                    <?php
                                    $mesesNombres = [
                                        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
                                        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
                                        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
                                    ];
                                    $currentM = (int)date('n');
                                    foreach ($mesesNombres as $num => $nom): ?>
                                        <option value="<?php echo $num; ?>" <?php echo ($num === $currentM) ? 'selected' : ''; ?>>
                                            <?php echo $nom; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-5">
                                <label for="filtroMesAnio" class="form-label-filter">
                                    <span class="label-icon">📆</span> Año
                                </label>
                                <select id="filtroMesAnio" name="anio" class="form-select form-select-custom">
                                    <?php 
                                    $currentYear = (int)date('Y');
                                    for ($y = $currentYear + 1; $y >= $currentYear - 6; $y--): ?>
                                        <option value="<?php echo $y; ?>" <?php echo ($y === $currentYear) ? 'selected' : ''; ?>>
                                            <?php echo $y; ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- 3. Por Año -->
                    <div class="col-lg-3 col-md-6 contenedor-filtro-tiempo d-none" id="colFiltroAnio">
                        <label for="fechaAnio" class="form-label-filter">
                            <span class="label-icon">📆</span> Seleccionar Año
                        </label>
                        <select id="fechaAnio" name="fecha_anio" class="form-select form-select-custom">
                            <?php 
                            $currentYear = (int)date('Y');
                            for ($y = $currentYear + 1; $y >= $currentYear - 6; $y--): ?>
                                <option value="<?php echo $y; ?>" <?php echo ($y === $currentYear) ? 'selected' : ''; ?>>
                                    <?php echo $y; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <!-- Filtro Secundario: Estatus Citas (solo cuando sección sea citas) -->
                    <div class="col-lg-2 col-md-6 d-none" id="colFiltroEstatus">
                        <label for="filtroEstatus" class="form-label-filter">
                            <span class="label-icon">🏷️</span> Estatus
                        </label>
                        <select id="filtroEstatus" name="estatus" class="form-select form-select-custom">
                            <option value="todos" selected>Todos</option>
                            <option value="aprobado">Aprobadas</option>
                            <option value="completado">Completadas</option>
                            <option value="pendiente">Pendientes</option>
                            <option value="cancelado">Canceladas</option>
                        </select>
                    </div>

                    <!-- Filtro: Tipo de Cita -->
                    <div class="col-lg-2 col-md-6" id="colFiltroTipoCita">
                        <label for="filtroTipoCita" class="form-label-filter">
                            <span class="label-icon">📌</span> Tipo de Cita
                        </label>
                        <select id="filtroTipoCita" name="tipo_cita" class="form-select form-select-custom">
                            <option value="todos" selected>Todos los Tipos</option>
                            <option value="campamento nocturno">Campamento Nocturno</option>
                            <option value="corporativo">Corporativo</option>
                            <option value="evento">Evento</option>
                            <option value="institucional">Institucional</option>
                            <option value="otro">Otro</option>
                            <option value="particular">Particular</option>
                        </select>
                    </div>

                    <!-- Botón de Filtrado -->
                    <div class="col-lg-auto col-md-6 flex-grow-1">
                        <button type="submit" id="btnConsultarReporte" class="btn-filter-apply w-100">
                            <svg viewBox="0 0 24 24" style="width: 18px; height: 18px; fill: currentColor;"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                            Generar
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Indicador de Período Consultador y Estado -->
    <div class="periodo-badge-container d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div class="d-flex align-items-center gap-2">
            <span class="badge-status-dot"></span>
            <span class="periodo-label text-muted">Período analizado:</span>
            <strong id="badgePeriodoTexto" class="periodo-valor-destacado">Cargando...</strong>
        </div>
        <div>
            <span id="badgeFechaExtremos" class="badge bg-light text-dark border px-3 py-2" style="font-size: 0.85rem; font-weight: 500;">
                01/09/2026 al 30/09/2026
            </span>
        </div>
    </div>

    <!-- Tarjetas de KPIs Generales y Específicos -->
    <div class="row g-3 mb-4 row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-xl-5" id="kpisReportesContainer">
        <!-- 1. Citas -->
        <div class="col">
            <div class="card-kpi-reporte shadow-sm">
                <div class="kpi-icon-wrap kpi-citas">
                    <svg viewBox="0 0 24 24"><path d="M19 4H18V2H16V4H8V2H6V4H5C3.89 4 3.01 4.9 3.01 6L3 20C3 21.1 3.89 22 5 22H19C20.1 22 21 21.1 21 20V6C21 4.9 20.1 4 19 4ZM19 20H5V10H19V20Z"/></svg>
                </div>
                <div class="kpi-data">
                    <span class="kpi-title" title="Citas registradas">CITAS</span>
                    <h3 class="kpi-number" id="kpiTotalCitas">0</h3>
                    <span class="kpi-subtext" id="kpiCitasSubtext" title="Estado de citas">0 aprobadas</span>
                </div>
            </div>
        </div>

        <!-- 2. Visitantes -->
        <div class="col">
            <div class="card-kpi-reporte shadow-sm">
                <div class="kpi-icon-wrap kpi-visitantes">
                    <svg viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5s-3 1.34-3 3 1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
                </div>
                <div class="kpi-data">
                    <span class="kpi-title" title="Total visitantes">VISITANTES</span>
                    <h3 class="kpi-number" id="kpiTotalVisitantes">0</h3>
                    <span class="kpi-subtext" id="kpiVisitantesSubtext" title="Promedio por cita">0 prom./cita</span>
                </div>
            </div>
        </div>

        <!-- 3. Zonas -->
        <div class="col">
            <div class="card-kpi-reporte shadow-sm">
                <div class="kpi-icon-wrap kpi-zonas">
                    <svg viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
                </div>
                <div class="kpi-data">
                    <span class="kpi-title" title="Zonas recorridas">ZONAS</span>
                    <h3 class="kpi-number" id="kpiTotalZonas">0</h3>
                    <span class="kpi-subtext" id="kpiZonasSubtext" title="Zonas visitadas">0 recorridas</span>
                </div>
            </div>
        </div>

        <!-- 4. Exoneraciones -->
        <div class="col">
            <div class="card-kpi-reporte shadow-sm">
                <div class="kpi-icon-wrap kpi-exoneraciones">
                    <svg viewBox="0 0 24 24"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm-2 16l-4-4 1.41-1.41L10 14.17l6.59-6.59L18 9l-8 8z"/></svg>
                </div>
                <div class="kpi-data">
                    <span class="kpi-title" title="Beneficiarios de exoneraciones">EXONERACIONES</span>
                    <h3 class="kpi-number" id="kpiTotalExoneraciones">0</h3>
                    <span class="kpi-subtext" id="kpiExoneracionesSubtext" title="Instituciones exoneradas">0 beneficiarios</span>
                </div>
            </div>
        </div>

        <!-- 5. Instituciones -->
        <div class="col">
            <div class="card-kpi-reporte shadow-sm">
                <div class="kpi-icon-wrap kpi-instituciones">
                    <svg viewBox="0 0 24 24"><path d="M12 3L1 9V11H3V20H5V11H19V20H21V11H23V9L12 3ZM12 7.5C12.83 7.5 13.5 8.17 13.5 9C13.5 9.83 12.83 10.5 12 10.5C11.17 10.5 10.5 9.83 10.5 9C10.5 8.17 11.17 7.5 12 7.5ZM7 20V13H9V20H7ZM15 20V13H17V20H15Z"/></svg>
                </div>
                <div class="kpi-data">
                    <span class="kpi-title" title="Instituciones atendidas">INSTITUCIONES</span>
                    <h3 class="kpi-number" id="kpiTotalInstituciones">0</h3>
                    <span class="kpi-subtext" id="kpiInstitucionesSubtext" title="Instituciones en el período">en el período</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Fila de Gráficos Analíticos -->
    <div class="row g-4 mb-4" id="graficosRowReporte">
        <div class="col-lg-7 col-12">
            <div class="card card-grafico-reporte shadow-sm">
                <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                    <h5 class="m-0 font-outfit fw-bold text-dark" id="tituloGraficoPrincipal">
                        Distribución de Métricas
                    </h5>
                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1" style="font-size: 0.75rem;">
                        Analítica Visual
                    </span>
                </div>
                <div class="card-body">
                    <div style="position: relative; height: 290px; width: 100%;">
                        <canvas id="chartPrincipalReporte"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5 col-12">
            <div class="card card-grafico-reporte shadow-sm">
                <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                    <h5 class="m-0 font-outfit fw-bold text-dark" id="tituloGraficoSecundario">
                        Proporción y Estatus
                    </h5>
                    <span class="badge bg-light text-muted border px-2 py-1" style="font-size: 0.75rem;">
                        Participación %
                    </span>
                </div>
                <div class="card-body d-flex align-items-center justify-content-center">
                    <div style="position: relative; height: 270px; width: 100%; max-width: 320px;">
                        <canvas id="chartSecundarioReporte"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Spinner Loader de Consulta -->
    <div id="loaderReporte" class="text-center py-5 d-none">
        <div class="spinner-border text-success" role="status" style="width: 3rem; height: 3rem;">
            <span class="visually-hidden">Generando reporte...</span>
        </div>
        <p class="text-muted mt-2 fw-semibold">Procesando y calculando estadísticas del parque...</p>
    </div>

    <!-- Contenedor Principal de la Tabla de Datos -->
    <div class="card card-tabla-reporte shadow-sm mb-4" id="cardTablaReporte">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2 border-0">
            <div>
                <h5 class="m-0 font-outfit fw-bold text-dark" id="tituloTablaReporte">
                    Detalle de Registros
                </h5>
                <small class="text-muted" id="subtituloTablaReporte">
                    Listado cronológico de datos procesados
                </small>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-light text-dark border px-3 py-2" id="conteoRegistrosBadge">
                    0 registros encontrados
                </span>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 text-nowrap" id="tablaReportes">
                    <thead class="table-light" id="tablaReportesHead">
                        <!-- Generado dinámicamente según la sección -->
                    </thead>
                    <tbody id="tablaReportesBody">
                        <!-- Generado dinámicamente vía AJAX -->
                    </tbody>
                </table>
            </div>

            <!-- Estado Vacío cuando no hay datos -->
            <div id="estadoVacioReporte" class="text-center py-5 d-none">
                <div style="font-size: 3rem; opacity: 0.6;">📋</div>
                <h5 class="fw-bold text-secondary mt-2">No se encontraron registros</h5>
                <p class="text-muted" style="max-width: 420px; margin: 0 auto; font-size: 0.9rem;">
                    No existen citas ni actividades registradas para los criterios y el período de tiempo seleccionado.
                </p>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- PLANTILLA INSTITUCIONAL OCULTA PARA GENERACIÓN DE PDF LIMPIO Y ELEGANTE -->
<!-- ========================================================================= -->
<div id="reporteImpresionWrapper" style="display: none;">
    <div id="reporteImprimible" class="documento-impresion-pdf">
        <!-- Encabezado Institucional -->
        <div class="pdf-header-container">
            <div class="pdf-header-logo">
                <img src="view/assets/img/logo.png" alt="Logo Parque Bararida" style="width: 75px; height: auto;">
            </div>
            <div class="pdf-header-titles">
                <p class="pdf-republica">REPÚBLICA BOLIVARIANA DE VENEZUELA</p>
                <p class="pdf-gobierno">GOBERNACIÓN DEL ESTADO LARA</p>
                <p class="pdf-institucion">PARQUE ZOOLÓGICO Y BOTÁNICO BARARIDA</p>
                <p class="pdf-departamento">SISTEMA DE GESTIÓN Y CONTROL DE CITAS INSTITUCIONALES</p>
            </div>
        </div>

        <div class="pdf-divider"></div>

        <!-- Título del Documento -->
        <div class="pdf-document-title-box">
            <h2 id="pdfTituloDocumento" class="pdf-title">INFORME EJECUTIVO Y ESTADÍSTICO</h2>
            <div class="pdf-subtitle-tag" id="pdfPeriodoTag">PERÍODO: SEPTIEMBRE 2026</div>
        </div>

        <!-- Ficha Técnica de Emisión -->
        <table class="pdf-metadata-table">
            <tr>
                <td style="width: 25%;"><strong>Fecha de Emisión:</strong></td>
                <td style="width: 25%;" id="pdfFechaEmision"><?php echo date('d/m/Y h:i A'); ?></td>
                <td style="width: 25%;"><strong>Generado por:</strong></td>
                <td style="width: 25%;"><?php echo htmlspecialchars($_SESSION['personal']['nombre_completo'] ?? $_SESSION['personal']['nombre']); ?></td>
            </tr>
            <tr>
                <td><strong>Rango Temporal:</strong></td>
                <td id="pdfRangoFechas">--</td>
                <td><strong>Rol en Sistema:</strong></td>
                <td><?php echo ucfirst($_SESSION['personal']['rol'] ?? 'Administrativo'); ?></td>
            </tr>
        </table>

        <!-- Resumen de Métricas / KPIs del Período -->
        <div class="pdf-section-title">RESUMEN CONSOLIDADO DE INDICADORES (KPIS)</div>
        <table class="pdf-kpis-table">
            <thead>
                <tr>
                    <th>Total Citas</th>
                    <th>Visitantes Recibidos</th>
                    <th>Zonas Visitadas</th>
                    <th>Exoneraciones</th>
                    <th>Instituciones Atendidas</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td id="pdfKpiCitas">0</td>
                    <td id="pdfKpiVisitantes">0</td>
                    <td id="pdfKpiZonas">0</td>
                    <td id="pdfKpiExoneraciones">0</td>
                    <td id="pdfKpiInstituciones">0</td>
                </tr>
            </tbody>
        </table>

        <!-- Tabla de Datos Detallada -->
        <div class="pdf-section-title" id="pdfTituloSeccionDetalle">DETALLE DE REGISTROS</div>
        <table class="pdf-data-table" id="pdfTablaDatos">
            <thead id="pdfTablaHead">
                <!-- Se llena dinámicamente -->
            </thead>
            <tbody id="pdfTablaBody">
                <!-- Se llena dinámicamente -->
            </tbody>
        </table>

        <!-- Bloque de Firmas y Validación -->
        <div class="pdf-signatures-box">
            <div class="pdf-signature-item">
                <div class="pdf-signature-line"></div>
                <p class="pdf-sig-name"><?php echo htmlspecialchars($_SESSION['personal']['nombre_completo'] ?? $_SESSION['personal']['nombre']); ?></p>
                <p class="pdf-sig-role">Elaborado por (<?php echo ucfirst($_SESSION['personal']['rol']); ?>)</p>
            </div>
            <div class="pdf-signature-item">
                <div class="pdf-signature-line"></div>
                <p class="pdf-sig-name">Dirección General</p>
                <p class="pdf-sig-role">Parque Zoológico y Botánico Bararida</p>
            </div>
            <div class="pdf-signature-item">
                <div class="pdf-signature-line"></div>
                <p class="pdf-sig-name">Sello Institucional</p>
                <p class="pdf-sig-role">Gobernación del Estado Lara</p>
            </div>
        </div>

        <div class="pdf-footer-note">
            Este documento ha sido emitido de manera automatizada a través del Sistema Integrado de Gestión del Parque Zoológico y Botánico Bararida. Posee validez de carácter informativo y administrativo interno.
        </div>
    </div>
</div>
