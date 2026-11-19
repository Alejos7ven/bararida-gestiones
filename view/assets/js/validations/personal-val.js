$(document).ready(function() {

    // Método para validar que no contenga números en el nombre de persona si no está registrado
    if (!$.validator.methods.soloLetras) {
        $.validator.addMethod("soloLetras", function(value, element) {
            if (this.optional(element)) return true;
            return /^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s\.\'\-]+$/.test(value);
        }, "Solo se permiten letras (sin números ni caracteres especiales).");
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
                    maxlength: 100
                }
            },
            messages: {
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
                    soloLetras: "El nombre completo no puede contener números."
                },
                password: {
                    required: "La contraseña es requerida.",
                    minlength: "La contraseña debe tener al menos 6 caracteres.",
                    maxlength: "La contraseña no puede exceder los 100 caracteres."
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
                    maxlength: 100
                }
            },
            messages: {
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
                    soloLetras: "El nombre completo no puede contener números."
                },
                password: {
                    minlength: "La contraseña debe tener al menos 6 caracteres.",
                    maxlength: "La contraseña no puede exceder los 100 caracteres."
                }
            }
        });
    }
});
