$(document).ready(function() {
    // Método personalizado para validar números de teléfono (solo números, sin letras, entre 7 y 15 dígitos)
    if (!$.validator.methods.formatoTelefono) {
        $.validator.addMethod("formatoTelefono", function(value, element) {
            if (this.optional(element)) return true;
            const clean = value.replace(/[^0-9]/g, '');
            return !/[a-zA-Z]/.test(value) && clean.length >= 7 && clean.length <= 15;
        }, "El teléfono debe contener solo números (entre 7 y 15 dígitos).");
    }

    // Método personalizado para validar correos electrónicos
    if (!$.validator.methods.formatoCorreo) {
        $.validator.addMethod("formatoCorreo", function(value, element) {
            if (this.optional(element)) return true;
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
        }, "El correo electrónico no tiene un formato válido (ej. contacto@ejemplo.com).");
    }

    // Aplicar independientemente a campos dinámicos con estas clases
    $.validator.addClassRules("input-telefono-val", {
        required: false,
        formatoTelefono: true,
        maxlength: 30
    });

    $.validator.addClassRules("input-correo-val", {
        required: false,
        formatoCorreo: true,
        maxlength: 30
    });

    // Formulario de Creación
    if ($('#formCrearInstitucion').length) {
        $('#formCrearInstitucion').validate({
            rules: {
                tipo_documento: {
                    required: true,
                    maxlength: 10
                },
                documento: {
                    required: true,
                    digits: true,
                    minlength: 6,
                    maxlength: 15
                },
                nombre_completo: {
                    required: true,
                    minlength: 3,
                    maxlength: 50,
                    noInicioEspacio: true
                },
                tipo_organizacion: {
                    required: true,
                    maxlength: 20
                },
                tipo_gestion: {
                    required: true,
                    maxlength: 20
                }
            },
            messages: {
                documento: {
                    required: "El RIF/Cédula es requerido.",
                    digits: "El RIF/Cédula debe contener solo números.",
                    minlength: "Debe tener al menos 6 dígitos.",
                    maxlength: "No puede exceder los 15 dígitos."
                },
                nombre_completo: {
                    required: "La Razón Social / Nombre Oficial es requerida.",
                    minlength: "La Razón Social debe tener al menos 3 caracteres.",
                    maxlength: "La Razón Social no puede superar los 50 caracteres.",
                    noInicioEspacio: "La Razón Social no puede iniciar con espacios ni estar vacía."
                },
                tipo_organizacion: {
                    required: "Debe seleccionar o especificar un tipo de organización.",
                    maxlength: "El tipo de organización no puede exceder los 20 caracteres."
                },
                tipo_gestion: {
                    required: "Debe seleccionar el tipo de gestión.",
                    maxlength: "El tipo de gestión no puede exceder los 20 caracteres."
                }
            }
        });
    }

    // Formulario de Edición
    if ($('#formEditarInstitucion').length) {
        $('#formEditarInstitucion').validate({
            rules: {
                tipo_documento: {
                    required: true,
                    maxlength: 10
                },
                documento: {
                    required: true,
                    digits: true,
                    minlength: 6,
                    maxlength: 15
                },
                nombre_completo: {
                    required: true,
                    minlength: 3,
                    maxlength: 50,
                    noInicioEspacio: true
                },
                tipo_organizacion: {
                    required: true,
                    maxlength: 20
                },
                tipo_gestion: {
                    required: true,
                    maxlength: 20
                }
            },
            messages: {
                documento: {
                    required: "El RIF/Cédula es requerido.",
                    digits: "El RIF/Cédula debe contener solo números.",
                    minlength: "Debe tener al menos 6 dígitos.",
                    maxlength: "No puede exceder los 15 dígitos."
                },
                nombre_completo: {
                    required: "La Razón Social / Nombre Oficial es requerida.",
                    minlength: "La Razón Social debe tener al menos 3 caracteres.",
                    maxlength: "La Razón Social no puede superar los 50 caracteres.",
                    noInicioEspacio: "La Razón Social no puede iniciar con espacios ni estar vacía."
                },
                tipo_organizacion: {
                    required: "Debe seleccionar o especificar un tipo de organización.",
                    maxlength: "El tipo de organización no puede exceder los 20 caracteres."
                },
                tipo_gestion: {
                    required: "Debe seleccionar el tipo de gestión.",
                    maxlength: "El tipo de gestión no puede exceder los 20 caracteres."
                }
            }
        });
    }
});
