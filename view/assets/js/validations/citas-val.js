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
        formatoTelefono: true
    });

    $.validator.addClassRules("input-correo-val", {
        required: false,
        formatoCorreo: true
    });

    // Formulario de Creación de Cita (Wizard Multi-paso)
    if ($('#formCrearCita').length) {
        $('#formCrearCita').validate({
            ignore: ":hidden, :disabled",
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
                tipo_documento_inst: {
                    required: true
                },
                documento_inst: {
                    required: true,
                    digits: true,
                    minlength: 6,
                    maxlength: 12
                },
                nombre: {
                    required: true,
                    minlength: 3
                },
                nombre_completo: {
                    required: true,
                    minlength: 3
                },
                tipo_organizacion: {
                    required: true
                },
                tipo_gestion: {
                    required: true
                },
                
                // Paso 3 (Detalles de la Cita)
                fecha_visita: {
                    required: true
                },
                hora_inicio: {
                    required: true
                },
                metodo_pago: {
                    required: true
                },
                pago_estado: {
                    required: true
                }
            },
            messages: {
                documento: {
                    required: "El RIF/Cédula es obligatorio.",
                    digits: "El RIF/Cédula debe contener solo números.",
                    minlength: "Debe tener al menos 6 dígitos.",
                    maxlength: "No puede exceder los 12 dígitos."
                },
                documento_inst: {
                    required: "El RIF/Cédula es obligatorio.",
                    digits: "El RIF/Cédula debe contener solo números.",
                    minlength: "Debe tener al menos 6 dígitos.",
                    maxlength: "No puede exceder los 12 dígitos."
                },
                nombre: {
                    required: "La Razón Social o Nombre Oficial es requerido.",
                    minlength: "La Razón Social debe tener al menos 3 caracteres."
                },
                nombre_completo: {
                    required: "La Razón Social o Nombre Oficial es requerido.",
                    minlength: "La Razón Social debe tener al menos 3 caracteres."
                },
                tipo_organizacion: {
                    required: "Debe seleccionar un tipo de organización."
                },
                tipo_gestion: {
                    required: "Debe seleccionar el tipo de gestión."
                },
                parroquia_id: {
                    required: "Debe seleccionar la parroquia."
                },
                fecha_visita: {
                    required: "Debe seleccionar una fecha para la visita guiada."
                },
                hora_inicio: {
                    required: "Debe definir la hora de inicio de la visita."
                },
                metodo_pago: {
                    required: "Debe seleccionar el método de pago."
                },
                pago_estado: {
                    required: "Debe seleccionar el estado del pago."
                }
            }
        });
    }

    // Formulario de Edición de Cita
    if ($('#formEditarCita').length) {
        $('#formEditarCita').validate({
            rules: {
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
                    required: true
                },
                pago_estado: {
                    required: true
                },
                estatus: {
                    required: true
                }
            },
            messages: {
                fecha_visita: {
                    required: "Debe seleccionar una fecha para la visita guiada."
                },
                hora_inicio: {
                    required: "Debe definir la hora de inicio de la visita."
                },
                metodo_pago: {
                    required: "Debe seleccionar el método de pago."
                }
            }
        });
    }
});
