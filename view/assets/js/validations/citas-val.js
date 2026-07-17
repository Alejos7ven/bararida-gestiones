$(document).ready(function() {
    // Formulario de Creación de Cita (Wizard Multi-paso)
    if ($('#formCrearCita').length) {
        $('#formCrearCita').validate({
            // Por defecto, ignora inputs ocultos (ideal para el paso 2 que puede estar oculto)
            ignore: ":hidden",
            rules: {
                // Paso 2 (Creación de Institución si corresponde)
                tipo_documento: {
                    required: true
                },
                documento: {
                    required: true,
                    digits: true,
                    minlength: 6,
                    maxlength: 12
                },
                nombre: {
                    required: true,
                    minlength: 3
                },
                tipo_organizacion: {
                    required: true
                },
                tipo_gestion: {
                    required: true
                },
                parroquia_id: {
                    required: true
                },
                ciudad_id: {
                    required: true
                },
                
                // Paso 3 (Detalles de la Cita)
                fecha_visita: {
                    required: true
                },
                hora_inicio: {
                    required: true
                },
                factura_paga: {
                    required: true
                }
            },
            messages: {
                documento: {
                    digits: "El RIF/Cédula debe contener solo números.",
                    minlength: "Debe tener al menos 6 dígitos.",
                    maxlength: "No puede exceder los 12 dígitos."
                },
                nombre: {
                    minlength: "La Razón Social debe tener al menos 3 caracteres."
                },
                fecha_visita: {
                    required: "Debe seleccionar una fecha para la visita guiada."
                },
                hora_inicio: {
                    required: "Debe definir la hora de inicio de la visita."
                }
            }
        });
    }

    // Formulario de Edición de Cita
    if ($('#formEditarCita').length) {
        $('#formEditarCita').validate({
            rules: {
                recorrido_id: {
                    required: false // Puede ser nulo
                },
                fecha_visita: {
                    required: true
                },
                hora_inicio: {
                    required: true
                },
                factura_paga: {
                    required: true
                }
            },
            messages: {
                fecha_visita: {
                    required: "Debe seleccionar una fecha para la visita guiada."
                },
                hora_inicio: {
                    required: "Debe definir la hora de inicio de la visita."
                }
            }
        });
    }
});
