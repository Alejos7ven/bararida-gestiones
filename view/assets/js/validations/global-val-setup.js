/**
 * CONFIGURACIÓN GLOBAL DE JQUERY VALIDATE Y UTILIDADES DE ERROR
 * Se define en ámbito global inmediatamente al cargar el archivo.
 */
(function() {
    'use strict';

    // =========================================================================
    // 1. UTILIDADES GLOBALES PARA MOSTRAR Y LIMPIAR ERRORES CON FOCO
    // =========================================================================

    /**
     * Limpia todos los estados is-invalid y textos de error de un formulario o contenedor
     */
    window.limpiarErroresFormulario = function(formSelector) {
        if (typeof jQuery === 'undefined') return;
        const $form = $(formSelector);
        if (!$form.length) return;
        $form.find('.is-invalid').removeClass('is-invalid');
        $form.find('.invalid-feedback').hide().text('');
    };

    /**
     * Marca un input específico como inválido, coloca el mensaje debajo y le hace focus
     * @param {string|jQuery|HTMLElement} element Selector o elemento del input
     * @param {string} mensaje Texto del error
     * @param {boolean} doFocus Si debe hacer focus y scroll suave (default true)
     */
    window.marcarErrorInput = function(element, mensaje, doFocus = true) {
        if (typeof jQuery === 'undefined') return false;
        const $el = $(element);
        if (!$el.length) return false;

        // Si está en un paso oculto del wizard, activar el paso
        const $stepPanel = $el.closest('.wizard-step-panel');
        if ($stepPanel.length && !$stepPanel.hasClass('active')) {
            const stepId = $stepPanel.attr('id');
            if (stepId === 'wizardStep1' && typeof showStep === 'function') showStep(1);
            else if (stepId === 'wizardStep2' && typeof showStep === 'function') showStep(2);
            else if (stepId === 'wizardStep3' && typeof showStep === 'function') showStep(3);
        }

        // Marcar clase de error
        $el.addClass('is-invalid').removeClass('is-valid');

        // Determinar contenedor para insertar el mensaje
        let $targetContainer = $el;
        if ($el.parent('.input-group').length) {
            $targetContainer = $el.parent('.input-group');
        } else if ($el.closest('.custom-tipo-organizacion-container').length) {
            $targetContainer = $el.closest('.custom-tipo-organizacion-container');
        }

        // Buscar o crear el contenedor .invalid-feedback
        let $feedback = $targetContainer.next('.invalid-feedback');
        if (!$feedback.length) {
            $feedback = $('<div class="invalid-feedback animate__animated animate__fadeIn"></div>');
            $feedback.insertAfter($targetContainer);
        }

        $feedback.text(mensaje).show();

        // Al comenzar a escribir o interactuar, limpiar el error de este campo
        $el.off('input.cleanErr change.cleanErr').on('input.cleanErr change.cleanErr', function() {
            $el.removeClass('is-invalid');
            $feedback.hide().text('');
        });

        if (doFocus) {
            setTimeout(function() {
                $el.trigger('focus');
                if ($el[0] && typeof $el[0].scrollIntoView === 'function') {
                    $el[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }, 100);
        }

        return true;
    };

    /**
     * Mapea un error devuelto por el servidor (AJAX) al input correspondiente y le hace focus
     * @param {string|jQuery} formSelector Formulario donde buscar el input
     * @param {string} errorMessage Mensaje de error devuelto
     * @param {string|null} fieldName Nombre del campo si vino del servidor
     * @param {jQuery|null} $alertBanner Banner de alerta opcional para mostrar también el mensaje
     */
    window.mapearErrorServidorAFormulario = function(formSelector, errorMessage, fieldName = null, $alertBanner = null) {
        if (typeof jQuery === 'undefined') return;
        const $form = $(formSelector);
        if (!$form.length) return;

        let $targetInput = null;

        // 1. Si el servidor envió el nombre del campo explícitamente
        if (fieldName) {
            $targetInput = $form.find(`[name="${fieldName}"], #${fieldName}, [name*="[${fieldName}]"]`).first();
        }

        // 2. Si no se encontró o no vino, buscar por palabras clave en el mensaje de error
        if (!$targetInput || !$targetInput.length) {
            const msgLower = (errorMessage || '').toLowerCase();

            if (msgLower.includes('documento') || msgLower.includes('rif') || msgLower.includes('cédula') || msgLower.includes('cedula')) {
                $targetInput = $form.find('[name="documento"], [name="cedula"], #documento_inst, #create_documento, #edit_documento, #cedula').filter(':visible, [type!="hidden"]').first();
                if (!$targetInput.length) $targetInput = $form.find('#documento_inst, #cedula, #create_documento, #edit_documento').first();
            } else if (msgLower.includes('nombre') || msgLower.includes('razón social') || msgLower.includes('razon social')) {
                $targetInput = $form.find('[name="nombre_completo"], [name="nombre"], #nombre_institucion, #create_nombre_completo, #edit_nombre_completo, [name="nombres"]').first();
            } else if (msgLower.includes('apellido')) {
                $targetInput = $form.find('[name="apellidos"]').first();
            } else if (msgLower.includes('contraseña') || msgLower.includes('password') || msgLower.includes('clave')) {
                $targetInput = $form.find('[name="password"], #password, #create_password, #edit_password').first();
            } else if (msgLower.includes('teléfono') || msgLower.includes('telefono')) {
                $targetInput = $form.find('.input-telefono-val, input[type="tel"], [name*="telefono"], [name*="contacto"]').first();
            } else if (msgLower.includes('correo') || msgLower.includes('email')) {
                $targetInput = $form.find('.input-correo-val, input[type="email"], [name*="correo"]').first();
            } else if (msgLower.includes('fecha') || msgLower.includes('cupo') || msgLower.includes('visita')) {
                $targetInput = $form.find('[name="fecha_visita"], #fecha_visita, #edit_fecha_visita, [name="fecha_nacimiento"]').first();
            } else if (msgLower.includes('hora')) {
                $targetInput = $form.find('[name="hora_inicio"], #hora_inicio, #edit_hora_inicio').first();
            } else if (msgLower.includes('pago') || msgLower.includes('método') || msgLower.includes('metodo')) {
                $targetInput = $form.find('[name="metodo_pago"], #metodo_pago, #edit_metodo_pago, [name="pago_estado"]').first();
            } else if (msgLower.includes('recorrido')) {
                $targetInput = $form.find('[name="recorrido_id"], #recorrido_id, #edit_recorrido_id, #nombreRecorrido').first();
            } else if (msgLower.includes('zona')) {
                $targetInput = $form.find('#nombreZona').first();
            } else if (msgLower.includes('organización') || msgLower.includes('organizacion')) {
                $targetInput = $form.find('[name="tipo_organizacion"], .select-tipo-organizacion').first();
            } else if (msgLower.includes('gestión') || msgLower.includes('gestion')) {
                $targetInput = $form.find('[name="tipo_gestion"]').first();
            } else if (msgLower.includes('sede') || msgLower.includes('parroquia') || msgLower.includes('ciudad') || msgLower.includes('estado') || msgLower.includes('municipio')) {
                $targetInput = $form.find('.select-ciudad, .select-parroquia, #parroquia_id, #ciudad_id').filter(':visible').first();
            } else if (msgLower.includes('rol')) {
                $targetInput = $form.find('[name="rol"], #create_rol, #edit_rol').first();
            }
        }

        // 3. Aplicar error en el input encontrado con focus
        if ($targetInput && $targetInput.length) {
            window.marcarErrorInput($targetInput, errorMessage, true);
        }

        // 4. Si se proporcionó un banner de alerta, mostrarlo también para redundancia visual
        if ($alertBanner && $alertBanner.length) {
            $alertBanner.removeClass('alert-success alert-warning alert-info')
                        .addClass('alert-danger')
                        .text(errorMessage)
                        .fadeIn(200);
        }
    };

    // =========================================================================
    // 2. CONFIGURACIÓN GLOBAL DE JQUERY VALIDATE CON FOCO AUTOMÁTICO
    // =========================================================================
    if (typeof jQuery !== 'undefined' && typeof jQuery.validator !== 'undefined') {
        $.validator.setDefaults({
            errorElement: 'div',
            errorClass: 'invalid-feedback',
            focusInvalid: true,
            highlight: function(element) {
                $(element).addClass('is-invalid').removeClass('is-valid');
            },
            unhighlight: function(element) {
                $(element).removeClass('is-invalid');
                const $container = $(element).parent('.input-group').length 
                    ? $(element).parent('.input-group') 
                    : $(element);
                $container.siblings('.invalid-feedback').hide().text('');
            },
            errorPlacement: function(error, element) {
                if (element.parent('.input-group').length) {
                    error.insertAfter(element.parent('.input-group'));
                } else if (element.closest('.custom-tipo-organizacion-container').length) {
                    error.insertAfter(element.closest('.custom-tipo-organizacion-container'));
                } else if (element.hasClass('select-tipo-organizacion')) {
                    const prefix = (element.attr('id') && element.attr('id').startsWith('create')) ? 'create' : 'edit';
                    const $customCont = $(`#${prefix}_custom_tipo_org_container`);
                    if ($customCont.length && $customCont.is(':visible')) {
                        error.insertAfter($customCont);
                    } else {
                        error.insertAfter(element);
                    }
                } else {
                    error.insertAfter(element);
                }
            },
            invalidHandler: function(event, validator) {
                // Cuando la validación falla, hacer Focus y scroll suave en el primer input con error
                if (validator.errorList && validator.errorList.length > 0) {
                    const firstInvalidElement = validator.errorList[0].element;
                    
                    // Si el elemento está dentro de un paso del Wizard inactivo, activar ese paso primero
                    const $stepPanel = $(firstInvalidElement).closest('.wizard-step-panel');
                    if ($stepPanel.length && !$stepPanel.hasClass('active')) {
                        const stepId = $stepPanel.attr('id');
                        if (stepId === 'wizardStep1' && typeof showStep === 'function') showStep(1);
                        else if (stepId === 'wizardStep2' && typeof showStep === 'function') showStep(2);
                        else if (stepId === 'wizardStep3' && typeof showStep === 'function') showStep(3);
                    }

                    setTimeout(function() {
                        $(firstInvalidElement).trigger('focus');
                        if (firstInvalidElement && typeof firstInvalidElement.scrollIntoView === 'function') {
                            firstInvalidElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        }
                    }, 80);
                }
            }
        });
    }

    // =========================================================================
    // 3. ESTILOS VISUALES MEJORADOS PARA INPUTS CON ERROR Y FEEDBACK
    // =========================================================================
    try {
        const style = document.createElement('style');
        style.innerHTML = `
            .invalid-feedback {
                display: block !important;
                color: #ef4444 !important;
                font-size: 0.8rem !important;
                font-weight: 500 !important;
                margin-top: 0.35rem !important;
                transition: all 0.2s ease-in-out;
            }
            .is-invalid {
                border-color: #ef4444 !important;
                box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.12) !important;
                background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23ef4444'%3e%3ccircle cx='6' cy='6' r='4.5' stroke-width='1.2'/%3e%3cpath stroke-linejoin='round' stroke-linecap='round' d='M6 3.8v2.8M6 8.2h.01'/%3e%3c/svg%3e") !important;
                background-repeat: no-repeat !important;
                background-position: right calc(0.4em + 0.2rem) center !important;
                background-size: calc(0.8em + 0.3rem) calc(0.8em + 0.3rem) !important;
            }
            .is-invalid:focus {
                border-color: #dc2626 !important;
                box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.22) !important;
            }
            .input-group > .is-invalid {
                z-index: 3 !important;
            }
        `;
        document.head.appendChild(style);
    } catch (e) {
        // Ignorar si document.head no está listo
    }
})();
