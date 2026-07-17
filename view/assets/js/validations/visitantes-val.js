$(document).ready(function() {
    // Formulario de Creación de Visitante
    if ($('#formCrearVisitante').length) {
        $('#formCrearVisitante').validate({
            rules: {
                cedula: {
                    required: false,
                    digits: true,
                    minlength: 6,
                    maxlength: 12
                },
                nombres: {
                    required: true,
                    minlength: 2
                },
                apellidos: {
                    required: true,
                    minlength: 2
                },
                fecha_nacimiento: {
                    required: true
                }
            },
            messages: {
                cedula: {
                    digits: "El documento debe contener solo números.",
                    minlength: "Debe tener al menos 6 dígitos.",
                    maxlength: "No puede exceder los 12 dígitos."
                },
                nombres: {
                    minlength: "Los nombres deben tener al menos 2 caracteres."
                },
                apellidos: {
                    minlength: "Los apellidos deben tener al menos 2 caracteres."
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
                    minlength: 6,
                    maxlength: 12
                },
                nombres: {
                    required: true,
                    minlength: 2
                },
                apellidos: {
                    required: true,
                    minlength: 2
                },
                fecha_nacimiento: {
                    required: true
                }
            },
            messages: {
                cedula: {
                    digits: "El documento debe contener solo números.",
                    minlength: "Debe tener al menos 6 dígitos.",
                    maxlength: "No puede exceder los 12 dígitos."
                },
                nombres: {
                    minlength: "Los nombres deben tener al menos 2 caracteres."
                },
                apellidos: {
                    minlength: "Los apellidos deben tener al menos 2 caracteres."
                }
            }
        });
    }
});
