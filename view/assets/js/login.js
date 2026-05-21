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
        const cedula = $('#cedula').val().trim();
        const password = $('#password').val();

        // Validaciones en cliente
        if (cedula === '' || password === '') {
            showAlert('danger', 'Por favor, rellene todos los campos del formulario.');
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
