$(document).ready(function() {
    // Configurar estilos globales de Bootstrap 5 para jQuery Validation
    $.validator.setDefaults({
        errorElement: 'div',
        errorClass: 'invalid-feedback',
        highlight: function(element) {
            $(element).addClass('is-invalid');
        },
        unhighlight: function(element) {
            $(element).removeClass('is-invalid');
        },
        errorPlacement: function(error, element) {
            if (element.parent('.input-group').length) {
                error.insertAfter(element.parent());
            } else if (element.hasClass('select-tipo-organizacion')) {
                const prefix = element.attr('id').startsWith('create') ? 'create' : 'edit';
                error.insertAfter($(`#${prefix}_custom_tipo_org_container`));
            } else {
                error.insertAfter(element);
            }
        }
    });

    // Añadir regla de estilos extra para asegurar visibilidad
    const style = document.createElement('style');
    style.innerHTML = `
        .invalid-feedback {
            display: block !important;
            color: #dc3545 !important;
            font-size: 0.825rem !important;
            margin-top: 0.25rem !important;
        }
        .is-invalid {
            border-color: #dc3545 !important;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5' stroke-width='1'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4v3.6h-.4zm0 4.6h.4v.4h-.4z'/%3e%3c/svg%3e") !important;
            background-repeat: no-repeat !important;
            background-position: right calc(0.375em + 0.1875rem) center !important;
            background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem) !important;
        }
    `;
    document.head.appendChild(style);
});
