$(document).ready(function() {
    // Formulario de Creación
    if ($('#formCrearInstitucion').length) {
        $('#formCrearInstitucion').validate({
            rules: {
                tipo_documento: {
                    required: true
                },
                documento: {
                    required: true,
                    digits: true,
                    minlength: 6,
                    maxlength: 12
                },
                nombre: {
                    required: true,
                    minlength: 3
                },
                tipo_organizacion: {
                    required: true
                },
                tipo_gestion: {
                    required: true
                },
                parroquia_id: {
                    required: true
                },
                ciudad_id: {
                    required: true
                }
            },
            messages: {
                documento: {
                    digits: "El RIF/Cédula debe contener solo números.",
                    minlength: "Debe tener al menos 6 dígitos.",
                    maxlength: "No puede exceder los 12 dígitos."
                },
                nombre: {
                    minlength: "La Razón Social debe tener al menos 3 caracteres."
                },
                tipo_organizacion: {
                    required: "Debe seleccionar o especificar un tipo de organización."
                },
                parroquia_id: {
                    required: "Debe seleccionar la parroquia correspondiente."
                },
                ciudad_id: {
                    required: "Debe seleccionar la ciudad correspondiente."
                }
            }
        });
    }

    // Formulario de Edición
    if ($('#formEditarInstitucion').length) {
        $('#formEditarInstitucion').validate({
            rules: {
                tipo_documento: {
                    required: true
                },
                documento: {
                    required: true,
                    digits: true,
                    minlength: 6,
                    maxlength: 12
                },
                nombre: {
                    required: true,
                    minlength: 3
                },
                tipo_organizacion: {
                    required: true
                },
                tipo_gestion: {
                    required: true
                },
                parroquia_id: {
                    required: true
                },
                ciudad_id: {
                    required: true
                }
            },
            messages: {
                documento: {
                    digits: "El RIF/Cédula debe contener solo números.",
                    minlength: "Debe tener al menos 6 dígitos.",
                    maxlength: "No puede exceder los 12 dígitos."
                },
                nombre: {
                    minlength: "La Razón Social debe tener al menos 3 caracteres."
                },
                tipo_organizacion: {
                    required: "Debe seleccionar o especificar un tipo de organización."
                },
                parroquia_id: {
                    required: "Debe seleccionar la parroquia correspondiente."
                },
                ciudad_id: {
                    required: "Debe seleccionar la ciudad correspondiente."
                }
            }
        });
    }
});
