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

    // Cargar los datos al iniciar la página
    $(document).ready(function() {
        cargarDatosDashboard();
    });
})();

