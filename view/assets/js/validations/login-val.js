/**
 * VALIDACIONES EN FRONTEND PARA EL FORMULARIO DE LOGIN
 * Utiliza jQuery Validate integrado con la configuración global del sistema
 */
$(document).ready(function() {
    if ($('#formLogin').length && typeof $.fn.validate !== 'undefined') {
        $('#formLogin').validate({
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
                password: {
                    required: true,
                    minlength: 6,
                    maxlength: 100
                }
            },
            messages: {
                tipo_documento: {
                    required: "Seleccione un tipo de documento."
                },
                cedula: {
                    required: "El número de documento es obligatorio.",
                    digits: "Por favor, ingrese solo números para la cédula.",
                    minlength: "La cédula debe tener al menos 6 dígitos.",
                    maxlength: "La cédula no puede exceder los 15 dígitos."
                },
                password: {
                    required: "La contraseña es requerida.",
                    minlength: "La contraseña debe tener al menos 6 caracteres.",
                    maxlength: "La contraseña no puede exceder los 100 caracteres."
                }
            }
        });
    }
});

