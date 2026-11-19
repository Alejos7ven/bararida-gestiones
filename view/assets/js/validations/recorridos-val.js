$(document).ready(function() {
    // Formulario de Creación de Recorrido
    if ($('#formCrearRecorrido').length) {
        $('#formCrearRecorrido').validate({
            rules: {
                nombre: {
                    required: true,
                    minlength: 3,
                    maxlength: 50
                }
            },
            messages: {
                nombre: {
                    required: "El nombre del recorrido es obligatorio.",
                    minlength: "El nombre del recorrido debe tener al menos 3 caracteres.",
                    maxlength: "El nombre del recorrido no puede exceder los 50 caracteres."
                }
            }
        });
    }

    // Formulario de Creación de Zona
    if ($('#formCrearZona').length) {
        $('#formCrearZona').validate({
            rules: {
                nombre: {
                    required: true,
                    minlength: 3,
                    maxlength: 50
                },
                coordenadas: {
                    required: true,
                    maxlength: 255
                }
            },
            messages: {
                nombre: {
                    required: "El nombre de la zona es obligatorio.",
                    minlength: "El nombre de la zona debe tener al menos 3 caracteres.",
                    maxlength: "El nombre de la zona no puede exceder los 50 caracteres."
                },
                coordenadas: {
                    required: "Debe hacer clic en el mapa para marcar las coordenadas de la zona.",
                    maxlength: "Las coordenadas no pueden exceder los 255 caracteres."
                }
            }
        });
    }
});
