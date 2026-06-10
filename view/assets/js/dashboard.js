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
    // LÓGICA DE CALENDARIO Y ESTADÍSTICAS DEL DASHBOARD
    // =========================================================================
    let currentCalendarDate = new Date();
    let cacheCalendario = [];
    let cacheEstadisticas = [];
    let chartInstance = null;

    const meses = [
        "Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio",
        "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"
    ];

    function cargarDatosDashboard() {
        if ($('#calendarGridBody').length === 0) return;

        $.ajax({
            url: 'controllers/CitaController.php?action=obtener_datos_dashboard',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    cacheCalendario = response.calendario || [];
                    cacheEstadisticas = response.estadisticas || [];
                    
                    // Renderizar Calendario
                    renderizarCalendario();
                    
                    // Renderizar Gráfica
                    renderizarGraficaEstadisticas();
                } else {
                    console.error('Error al cargar datos del dashboard:', response.message);
                }
            },
            error: function(err) {
                console.error('Error de red al cargar estadísticas del dashboard.', err);
            }
        });
    }

    function renderizarCalendario() {
        const year = currentCalendarDate.getFullYear();
        const month = currentCalendarDate.getMonth();

        // Actualizar título del mes
        $('#calendarMonthYear').text(`${meses[month]} ${year}`);

        const firstDayIndex = new Date(year, month, 1).getDay(); // 0 = Domingo, 1 = Lunes, etc.
        const totalDays = new Date(year, month + 1, 0).getDate();

        const $grid = $('#calendarGridBody');
        $grid.empty();

        // Rellenar días vacíos previos al día 1 del mes
        for (let i = 0; i < firstDayIndex; i++) {
            $grid.append('<div class="calendar-day empty"></div>');
        }

        const hoy = new Date();
        const hoyString = `${hoy.getFullYear()}-${String(hoy.getMonth() + 1).padStart(2, '0')}-${String(hoy.getDate()).padStart(2, '0')}`;

        // Rellenar los días del mes
        for (let day = 1; day <= totalDays; day++) {
            const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            
            // Buscar si la fecha tiene visitas en cacheCalendario
            const visit = cacheCalendario.find(c => c.fecha_visita === dateStr);
            
            let classList = "calendar-day";
            let badgeHtml = "";
            let tooltipHtml = "";

            if (dateStr === hoyString) {
                classList += " today";
            }

            if (visit) {
                classList += " has-visit";
                const totalInst = parseInt(visit.total_instituciones);
                const textBadge = totalInst === 1 ? "1 Inst." : `${totalInst} Insts.`;
                badgeHtml = `<div class="visit-badge" title="${textBadge}">${textBadge}</div>`;
                
                // Procesar los nombres de las instituciones para mostrarlas en el tooltip nativo
                if (visit.instituciones_nombres) {
                    const nombres = visit.instituciones_nombres.split('||').join('\n• ');
                    tooltipHtml = `title="Visitas programadas:\n• ${nombres}"`;
                }
            }

            $grid.append(`
                <div class="${classList}" data-date="${dateStr}" ${tooltipHtml}>
                    <span class="day-number">${day}</span>
                    ${badgeHtml}
                </div>
            `);
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

    // Inicializar navegación del calendario
    $(document).on('click', '#btnPrevMonth', function() {
        currentCalendarDate.setMonth(currentCalendarDate.getMonth() - 1);
        renderizarCalendario();
    });

    $(document).on('click', '#btnNextMonth', function() {
        currentCalendarDate.setMonth(currentCalendarDate.getMonth() + 1);
        renderizarCalendario();
    });

    // Cargar los datos al iniciar la página
    $(document).ready(function() {
        cargarDatosDashboard();
    });
})();

