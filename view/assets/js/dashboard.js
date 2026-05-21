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
})();
