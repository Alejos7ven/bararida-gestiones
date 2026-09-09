/**
 * dashboard.js — Lógica del panel de administración
 * Parque Zoológico y Botánico Bararida
 *
 * Maneja el sidebar responsivo con botón hamburguesa y overlay
 * para dispositivos móviles.
 */
(function () {
    'use strict';

    var toggleBtn = document.getElementById('sidebarToggle');
    var sidebar   = document.getElementById('sidebar');
    var overlay   = document.getElementById('sidebarOverlay');

    if (!toggleBtn || !sidebar || !overlay) return;

    function openSidebar() {
        sidebar.classList.add('sidebar-open');
        overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeSidebar() {
        sidebar.classList.remove('sidebar-open');
        overlay.classList.remove('active');
        document.body.style.overflow = '';
    }

    // Abrir/cerrar al presionar el botón hamburguesa
    toggleBtn.addEventListener('click', function () {
        if (sidebar.classList.contains('sidebar-open')) {
            closeSidebar();
        } else {
            openSidebar();
        }
    });

    // Cerrar sidebar al hacer clic en el overlay
    overlay.addEventListener('click', closeSidebar);

    // Cerrar sidebar al hacer clic en cualquier enlace del menú en mobile
    sidebar.querySelectorAll('.sidebar-menu a').forEach(function (link) {
        link.addEventListener('click', function () {
            if (window.innerWidth <= 768) {
                closeSidebar();
            }
        });
    });

    // Restaurar estado al redimensionar a escritorio
    window.addEventListener('resize', function () {
        if (window.innerWidth > 768) {
            closeSidebar();
        }
    });

    // Redireccionar al login si ocurre un error 401 (Sesión no activa o expirada) en peticiones AJAX
    $(document).ajaxError(function (event, jqXHR, ajaxSettings, thrownError) {
        if (jqXHR.status === 401) {
            window.location.href = 'index.php';
        }
    });

    // =========================================================================
    // LÓGICA DE CALENDARIO Y ESTADÍSTICAS DEL DASHBOARD (FullCalendar)
    // =========================================================================
    let cacheEstadisticas = [];
    let cacheCitas = [];
    let chartInstance = null;
    let fullCalendarInstance = null;

    function cargarDatosDashboard() {
        if ($('#dashboardFullCalendar').length === 0) return;

        // 1. Cargar estadísticas para la gráfica de visitantes
        $.ajax({
            url: 'controllers/CitaController.php?action=obtener_datos_dashboard',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    cacheEstadisticas = response.estadisticas || [];
                    
                    // Renderizar Gráfica
                    renderizarGraficaEstadisticas();
                } else {
                    console.error('Error al cargar estadísticas:', response.message);
                }
            },
            error: function(err) {
                console.error('Error de red al cargar estadísticas del dashboard.', err);
            }
        });

        // 2. Cargar citas completas para FullCalendar
        $.ajax({
            url: 'controllers/CitaController.php?action=listar',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    cacheCitas = response.data || [];
                    
                    // Inicializar FullCalendar
                    inicializarFullCalendar(cacheCitas);
                    
                    // Mostrar alertas de capacidad
                    procesarAlertasCapacidad(cacheCitas);
                } else {
                    console.error('Error al obtener citas para calendario:', response.message);
                }
            },
            error: function(err) {
                console.error('Error de red al cargar citas del calendario.', err);
            }
        });
    }

    function calcularHoraFin(fechaStr, horaInicioStr) {
        const parts = horaInicioStr.split(':');
        let hour = parseInt(parts[0]);
        let minutes = parts[1];
        hour = (hour + 1) % 24;
        const hourStr = String(hour).padStart(2, '0');
        return `${fechaStr}T${hourStr}:${minutes}:00`;
    }

    function inicializarFullCalendar(citas) {
        const calendarEl = document.getElementById('dashboardFullCalendar');
        if (!calendarEl) return;

        // Agrupar citas por fecha para calcular la densidad de celdas
        const citasPorFecha = {};
        citas.forEach(function(c) {
            citasPorFecha[c.fecha_visita] = (citasPorFecha[c.fecha_visita] || 0) + 1;
        });

        // Agrupar citas por fecha y hora exacta (bloque horario) para evitar superposición
        const citasPorBloque = {};
        citas.forEach(function(c) {
            const key = `${c.fecha_visita}T${c.hora_inicio}`;
            if (!citasPorBloque[key]) {
                citasPorBloque[key] = [];
            }
            citasPorBloque[key].push(c);
        });

        // Mapear al formato de FullCalendar (agrupando por bloque horario)
        const events = [];
        Object.keys(citasPorBloque).forEach(function(key) {
            const grupo = citasPorBloque[key];
            const primerCita = grupo[0];
            const [fecha, hora] = key.split('T');

            if (grupo.length === 1) {
                // Una sola cita en el bloque horario
                events.push({
                    id: primerCita.id,
                    title: `${primerCita.razon_social} (${primerCita.recorrido_nombre || 'Libre'})`,
                    start: key,
                    end: calcularHoraFin(fecha, primerCita.hora_inicio),
                    className: `fc-event-status-${primerCita.estatus || 'pendiente'}`,
                    extendedProps: {
                        citas: grupo,
                        esAgrupado: false,
                        institucion: primerCita.razon_social,
                        recorrido: primerCita.recorrido_nombre || 'Ninguno / Libre',
                        visitantes: primerCita.total_visitantes,
                        pago: primerCita.pago_estado,
                        estatus: primerCita.estatus,
                        hora: formatTime(primerCita.hora_inicio)
                    }
                });
            } else {
                // Múltiples citas en el mismo bloque horario
                const totalVisitantes = grupo.reduce((sum, item) => sum + parseInt(item.total_visitantes || 0), 0);
                events.push({
                    id: 'group-' + key,
                    title: `${grupo.length} citas`,
                    start: key,
                    end: calcularHoraFin(fecha, primerCita.hora_inicio),
                    className: 'fc-event-grouped',
                    extendedProps: {
                        citas: grupo,
                        esAgrupado: true,
                        totalCitas: grupo.length,
                        totalVisitantes: totalVisitantes,
                        hora: formatTime(primerCita.hora_inicio)
                    }
                });
            }
        });

        if (fullCalendarInstance) {
            fullCalendarInstance.destroy();
        }

        fullCalendarInstance = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            locale: 'es',
            firstDay: 1, // Lunes
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek' // Reestablecer timeGridWeek a petición del usuario para tener horas
            },
            buttonText: {
                today: 'Hoy',
                month: 'Mes',
                week: 'Semana'
            },
            dayMaxEvents: 3, // límite de eventos por celda (máximo 3, si hay más muestra link)
            slotEventOverlap: false, // evitar superposición en vistas de tiempo
            allDaySlot: false, // ocultar la fila de todo el día (all-day)
            slotMinTime: '07:00:00', // Bloque horario desde 7:00 AM
            slotMaxTime: '17:00:00', // Bloque horario hasta 5:00 PM
            height: 'auto', // adaptarse a la altura del contenido sin scroll vertical interno
            moreLinkText: 'Ver más',
            events: events,
            eventContent: function(arg) {
                const props = arg.event.extendedProps;
                
                // 1. Calendario Mensual (dayGridMonth): Renderizar píldora compacta de una sola línea
                if (arg.view.type === 'dayGridMonth') {
                    const monthPill = document.createElement('div');
                    monthPill.className = 'fc-event-month-pill d-flex align-items-center w-100';
                    monthPill.style.overflow = 'hidden';
                    monthPill.style.textOverflow = 'ellipsis';
                    monthPill.style.whiteSpace = 'nowrap';
                    monthPill.style.fontSize = '0.70rem';
                    monthPill.style.fontWeight = '700';
                    monthPill.style.padding = '1px 3px';
                    
                    const timeStr = arg.timeText ? arg.timeText + ' ' : '';
                    if (props && props.esAgrupado) {
                        monthPill.textContent = timeStr + `(${props.totalCitas} citas)`;
                    } else {
                        monthPill.textContent = timeStr + arg.event.title;
                    }
                    
                    return { domNodes: [monthPill] };
                }
                
                // 2. Calendario Semanal (timeGridWeek)
                const container = document.createElement('div');
                container.className = 'fc-event-main-frame d-flex flex-column justify-content-center w-100 h-100';
                container.style.padding = '2px 4px';
                
                if (arg.timeText) {
                    const timeEl = document.createElement('div');
                    timeEl.className = 'fc-event-time';
                    timeEl.style.fontWeight = '700';
                    timeEl.style.fontSize = '0.68rem';
                    timeEl.style.marginBottom = '2px';
                    timeEl.textContent = arg.timeText;
                    container.appendChild(timeEl);
                }
                
                if (props && props.esAgrupado) {
                    // Renderizar las citas agrupadas una por línea
                    const listEl = document.createElement('div');
                    listEl.className = 'fc-event-grouped-list d-flex flex-column w-100';
                    
                    props.citas.forEach(function(c) {
                        const itemEl = document.createElement('div');
                        itemEl.className = 'fc-event-grouped-item';
                        itemEl.style.fontSize = '0.68rem';
                        itemEl.style.fontWeight = '600';
                        itemEl.style.whiteSpace = 'nowrap';
                        itemEl.style.overflow = 'hidden';
                        itemEl.style.textOverflow = 'ellipsis';
                        itemEl.textContent = c.razon_social;
                        listEl.appendChild(itemEl);
                    });
                    
                    container.appendChild(listEl);
                } else {
                    const titleEl = document.createElement('div');
                    titleEl.className = 'fc-event-title';
                    titleEl.style.fontWeight = '600';
                    titleEl.style.fontSize = '0.72rem';
                    titleEl.style.whiteSpace = 'normal';
                    titleEl.style.wordBreak = 'break-word';
                    titleEl.textContent = arg.event.title;
                    container.appendChild(titleEl);
                }
                
                return { domNodes: [container] };
            },
            dayCellDidMount: function(info) {
                const dateStr = info.date.toLocaleDateString('sv-SE'); // Formato AAAA-MM-DD local
                const count = citasPorFecha[dateStr] || 0;
                
                if (count === 1) {
                    info.el.classList.add('fc-day-density-low');
                } else if (count === 2) {
                    info.el.classList.add('fc-day-density-medium');
                } else if (count >= 3) {
                    info.el.classList.add('fc-day-density-high');
                }
            },
            eventDidMount: function(info) {
                // Agregar tooltip nativo con detalles
                const props = info.event.extendedProps;
                const tooltipText = `Institución: ${props.institucion}\nHora: ${props.hora}\nRecorrido: ${props.recorrido}\nVisitantes: ${props.visitantes || 0}\nPago: ${props.pago === 'pagado' ? 'Pagado' : 'Pendiente'}\n(Haga clic para ver detalles)`;
                info.el.setAttribute('title', tooltipText);
            },
            eventClick: function(info) {
                const props = info.event.extendedProps;
                abrirModalDetalleBloque(props.citas, props.hora);
            }
        });

        fullCalendarInstance.render();
    }

    function abrirModalDetalleBloque(citasList, hora) {
        const $container = $('#listaCitasBloqueContainer');
        $container.empty();

        // Actualizar título del modal con la hora
        $('#modalDetalleBloqueHorarioLabel').text(`Citas programadas a las ${hora}`);

        citasList.forEach(function(c) {
            // Estatus badge
            let badgeEstatus = '';
            if (c.estatus === 'aprobado') {
                badgeEstatus = '<span class="badge bg-success" style="font-size:0.75rem;">Aprobada</span>';
            } else if (c.estatus === 'completado') {
                badgeEstatus = '<span class="badge bg-dark" style="font-size:0.75rem;">Completada</span>';
            } else if (c.estatus === 'cancelado') {
                badgeEstatus = '<span class="badge bg-danger" style="font-size:0.75rem;">Cancelada</span>';
            } else {
                badgeEstatus = '<span class="badge bg-primary" style="font-size:0.75rem;">Pendiente</span>';
            }

            // Pago badge
            const badgePago = c.pago_estado === 'pagado'
                ? '<span class="badge bg-success" style="font-size:0.75rem;">Pagado</span>'
                : '<span class="badge bg-warning text-dark" style="font-size:0.75rem;">Pago Pendiente</span>';

            const itemHtml = `
                <div class="card mb-3 shadow-none border" style="border-radius:12px; overflow:hidden;">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center py-2" style="border-bottom: 1px solid #e2e8f0;">
                        <strong>ID: #CIT-${c.id}</strong>
                        <div>
                            ${badgeEstatus}
                            ${badgePago}
                        </div>
                    </div>
                    <div class="card-body py-2.5 px-3" style="font-size:0.85rem; line-height:1.45;">
                        <p class="mb-1"><strong>Institución:</strong> ${c.razon_social} (${c.tipo_documento}-${c.RIF})</p>
                        <p class="mb-1"><strong>Recorrido:</strong> ${c.recorrido_nombre || 'Ninguno / Libre'}</p>
                        <p class="mb-0"><strong>Visitantes:</strong> <span class="badge bg-info text-dark" style="font-size:0.72rem; font-weight:600;">${c.total_visitantes} visitantes</span></p>
                    </div>
                </div>
            `;
            $container.append(itemHtml);
        });

        $('#modalDetalleBloqueHorario').modal('show');
    }

    function procesarAlertasCapacidad(citas) {
        const $warningsDiv = $('#dashboardCapacityWarnings');
        if ($warningsDiv.length === 0) return;

        // Agrupar citas por fecha
        const citasPorFecha = {};
        citas.forEach(function(c) {
            citasPorFecha[c.fecha_visita] = (citasPorFecha[c.fecha_visita] || 0) + 1;
        });

        // Filtrar días sobrecargados (fecha >= hoy y citas > 3)
        const hoy = new Date();
        hoy.setHours(0,0,0,0);
        
        const diasSobrecargados = [];
        Object.keys(citasPorFecha).forEach(function(fechaStr) {
            const parts = fechaStr.split('-');
            const dateObj = new Date(parts[0], parts[1] - 1, parts[2]);
            if (dateObj >= hoy && citasPorFecha[fechaStr] > 3) {
                diasSobrecargados.push({
                    fecha: fechaStr,
                    cantidad: citasPorFecha[fechaStr]
                });
            }
        });

        if (diasSobrecargados.length > 0) {
            // Ordenar de más cercano a más lejano
            diasSobrecargados.sort(function(a, b) {
                return new Date(a.fecha) - new Date(b.fecha);
            });

            let warningHtml = `
                <div class="capacity-warning-card shadow-sm">
                    <h5>
                        <svg viewBox="0 0 24 24" style="width:18px; height:18px; fill: currentColor; margin-right: 4px; vertical-align: text-bottom;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                        Advertencia de Capacidad
                    </h5>
                    <p style="margin: 0 0 8px 0; font-size: 0.78rem; opacity: 0.9;">Los siguientes días de visita programada exceden la capacidad recomendada de 3 citas diarias. Verifique la disponibilidad de guías:</p>
                    <div style="max-height: 100px; overflow-y: auto;">
            `;

            diasSobrecargados.forEach(function(item) {
                warningHtml += `
                    <div class="capacity-warning-item">
                        <strong>${formatLongDate(item.fecha)}</strong>: <span class="badge bg-danger">${item.cantidad} citas</span> agendadas.
                    </div>
                `;
            });

            warningHtml += `
                    </div>
                </div>
            `;
            $warningsDiv.html(warningHtml).fadeIn(250);
        } else {
            $warningsDiv.hide().empty();
        }
    }

    function renderizarGraficaEstadisticas() {
        const canvas = document.getElementById('chartVisitantes');
        if (!canvas) return;

        const ctx = canvas.getContext('2d');
        
        // Destruir instancia anterior si existe
        if (chartInstance) {
            chartInstance.destroy();
        }

        // Si no hay datos, mostrar el placeholder y ocultar el canvas
        if (cacheEstadisticas.length === 0) {
            $('#chartVisitantes').hide();
            $('#chartNoDataPlaceholder').css('display', 'flex');
            return;
        }

        // Si hay datos, ocultar el placeholder y mostrar el canvas
        $('#chartNoDataPlaceholder').hide();
        $('#chartVisitantes').show();

        // Ordenar estadísticas por fecha
        const sortedStats = [...cacheEstadisticas].sort((a, b) => new Date(a.fecha_visita) - new Date(b.fecha_visita));

        // Obtener solo las últimas 15 fechas para evitar saturar el gráfico
        const limitStats = sortedStats.slice(-15);

        const labels = limitStats.map(s => {
            const dateParts = s.fecha_visita.split('-');
            if (dateParts.length === 3) {
                const mesesAbbr = ["Ene", "Feb", "Mar", "Abr", "May", "Jun", "Jul", "Ago", "Sep", "Oct", "Nov", "Dic"];
                return `${parseInt(dateParts[2])} ${mesesAbbr[parseInt(dateParts[1]) - 1]}`;
            }
            return s.fecha_visita;
        });

        const data = limitStats.map(s => parseInt(s.total_visitantes));

        chartInstance = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Cantidad de Visitantes',
                    data: data,
                    borderColor: '#0D9039', // verde principal del parque
                    backgroundColor: 'rgba(13, 144, 57, 0.1)',
                    borderWidth: 3,
                    tension: 0.3,
                    fill: true,
                    pointBackgroundColor: '#0D9039',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: '#1a202c',
                        titleColor: '#ffffff',
                        bodyColor: '#ffffff',
                        titleFont: { family: 'Outfit', weight: '700' },
                        bodyFont: { family: 'Outfit' },
                        padding: 12,
                        cornerRadius: 8,
                        displayColors: false,
                        callbacks: {
                            label: function(context) {
                                return `Visitantes: ${context.raw}`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(226, 232, 240, 0.6)'
                        },
                        ticks: {
                            color: '#64748b',
                            font: { family: 'Outfit', weight: '600', size: 11 }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: '#64748b',
                            font: { family: 'Outfit', weight: '600', size: 11 }
                        }
                    }
                }
            }
        });
    }

    // =========================================================================
    // AUXILIARES
    // =========================================================================
    function formatLongDate(dateStr) {
        const parts = dateStr.split('-');
        if (parts.length !== 3) return dateStr;
        const d = new Date(parts[0], parts[1] - 1, parts[2]);
        const opciones = { weekday: 'long', day: 'numeric', month: 'long' };
        let formatted = d.toLocaleDateString('es-ES', opciones);
        return formatted.charAt(0).toUpperCase() + formatted.slice(1);
    }

    function formatTime(timeString) {
        if (!timeString) return '';
        const parts = timeString.split(':');
        if (parts.length < 2) return timeString;
        
        let hour = parseInt(parts[0]);
        const minutes = parts[1];
        const ampm = hour >= 12 ? 'PM' : 'AM';
        
        hour = hour % 12;
        hour = hour ? hour : 12; // 0 debe ser 12
        
        return `${hour}:${minutes} ${ampm}`;
    }

    // Ajustar posición del popover de FullCalendar para evitar desbordamiento lateral y vertical
    $(document).on('click', '.fc-more-link', function () {
        setTimeout(function () {
            const $popover = $('.fc-popover');
            if ($popover.length === 0) return;

            // Encontrar el contenedor relativo más cercano (el contenedor del calendario)
            const $container = $popover.closest('#dashboardFullCalendar, #calendarReserva');
            if ($container.length === 0) return;

            // 1. Evitar desbordamiento lateral (horizontal)
            const containerWidth = $container.width();
            const popoverWidth = $popover.outerWidth();
            const popoverLeft = parseFloat($popover.css('left')) || 0;

            if (popoverLeft + popoverWidth > containerWidth) {
                const newLeft = Math.max(10, containerWidth - popoverWidth - 10);
                $popover.css('left', newLeft + 'px');
            }

            // 2. Evitar desbordamiento inferior (vertical)
            const containerHeight = $container.height();
            const popoverHeight = $popover.outerHeight();
            const popoverTop = parseFloat($popover.css('top')) || 0;

            if (popoverTop + popoverHeight > containerHeight) {
                const newTop = Math.max(10, containerHeight - popoverHeight - 10);
                $popover.css('top', newTop + 'px');
            }
        }, 50);
    });

    let allGuias = [];

    let timerRecorridosInterval = null;

    function iniciarContadorRecorridos() {
        if (timerRecorridosInterval) {
            clearInterval(timerRecorridosInterval);
            timerRecorridosInterval = null;
        }

        if ($('.timer-recorrido').length === 0) return;

        timerRecorridosInterval = setInterval(function() {
            let hayActivos = false;
            $('.timer-recorrido').each(function() {
                let restantes = parseInt($(this).data('restantes'), 10);
                if (isNaN(restantes)) return;

                if (restantes > 0) {
                    restantes--;
                    $(this).data('restantes', restantes);
                    const h = Math.floor(restantes / 3600);
                    const m = Math.floor((restantes % 3600) / 60);
                    const s = restantes % 60;
                    const format = (h > 0 ? (h < 10 ? '0' + h : h) + ':' : '') + 
                                   (m < 10 ? '0' + m : m) + ':' + 
                                   (s < 10 ? '0' + s : s);
                    $(this).find('.tiempo-display').text(format);
                    hayActivos = true;
                } else {
                    $(this).find('.tiempo-display').text('00:00 (Completando...)');
                    cargarCitasHoy();
                }
            });

            if (!hayActivos && timerRecorridosInterval) {
                clearInterval(timerRecorridosInterval);
                timerRecorridosInterval = null;
            }
        }, 1000);
    }

    function cargarCitasHoy() {
        const $loader = $('#loaderCitasHoy');
        const $wrapper = $('#citasHoyWrapper');
        const $body = $('#tablaCitasHoyBody');

        if ($body.length === 0) return;

        $wrapper.hide();
        $loader.show();

        $.ajax({
            url: 'controllers/CitaController.php?action=citas_hoy',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                $loader.hide();
                $body.empty();

                if (response.status === 'success') {
                    const citas = response.data || [];
                    if (citas.length === 0) {
                        $body.append('<tr><td colspan="7" class="text-center text-muted py-4">No hay visitas guiadas programadas para el día de hoy.</td></tr>');
                        $wrapper.fadeIn(300);
                        return;
                    }

                    citas.forEach(function(c) {
                        const hora = formatTime(c.hora_inicio);
                        let guiasHtml = '';
                        
                        // Si el usuario es administrador o superadmin, permitir asignación
                        const userRol = $('#quickAccessCard').data('user-rol') || '';
                        const esAdmin = userRol === 'superadmin' || userRol === 'admin';
                        const esMiAsignacion = parseInt(c.es_mi_asignacion, 10) === 1;
                        const tieneGuia = !!c.guias_asignados;
                        const puedeGestionarRecorrido = esAdmin || esMiAsignacion;

                        if (esAdmin) {
                            if (c.guias_asignados) {
                                guiasHtml = `<div class="table-actions d-flex align-items-center justify-content-center gap-2 flex-wrap">
                                    <span class="badge bg-success" style="font-size:0.75rem; font-weight:600;">${c.guias_asignados}</span>
                                    <button class="btn-action btn-action-edit btn-gestionar-guia-dash" data-id="${c.id}" title="Gestionar guías asignados">
                                        <svg viewBox="0 0 24 24"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
                                    </button>
                                </div>`;
                            } else {
                                guiasHtml = `<div class="table-actions justify-content-center">
                                    <button class="btn-action btn-action-edit btn-gestionar-guia-dash" data-id="${c.id}" title="Asignar guía a esta cita">
                                        <svg viewBox="0 0 24 24"><path d="M15 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm-9-2V7H4v3H1v2h3v3h2v-3h3v-2H6zm9 4c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                                    </button>
                                </div>`;
                            }
                        } else {
                            guiasHtml = c.guias_asignados
                                ? `<span class="badge bg-success" style="font-size:0.75rem; font-weight:600;">${c.guias_asignados}</span>`
                                : '<span class="text-muted" style="font-size:0.8rem; font-style:italic;">No asignado</span>';
                        }

                        // Construcción de la columna Estado / Recorrido
                        let estadoRecorridoHtml = '';
                        if (c.estatus === 'en_recorrido') {
                            const segRestantes = parseInt(c.segundos_restantes, 10) || 0;
                            const h = Math.floor(segRestantes / 3600);
                            const m = Math.floor((segRestantes % 3600) / 60);
                            const s = segRestantes % 60;
                            const formatInit = (h > 0 ? (h < 10 ? '0' + h : h) + ':' : '') + 
                                               (m < 10 ? '0' + m : m) + ':' + 
                                               (s < 10 ? '0' + s : s);

                            let btnTerminar = '';
                            if (puedeGestionarRecorrido) {
                                btnTerminar = `<div class="mt-1"><button class="btn btn-sm btn-outline-danger py-0 px-2 btn-terminar-recorrido" data-id="${c.id}" style="font-size:0.75rem; border-radius:6px;" title="Finalizar recorrido">⏹ Terminar</button></div>`;
                            }

                            estadoRecorridoHtml = `
                                <div>
                                    <span class="badge bg-primary d-inline-flex align-items-center gap-1" style="font-size:0.75rem; padding: 0.35em 0.65em;">
                                        <span class="spinner-grow spinner-grow-sm text-light" style="width:0.45rem;height:0.45rem;" role="status"></span>
                                        En Recorrido
                                    </span>
                                    <div class="mt-1 font-monospace text-primary fw-bold timer-recorrido" data-restantes="${segRestantes}" style="font-size:0.75rem;">
                                        Restante: <span class="tiempo-display">${formatInit}</span>
                                    </div>
                                    ${btnTerminar}
                                </div>
                            `;
                        } else if (c.estatus === 'completado') {
                            estadoRecorridoHtml = `
                                <span class="badge bg-secondary d-inline-flex align-items-center gap-1" style="font-size:0.75rem; padding: 0.35em 0.65em;">
                                    <svg viewBox="0 0 24 24" style="width:12px;height:12px;fill:currentColor;"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg> Completado
                                </span>
                            `;
                        } else if (c.estatus === 'cancelado') {
                            estadoRecorridoHtml = `<span class="badge bg-danger" style="font-size:0.75rem;">Cancelado</span>`;
                        } else {
                            // Cita pendiente / aprobada
                            if (tieneGuia && puedeGestionarRecorrido) {
                                estadoRecorridoHtml = `
                                    <button class="btn btn-sm btn-success py-1 px-3 btn-empezar-recorrido shadow-sm" data-id="${c.id}" style="font-size:0.78rem; font-weight:600; border-radius:8px; background-color: #0D9039; border-color: #0D9039;" title="Iniciar el recorrido ahora">
                                        ▶ Empezar Recorrido
                                    </button>
                                `;
                            } else if (tieneGuia) {
                                estadoRecorridoHtml = `<span class="badge bg-info text-dark" style="font-size:0.75rem;">Guía Asignado</span>`;
                            } else {
                                estadoRecorridoHtml = `<span class="badge bg-warning text-dark" style="font-size:0.75rem;">Sin Asignar</span>`;
                            }
                        }

                        const rowHtml = `
                            <tr>
                                <td><strong>#CIT-${c.id}</strong></td>
                                <td><strong>${hora}</strong></td>
                                <td>
                                    <strong>${c.tipo_documento}-${c.rif}</strong><br>
                                    <small class="text-muted">${c.razon_social}</small>
                                </td>
                                <td>${c.recorrido_nombre || '<span class="text-muted" style="font-style:italic;">Sin recorrido</span>'}</td>
                                <td><span class="badge bg-info text-dark" style="font-size:0.75rem; font-weight:600;">${c.total_visitantes}</span></td>
                                <td>${guiasHtml}</td>
                                <td>${estadoRecorridoHtml}</td>
                            </tr>
                        `;
                        $body.append(rowHtml);
                    });

                    iniciarContadorRecorridos();
                    $wrapper.fadeIn(300);
                } else {
                    $body.append(`<tr><td colspan="7" class="text-center text-danger py-4">No se pudo cargar la agenda del día: ${response.message}</td></tr>`);
                    $wrapper.fadeIn(300);
                }
            },
            error: function() {
                $loader.hide();
                $body.append('<tr><td colspan="7" class="text-center text-danger py-4">Error al cargar la agenda de hoy.</td></tr>');
                $wrapper.fadeIn(300);
            }
        });
    }
        });
    }

    // Cargar guías disponibles en el sistema (roles 'guia')
    function cargarGuiasDisponibles(callback) {
        if (allGuias.length > 0) {
            if (callback) callback(allGuias);
            return;
        }

        $.ajax({
            url: 'controllers/CitaController.php?action=listar_guias',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    allGuias = response.data || [];
                    
                    const $select = $('#selectGuiaDashboard');
                    $select.empty().append('<option value="">Seleccione un guía...</option>');
                    allGuias.forEach(function(g) {
                        $select.append(`<option value="${g.id}">${g.nombre} ${g.apellido}</option>`);
                    });

                    if (callback) callback(allGuias);
                } else {
                    console.error('Error al cargar guías:', response.message);
                }
            },
            error: function() {
                console.error('Error de red al cargar guías.');
            }
        });
    }

    // Cargar guías asignados a la cita para mostrarlos en el modal
    function cargarGuiasAsignadosCita(citaId) {
        const $container = $('#guiasAsignadosActualesDashboard');
        $container.empty().append('<div class="text-center text-muted py-2">Cargando guías asignados...</div>');

        $.ajax({
            url: `controllers/CitaController.php?action=guias_cita&cita_id=${citaId}`,
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                $container.empty();
                if (response.status === 'success' && response.data && response.data.length > 0) {
                    response.data.forEach(function(g) {
                        const rolText = g.rol_guia === 'principal' ? 'Principal' : 'Apoyo';
                        const badgeClass = g.rol_guia === 'principal' ? 'bg-success' : 'bg-secondary';
                        const itemHtml = `
                            <div class="d-flex justify-content-between align-items-center p-2 rounded border bg-white">
                                <div>
                                    <strong>${g.nombre} ${g.apellido}</strong> 
                                    <span class="badge ${badgeClass} ms-1" style="font-size: 0.65rem;">${rolText}</span>
                                </div>
                                <button type="button" class="btn btn-sm btn-link text-danger p-0 btn-quitar-guia-dash" data-cita-id="${citaId}" data-guia-id="${g.personal_id}" style="text-decoration:none; font-weight:700;" title="Quitar asignación">✕ Quitar</button>
                            </div>
                        `;
                        $container.append(itemHtml);
                    });
                } else {
                    $container.append('<div class="text-center text-muted py-2 italic">Ningún guía asignado aún para esta visita.</div>');
                }
            },
            error: function() {
                $container.empty().append('<div class="text-center text-danger py-2">Error al cargar asignaciones.</div>');
            }
        });
    }

    // Evento: Abrir modal de asignación de guía
    $(document).on('click', '.btn-gestionar-guia-dash', function() {
        const citaId = $(this).data('id');
        $('#assign_guia_cita_id').val(citaId);
        
        cargarGuiasDisponibles();
        cargarGuiasAsignadosCita(citaId);
        
        $('#modalAsignarGuiaDashboard').modal('show');
    });

    // Evento: Enviar formulario de asignación de guía
    $('#formAsignarGuiaDashboard').on('submit', function(e) {
        e.preventDefault();
        const citaId = $('#assign_guia_cita_id').val();
        const $submitBtn = $(this).find('button[type="submit"]');
        
        $submitBtn.prop('disabled', true).text('Guardando...');

        $.ajax({
            url: 'controllers/CitaController.php?action=asignar_guia',
            method: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response) {
                $submitBtn.prop('disabled', false).text('Asignar Guía');
                if (response.status === 'success') {
                    // Limpiar select
                    $('#selectGuiaDashboard').val('');
                    // Recargar guías asignados en el modal
                    cargarGuiasAsignadosCita(citaId);
                    // Recargar tabla de citas de hoy
                    cargarCitasHoy();
                } else {
                    alert(response.message);
                }
            },
            error: function() {
                $submitBtn.prop('disabled', false).text('Asignar Guía');
                alert('Error al realizar la asignación de guía.');
            }
        });
    });

    // Evento: Quitar guía asignado
    $(document).on('click', '.btn-quitar-guia-dash', function() {
        const citaId = $(this).data('cita-id');
        const personalId = $(this).data('guia-id');
        const $btn = $(this);

        if (!confirm('¿Está seguro de remover a este guía de la visita guiada?')) {
            return;
        }

        $btn.prop('disabled', true).text('Removiendo...');

        $.ajax({
            url: 'controllers/CitaController.php?action=desasignar_guia',
            method: 'POST',
            data: { cita_id: citaId, personal_id: personalId },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    cargarGuiasAsignadosCita(citaId);
                    cargarCitasHoy();
                } else {
                    $btn.prop('disabled', false).text('✕ Quitar');
                    alert(response.message);
                }
            },
            error: function() {
                $btn.prop('disabled', false).text('✕ Quitar');
                alert('Error al remover la asignación de guía.');
            }
        });
    });

    // Evento: Iniciar recorrido
    $(document).on('click', '.btn-empezar-recorrido', function() {
        const citaId = $(this).data('id');
        const $btn = $(this);

        if (!confirm('¿Desea iniciar el recorrido guiado para esta cita? Se activará el contador de 2 horas.')) {
            return;
        }

        $btn.prop('disabled', true).text('Iniciando...');

        $.ajax({
            url: 'controllers/CitaController.php?action=empezar_recorrido',
            method: 'POST',
            data: { cita_id: citaId },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    cargarCitasHoy();
                } else {
                    $btn.prop('disabled', false).text('▶ Empezar Recorrido');
                    alert(response.message || 'Error al iniciar el recorrido.');
                }
            },
            error: function(xhr) {
                $btn.prop('disabled', false).text('▶ Empezar Recorrido');
                const msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Error al comunicarse con el servidor.';
                alert(msg);
            }
        });
    });

    // Evento: Terminar recorrido manualmente
    $(document).on('click', '.btn-terminar-recorrido', function() {
        const citaId = $(this).data('id');
        const $btn = $(this);

        if (!confirm('¿Está seguro de dar por finalizado este recorrido guiado?')) {
            return;
        }

        $btn.prop('disabled', true).text('Terminando...');

        $.ajax({
            url: 'controllers/CitaController.php?action=terminar_recorrido',
            method: 'POST',
            data: { cita_id: citaId },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    cargarCitasHoy();
                } else {
                    $btn.prop('disabled', false).text('⏹ Terminar');
                    alert(response.message || 'Error al finalizar el recorrido.');
                }
            },
            error: function(xhr) {
                $btn.prop('disabled', false).text('⏹ Terminar');
                const msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Error al comunicarse con el servidor.';
                alert(msg);
            }
        });
    });

    // Cargar los datos al iniciar la página
    $(document).ready(function() {
        // Siempre cargar citas de hoy si el widget existe en el DOM
        if ($('#tablaCitasHoyBody').length > 0) {
            cargarCitasHoy();
        }
        // Cargar datos del calendario y estadísticas (requiere FullCalendar)
        cargarDatosDashboard();
    });
})();

