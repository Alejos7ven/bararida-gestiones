$(document).ready(function() {

    // Método para validar que no contenga números ni inicie con espacios en el nombre
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

    // Formulario de Creación
    if ($('#formCrearPersonal').length) {
        $('#formCrearPersonal').validate({
            rules: {
                tipo_documento: {
                    required: true
                },
                cedula: {
                    required: true,
                    digits: true,
                    minlength: 6,
                    maxlength: 15
                },
                nombre_completo: {
                    required: true,
                    minlength: 3,
                    maxlength: 50,
                    soloLetras: true
                },
                rol: {
                    required: true
                },
                password: {
                    required: true,
                    minlength: 6,
                    maxlength: 20,
                    noInicioEspacio: true
                }
            },
            messages: {
                tipo_documento: {
                    required: "Seleccione el tipo de documento (V o E)."
                },
                cedula: {
                    required: "El número de documento es obligatorio.",
                    digits: "Por favor, ingrese solo números para la cédula.",
                    minlength: "La cédula debe tener al menos 6 dígitos.",
                    maxlength: "La cédula no puede exceder los 15 dígitos."
                },
                nombre_completo: {
                    required: "El nombre completo es requerido.",
                    minlength: "El nombre completo debe tener al menos 3 caracteres.",
                    maxlength: "El nombre completo no puede superar los 50 caracteres.",
                    soloLetras: "El nombre completo debe contener solo letras y no puede iniciar con espacios."
                },
                password: {
                    required: "La contraseña es requerida.",
                    minlength: "La contraseña debe tener al menos 6 caracteres.",
                    maxlength: "La contraseña no puede exceder los 20 caracteres.",
                    noInicioEspacio: "La contraseña no puede iniciar con espacios ni estar compuesta únicamente por espacios."
                }
            }
        });
    }

    // Formulario de Edición
    if ($('#formEditarPersonal').length) {
        $('#formEditarPersonal').validate({
            rules: {
                tipo_documento: {
                    required: true
                },
                cedula: {
                    required: true,
                    digits: true,
                    minlength: 6,
                    maxlength: 15
                },
                nombre_completo: {
                    required: true,
                    minlength: 3,
                    maxlength: 50,
                    soloLetras: true
                },
                rol: {
                    required: true
                },
                password: {
                    required: false,
                    minlength: 6,
                    maxlength: 20,
                    noInicioEspacio: true
                }
            },
            messages: {
                tipo_documento: {
                    required: "Seleccione el tipo de documento (V o E)."
                },
                cedula: {
                    required: "El número de documento es obligatorio.",
                    digits: "Por favor, ingrese solo números para la cédula.",
                    minlength: "La cédula debe tener al menos 6 dígitos.",
                    maxlength: "La cédula no puede exceder los 15 dígitos."
                },
                nombre_completo: {
                    required: "El nombre completo es requerido.",
                    minlength: "El nombre completo debe tener al menos 3 caracteres.",
                    maxlength: "El nombre completo no puede superar los 50 caracteres.",
                    soloLetras: "El nombre completo debe contener solo letras y no puede iniciar con espacios."
                },
                password: {
                    minlength: "La contraseña debe tener al menos 6 caracteres.",
                    maxlength: "La contraseña no puede exceder los 20 caracteres.",
                    noInicioEspacio: "La contraseña no puede iniciar con espacios ni estar compuesta únicamente por espacios."
                }
            }
        });
    }
});
