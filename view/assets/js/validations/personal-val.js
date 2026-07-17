$(document).ready(function() {

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
                    maxlength: 12
                },
                nombre: {
                    required: true,
                    minlength: 2
                },
                apellido: {
                    required: true,
                    minlength: 2
                },
                rol_id: {
                    required: true
                },
                password: {
                    required: true,
                    minlength: 6
                }
            },
            messages: {
                cedula: {
                    digits: "Por favor, ingrese solo números para la cédula.",
                    minlength: "La cédula debe tener al menos 6 dígitos.",
                    maxlength: "La cédula no puede exceder los 12 dígitos."
                },
                password: {
                    minlength: "La contraseña debe tener al menos 4 caracteres."
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
                    maxlength: 12
                },
                nombre: {
                    required: true,
                    minlength: 2
                },
                apellido: {
                    required: true,
                    minlength: 2
                },
                rol: {
                    required: true
                },
                password: {
                    required: false,
                    minlength: 6
                }
            },
            messages: {
                cedula: {
                    digits: "Por favor, ingrese solo números para la cédula.",
                    minlength: "La cédula debe tener al menos 6 dígitos.",
                    maxlength: "La cédula no puede exceder los 12 dígitos."
                },
                password: {
                    minlength: "La contraseña debe tener al menos 6 caracteres."
                }
            }
        });
    }
});
