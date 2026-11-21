/**
 * Módulo de Reportes y Estadísticas
 * Parque Zoológico y Botánico Bararida
 * Manejo de consultas AJAX, renderizado dinámico, gráficos con Chart.js y exportación a PDF
 */

$(document).ready(function() {
    const $alertFeedback = $('#alertFeedbackGlobal');
    let chartPrincipalInstance = null;
    let chartSecundarioInstance = null;
    let ultimoReporteData = null;

    // Paleta de colores oficial del parque Bararida
    const PARK_COLORS = {
        primary: '#0D9039',
        secondary: '#83D476',
        accent1: '#2563eb',
        accent2: '#d97706',
        accent3: '#7c3aed',
        accent4: '#0891b2',
        danger: '#ef4444',
        warning: '#f59e0b',
        muted: '#94a3b8'
    };

    // =========================================================================
    // CONTROLADORES DE EVENTOS DE FILTROS
    // =========================================================================

    // Cambio en modalidad temporal (día, mes, año)
    $('#filtroTiempo').on('change', function() {
        const modo = $(this).val();
        $('.contenedor-filtro-tiempo').addClass('d-none');

        if (modo === 'dia') {
            $('#colFiltroDia').removeClass('d-none');
        } else if (modo === 'mes') {
            $('#colFiltroMes').removeClass('d-none');
        } else if (modo === 'anio') {
            $('#colFiltroAnio').removeClass('d-none');
        }
        ejecutarConsultaReporte();
    });

    // Cambio en tipo de informe
    $('#filtroSeccion').on('change', function() {
        const seccion = $(this).val();
        if (seccion === 'citas') {
            $('#colFiltroEstatus').removeClass('d-none');
        } else {
            $('#colFiltroEstatus').addClass('d-none');
        }
        ejecutarConsultaReporte();
    });

    // Cambio en estatus de cita
    $('#filtroEstatus').on('change', function() {
        ejecutarConsultaReporte();
    });

    // Cambio en tipo de cita
    $('#filtroTipoCita').on('change', function() {
        ejecutarConsultaReporte();
    });

    // Cambio en mes o año separado
    $('#filtroMesMes, #filtroMesAnio').on('change', function() {
        ejecutarConsultaReporte();
    });

    // Cambio en año completo
    $('#fechaAnio').on('change', function() {
        ejecutarConsultaReporte();
    });

    // Botón para fijar día de hoy
    $('#btnDiaHoy').on('click', function() {
        const hoy = new Date().toISOString().split('T')[0];
        $('#fechaDia').val(hoy);
        ejecutarConsultaReporte();
    });

    // Envío del formulario de filtros
    $('#formFiltroReportes').on('submit', function(e) {
        e.preventDefault();
        ejecutarConsultaReporte();
    });

    // =========================================================================
    // CONSULTA PRINCIPAL AJAX
    // =========================================================================
    function ejecutarConsultaReporte() {
        const seccion = $('#filtroSeccion').val() || 'general';
        const filtroTiempo = $('#filtroTiempo').val() || 'mes';
        const estatus = $('#filtroEstatus').val() || 'todos';
        const tipoCita = $('#filtroTipoCita').val() || 'todos';

        let params = {
            seccion: seccion,
            filtro_tiempo: filtroTiempo,
            estatus: estatus,
            tipo_cita: tipoCita
        };

        if (filtroTiempo === 'dia') {
            params.fecha_dia = $('#fechaDia').val();
        } else if (filtroTiempo === 'mes') {
            params.mes = $('#filtroMesMes').val();
            params.anio = $('#filtroMesAnio').val();
        } else if (filtroTiempo === 'anio') {
            params.fecha_anio = $('#fechaAnio').val();
        }

        // Mostrar loader
        $('#loaderReporte').removeClass('d-none');
        $('#cardTablaReporte').addClass('opacity-50');
        $('#btnConsultarReporte').prop('disabled', true);

        $.ajax({
            url: 'controllers/ReporteController.php?action=consultar',
            method: 'GET',
            data: params,
            dataType: 'json',
            success: function(response) {
                $('#loaderReporte').addClass('d-none');
                $('#cardTablaReporte').removeClass('opacity-50');
                $('#btnConsultarReporte').prop('disabled', false);

                if (response.status === 'success') {
                    ultimoReporteData = response;
                    actualizarIndicadoresPeriodo(response.periodo);
                    actualizarTarjetasKPI(response.kpis_globales);
                    actualizarGraficos(response.seccion, response.reporte, response.kpis_globales);
                    renderizarTablaDatos(response.seccion, response.reporte);
                    prepararPlantillaImpresion(response);
                } else {
                    mostrarAlertaFeedback(response.message || 'Error al obtener reporte', 'danger');
                }
            },
            error: function(xhr) {
                $('#loaderReporte').addClass('d-none');
                $('#cardTablaReporte').removeClass('opacity-50');
                $('#btnConsultarReporte').prop('disabled', false);

                let msg = 'Ocurrió un error al procesar el reporte.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                mostrarAlertaFeedback(msg, 'danger');
            }
        });
    }

    // Exponer globalmente para jQuery Validate
    window.ejecutarConsultaReporte = ejecutarConsultaReporte;

    // =========================================================================
    // ACTUALIZACIÓN DE INTERFAZ: PERÍODO Y KPIS
    // =========================================================================
    function actualizarIndicadoresPeriodo(periodo) {
        if (!periodo) return;
        $('#badgePeriodoTexto').text(periodo.etiqueta || 'Período Seleccionado');

        const fechaIniFormatted = formatearFechaVisual(periodo.inicio);
        const fechaFinFormatted = formatearFechaVisual(periodo.fin);

        if (periodo.inicio === periodo.fin) {
            $('#badgeFechaExtremos').text(fechaIniFormatted);
        } else {
            $('#badgeFechaExtremos').text(`${fechaIniFormatted} al ${fechaFinFormatted}`);
        }
    }

    function actualizarTarjetasKPI(kpis) {
        if (!kpis) return;

        // 1. Citas
        $('#kpiTotalCitas').text(kpis.citas.total);
        $('#kpiCitasSubtext')
            .text(`${kpis.citas.aprobadas} aprob. • ${kpis.citas.completadas} compl.`)
            .attr('title', `${kpis.citas.aprobadas} aprobadas, ${kpis.citas.completadas} completadas, ${kpis.citas.pendientes} pendientes`);

        // 2. Visitantes
        $('#kpiTotalVisitantes').text(kpis.visitantes.total);
        $('#kpiVisitantesSubtext')
            .text(`${kpis.visitantes.promedio_por_cita} prom./cita`)
            .attr('title', `Promedio de ${kpis.visitantes.promedio_por_cita} visitantes por cita`);

        // 3. Zonas
        $('#kpiTotalZonas').text(kpis.zonas.total_visitas);
        $('#kpiZonasSubtext')
            .text(`${kpis.zonas.zonas_distintas} recorridas`)
            .attr('title', `${kpis.zonas.total_visitas} visitas en ${kpis.zonas.zonas_distintas} zonas distintas`);

        // 4. Exoneraciones
        $('#kpiTotalExoneraciones').text(kpis.exoneraciones.visitantes);
        $('#kpiExoneracionesSubtext')
            .text(`${kpis.exoneraciones.instituciones} instituciones`)
            .attr('title', `${kpis.exoneraciones.visitantes} personas en ${kpis.exoneraciones.instituciones} instituciones exoneradas`);

        // 5. Instituciones
        $('#kpiTotalInstituciones').text(kpis.instituciones.total);
        $('#kpiInstitucionesSubtext')
            .text(`en el período`)
            .attr('title', `${kpis.instituciones.total} instituciones atendidas`);
    }

    // =========================================================================
    // RENDERIZADO DE GRÁFICOS (CHART.JS)
    // =========================================================================
    function actualizarGraficos(seccion, reporte, kpis) {
        const canvasP = document.getElementById('chartPrincipalReporte');
        const canvasS = document.getElementById('chartSecundarioReporte');
        if (!canvasP || !canvasS) return;

        // Destruir gráficos anteriores para evitar solapamientos
        if (chartPrincipalInstance) chartPrincipalInstance.destroy();
        if (chartSecundarioInstance) chartSecundarioInstance.destroy();

        let labelsP = [], dataP = [], colorsP = [];
        let labelsS = [], dataS = [], colorsS = [];
        let tipoP = 'bar';

        if (seccion === 'general') {
            $('#tituloGraficoPrincipal').text('Afluencia y Métricas del Período');
            $('#tituloGraficoSecundario').text('Estatus de Citas');

            labelsP = ['Citas Registradas', 'Visitantes Recibidos', 'Zonas Recorridas', 'Benef. Exoneración', 'Instituciones'];
            dataP = [
                kpis.citas.total,
                kpis.visitantes.total,
                kpis.zonas.total_visitas,
                kpis.exoneraciones.visitantes,
                kpis.instituciones.total
            ];
            colorsP = [PARK_COLORS.primary, PARK_COLORS.accent1, PARK_COLORS.accent2, PARK_COLORS.accent3, PARK_COLORS.accent4];

            labelsS = ['Aprobadas', 'Completadas', 'Pendientes', 'Canceladas'];
            dataS = [kpis.citas.aprobadas, kpis.citas.completadas, kpis.citas.pendientes, kpis.citas.canceladas];
            colorsS = [PARK_COLORS.primary, PARK_COLORS.accent1, PARK_COLORS.warning, PARK_COLORS.danger];

        } else if (seccion === 'citas') {
            $('#tituloGraficoPrincipal').text('Distribución de Citas por Estatus');
            $('#tituloGraficoSecundario').text('Proporción de Estatus (%)');

            const est = (reporte && reporte.resumen && reporte.resumen.estatus) ? reporte.resumen.estatus : {};
            labelsP = ['Aprobadas', 'Completadas', 'Pendientes', 'Canceladas'];
            dataP = [est.aprobado || 0, est.completado || 0, est.pendiente || 0, est.cancelado || 0];
            colorsP = [PARK_COLORS.primary, PARK_COLORS.accent1, PARK_COLORS.warning, PARK_COLORS.danger];

            labelsS = labelsP;
            dataS = dataP;
            colorsS = colorsP;

        } else if (seccion === 'zonas') {
            $('#tituloGraficoPrincipal').text('Top Zonas Más Visitadas (Frecuencia)');
            $('#tituloGraficoSecundario').text('Participación por Zona');

            const datosZ = (reporte && reporte.datos) ? reporte.datos.slice(0, 7) : [];
            if (datosZ.length > 0) {
                datosZ.forEach(function(z) {
                    labelsP.push(z.zona_nombre);
                    dataP.push(parseInt(z.total_visitas) || 0);
                    colorsP.push(PARK_COLORS.primary);

                    labelsS.push(z.zona_nombre);
                    dataS.push(parseInt(z.total_visitas) || 0);
                });
                colorsS = [PARK_COLORS.primary, PARK_COLORS.secondary, PARK_COLORS.accent1, PARK_COLORS.accent2, PARK_COLORS.accent3, PARK_COLORS.accent4, PARK_COLORS.warning];
            } else {
                labelsP = ['Sin visitas'];
                dataP = [0];
                colorsP = [PARK_COLORS.muted];
                labelsS = ['Sin visitas'];
                dataS = [1];
                colorsS = [PARK_COLORS.muted];
            }

        } else if (seccion === 'exoneraciones') {
            $('#tituloGraficoPrincipal').text('Visitantes Exonerados por Tipo de Gestión');
            $('#tituloGraficoSecundario').text('Gestión Institucional');

            const res = (reporte && reporte.resumen) ? reporte.resumen : {};
            const ges = res.distribucion_gestion || {};
            labelsP = ['Gestión Pública', 'Gestión Privada'];
            dataP = [ges.publica || 0, ges.privada || 0];
            colorsP = [PARK_COLORS.primary, PARK_COLORS.accent3];

            labelsS = labelsP;
            dataS = dataP;
            colorsS = colorsP;

        } else if (seccion === 'visitantes') {
            $('#tituloGraficoPrincipal').text('Distribución de Visitantes por Grupo Etario');
            $('#tituloGraficoSecundario').text('Proporción Grupos Etarios');

            const res = (reporte && reporte.resumen) ? reporte.resumen : {};
            const grupos = res.grupos_etarios || {};
            labelsP = Object.keys(grupos);
            dataP = Object.values(grupos);
            colorsP = [PARK_COLORS.primary, PARK_COLORS.accent1, PARK_COLORS.accent2, PARK_COLORS.accent3, PARK_COLORS.muted];

            labelsS = labelsP;
            dataS = dataP;
            colorsS = colorsP;

        } else if (seccion === 'instituciones') {
            $('#tituloGraficoPrincipal').text('Instituciones Atendidas por Organización');
            $('#tituloGraficoSecundario').text('Distribución por Gestión');

            const res = (reporte && reporte.resumen) ? reporte.resumen : {};
            const orgs = res.distribucion_organizacion || {};
            labelsP = Object.keys(orgs);
            dataP = Object.values(orgs);
            colorsP = [PARK_COLORS.primary, PARK_COLORS.accent1, PARK_COLORS.accent2, PARK_COLORS.accent4];

            const ges = res.distribucion_gestion || {};
            labelsS = ['Pública', 'Privada'];
            dataS = [ges.publica || 0, ges.privada || 0];
            colorsS = [PARK_COLORS.primary, PARK_COLORS.accent3];
        }

        // Gráfico Principal (Barras)
        chartPrincipalInstance = new Chart(canvasP.getContext('2d'), {
            type: tipoP,
            data: {
                labels: labelsP,
                datasets: [{
                    label: 'Registros',
                    data: dataP,
                    backgroundColor: colorsP,
                    borderRadius: 8,
                    maxBarThickness: 45
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0 }
                    }
                }
            }
        });

        // Gráfico Secundario (Doughnut)
        chartSecundarioInstance = new Chart(canvasS.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: labelsS,
                datasets: [{
                    data: dataS,
                    backgroundColor: colorsS,
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { boxWidth: 12, padding: 10, font: { size: 11 } }
                    }
                },
                cutout: '60%'
            }
        });
    }

    // =========================================================================
    // RENDERIZADO DE TABLA DINÁMICA DE DATOS (DataTables Server-Side)
    // =========================================================================
    function renderizarTablaDatos(seccion, reporte) {
        if ($.fn.DataTable.isDataTable('#tablaReportes')) {
            $('#tablaReportes').DataTable().destroy();
        }
        const $head = $('#tablaReportesHead');
        const $body = $('#tablaReportesBody');
        $head.empty();
        $body.empty();

        let datos = [];
        if (seccion === 'general') {
            datos = reporte.citas_recientes || [];
            $('#tituloTablaReporte').text('Citas Realizadas en el Período');
            $('#subtituloTablaReporte').text('Listado cronológico de citas del resumen ejecutivo');
        } else {
            datos = reporte.datos || [];
            $('#tituloTablaReporte').text(obtenerTituloSeccion(seccion));
            $('#subtituloTablaReporte').text(`Detalle analítico para la sección de ${seccion}`);
        }

        const totalRegistros = datos.length;
        $('#conteoRegistrosBadge').text(`${totalRegistros} registro${totalRegistros === 1 ? '' : 's'}`);

        if (totalRegistros === 0) {
            $('#estadoVacioReporte').removeClass('d-none');
            $('#tablaReportes').addClass('d-none');
            return;
        }

        $('#estadoVacioReporte').addClass('d-none');
        $('#tablaReportes').removeClass('d-none');

        let columnsConfig = [];

        if (seccion === 'general' || seccion === 'citas') {
            $head.html(`
                <tr>
                    <th># Cita</th>
                    <th>Fecha y Hora</th>
                    <th>Institución</th>
                    <th>Tipo de Cita</th>
                    <th>Tipo y Gestión</th>
                    <th>Recorrido Asignado</th>
                    <th>Visitantes</th>
                    <th>Estatus</th>
                    <th>Método / Pago</th>
                </tr>
            `);

            columnsConfig = [
                {
                    data: 'id',
                    render: function(data) { return `<strong>#${data}</strong>`; }
                },
                {
                    data: 'fecha_visita',
                    render: function(data, type, c) {
                        return `<div>${formatearFechaVisual(c.fecha_visita)}</div><small class="text-muted">${c.hora_inicio ? c.hora_inicio.substring(0, 5) : '--'}</small>`;
                    }
                },
                {
                    data: 'institucion_nombre',
                    render: function(data, type, c) {
                        return `<div class="fw-bold">${c.institucion_nombre || 'Sin nombre'}</div><small class="text-muted">${c.tipo_documento || 'J'}-${c.institucion_rif || ''}</small>`;
                    }
                },
                {
                    data: 'tipo',
                    render: function(data) {
                        return `<span class="badge bg-light text-success border" style="font-size:0.75rem; text-transform:capitalize; font-weight:600;">${data || 'Institucional'}</span>`;
                    }
                },
                {
                    data: 'tipo_organizacion',
                    render: function(data, type, c) {
                        const gestionBadge = `<span class="badge-gestion-${c.tipo_gestion}">${c.tipo_gestion === 'publica' ? 'Pública' : 'Privada'}</span>`;
                        return `<div>${c.tipo_organizacion || 'Educativa'}</div><div>${gestionBadge}</div>`;
                    }
                },
                {
                    data: 'recorrido_nombre',
                    render: function(data) { return data || '<span class="text-muted">No asignado</span>'; }
                },
                {
                    data: 'total_visitantes',
                    className: 'fw-bold',
                    render: function(data) { return data || 0; }
                },
                {
                    data: 'estatus',
                    render: function(data) { return obtenerBadgeEstatus(data); }
                },
                {
                    data: 'metodo_pago',
                    render: function(data, type, c) {
                        return `<div style="font-size: 0.85rem;">${c.metodo_pago || 'No especificado'}</div><small class="text-muted">Estado: ${c.pago_estado || 'pendiente'}</small>`;
                    }
                }
            ];

        } else if (seccion === 'zonas') {
            $head.html(`
                <tr>
                    <th>Zona</th>
                    <th>Total de Visitas</th>
                    <th>Visitantes Estimados</th>
                    <th>Recorridos Asociados</th>
                    <th>Estado en Período</th>
                </tr>
            `);

            columnsConfig = [
                {
                    data: 'zona_nombre',
                    className: 'fw-bold text-dark',
                    render: function(data) { return `<span class="me-2">📍</span>${data}`; }
                },
                {
                    data: 'total_visitas',
                    render: function(data) {
                        return `<span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 fs-6 fw-bold">${data} visitas</span>`;
                    }
                },
                {
                    data: 'total_visitantes_zona',
                    className: 'fw-semibold',
                    render: function(data) { return `${data || 0} personas`; }
                },
                {
                    data: 'recorridos_asociados',
                    className: 'text-muted',
                    render: function(data) {
                        return `<div style="max-width: 250px; white-space: normal;">${data || 'Recorrido Estándar'}</div>`;
                    }
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function() {
                        return `<span class="badge bg-light text-success border border-success px-2 py-1">Activa en citas</span>`;
                    }
                }
            ];

        } else if (seccion === 'exoneraciones') {
            $head.html(`
                <tr>
                    <th># Cita</th>
                    <th>Fecha</th>
                    <th>Institución</th>
                    <th>Gestión</th>
                    <th>Motivo de Exoneración</th>
                    <th>Visitantes Exonerados</th>
                    <th>Estatus Cita</th>
                </tr>
            `);

            columnsConfig = [
                {
                    data: 'cita_id',
                    render: function(data) { return `<strong>#${data}</strong>`; }
                },
                {
                    data: 'fecha_visita',
                    render: function(data) { return formatearFechaVisual(data); }
                },
                {
                    data: 'institucion_nombre',
                    render: function(data, type, e) {
                        return `<div class="fw-bold">${e.institucion_nombre}</div><small class="text-muted">${e.tipo_documento || 'J'}-${e.institucion_rif}</small>`;
                    }
                },
                {
                    data: 'tipo_gestion',
                    render: function(data) {
                        return `<span class="badge-gestion-${data}">${data === 'publica' ? 'Pública' : 'Privada'}</span>`;
                    }
                },
                {
                    data: 'motivo_exoneracion',
                    render: function(data) {
                        return `<span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2 py-1">${data}</span>`;
                    }
                },
                {
                    data: 'total_visitantes_exonerados',
                    className: 'fw-bold text-success fs-6',
                    render: function(data) { return data || 0; }
                },
                {
                    data: 'estatus',
                    render: function(data) { return obtenerBadgeEstatus(data); }
                }
            ];

        } else if (seccion === 'visitantes') {
            $head.html(`
                <tr>
                    <th>Cédula</th>
                    <th>Nombres y Apellidos</th>
                    <th>Fecha Nacimiento</th>
                    <th>Edad Estimada</th>
                    <th>Grupo Etario</th>
                    <th>Fecha Visita</th>
                    <th>Institución</th>
                </tr>
            `);

            columnsConfig = [
                {
                    data: 'cedula',
                    className: 'fw-semibold',
                    render: function(data) { return data || 'S/C'; }
                },
                {
                    data: 'apellidos',
                    className: 'fw-bold',
                    render: function(data, type, v) { return `${v.nombres || ''} ${v.apellidos || ''}`.trim(); }
                },
                {
                    data: 'fecha_nacimiento',
                    render: function(data) { return data ? formatearFechaVisual(data) : 'No registrada'; }
                },
                {
                    data: 'edad_estimada',
                    render: function(data) { return data !== null ? data + ' años' : 'N/A'; }
                },
                {
                    data: 'grupo_etario',
                    render: function(data) {
                        return `<span class="badge bg-light text-dark border px-2 py-1">${data}</span>`;
                    }
                },
                {
                    data: 'fecha_visita',
                    render: function(data) { return formatearFechaVisual(data); }
                },
                {
                    data: 'institucion_nombre',
                    className: 'text-muted',
                    render: function(data) { return data || '--'; }
                }
            ];

        } else if (seccion === 'instituciones') {
            $head.html(`
                <tr>
                    <th>RIF / Documento</th>
                    <th>Institución</th>
                    <th>Tipo Organización</th>
                    <th>Gestión</th>
                    <th>Total Citas</th>
                    <th>Visitantes Aportados</th>
                    <th>Última Visita</th>
                </tr>
            `);

            columnsConfig = [
                {
                    data: 'rif',
                    className: 'fw-bold',
                    render: function(data, type, i) { return `${i.tipo_documento || 'J'}-${i.rif}`; }
                },
                {
                    data: 'institucion_nombre',
                    className: 'fw-bold text-dark'
                },
                {
                    data: 'tipo_organizacion',
                    render: function(data) { return data || 'Educativa'; }
                },
                {
                    data: 'tipo_gestion',
                    render: function(data) {
                        return `<span class="badge-gestion-${data}">${data === 'publica' ? 'Pública' : 'Privada'}</span>`;
                    }
                },
                {
                    data: 'total_citas',
                    render: function(data) {
                        return `<span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 fw-bold">${data} citas</span>`;
                    }
                },
                {
                    data: 'total_visitantes_atendidos',
                    className: 'fw-bold',
                    render: function(data) { return `${data || 0} personas`; }
                },
                {
                    data: 'ultima_visita',
                    render: function(data) { return formatearFechaVisual(data); }
                }
            ];
        }

        // Inicializar DataTables en modo Server-Side para la tabla analítica del reporte
        $('#tablaReportes').DataTable({
            serverSide: true,
            processing: true,
            responsive: true,
            pageLength: 10,
            order: [],
            ajax: {
                url: 'controllers/ReporteController.php?action=listar_tabla',
                type: 'GET',
                dataType: 'json',
                data: function(d) {
                    const filtroTiempo = $('#filtroTiempo').val() || 'mes';
                    d.seccion = seccion;
                    d.filtro_tiempo = filtroTiempo;
                    d.estatus = $('#filtroEstatus').val() || 'todos';
                    d.tipo_cita = $('#filtroTipoCita').val() || 'todos';
                    if (filtroTiempo === 'dia') {
                        d.fecha_dia = $('#fechaDia').val();
                    } else if (filtroTiempo === 'mes') {
                        d.mes = $('#filtroMesMes').val();
                        d.anio = $('#filtroMesAnio').val();
                    } else if (filtroTiempo === 'anio') {
                        d.fecha_anio = $('#fechaAnio').val();
                    }
                },
                dataSrc: function(json) {
                    if (json && typeof json.recordsFiltered !== 'undefined') {
                        const count = parseInt(json.recordsFiltered, 10) || 0;
                        $('#conteoRegistrosBadge').text(`${count} registro${count === 1 ? '' : 's'}`);
                    }
                    return (json && json.data) ? json.data : [];
                }
            },
            columns: columnsConfig
        });
    }

    // =========================================================================
    // SINCRONIZACIÓN DE LA PLANTILLA INSTITUCIONAL PARA IMPRESIÓN (PDF)
    // =========================================================================
    function prepararPlantillaImpresion(response) {
        const seccion = response.seccion;
        const periodo = response.periodo;
        const kpis = response.kpis_globales;
        const reporte = response.reporte;

        // Título de Documento
        const titulos = {
            general: 'INFORME EJECUTIVO GENERAL Y ESTADÍSTICAS',
            citas: 'INFORME OFICIAL DE CITAS Y VISITAS GUIADAS',
            zonas: 'INFORME DE AFLUENCIA Y VISITAS POR ZONA',
            exoneraciones: 'INFORME CONSOLIDADO DE BENEFICIARIOS DE EXONERACIÓN',
            visitantes: 'INFORME DEMOGRÁFICO DE VISITANTES ATENDIDOS',
            instituciones: 'INFORME DE INSTITUCIONES Y COMUNIDADES ATENDIDAS'
        };

        $('#pdfTituloDocumento').text(titulos[seccion] || 'INFORME ESTADÍSTICO OFICIAL');
        $('#pdfPeriodoTag').text(`PERÍODO: ${(periodo.etiqueta || '').toUpperCase()}`);
        $('#pdfRangoFechas').text(`${formatearFechaVisual(periodo.inicio)} al ${formatearFechaVisual(periodo.fin)}`);

        // Resumen KPIs
        $('#pdfKpiCitas').text(kpis.citas.total);
        $('#pdfKpiVisitantes').text(kpis.visitantes.total);
        $('#pdfKpiZonas').text(kpis.zonas.total_visitas);
        $('#pdfKpiExoneraciones').text(kpis.exoneraciones.visitantes);
        $('#pdfKpiInstituciones').text(kpis.instituciones.total);

        // Tabla detallada de impresión
        const $pdfHead = $('#pdfTablaHead');
        const $pdfBody = $('#pdfTablaBody');
        $pdfHead.empty();
        $pdfBody.empty();

        let items = (seccion === 'general') ? (reporte.citas_recientes || []) : (reporte.datos || []);

        if (seccion === 'general' || seccion === 'citas') {
            $pdfHead.html(`
                <tr>
                    <th style="width: 8%;"># Cita</th>
                    <th style="width: 14%;">Fecha</th>
                    <th style="width: 22%;">Institución</th>
                    <th style="width: 14%;">Tipo Cita</th>
                    <th style="width: 12%;">Gestión</th>
                    <th style="width: 12%;">Recorrido</th>
                    <th style="width: 8%;">Visitantes</th>
                    <th style="width: 10%;">Estatus</th>
                </tr>
            `);

            items.forEach(function(c) {
                $pdfBody.append(`
                    <tr>
                        <td style="text-align: center;">#${c.id}</td>
                        <td>${formatearFechaVisual(c.fecha_visita)} ${c.hora_inicio ? c.hora_inicio.substring(0, 5) : ''}</td>
                        <td><strong>${c.institucion_nombre}</strong><br><small>${c.tipo_documento}-${c.institucion_rif}</small></td>
                        <td style="text-transform: capitalize;">${c.tipo || 'Institucional'}</td>
                        <td>${c.tipo_gestion === 'publica' ? 'Pública' : 'Privada'}</td>
                        <td>${c.recorrido_nombre || 'N/A'}</td>
                        <td style="text-align: center; font-weight: bold;">${c.total_visitantes}</td>
                        <td style="text-align: center;">${(c.estatus || '').toUpperCase()}</td>
                    </tr>
                `);
            });

        } else if (seccion === 'zonas') {
            $pdfHead.html(`
                <tr>
                    <th style="width: 40%;">Zona del Parque</th>
                    <th style="width: 20%;">Total Recorridos/Visitas</th>
                    <th style="width: 20%;">Visitantes Estimados</th>
                    <th style="width: 20%;">Estado en Período</th>
                </tr>
            `);

            items.forEach(function(z) {
                $pdfBody.append(`
                    <tr>
                        <td><strong>${z.zona_nombre}</strong></td>
                        <td style="text-align: center; font-weight: bold;">${z.total_visitas}</td>
                        <td style="text-align: center;">${z.total_visitantes_zona} personas</td>
                        <td style="text-align: center;">Activa en Citas</td>
                    </tr>
                `);
            });

        } else if (seccion === 'exoneraciones') {
            $pdfHead.html(`
                <tr>
                    <th style="width: 10%;"># Cita</th>
                    <th style="width: 15%;">Fecha</th>
                    <th style="width: 30%;">Institución</th>
                    <th style="width: 15%;">Gestión</th>
                    <th style="width: 15%;">Motivo</th>
                    <th style="width: 15%;">Visitantes Exon.</th>
                </tr>
            `);

            items.forEach(function(e) {
                $pdfBody.append(`
                    <tr>
                        <td style="text-align: center;">#${e.cita_id}</td>
                        <td>${formatearFechaVisual(e.fecha_visita)}</td>
                        <td><strong>${e.institucion_nombre}</strong><br><small>${e.tipo_documento}-${e.institucion_rif}</small></td>
                        <td>${e.tipo_gestion === 'publica' ? 'Pública' : 'Privada'}</td>
                        <td>${e.motivo_exoneracion}</td>
                        <td style="text-align: center; font-weight: bold;">${e.total_visitantes_exonerados}</td>
                    </tr>
                `);
            });

        } else if (seccion === 'visitantes') {
            $pdfHead.html(`
                <tr>
                    <th style="width: 15%;">Cédula</th>
                    <th style="width: 30%;">Nombres y Apellidos</th>
                    <th style="width: 15%;">Edad Est.</th>
                    <th style="width: 20%;">Grupo Etario</th>
                    <th style="width: 20%;">Fecha Visita</th>
                </tr>
            `);

            items.forEach(function(v) {
                $pdfBody.append(`
                    <tr>
                        <td>${v.cedula || 'S/C'}</td>
                        <td><strong>${v.nombres || ''} ${v.apellidos || ''}</strong></td>
                        <td style="text-align: center;">${v.edad_estimada !== null ? v.edad_estimada + ' años' : 'N/A'}</td>
                        <td>${v.grupo_etario}</td>
                        <td>${formatearFechaVisual(v.fecha_visita)}</td>
                    </tr>
                `);
            });

        } else if (seccion === 'instituciones') {
            $pdfHead.html(`
                <tr>
                    <th style="width: 18%;">RIF</th>
                    <th style="width: 34%;">Institución</th>
                    <th style="width: 18%;">Tipo / Gestión</th>
                    <th style="width: 15%;">Total Citas</th>
                    <th style="width: 15%;">Visitantes</th>
                </tr>
            `);

            items.forEach(function(i) {
                $pdfBody.append(`
                    <tr>
                        <td>${i.tipo_documento}-${i.rif}</td>
                        <td><strong>${i.institucion_nombre}</strong></td>
                        <td>${i.tipo_organizacion} (${i.tipo_gestion})</td>
                        <td style="text-align: center; font-weight: bold;">${i.total_citas}</td>
                        <td style="text-align: center; font-weight: bold;">${i.total_visitantes_atendidos}</td>
                    </tr>
                `);
            });
        }

        if (items.length === 0) {
            $pdfBody.html(`<tr><td colspan="7" style="text-align: center; padding: 20px; color: #64748b;">No se encontraron registros para el período consultado.</td></tr>`);
        }
    }

    // =========================================================================
    // GENERACIÓN Y DESCARGA DEL PDF (HTML2PDF)
    // =========================================================================
    $('#btnDescargarPdf').on('click', function() {
        if (!ultimoReporteData) {
            mostrarAlertaFeedback('Por favor genere primero un reporte para exportar a PDF.', 'warning');
            return;
        }

        const $btn = $(this);
        const originalHtml = $btn.html();
        $btn.prop('disabled', true).html(`
            <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
            Generando PDF...
        `);

        const elemento = document.getElementById('reporteImprimible');
        const seccion = ultimoReporteData.seccion || 'reporte';
        const periodo = (ultimoReporteData.periodo && ultimoReporteData.periodo.etiqueta) 
            ? ultimoReporteData.periodo.etiqueta.replace(/\s+/g, '_').toLowerCase() 
            : 'periodo';
        const filename = `informe_bararida_${seccion}_${periodo}.pdf`;

        // Mostrar temporalmente el contenedor para renderizado de html2canvas
        const $wrapper = $('#reporteImpresionWrapper');
        $wrapper.css({
            'display': 'block',
            'position': 'fixed',
            'top': '0',
            'left': '0',
            'width': '800px',
            'z-index': '-9999',
            'background': '#ffffff'
        });

        const opciones = {
            margin: [10, 10, 10, 10],
            filename: filename,
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: {
                scale: 2,
                useCORS: true,
                letterRendering: true,
                logging: false
            },
            jsPDF: {
                unit: 'mm',
                format: 'a4',
                orientation: 'portrait'
            }
        };

        html2pdf().set(opciones).from(elemento).save()
            .then(function() {
                $wrapper.css('display', 'none');
                $btn.prop('disabled', false).html(originalHtml);
                mostrarAlertaFeedback('¡El informe en PDF ha sido generado y descargado con éxito!', 'success');
            })
            .catch(function(err) {
                console.error('Error al generar PDF:', err);
                $wrapper.css('display', 'none');
                $btn.prop('disabled', false).html(originalHtml);
                mostrarAlertaFeedback('Ocurrió un inconveniente al generar el archivo PDF.', 'danger');
            });
    });

    // =========================================================================
    // UTILIDADES AUXILIARES
    // =========================================================================
    function formatearFechaVisual(fechaStr) {
        if (!fechaStr) return '--';
        const partes = fechaStr.split('-');
        if (partes.length === 3) {
            return `${partes[2]}/${partes[1]}/${partes[0]}`;
        }
        return fechaStr;
    }

    function obtenerBadgeEstatus(estatus) {
        const est = (estatus || 'pendiente').toLowerCase();
        let label = 'Pendiente';
        let cssClass = 'badge-status-pendiente';

        if (est === 'aprobado') {
            label = 'Aprobada';
            cssClass = 'badge-status-aprobado';
        } else if (est === 'completado') {
            label = 'Completada';
            cssClass = 'badge-status-completado';
        } else if (est === 'cancelado') {
            label = 'Cancelada';
            cssClass = 'badge-status-cancelado';
        }

        return `<span class="badge ${cssClass} px-2 py-1">${label}</span>`;
    }

    function obtenerTituloSeccion(seccion) {
        const titulos = {
            general: 'Resumen Ejecutivo General',
            citas: 'Reporte de Citas Realizadas',
            zonas: 'Reporte de Visitas por Zona',
            exoneraciones: 'Reporte de Exoneraciones Otorgadas',
            visitantes: 'Reporte de Afluencia de Visitantes',
            instituciones: 'Reporte de Instituciones Atendidas'
        };
        return titulos[seccion] || 'Informe Detallado';
    }

    function mostrarAlertaFeedback(mensaje, tipo) {
        if (!$alertFeedback.length) return;
        $alertFeedback.removeClass('alert-toast-success alert-toast-danger alert-toast-warning')
                      .addClass(`alert-toast-${tipo}`)
                      .html(`<strong>${tipo === 'success' ? 'Éxito: ' : (tipo === 'warning' ? 'Aviso: ' : 'Error: ')}</strong> ${mensaje}`)
                      .slideDown(250);

        setTimeout(function() {
            $alertFeedback.slideUp(250);
        }, 5000);
    }

    // Ejecutar consulta inicial automática al cargar la vista
    ejecutarConsultaReporte();
});
