/**
 * Configuración en Español y Parámetros Globales para DataTables
 * Parque Zoológico y Botánico Bararida
 */
const DATATABLES_ES = {
    processing: "Procesando...",
    search: "Buscar:",
    searchPlaceholder: "Buscar en la tabla...",
    lengthMenu: "Mostrar _MENU_ registros por página",
    info: "Mostrando _START_ a _END_ de _TOTAL_ registros",
    infoEmpty: "Mostrando 0 a 0 de 0 registros",
    infoFiltered: "(filtrado de un total de _MAX_ registros)",
    infoPostFix: "",
    loadingRecords: "Cargando registros...",
    zeroRecords: "No se encontraron registros coincidentes",
    emptyTable: "No hay registros disponibles en la tabla",
    paginate: {
        first: "Primero",
        previous: "&laquo; Anterior",
        next: "Siguiente &raquo;",
        last: "Último"
    },
    aria: {
        sortAscending: ": activar para ordenar la columna de manera ascendente",
        sortDescending: ": activar para ordenar la columna de manera descendente"
    }
};

// Aplicar configuración por defecto a todas las instancias de DataTable en la aplicación
if (typeof jQuery !== 'undefined' && jQuery.fn.dataTable) {
    jQuery.extend(true, jQuery.fn.dataTable.defaults, {
        language: DATATABLES_ES,
        responsive: true,
        pageLength: 10,
        lengthMenu: [[5, 10, 25, 50, 100], [5, 10, 25, 50, 100]],
        autoWidth: false,
        dom: "<'row align-items-center mb-3'<'col-12 col-md-6 d-flex align-items-center gap-2 mb-2 mb-md-0'l><'col-12 col-md-6 d-flex justify-content-md-end justify-content-start'f>>" +
             "<'row'<'col-12'tr>>" +
             "<'row align-items-center mt-3'<'col-12 col-md-5 mb-2 mb-md-0'i><'col-12 col-md-7 d-flex justify-content-md-end justify-content-center'p>>"
    });
}

