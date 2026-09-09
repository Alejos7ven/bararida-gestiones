/**
 * Validaciones de Frontend con jQuery Validate
 * Formulario de Consulta de Reportes y Estadísticas
 */

$(document).ready(function() {
    if ($('#formFiltroReportes').length) {
        $('#formFiltroReportes').validate({
            rules: {
                seccion: {
                    required: true
                },
                filtro_tiempo: {
                    required: true
                },
                fecha_dia: {
                    required: function() {
                        return $('#filtroTiempo').val() === 'dia';
                    },
                    date: true
                },
                mes: {
                    required: function() {
                        return $('#filtroTiempo').val() === 'mes';
                    },
                    digits: true,
                    min: 1,
                    max: 12
                },
                anio: {
                    required: function() {
                        return $('#filtroTiempo').val() === 'mes';
                    },
                    digits: true,
                    min: 1900,
                    max: 2100
                },
                fecha_anio: {
                    required: function() {
                        return $('#filtroTiempo').val() === 'anio';
                    },
                    digits: true,
                    min: 1900,
                    max: 2100
                }
            },
            messages: {
                seccion: {
                    required: "Debe seleccionar un tipo de informe."
                },
                filtro_tiempo: {
                    required: "Debe seleccionar una modalidad temporal."
                },
                fecha_dia: {
                    required: "Seleccione una fecha válida para el día a consultar.",
                    date: "Ingrese una fecha válida."
                },
                mes: {
                    required: "Seleccione el mes para la consulta."
                },
                anio: {
                    required: "Seleccione el año para la consulta."
                },
                fecha_anio: {
                    required: "Seleccione el año correspondiente a consultar.",
                    digits: "El año debe ser numérico.",
                    min: "El año no puede ser anterior a 1900.",
                    max: "El año no puede ser superior a 2100."
                }
            },
            errorElement: "div",
            errorPlacement: function(error, element) {
                error.addClass("invalid-feedback");
                if (element.parent(".input-group").length) {
                    error.insertAfter(element.parent());
                } else {
                    error.insertAfter(element);
                }
            },
            highlight: function(element, errorClass, validClass) {
                $(element).addClass("is-invalid").removeClass("is-valid");
            },
            unhighlight: function(element, errorClass, validClass) {
                $(element).removeClass("is-invalid").addClass("is-valid");
            },
            submitHandler: function(form, event) {
                event.preventDefault();
                if (typeof window.ejecutarConsultaReporte === 'function') {
                    window.ejecutarConsultaReporte();
                }
                return false;
            }
        });
    }
});
