$(document).ready(function() {
    // Método personalizado para validar números de teléfono (solo números, sin letras, entre 7 y 15 dígitos)
    $.validator.addMethod("formatoTelefono", function(value, element) {
        if (this.optional(element)) return true;
        const clean = value.replace(/[^0-9]/g, '');
        return !/[a-zA-Z]/.test(value) && clean.length >= 7 && clean.length <= 15;
    }, "El teléfono debe contener solo números (entre 7 y 15 dígitos).");

    // Método personalizado para validar correos electrónicos
    $.validator.addMethod("formatoCorreo", function(value, element) {
        if (this.optional(element)) return true;
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
    }, "El correo electrónico no tiene un formato válido (ej. contacto@ejemplo.com).");

    // Aplicar independientemente a campos dinámicos con estas clases
    $.validator.addClassRules("input-telefono-val", {
        required: false,
        formatoTelefono: true
    });

    $.validator.addClassRules("input-correo-val", {
        required: false,
        formatoCorreo: true
    });

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
                nombre_completo: {
                    required: true,
                    minlength: 3,
                    maxlength: 150
                },
                tipo_organizacion: {
                    required: true
                },
                tipo_gestion: {
                    required: true
                }
            },
            messages: {
                documento: {
                    digits: "El RIF/Cédula debe contener solo números.",
                    minlength: "Debe tener al menos 6 dígitos.",
                    maxlength: "No puede exceder los 12 dígitos."
                },
                nombre_completo: {
                    required: "La Razón Social / Nombre Oficial es requerida.",
                    minlength: "La Razón Social debe tener al menos 3 caracteres."
                },
                tipo_organizacion: {
                    required: "Debe seleccionar o especificar un tipo de organización."
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
                nombre_completo: {
                    required: true,
                    minlength: 3,
                    maxlength: 150
                },
                tipo_organizacion: {
                    required: true
                },
                tipo_gestion: {
                    required: true
                }
            },
            messages: {
                documento: {
                    digits: "El RIF/Cédula debe contener solo números.",
                    minlength: "Debe tener al menos 6 dígitos.",
                    maxlength: "No puede exceder los 12 dígitos."
                },
                nombre_completo: {
                    required: "La Razón Social / Nombre Oficial es requerida.",
                    minlength: "La Razón Social debe tener al menos 3 caracteres."
                },
                tipo_organizacion: {
                    required: "Debe seleccionar o especificar un tipo de organización."
                }
            }
        });
    }
});
