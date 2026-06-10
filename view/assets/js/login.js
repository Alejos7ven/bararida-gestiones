$(document).ready(function() {
    // Manejador del Formulario
    $('#formLogin').on('submit', function(e) {
        e.preventDefault();

        // Referencias de UI
        const $form = $(this);
        const $btnSubmit = $('#btnLogin');
        const $spinner = $btnSubmit.find('.spinner-border');
        const $btnText = $btnSubmit.find('span:not(.spinner-border)');
        const $alert = $('#alertFeedback');

        // Ocultar alerta previa
        $alert.fadeOut(150);

        // Obtener datos
        const tipo_documento = $('#tipo_documento').val();
        const cedula = $('#cedula').val().trim();
        const password = $('#password').val();

        // Validaciones en cliente
        if (!tipo_documento || !['V', 'E', 'J', 'G', 'P'].includes(tipo_documento)) {
            showAlert('danger', 'El tipo de documento seleccionado no es válido.');
            return;
        }

        if (cedula === '') {
            showAlert('danger', 'El número de documento es requerido.');
            return;
        }

        if (!/^\d+$/.test(cedula)) {
            showAlert('danger', 'El número de documento debe contener solo dígitos.');
            return;
        }

        if (password === '') {
            showAlert('danger', 'La contraseña es requerida.');
            return;
        }

        // Deshabilitar botón e iniciar spinner
        $btnSubmit.prop('disabled', true);
        $spinner.show();
        $btnText.text('Verificando...');

        // Petición AJAX limpia
        $.ajax({
            url: 'controllers/AuthController.php?action=login',
            type: 'POST',
            dataType: 'json',
            data: {
                tipo_documento: $('#tipo_documento').val(),
                cedula: cedula,
                password: password
            },
            success: function(response) {
                if (response.status === 'success') {
                    showAlert('success', response.message + ' Redirigiendo...');
                    // Esperar un momento para la animación y redirigir
                    setTimeout(function() {
                        window.location.href = 'index.php';
                    }, 1000);
                }
            },
            error: function(xhr) {
                let errorMessage = 'Ocurrió un error inesperado. Intente de nuevo.';
                
                // Parsear el error si viene en JSON
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                }
                
                showAlert('danger', errorMessage);
                
                // Reactivar el botón
                $btnSubmit.prop('disabled', false);
                $spinner.hide();
                $btnText.text('Ingresar');
            }
        });
    });

    // Mostrar/ocultar contraseña
    $('#togglePassword').on('click', function() {
        const $passwordInput = $('#password');
        const type = $passwordInput.attr('type') === 'password' ? 'text' : 'password';
        $passwordInput.attr('type', type);
        
        // Cambiar icono SVG
        const $eyeIcon = $('#eyeIcon');
        if (type === 'text') {
            // Icono de ojo tachado (cerrado)
            $eyeIcon.html('<path d="M12 7c2.76 0 5 2.24 5 5 0 .65-.13 1.26-.36 1.82l2.92 2.92C21.27 15.39 22.4 13.81 23 12c-1.73-4.39-6-7.5-11-7.5-1.4 0-2.74.25-4 .7l2.18 2.18C10.74 7.13 11.35 7 12 7zM2 4.27l2.28 2.28.46.46C3.08 8.3 1.78 10.02 1 12c1.73 4.39 6 7.5 11 7.5 1.55 0 3.03-.3 4.38-.84l.42.42L19.73 22 21 20.73 3.27 3 2 4.27zM7.53 9.8l1.55 1.55c-.05.21-.08.43-.08.65 0 1.66 1.34 3 3 3 .22 0 .44-.03.65-.08l1.55 1.55c-.67.33-1.41.53-2.2.53-2.76 0-5-2.24-5-5 0-.79.2-1.53.53-2.2zm4.31-.78l3.15 3.15.02-.16c0-1.66-1.34-3-3-3l-.17.01z"/>');
        } else {
            // Icono de ojo abierto
            $eyeIcon.html('<path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>');
        }
    });

    // Función para mostrar alertas sutiles
    function showAlert(type, message) {
        const $alert = $('#alertFeedback');
        $alert.removeClass('alert-danger alert-success');
        
        if (type === 'danger') {
            $alert.addClass('alert-danger');
        } else if (type === 'success') {
            $alert.addClass('alert-success');
        }
        
        $alert.text(message).fadeIn(300);
    }
});
