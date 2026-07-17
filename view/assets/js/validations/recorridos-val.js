$(document).ready(function() {
    // Formulario de Creación de Recorrido
    if ($('#formCrearRecorrido').length) {
        $('#formCrearRecorrido').validate({
            rules: {
                nombre: {
                    required: true,
                    minlength: 3
                }
            },
            messages: {
                nombre: {
                    minlength: "El nombre del recorrido debe tener al menos 3 caracteres."
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
                    minlength: 3
                },
                coordenadas: {
                    required: true
                }
            },
            messages: {
                nombre: {
                    minlength: "El nombre de la zona debe tener al menos 3 caracteres."
                },
                coordenadas: {
                    required: "Debe hacer clic en el mapa para marcar las coordenadas de la zona."
                }
            }
        });
    }
});
