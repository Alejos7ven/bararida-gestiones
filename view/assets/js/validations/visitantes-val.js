$(document).ready(function() {
    // Método personalizado para verificar que la fecha no sea futura
    $.validator.addMethod("fechaNoFutura", function(value, element) {
        if (this.optional(element)) return true;
        const inputDate = new Date(value + 'T00:00:00');
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        return inputDate <= today;
    }, "La fecha de nacimiento no puede ser futura (máximo la fecha actual).");

    // Método personalizado para verificar que solo contenga letras (sin números)
    $.validator.addMethod("soloLetras", function(value, element) {
        if (this.optional(element)) return true;
        return /^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s\.\'\-]+$/.test(value);
    }, "Solo se permiten letras (sin números ni caracteres especiales).");

    // Formulario de Creación de Visitante
    if ($('#formCrearVisitante').length) {
        $('#formCrearVisitante').validate({
            rules: {
                cedula: {
                    required: false,
                    digits: true,
                    minlength: 5,
                    maxlength: 12
                },
                nombres: {
                    required: true,
                    minlength: 2,
                    soloLetras: true
                },
                apellidos: {
                    required: true,
                    minlength: 2,
                    soloLetras: true
                },
                fecha_nacimiento: {
                    required: true,
                    fechaNoFutura: true
                }
            },
            messages: {
                cedula: {
                    digits: "El documento debe contener solo números.",
                    minlength: "Debe tener al menos 5 dígitos.",
                    maxlength: "No puede exceder los 12 dígitos."
                },
                nombres: {
                    required: "El nombre es obligatorio.",
                    minlength: "Los nombres deben tener al menos 2 caracteres.",
                    soloLetras: "Los nombres no pueden contener números."
                },
                apellidos: {
                    required: "El apellido es obligatorio.",
                    minlength: "Los apellidos deben tener al menos 2 caracteres.",
                    soloLetras: "Los apellidos no pueden contener números."
                },
                fecha_nacimiento: {
                    required: "La fecha de nacimiento es obligatoria.",
                    fechaNoFutura: "La fecha de nacimiento no puede ser futura (máximo hoy)."
                }
            }
        });
    }

    // Formulario de Edición de Visitante
    if ($('#formEditarVisitante').length) {
        $('#formEditarVisitante').validate({
            rules: {
                cedula: {
                    required: false,
                    digits: true,
                    minlength: 5,
                    maxlength: 12
                },
                nombres: {
                    required: true,
                    minlength: 2,
                    soloLetras: true
                },
                apellidos: {
                    required: true,
                    minlength: 2,
                    soloLetras: true
                },
                fecha_nacimiento: {
                    required: true,
                    fechaNoFutura: true
                }
            },
            messages: {
                cedula: {
                    digits: "El documento debe contener solo números.",
                    minlength: "Debe tener al menos 5 dígitos.",
                    maxlength: "No puede exceder los 12 dígitos."
                },
                nombres: {
                    required: "El nombre es obligatorio.",
                    minlength: "Los nombres deben tener al menos 2 caracteres.",
                    soloLetras: "Los nombres no pueden contener números."
                },
                apellidos: {
                    required: "El apellido es obligatorio.",
                    minlength: "Los apellidos deben tener al menos 2 caracteres.",
                    soloLetras: "Los apellidos no pueden contener números."
                },
                fecha_nacimiento: {
                    required: "La fecha de nacimiento es obligatoria.",
                    fechaNoFutura: "La fecha de nacimiento no puede ser futura (máximo hoy)."
                }
            }
        });
    }
});
