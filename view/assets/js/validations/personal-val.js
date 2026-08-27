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
                nombre_completo: {
                    required: true,
                    minlength: 3,
                    maxlength: 150
                },
                rol: {
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
                nombre_completo: {
                    required: "El nombre completo es requerido.",
                    minlength: "El nombre completo debe tener al menos 3 caracteres."
                },
                password: {
                    minlength: "La contraseña debe tener al menos 6 caracteres."
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
                nombre_completo: {
                    required: true,
                    minlength: 3,
                    maxlength: 150
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
                nombre_completo: {
                    required: "El nombre completo es requerido.",
                    minlength: "El nombre completo debe tener al menos 3 caracteres."
                },
                password: {
                    minlength: "La contraseña debe tener al menos 6 caracteres."
                }
            }
        });
    }
});
