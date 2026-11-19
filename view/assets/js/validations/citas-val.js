$(document).ready(function() {
    // Registrar métodos de validación de teléfono y correo si no existen
    if (!$.validator.methods.formatoTelefono) {
        $.validator.addMethod("formatoTelefono", function(value, element) {
            if (this.optional(element)) return true;
            const clean = value.replace(/[^0-9]/g, '');
            return !/[a-zA-Z]/.test(value) && clean.length >= 7 && clean.length <= 15;
        }, "El teléfono debe contener solo números (entre 7 y 15 dígitos).");
    }

    if (!$.validator.methods.formatoCorreo) {
        $.validator.addMethod("formatoCorreo", function(value, element) {
            if (this.optional(element)) return true;
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
        }, "El correo electrónico no tiene un formato válido (ej. contacto@ejemplo.com).");
    }

    $.validator.addClassRules("input-telefono-val", {
        required: false,
        formatoTelefono: true,
        maxlength: 30
    });

    $.validator.addClassRules("input-correo-val", {
        required: false,
        formatoCorreo: true,
        maxlength: 30
    });

    // Formulario de Creación de Cita (Wizard Multi-paso)
    if ($('#formCrearCita').length) {
        $('#formCrearCita').validate({
            ignore: ":hidden, :disabled",
            rules: {
                // Paso 2 (Creación de Institución si corresponde)
                tipo_documento: {
                    required: true,
                    maxlength: 10
                },
                documento: {
                    required: true,
                    digits: true,
                    minlength: 6,
                    maxlength: 15
                },
                tipo_documento_inst: {
                    required: true,
                    maxlength: 10
                },
                documento_inst: {
                    required: true,
                    digits: true,
                    minlength: 6,
                    maxlength: 15
                },
                nombre: {
                    required: true,
                    minlength: 3,
                    maxlength: 50,
                    noInicioEspacio: true
                },
                nombre_completo: {
                    required: true,
                    minlength: 3,
                    maxlength: 50,
                    noInicioEspacio: true
                },
                tipo_organizacion: {
                    required: true,
                    maxlength: 20
                },
                tipo_gestion: {
                    required: true,
                    maxlength: 20
                },
                nueva_sede_detalles: {
                    required: false,
                    maxlength: 150
                },
                
                // Paso 3 (Detalles de la Cita)
                tipo: {
                    required: true,
                    maxlength: 25
                },
                fecha_visita: {
                    required: true
                },
                hora_inicio: {
                    required: true
                },
                metodo_pago: {
                    required: true,
                    maxlength: 25
                },
                pago_estado: {
                    required: true,
                    maxlength: 20
                }
            },
            messages: {
                documento: {
                    required: "El RIF/Cédula es obligatorio.",
                    digits: "El RIF/Cédula debe contener solo números.",
                    minlength: "Debe tener al menos 6 dígitos.",
                    maxlength: "No puede exceder los 15 dígitos."
                },
                documento_inst: {
                    required: "El RIF/Cédula es obligatorio.",
                    digits: "El RIF/Cédula debe contener solo números.",
                    minlength: "Debe tener al menos 6 dígitos.",
                    maxlength: "No puede exceder los 15 dígitos."
                },
                nombre: {
                    required: "La Razón Social o Nombre Oficial es requerido.",
                    minlength: "La Razón Social debe tener al menos 3 caracteres.",
                    maxlength: "La Razón Social no puede superar los 50 caracteres.",
                    noInicioEspacio: "No puede iniciar con espacios ni estar vacío."
                },
                nombre_completo: {
                    required: "La Razón Social o Nombre Oficial es requerido.",
                    minlength: "La Razón Social debe tener al menos 3 caracteres.",
                    maxlength: "La Razón Social no puede superar los 50 caracteres.",
                    noInicioEspacio: "No puede iniciar con espacios ni estar vacío."
                },
                tipo_organizacion: {
                    required: "Debe seleccionar un tipo de organización.",
                    maxlength: "El tipo de organización no puede superar los 20 caracteres."
                },
                tipo_gestion: {
                    required: "Debe seleccionar el tipo de gestión.",
                    maxlength: "El tipo de gestión no puede superar los 20 caracteres."
                },
                parroquia_id: {
                    required: "Debe seleccionar la parroquia."
                },
                tipo: {
                    required: "Debe seleccionar el tipo de cita.",
                    maxlength: "El tipo de cita no puede exceder los 25 caracteres."
                },
                fecha_visita: {
                    required: "Debe seleccionar una fecha para la visita guiada."
                },
                hora_inicio: {
                    required: "Debe definir la hora de inicio de la visita."
                },
                metodo_pago: {
                    required: "Debe seleccionar el método de pago.",
                    maxlength: "El método de pago no puede exceder los 25 caracteres."
                },
                pago_estado: {
                    required: "Debe seleccionar el estado del pago.",
                    maxlength: "El estado de pago no puede exceder los 20 caracteres."
                }
            }
        });
    }

    // Formulario de Edición de Cita
    if ($('#formEditarCita').length) {
        $('#formEditarCita').validate({
            rules: {
                sede_id: {
                    required: false
                },
                tipo: {
                    required: true,
                    maxlength: 25
                },
                recorrido_id: {
                    required: false
                },
                fecha_visita: {
                    required: true
                },
                hora_inicio: {
                    required: true
                },
                metodo_pago: {
                    required: true,
                    maxlength: 25
                },
                pago_estado: {
                    required: true,
                    maxlength: 20
                },
                estatus: {
                    required: true,
                    maxlength: 20
                }
            },
            messages: {
                tipo: {
                    required: "Debe seleccionar el tipo de cita.",
                    maxlength: "El tipo de cita no puede exceder los 25 caracteres."
                },
                fecha_visita: {
                    required: "Debe seleccionar una fecha para la visita guiada."
                },
                hora_inicio: {
                    required: "Debe definir la hora de inicio de la visita."
                },
                metodo_pago: {
                    required: "Debe seleccionar el método de pago.",
                    maxlength: "El método de pago no puede exceder los 25 caracteres."
                },
                pago_estado: {
                    required: "Debe seleccionar el estado del pago.",
                    maxlength: "El estado de pago no puede exceder los 20 caracteres."
                },
                estatus: {
                    required: "Debe seleccionar el estatus de la cita.",
                    maxlength: "El estatus no puede exceder los 20 caracteres."
                }
            }
        });
    }
});
