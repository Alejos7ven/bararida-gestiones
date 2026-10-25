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
    let cacheRecorridosDisponibles = [];
    let chartInstance = null;
    let fullCalendarInstance = null;

    function cargarDatosDashboard() {
        // 1. Cargar estadísticas para la gráfica de visitantes (si existe el canvas)
        if ($('#chartVisitantes').length > 0) {
            $.ajax({
                url: 'controllers/CitaController.php?action=obtener_datos_dashboard',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        cacheEstadisticas = response.estadisticas || [];
                        renderizarGraficaEstadisticas();
                    } else {
                        console.error('Error al cargar estadísticas:', response.message);
                    }
                },
                error: function(err) {
                    console.error('Error de red al cargar estadísticas del dashboard.', err);
                }
            });
        }

        // 2. Cargar citas completas para FullCalendar (solo si el calendario está en el DOM, no para guías)
        if ($('#dashboardFullCalendar').length > 0) {
            $.ajax({
                url: 'controllers/CitaController.php?action=listar',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        cacheCitas = response.data || [];
                        inicializarFullCalendar(cacheCitas);
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
                        $body.append('<tr><td colspan="8" class="text-center text-muted py-4">No hay visitas guiadas programadas para el día de hoy.</td></tr>');
                        $wrapper.fadeIn(300);
                        return;
                    }

                    citas.forEach(function(c) {
                        const hora = formatTime(c.hora_inicio);
                        const userRol = ($('#quickAccessCard').data('user-rol') || '').toString().toLowerCase().trim();
                        const esAdmin = userRol === 'superadmin' || userRol === 'admin';
                        const esMiAsignacion = parseInt(c.es_mi_asignacion, 10) === 1;
                        const tieneGuia = !!c.guias_asignados;
                        const puedeGestionarRecorrido = esAdmin || esMiAsignacion;

                        // 1. Columna Guía(s) - Solo píldoras con los nombres de los guías
                        let guiasHtml = '';
                        if (c.guias_asignados) {
                            const listado = c.guias_asignados.split(',').map(g => g.trim());
                            guiasHtml = listado.map(nombre => `<span class="badge bg-success mb-1 d-inline-block text-truncate shadow-xs" style="max-width: 140px; font-size:0.75rem; font-weight:600;" title="${nombre}">${nombre}</span>`).join('<br>');
                        } else {
                            guiasHtml = `<span class="badge bg-light text-muted border" style="font-size:0.75rem;">Sin asignar</span>`;
                        }

                        // 2. Columna Estado - Badges descriptivos y timer dinámico si está en recorrido
                        let estadoHtml = '';
                        if (c.estatus === 'en_recorrido') {
                            const segRestantes = parseInt(c.segundos_restantes, 10) || 0;
                            const h = Math.floor(segRestantes / 3600);
                            const m = Math.floor((segRestantes % 3600) / 60);
                            const s = segRestantes % 60;
                            const formatInit = (h > 0 ? (h < 10 ? '0' + h : h) + ':' : '') + 
                                               (m < 10 ? '0' + m : m) + ':' + 
                                               (s < 10 ? '0' + s : s);

                            estadoHtml = `
                                <div>
                                    <span class="badge bg-primary d-inline-flex align-items-center gap-1 shadow-sm" style="font-size:0.75rem; padding: 0.35em 0.65em;">
                                        <span class="spinner-grow spinner-grow-sm text-light" style="width:0.4rem;height:0.4rem;" role="status"></span>
                                        En Recorrido
                                    </span>
                                    <div class="mt-1 font-monospace text-primary fw-bold timer-recorrido" data-restantes="${segRestantes}" style="font-size:0.72rem;">
                                        Restante: <span class="tiempo-display">${formatInit}</span>
                                    </div>
                                </div>
                            `;
                        } else if (c.estatus === 'completado') {
                            estadoHtml = `
                                <span class="badge bg-secondary d-inline-flex align-items-center gap-1" style="font-size:0.75rem; padding: 0.35em 0.65em;">
                                    <svg viewBox="0 0 24 24" style="width:12px;height:12px;fill:currentColor;"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg> Completado
                                </span>
                            `;
                        } else if (c.estatus === 'cancelado') {
                            estadoHtml = `<span class="badge bg-danger" style="font-size:0.75rem; padding: 0.35em 0.65em;">Cancelado</span>`;
                        } else if (c.estatus === 'aprobado') {
                            estadoHtml = `<span class="badge bg-success" style="font-size:0.75rem; padding: 0.35em 0.65em;">Aprobada</span>`;
                        } else {
                            estadoHtml = `<span class="badge bg-warning text-dark" style="font-size:0.75rem; padding: 0.35em 0.65em;">Pendiente</span>`;
                        }

                        // 3. Columna Acciones - Botones claros, estilizados y ubicados exclusivamente aquí
                        let accionesBtns = [];

                        // Botón Asignar / Gestionar Guía (Solo Administradores / SuperAdmin)
                        if (esAdmin) {
                            const asigTitle = tieneGuia ? 'Gestionar guías asignados' : 'Asignar guía a esta cita';
                            const asigIcon = tieneGuia
                                ? '<svg viewBox="0 0 24 24" style="width:15px;height:15px;fill:currentColor;"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>'
                                : '<svg viewBox="0 0 24 24" style="width:15px;height:15px;fill:currentColor;"><path d="M15 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm-9-2V7H4v3H1v2h3v3h2v-3h3v-2H6zm9 4c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>';
                            
                            accionesBtns.push(`
                                <button class="btn btn-sm btn-outline-secondary btn-gestionar-guia-dash d-inline-flex align-items-center justify-content-center" data-id="${c.id}" title="${asigTitle}" style="border-radius: 8px; width: 32px; height: 32px; padding: 0;">
                                    ${asigIcon}
                                </button>
                            `);
                        }

                        // Botón Iniciar Recorrido / Aceptar Visita (Para el Guía Asignado o Admin)
                        if (tieneGuia && puedeGestionarRecorrido && c.estatus !== 'en_recorrido' && c.estatus !== 'completado' && c.estatus !== 'cancelado') {
                            const btnTexto = esAdmin ? 'Iniciar' : 'Iniciar Recorrido';
                            accionesBtns.push(`
                                <button class="btn btn-sm btn-success btn-empezar-recorrido d-inline-flex align-items-center gap-1.5 py-1 px-2.5 shadow-sm fw-bold" data-id="${c.id}" title="Iniciar / Aceptar recorrido guiado" style="border-radius: 8px; font-size: 0.78rem; white-space: nowrap;">
                                    <svg viewBox="0 0 24 24" style="width:14px;height:14px;fill:currentColor;"><path d="M8 5v14l11-7z"/></svg>
                                    ${btnTexto}
                                </button>
                            `);
                        }

                        // Botón Finalizar Recorrido (Para el Guía Asignado o Admin)
                        if (c.estatus === 'en_recorrido' && puedeGestionarRecorrido) {
                            const btnTexto = esAdmin ? 'Finalizar' : 'Finalizar Recorrido';
                            accionesBtns.push(`
                                <button class="btn btn-sm btn-danger btn-terminar-recorrido d-inline-flex align-items-center gap-1.5 py-1 px-2.5 shadow-sm fw-bold" data-id="${c.id}" title="Finalizar recorrido guiado" style="border-radius: 8px; font-size: 0.78rem; white-space: nowrap;">
                                    <svg viewBox="0 0 24 24" style="width:14px;height:14px;fill:currentColor;"><path d="M6 6h12v12H6z"/></svg>
                                    ${btnTexto}
                                </button>
                            `);
                        }

                        // Si ya está completado
                        if (c.estatus === 'completado') {
                            accionesBtns.push(`
                                <span class="badge bg-light text-secondary border px-2 py-1" style="font-size:0.75rem;">Visita Finalizada</span>
                            `);
                        }

                        let accionesHtml = '';
                        if (accionesBtns.length > 0) {
                            accionesHtml = `<div class="d-flex align-items-center justify-content-center flex-wrap gap-1">${accionesBtns.join('')}</div>`;
                        } else {
                            accionesHtml = `<span class="text-muted" style="font-size:0.8rem;">—</span>`;
                        }

                        // 4. Columna Recorrido (Visualización interactiva y asignación)
                        let recorridoHtml = '';
                        const recId = c.recorrido_id ? parseInt(c.recorrido_id, 10) : null;
                        const recNombre = c.recorrido_nombre || '';

                        if (recNombre && recId) {
                            recorridoHtml = `
                                <div class="d-inline-flex align-items-center justify-content-center flex-wrap gap-1">
                                    <button type="button" class="btn btn-sm btn-outline-success btn-ver-recorrido-mapa py-0.5 px-2 d-inline-flex align-items-center gap-1 shadow-2xs fw-medium" data-recorrido-id="${recId}" title="Ver trazado en el mapa interactivo">
                                        <svg viewBox="0 0 24 24" style="width:13px;height:13px;fill:currentColor;"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
                                        <span>${recNombre}</span>
                                    </button>
                                    ${puedeGestionarRecorrido ? `
                                        <button type="button" class="btn btn-sm btn-link text-success p-0 ms-1 btn-abrir-asignar-recorrido" data-id="${c.id}" data-institucion="${c.razon_social}" data-recorrido-id="${recId}" title="Cambiar recorrido">
                                            <svg viewBox="0 0 24 24" style="width:14px;height:14px;fill:currentColor;"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
                                        </button>
                                    ` : ''}
                                </div>
                            `;
                        } else {
                            if (puedeGestionarRecorrido) {
                                recorridoHtml = `
                                    <button type="button" class="btn btn-sm btn-outline-primary btn-abrir-asignar-recorrido py-0.5 px-2 d-inline-flex align-items-center gap-1 shadow-2xs fw-bold" data-id="${c.id}" data-institucion="${c.razon_social}" data-recorrido-id="" style="border-radius:8px; font-size:0.75rem;" title="Asignar recorrido a esta cita">
                                        <svg viewBox="0 0 24 24" style="width:12px;height:12px;fill:currentColor;"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                                        Asignar
                                    </button>
                                `;
                            } else {
                                recorridoHtml = `<span class="badge bg-light text-muted border" style="font-size:0.75rem;">Sin recorrido</span>`;
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
                                <td>${recorridoHtml}</td>
                                <td><span class="badge bg-info text-dark" style="font-size:0.75rem; font-weight:600;">${c.total_visitantes}</span></td>
                                <td>${guiasHtml}</td>
                                <td>${estadoHtml}</td>
                                <td>${accionesHtml}</td>
                            </tr>
                        `;
                        $body.append(rowHtml);
                    });

                    iniciarContadorRecorridos();
                    $wrapper.fadeIn(300);
                } else {
                    $body.append(`<tr><td colspan="8" class="text-center text-danger py-4">No se pudo cargar la agenda del día: ${response.message}</td></tr>`);
                    $wrapper.fadeIn(300);
                }
            },
            error: function() {
                $loader.hide();
                $body.append('<tr><td colspan="8" class="text-center text-danger py-4">Error al cargar la agenda de hoy.</td></tr>');
                $wrapper.fadeIn(300);
            }
        });
    }

    // Cargar guías disponibles en el sistema con validación de disponibilidad por fecha
    function cargarGuiasDisponibles(callback, citaId) {
        const url = citaId 
            ? `controllers/CitaController.php?action=listar_guias&cita_id=${citaId}` 
            : 'controllers/CitaController.php?action=listar_guias';

        const $select = $('#selectGuiaDashboard');
        $select.empty().append('<option value="">Cargando guías...</option>');

        $.ajax({
            url: url,
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    allGuias = response.data || [];
                    
                    $select.empty().append('<option value="">Seleccione un guía...</option>');
                    allGuias.forEach(function(g) {
                        const nombreMostrar = g.nombre_completo || `${g.nombre || ''} ${g.apellido || ''}`.trim();
                        const estaDisponible = g.disponible !== false;
                        if (estaDisponible) {
                            $select.append(`<option value="${g.id}">${nombreMostrar}</option>`);
                        } else {
                            $select.append(`<option value="${g.id}" disabled style="color:#94a3b8; background-color:#f1f5f9;">${nombreMostrar} (Ocupado - Cita activa hoy)</option>`);
                        }
                    });

                    if (callback) callback(allGuias);
                } else {
                    console.error('Error al cargar guías:', response.message);
                }
            },
            error: function(xhr) {
                console.error('Error de red al cargar guías:', xhr);
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
                        const nombreMostrar = g.nombre_completo || `${g.nombre || ''} ${g.apellido || ''}`.trim();
                        const itemHtml = `
                            <div class="d-flex justify-content-between align-items-center p-2 rounded border bg-white mb-2 shadow-xs">
                                <div>
                                    <strong>${nombreMostrar}</strong> 
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
            error: function(xhr) {
                const msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Error al cargar asignaciones.';
                $container.empty().append(`<div class="text-center text-danger py-2">${msg}</div>`);
            }
        });
    }

    // Evento: Abrir modal de asignación de guía
    $(document).on('click', '.btn-gestionar-guia-dash', function() {
        const citaId = $(this).data('id');
        $('#assign_guia_cita_id').val(citaId);
        
        cargarGuiasDisponibles(null, citaId);
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
                    // Recargar guías disponibles con disponibilidad actualizada
                    cargarGuiasDisponibles(null, citaId);
                    // Recargar guías asignados en el modal
                    cargarGuiasAsignadosCita(citaId);
                    // Recargar tabla de citas de hoy
                    cargarCitasHoy();
                } else {
                    alert(response.message);
                }
            },
            error: function(xhr) {
                $submitBtn.prop('disabled', false).text('Asignar Guía');
                const msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Error al realizar la asignación de guía.';
                alert(msg);
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
                    cargarGuiasDisponibles(null, citaId);
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

    // =========================================================================
    // LÓGICA DE ASIGNACIÓN INTERACTIVA DE RECORRIDOS (ADMIN Y GUÍA ASIGNADO)
    // =========================================================================

    // Cargar todos los recorridos disponibles en el sistema para el modal
    function cargarRecorridosParaModal(callback) {
        if (cacheRecorridosDisponibles.length > 0) {
            if (callback) callback(cacheRecorridosDisponibles);
            return;
        }

        $.ajax({
            url: 'controllers/RecorridoController.php?action=listar_recorridos',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    cacheRecorridosDisponibles = response.data || [];
                    if (callback) callback(cacheRecorridosDisponibles);
                } else {
                    console.error('Error al cargar recorridos:', response.message);
                }
            },
            error: function(xhr) {
                console.error('Error de red al cargar lista de recorridos:', xhr);
            }
        });
    }

    // Poblar el selector del modal con los recorridos
    function poblarSelectRecorridosModal(recorridoActualId) {
        const $select = $('#selectRecorridoModal');
        $select.empty().append('<option value="">Seleccionar Recorrido</option>');

        cacheRecorridosDisponibles.forEach(function(rec) {
            const paradasCount = (rec.paradas && Array.isArray(rec.paradas)) ? rec.paradas.length : 0;
            const selected = (recorridoActualId && parseInt(rec.id, 10) === parseInt(recorridoActualId, 10)) ? 'selected' : '';
            $select.append(`<option value="${rec.id}" ${selected}>${rec.nombre} (${paradasCount} paradas)</option>`);
        });

        actualizarPreviewRecorridoModal(recorridoActualId);
    }

    // Actualizar previsualización interactiva de paradas del recorrido y sincronizar con el mapa
    function actualizarPreviewRecorridoModal(recId) {
        const $box = $('#modalRecorridoPreviewBox');
        const $list = $('#previewRecorridoStepsList');
        const $count = $('#previewRecorridoParadasCount');

        if (!recId) {
            $box.hide();
            $list.empty();
            return;
        }

        const rec = cacheRecorridosDisponibles.find(r => parseInt(r.id, 10) === parseInt(recId, 10));
        if (!rec || !rec.paradas || rec.paradas.length === 0) {
            $box.show();
            $count.text('0 paradas');
            $list.html('<span class="text-muted italic">Este recorrido no tiene paradas registradas aún.</span>');
            return;
        }

        $count.text(`${rec.paradas.length} paradas`);
        let stepsHtml = '';
        rec.paradas.forEach(function(p, idx) {
            stepsHtml += `
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-primary rounded-circle" style="width:20px;height:20px;display:flex;align-items:center;justify-content:center;font-size:0.7rem;flex-shrink:0;">${p.orden || (idx + 1)}</span>
                    <span class="fw-semibold text-dark text-truncate">${p.zona_nombre || 'Zona del Parque'}</span>
                </div>
            `;
        });
        $list.html(stepsHtml);
        $box.slideDown(200);

        // Sincronizar en tiempo real el trazado en el módulo interactivo del mapa ("Itinerarios y Zonas")
        if ($('#selectRecorridoDashboard').length > 0) {
            $('#selectRecorridoDashboard').val(recId).trigger('change');
        }
    }

    // Evento: Abrir modal de asignación de recorrido desde la tabla
    $(document).on('click', '.btn-abrir-asignar-recorrido', function() {
        const citaId = $(this).data('id');
        const institucion = $(this).data('institucion') || `Visita #CIT-${citaId}`;
        const recorridoId = $(this).data('recorrido-id') || '';

        $('#modal_recorrido_cita_id').val(citaId);
        $('#modalRecorridoInstitucionText').text(institucion);

        cargarRecorridosParaModal(function() {
            poblarSelectRecorridosModal(recorridoId);
            $('#modalAsignarRecorridoDashboard').modal('show');
        });
    });

    // Evento: Cambio en el select de recorridos del modal
    $(document).on('change', '#selectRecorridoModal', function() {
        const recId = $(this).val();
        actualizarPreviewRecorridoModal(recId);
    });

    // Evento: Ver en mapa desde el modal de previsualización
    $(document).on('click', '#btnPreviewVerEnMapa', function() {
        $('#modalAsignarRecorridoDashboard').modal('hide');
        const mapCard = document.querySelector('.dashboard-map-card');
        if (mapCard) {
            mapCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
            $(mapCard).addClass('map-highlight-pulse');
            setTimeout(() => $(mapCard).removeClass('map-highlight-pulse'), 1800);
        }
    });

    // Evento: Hacer clic en el botón de un recorrido en la tabla para visualizarlo directamente en el mapa
    $(document).on('click', '.btn-ver-recorrido-mapa', function() {
        const recId = $(this).data('recorrido-id');
        if (!recId) return;

        if ($('#selectRecorridoDashboard').length > 0) {
            $('#selectRecorridoDashboard').val(recId).trigger('change');
        }

        const mapCard = document.querySelector('.dashboard-map-card');
        if (mapCard) {
            mapCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
            $(mapCard).addClass('map-highlight-pulse');
            setTimeout(() => $(mapCard).removeClass('map-highlight-pulse'), 1800);
        }
    });

    // Evento: Guardar recorrido asignado a la cita
    $('#formAsignarRecorridoDashboard').on('submit', function(e) {
        e.preventDefault();
        const citaId = $('#modal_recorrido_cita_id').val();
        const recId = $('#selectRecorridoModal').val();
        const $submitBtn = $(this).find('button[type="submit"]');

        $submitBtn.prop('disabled', true).text('Guardando...');

        $.ajax({
            url: 'controllers/CitaController.php?action=asignar_recorrido',
            method: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response) {
                $submitBtn.prop('disabled', false).text('Guardar Recorrido');
                if (response.status === 'success') {
                    $('#modalAsignarRecorridoDashboard').modal('hide');
                    // Recargar tabla de citas para mostrar el nuevo recorrido
                    cargarCitasHoy();
                    // Sincronizar en el mapa interactivo
                    if (recId && $('#selectRecorridoDashboard').length > 0) {
                        $('#selectRecorridoDashboard').val(recId).trigger('change');
                    }
                } else {
                    alert(response.message || 'No se pudo asignar el recorrido.');
                }
            },
            error: function(xhr) {
                $submitBtn.prop('disabled', false).text('Guardar Recorrido');
                const msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Error al guardar el recorrido.';
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

