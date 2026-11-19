$(document).ready(function() {
    // Método personalizado para verificar que la fecha no sea futura
    if (!$.validator.methods.fechaNoFutura) {
        $.validator.addMethod("fechaNoFutura", function(value, element) {
            if (this.optional(element)) return true;
            const inputDate = new Date(value + 'T00:00:00');
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            return inputDate <= today;
        }, "La fecha de nacimiento no puede ser futura (máximo la fecha actual).");
    }

    // Método personalizado para verificar que solo contenga letras (sin números ni iniciar con espacios)
    if (!$.validator.methods.soloLetras) {
        $.validator.addMethod("soloLetras", function(value, element) {
            if (this.optional(element)) return true;
            if (typeof value !== 'string') return false;
            if ($.trim(value) === '') return false;
            if (/^\s/.test(value)) return false;
            if (/\d/.test(value)) return false;
            return /^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ][a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s\.\'\-]*$/.test(value);
        }, "Solo se permiten letras (no puede iniciar con espacios ni contener números).");
    }

    // Formulario de Creación de Visitante
    if ($('#formCrearVisitante').length) {
        $('#formCrearVisitante').validate({
            rules: {
                cedula: {
                    required: false,
                    digits: true,
                    minlength: 5,
                    maxlength: 15
                },
                nombres: {
                    required: true,
                    minlength: 2,
                    maxlength: 25,
                    soloLetras: true
                },
                apellidos: {
                    required: true,
                    minlength: 2,
                    maxlength: 25,
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
                    maxlength: "No puede exceder los 15 dígitos."
                },
                nombres: {
                    required: "El nombre es obligatorio.",
                    minlength: "Los nombres deben tener al menos 2 caracteres.",
                    maxlength: "Los nombres no pueden exceder los 25 caracteres.",
                    soloLetras: "Los nombres deben contener solo letras y no pueden iniciar con espacios."
                },
                apellidos: {
                    required: "El apellido es obligatorio.",
                    minlength: "Los apellidos deben tener al menos 2 caracteres.",
                    maxlength: "Los apellidos no pueden exceder los 25 caracteres.",
                    soloLetras: "Los apellidos deben contener solo letras y no pueden iniciar con espacios."
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
                    maxlength: 15
                },
                nombres: {
                    required: true,
                    minlength: 2,
                    maxlength: 25,
                    soloLetras: true
                },
                apellidos: {
                    required: true,
                    minlength: 2,
                    maxlength: 25,
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
                    maxlength: "No puede exceder los 15 dígitos."
                },
                nombres: {
                    required: "El nombre es obligatorio.",
                    minlength: "Los nombres deben tener al menos 2 caracteres.",
                    maxlength: "Los nombres no pueden exceder los 25 caracteres.",
                    soloLetras: "Los nombres deben contener solo letras y no pueden iniciar con espacios."
                },
                apellidos: {
                    required: "El apellido es obligatorio.",
                    minlength: "Los apellidos deben tener al menos 2 caracteres.",
                    maxlength: "Los apellidos no pueden exceder los 25 caracteres.",
                    soloLetras: "Los apellidos deben contener solo letras y no pueden iniciar con espacios."
                },
                fecha_nacimiento: {
                    required: "La fecha de nacimiento es obligatoria.",
                    fechaNoFutura: "La fecha de nacimiento no puede ser futura (máximo hoy)."
                }
            }
        });
    }
});
