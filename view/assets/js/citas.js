/**
 * Lógica del Módulo de Citas - citas.js
 * Parque Zoológico y Botánico Bararida
 */

$(document).ready(function() {
    // Referencias DOM principales
    const $tablaCitas = $('#tablaCitasBody');
    const $loader = $('#loaderCitas');
    const $alertFeedback = $('#alertFeedbackGlobal');
    
    // Variables de estado del Wizard (Modal de registro)
    let currentStep = 1;
    let selectedInstitutionId = null; // null si no está registrada, entero si existe

    // Cargar citas al iniciar
    listarCitas();
    cargarEstados();

    // =========================================================================
    // 1. LISTAR CITAS (AJAX)
    // =========================================================================
    function listarCitas() {
        $tablaCitas.hide();
        $loader.show();

        $.ajax({
            url: 'controllers/CitaController.php?action=listar',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                $loader.hide();
                $tablaCitas.empty();

                if (response.status === 'success' && response.data.length > 0) {
                    response.data.forEach(function(c) {
                        // Badge de estatus de la cita
                        let badgeEstatus = '';
                        if (c.estatus === 'aprobado') {
                            badgeEstatus = '<span class="badge-premium badge-premium-admin">Aprobada</span>';
                        } else if (c.estatus === 'completado') {
                            badgeEstatus = '<span class="badge-premium badge-premium-superadmin">Completada</span>';
                        } else if (c.estatus === 'cancelado') {
                            badgeEstatus = '<span class="badge-premium badge-premium-danger">Cancelada</span>';
                        } else {
                            badgeEstatus = '<span class="badge-premium badge-premium-guia">Pendiente</span>';
                        }

                        // Badge del estado del pago
                        const badgePago = c.pago_estado === 'pagado' 
                            ? '<span class="badge-premium badge-payment-paid">Pagado</span>'
                            : '<span class="badge-premium badge-payment-pending">Pendiente</span>';

                        // Badge de cantidad de visitantes
                        const badgeVisitantes = parseInt(c.total_visitantes) > 0
                            ? `<span class="badge-premium badge-premium-info btn-ver-visitantes" data-id="${c.id}" data-name="${c.razon_social}" style="cursor: pointer;">${c.total_visitantes} visitantes</span>`
                            : '<span class="badge-premium bg-secondary">0 visitantes</span>';

                        const btnImportarHtml = `
                                        <button class="btn-action btn-action-edit btn-importar-visitantes" data-id="${c.id}" data-name="${c.razon_social}" title="Importar Visitantes (CSV)">
                                            <!-- SVG Import -->
                                            <svg viewBox="0 0 24 24" style="width:20px; height:20px;"><path d="M19.35 10.04C18.67 6.59 15.64 4 12 4 9.11 4 6.6 5.64 5.35 8.04 2.34 8.36 0 10.91 0 14c0 3.31 2.69 6 6 6h13c2.76 0 5-2.24 5-5 0-2.64-2.05-4.78-4.65-4.96zM14 13v4h-4v-4H7l5-5 5 5h-3z"/></svg>
                                        </button>
                        `;

                        const rowHtml = `
                            <tr class="cita-row" data-search="${c.razon_social.toLowerCase()} ${c.RIF} ${c.id}">
                                <td><strong>#CIT-${c.id}</strong></td>
                                <td>
                                    <span style="white-space: nowrap;"><strong>${c.tipo_documento}-${c.RIF}</strong></span><br>
                                    <small class="text-muted" style="font-size:0.8rem;">${c.razon_social}</small>
                                </td>
                                <td>
                                    ${formatDate(c.fecha_visita)}<br>
                                    <small class="text-success fw-600">${formatTime(c.hora_inicio)}</small>
                                </td>
                                <td>${badgeVisitantes}</td>
                                <td><span class="text-muted italic">Carlos Rodríguez</span></td>
                                <td>${badgeEstatus}</td>
                                <td>${badgePago}</td>
                                <td>
                                    <div class="table-actions">
                                        ${btnImportarHtml}
                                        <a href="controllers/CitaController.php?action=descargar_plantilla&cita_id=${c.id}" class="btn-action btn-action-edit" title="Descargar Plantilla Excel/CSV" target="_blank">
                                            <!-- SVG Download -->
                                            <svg viewBox="0 0 24 24" style="width:20px; height:20px;"><path d="M19.35 10.04C18.67 6.59 15.64 4 12 4 9.11 4 6.6 5.64 5.35 8.04 2.34 8.36 0 10.91 0 14c0 3.31 2.69 6 6 6h13c2.76 0 5-2.24 5-5 0-2.64-2.05-4.78-4.65-4.96zM17 13l-5 5-5-5h3V9h4v4h3z"/></svg>
                                        </a>
                                        <button class="btn-action btn-action-delete btn-eliminar-cita" data-id="${c.id}" data-name="${c.razon_social}" title="Eliminar Cita">
                                            <!-- SVG Trash -->
                                            <svg viewBox="0 0 24 24" style="width:20px; height:20px;"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        `;
                        $tablaCitas.append(rowHtml);
                    });
                    $tablaCitas.fadeIn(300);
                } else {
                    $tablaCitas.append('<tr><td colspan="8" class="text-center text-muted py-4">No hay citas agendadas registradas en el sistema.</td></tr>');
                    $tablaCitas.fadeIn(300);
                }
            },
            error: function(xhr) {
                $loader.hide();
                showGlobalAlert('Ocurrió un error al cargar el listado de citas.', 'danger');
            }
        });
    }

    // Buscador en tiempo real de citas
    $('#searchCitas').on('input', function() {
        const query = $(this).val().toLowerCase().trim();
        if (query === '') {
            $('.cita-row').show();
            return;
        }
        $('.cita-row').each(function() {
            const searchText = $(this).data('search');
            if (searchText.indexOf(query) > -1) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });

    // =========================================================================
    // 2. CARGA DE UBICACIONES EN CASCADA
    // =========================================================================
    function cargarEstados() {
        const $selectEstado = $('#estado_id');
        $.ajax({
            url: 'controllers/LocationController.php?action=estados',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    $selectEstado.empty().append('<option value="">Seleccione Estado</option>');
                    response.data.forEach(function(est) {
                        $selectEstado.append(`<option value="${est.id_estado}">${est.estado}</option>`);
                    });
                }
            }
        });
    }

    $('#estado_id').on('change', function() {
        const estadoId = $(this).val();
        const $selectMun = $('#municipio_id');
        const $selectCiu = $('#ciudad_id');
        const $selectPar = $('#parroquia_id');

        $selectMun.empty().append('<option value="">Seleccione Municipio</option>').prop('disabled', true);
        $selectCiu.empty().append('<option value="">Seleccione Ciudad</option>').prop('disabled', true);
        $selectPar.empty().append('<option value="">Seleccione Parroquia</option>').prop('disabled', true);

        if (!estadoId) return;

        // Cargar Municipios
        $.ajax({
            url: `controllers/LocationController.php?action=municipios&estado_id=${estadoId}`,
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    $selectMun.prop('disabled', false);
                    response.data.forEach(function(m) {
                        $selectMun.append(`<option value="${m.id_municipio}">${m.municipio}</option>`);
                    });
                }
            }
        });

        // Cargar Ciudades
        $.ajax({
            url: `controllers/LocationController.php?action=ciudades&estado_id=${estadoId}`,
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    $selectCiu.prop('disabled', false);
                    response.data.forEach(function(c) {
                        $selectCiu.append(`<option value="${c.id_ciudad}">${c.ciudad}</option>`);
                    });
                }
            }
        });
    });

    $('#municipio_id').on('change', function() {
        const municipioId = $(this).val();
        const $selectPar = $('#parroquia_id');

        $selectPar.empty().append('<option value="">Seleccione Parroquia</option>').prop('disabled', true);

        if (!municipioId) return;

        // Cargar Parroquias
        $.ajax({
            url: `controllers/LocationController.php?action=parroquias&municipio_id=${municipioId}`,
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    $selectPar.prop('disabled', false);
                    response.data.forEach(function(p) {
                        $selectPar.append(`<option value="${p.id_parroquia}">${p.parroquia}</option>`);
                    });
                }
            }
        });
    });

    // =========================================================================
    // 3. WIZARD / REGISTRO MULTI-PASO
    // =========================================================================
    
    // Al abrir el modal, resetear variables e ir al paso 1
    $('#modalCrearCita').on('show.bs.modal', function () {
        resetWizard();
    });

    function resetWizard() {
        currentStep = 1;
        selectedInstitutionId = null;
        $('#formCrearCita')[0].reset();
        
        // Limpiar contenedor de contactos e insertar el campo obligatorio inicial
        $('#contactsContainer').empty();
        agregarFilaContacto('principal');

        // Deshabilitar selectores geográficos subordinados
        $('#municipio_id, #ciudad_id, #parroquia_id').prop('disabled', true);

        // Limpiar cualquier tipo de organización no predefinido
        $('#tipo_organizacion option').each(function() {
            const val = $(this).val();
            const predefined = ['', 'Colegio Privado', 'Escuela Pública', 'Liceo Público', 'Liceo Privado', 'Fundación / ONG', 'Institución Gubernamental', 'Empresa Privada', 'Comunidad Organizada', 'Otro'];
            if (predefined.indexOf(val) === -1) {
                $(this).remove();
            }
        });

        // Ocultar alerta de RIF
        $('#rifCheckStatus').hide().removeClass('alert-success alert-warning alert-danger').text('');
        
        // Resetear botones
        $('#btnWizardPrev').hide();
        $('#btnWizardNext').text('Siguiente');
        $('#btnWizardNext').prop('disabled', true); // Se habilita al verificar el RIF exitosamente

        showStep(currentStep);
    }

    function showStep(step) {
        $('.wizard-step-panel').removeClass('active');
        $(`#wizardStep${step}`).addClass('active');

        // Actualizar barra de progreso del Step Wizard
        const percentage = ((step - 1) / 2) * 100;
        $('.step-wizard-progress').css('width', `${percentage}%`);

        // Actualizar indicadores circulares
        $('.step-wizard-item').each(function(index) {
            const stepNum = index + 1;
            $(this).removeClass('active completed');
            if (stepNum === step) {
                $(this).addClass('active');
            } else if (stepNum < step) {
                $(this).addClass('completed');
            }
        });

        // Configuración de botones de navegación
        if (step === 1) {
            $('#btnWizardPrev').hide();
            $('#btnWizardNext').hide();
        } else {
            $('#btnWizardPrev').show();
            $('#btnWizardNext').show();
        }

        if (step === 3) {
            $('#btnWizardNext').text('Agendar Cita');
        } else {
            $('#btnWizardNext').text('Siguiente');
        }
    }

    // BOTÓN: VERIFICAR RIF (PASO 1)
    $('#btnVerificarRif').on('click', function() {
        const tipoDoc = $('#tipo_documento_check').val();
        const RIF = $('#cedula_check').val().trim();
        const $btn = $(this);
        const $statusBox = $('#rifCheckStatus');

        if (RIF === '' || !/^\d+$/.test(RIF)) {
            $statusBox.removeClass('alert-success alert-warning alert-danger')
                      .addClass('alert-danger')
                      .text('Por favor, ingrese un número de RIF válido (solo dígitos).')
                      .fadeIn(200);
            return;
        }

        $btn.prop('disabled', true).text('Verificando...');
        $statusBox.hide();

        $.ajax({
            url: `controllers/CitaController.php?action=comprobar_institucion&tipo_documento=${tipoDoc}&documento=${RIF}`,
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                $btn.prop('disabled', false).text('Verificar RIF');
                
                if (response.status === 'success') {
                    // Pasar los valores de paso 1 al formulario final
                    $('#final_tipo_documento').val(tipoDoc);
                    $('#final_documento').val(RIF);

                    if (response.exists) {
                        // Guardar el ID de la institución
                        selectedInstitutionId = response.data.institucion_id;
                        $('#final_institucion_id').val(selectedInstitutionId);

                        // Rellenar campos del Paso 2 (Solo Lectura)
                        prellenarDatosInstitucion(response.data);

                        $statusBox.removeClass('alert-danger alert-warning')
                                  .addClass('alert-success')
                                  .html(`<strong>✓ Institución Encontrada:</strong> ${response.data.nombre}. Redirigiendo...`)
                                  .fadeIn(200);
                    } else {
                        // Habilitar campos del Paso 2 para creación
                        selectedInstitutionId = null;
                        $('#final_institucion_id').val('');
                        limpiarYEditarDatosInstitucion();

                        $statusBox.removeClass('alert-danger alert-success')
                                  .addClass('alert-warning')
                                  .html('<strong>⚠ Institución no registrada:</strong> Redirigiendo para registrar...')
                                  .fadeIn(200);
                    }
                    
                    // Habilitar avance del wizard y transición automática
                    $('#btnWizardNext').prop('disabled', false);
                    setTimeout(function() {
                        currentStep = 2;
                        showStep(currentStep);
                    }, 800);
                }
            },
            error: function() {
                $btn.prop('disabled', false).text('Verificar RIF');
                $statusBox.removeClass('alert-success alert-warning')
                          .addClass('alert-danger')
                          .text('Error de red al comprobar RIF. Intente nuevamente.')
                          .fadeIn(200);
            }
        });
    });

    // BOTÓN: SIGUIENTE / GUARDAR
    $('#btnWizardNext').on('click', function() {
        if (currentStep === 1) {
            // Ir al paso 2
            currentStep = 2;
            showStep(currentStep);
        } else if (currentStep === 2) {
            // Validaciones Paso 2 (Solo si es nueva institución)
            if (selectedInstitutionId === null) {
                const nombre = $('#nombre_institucion').val().trim();
                const tipoOrg = $('#tipo_organizacion').val();
                const ciudadId = $('#ciudad_id').val();
                const parroquiaId = $('#parroquia_id').val();
                
                if (nombre === '' || !tipoOrg || !ciudadId || !parroquiaId) {
                    alert('Por favor, rellene todos los campos obligatorios de la institución.');
                    return;
                }

                // Validar contactos
                let contactoValido = false;
                $('.contact-input').each(function() {
                    if ($(this).val().trim() !== '') {
                        contactoValido = true;
                    }
                });

                if (!contactoValido) {
                    alert('Debe registrar al menos un número de contacto válido en el directorio.');
                    return;
                }
            }

            // Ir al paso 3
            currentStep = 3;
            showStep(currentStep);
        } else if (currentStep === 3) {
            // Validaciones Paso 3 (Cita)
            const fechaVisita = $('#fecha_visita').val();
            const horaInicio = $('#hora_inicio').val();
            const metodoPago = $('#metodo_pago').val();

            if (!fechaVisita || !horaInicio || !metodoPago) {
                alert('Por favor, ingrese todos los datos obligatorios para agendar la cita.');
                return;
            }

            // Procesar y enviar cita mediante AJAX
            guardarCita();
        }
    });

    // BOTÓN: ATRÁS
    $('#btnWizardPrev').on('click', function() {
        if (currentStep > 1) {
            currentStep--;
            showStep(currentStep);
        }
    });

    function prellenarDatosInstitucion(data) {
        $('#nombre_institucion').val(data.nombre).prop('disabled', true);
        
        // Agregar dinámicamente la opción si no existe en el select
        if (data.tipo_organizacion && $('#tipo_organizacion option[value="' + data.tipo_organizacion + '"]').length === 0) {
            $('#tipo_organizacion').append(`<option value="${data.tipo_organizacion}">${data.tipo_organizacion}</option>`);
        }
        $('#tipo_organizacion').val(data.tipo_organizacion).prop('disabled', true);
        
        // Limpiar selects geográficos y forzar a mostrar los nombres de forma simple
        $('#estado_id').empty().append(`<option value="${data.id_estado}" selected>${data.estado || 'Estado Pre-cargado'}</option>`).prop('disabled', true);
        $('#municipio_id').empty().append(`<option value="${data.id_municipio}" selected>${data.municipio || 'Municipio Pre-cargado'}</option>`).prop('disabled', true);
        $('#ciudad_id').empty().append(`<option value="${data.ciudad_id}" selected>${data.ciudad || 'Ciudad Pre-cargada'}</option>`).prop('disabled', true);
        $('#parroquia_id').empty().append(`<option value="${data.parroquia_id}" selected>${data.parroquia || 'Parroquia Pre-cargada'}</option>`).prop('disabled', true);
        
        $('#detalles_direccion').val(data.detalles_direccion || '').prop('disabled', true);

        // Prellenar contactos
        $('#contactsContainer').empty();
        if (data.contactos && data.contactos.length > 0) {
            data.contactos.forEach(function(c) {
                let labelTipo = c.tipo === 'principal' ? 'Teléfono Principal' : (c.tipo === 'secundario' ? 'Teléfono Secundario' : 'Correo Electrónico');
                const contactHtml = `
                    <div class="contact-row">
                        <input type="text" class="form-control form-premium-control" value="${c.contacto}" disabled>
                        <span class="badge bg-secondary p-2.5" style="border-radius:10px; font-size:0.75rem;">${labelTipo}</span>
                    </div>
                `;
                $('#contactsContainer').append(contactHtml);
            });
        }
        $('#btnAddNewContact').hide();
    }

    // Limpiar campos y habilitar edición
    function limpiarYEditarDatosInstitucion() {
        $('#nombre_institucion').val('').prop('disabled', false);
        $('#tipo_organizacion').val('').prop('disabled', false);
        
        // Recargar estados
        cargarEstados();
        $('#estado_id').prop('disabled', false);
        
        $('#municipio_id').empty().append('<option value="">Seleccione Municipio</option>').prop('disabled', true);
        $('#ciudad_id').empty().append('<option value="">Seleccione Ciudad</option>').prop('disabled', true);
        $('#parroquia_id').empty().append('<option value="">Seleccione Parroquia</option>').prop('disabled', true);
        
        $('#detalles_direccion').val('').prop('disabled', false);

        $('#contactsContainer').empty();
        agregarFilaContacto('principal');
        $('#btnAddNewContact').show();
    }

    // =========================================================================
    // 4. DIRECCIÓN DE CONTACTOS (FILAS DINÁMICAS EN WIZARD)
    // =========================================================================
    let contactCount = 0;
    
    function agregarFilaContacto(forcedType = '') {
        contactCount++;
        const isPrincipal = forcedType === 'principal' || $('#contactsContainer .contact-row').length === 0;
        const typeSelected = isPrincipal ? 'principal' : 'secundario';
        const deleteButton = isPrincipal 
            ? '<div style="width:28px;"></div>' 
            : `<button type="button" class="btn-remove-contact btn-eliminar-contacto-row" title="Eliminar Contacto">✕</button>`;

        const rowHtml = `
            <div class="contact-row" id="contactRow_${contactCount}">
                <input type="text" class="form-control form-premium-control contact-input" 
                       name="contactos[${contactCount}][contacto]" placeholder="Número o correo..." required>
                <select class="form-select form-premium-control" name="contactos[${contactCount}][tipo]" style="max-width:180px;">
                    <option value="principal" ${typeSelected === 'principal' ? 'selected' : ''}>Principal</option>
                    <option value="secundario" ${typeSelected === 'secundario' ? 'selected' : ''}>Secundario</option>
                    <option value="correo">Correo</option>
                </select>
                ${deleteButton}
            </div>
        `;
        $('#contactsContainer').append(rowHtml);
    }

    $('#btnAddNewContact').on('click', function() {
        agregarFilaContacto();
    });

    $(document).on('click', '.btn-eliminar-contacto-row', function() {
        $(this).closest('.contact-row').remove();
    });

    // =========================================================================
    // 5. GUARDAR CITA (AJAX POST)
    // =========================================================================
    function guardarCita() {
        const $btn = $('#btnWizardNext');
        
        // Habilitar campos temporalmente para serializar RIF y Razón Social pre-cargados
        $('#formCrearCita :disabled').prop('disabled', false);
        const formData = $('#formCrearCita').serialize();
        
        // Deshabilitar de nuevo para evitar desconfiguración si falla
        if (selectedInstitutionId !== null) {
            $('#nombre_institucion, #tipo_organizacion, #estado_id, #municipio_id, #ciudad_id, #parroquia_id, #detalles_direccion').prop('disabled', true);
        }

        $btn.prop('disabled', true).text('Guardando cita...');

        $.ajax({
            url: 'controllers/CitaController.php?action=crear',
            method: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                $btn.prop('disabled', false).text('Agendar Cita');
                
                if (response.status === 'success') {
                    $('#modalCrearCita').modal('hide');
                    showGlobalAlert(response.message, 'success');
                    listarCitas();
                } else {
                    alert(response.message);
                }
            },
            error: function(xhr) {
                $btn.prop('disabled', false).text('Agendar Cita');
                let errorMsg = 'Error en el servidor al registrar la cita.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                alert(errorMsg);
            }
        });
    }

    // =========================================================================
    // 6. ELIMINAR CITA
    // =========================================================================
    $(document).on('click', '.btn-eliminar-cita', function() {
        const id = $(this).data('id');
        const name = $(this).data('name');

        $('#delete_cita_id').val(id);
        $('#deleteCitaNameSpan').text(`#CIT-${id} de ${name}`);
        $('#modalEliminarCita').modal('show');
    });

    $('#btnConfirmarEliminarCita').on('click', function() {
        const id = $('#delete_cita_id').val();
        const $btn = $(this);

        $btn.prop('disabled', true).text('Eliminando...');

        $.ajax({
            url: 'controllers/CitaController.php?action=eliminar',
            method: 'POST',
            data: { id: id },
            dataType: 'json',
            success: function(response) {
                $btn.prop('disabled', false).text('Eliminar Cita');
                $('#modalEliminarCita').modal('hide');

                if (response.status === 'success') {
                    showGlobalAlert(response.message, 'success');
                    listarCitas();
                } else {
                    showGlobalAlert(response.message, 'danger');
                }
            },
            error: function(xhr) {
                $btn.prop('disabled', false).text('Eliminar Cita');
                $('#modalEliminarCita').modal('hide');
                let errorMsg = 'Error en el servidor al intentar eliminar la cita.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                showGlobalAlert(errorMsg, 'danger');
            }
        });
    });

    // =========================================================================
    // 7. IMPORTACIÓN DE VISITANTES DESDE CSV (DRAG & DROP)
    // =========================================================================
    const $dropZone = $('#csvDropZone');
    const $fileInput = $('#csvFileInput');
    const $fileInfo = $('#csvFileInfo');
    const $btnImport = $('#btnConfirmarImportarCSV');
    let selectedCsvFile = null;

    $(document).on('click', '.btn-importar-visitantes', function() {
        const id = $(this).data('id');
        const name = $(this).data('name');

        $('#import_cita_id').val(id);
        $('#importCitaNombreSpan').text(`#CIT-${id} - ${name}`);
        
        // Resetear formulario de archivo
        $fileInput.val('');
        selectedCsvFile = null;
        $fileInfo.hide().text('');
        $('#btnSiguientePrevisualizar').prop('disabled', true);
        
        // Resetear paneles a estado inicial (Cargar Archivo)
        $('#importPreviewPanel').hide();
        $('#importPreviewFooter').attr('style', 'display: none !important;');
        $('#importUploadPanel').show();
        $('#importUploadFooter').attr('style', 'display: flex !important;');
        $('#importPreviewTableBody').empty();

        $('#modalImportarVisitantes').modal('show');
    });

    // Clic en la zona de drop abre el selector de archivos
    $dropZone.on('click', function() {
        $fileInput.click();
    });

    $fileInput.on('change', function() {
        handleFileSelect(this.files[0]);
    });

    // Drag & Drop handlers
    $dropZone.on('dragover', function(e) {
        e.preventDefault();
        $(this).addClass('dragover');
    });

    $dropZone.on('dragleave', function() {
        $(this).removeClass('dragover');
    });

    $dropZone.on('drop', function(e) {
        e.preventDefault();
        $(this).removeClass('dragover');
        const file = e.originalEvent.dataTransfer.files[0];
        handleFileSelect(file);
    });

    function handleFileSelect(file) {
        if (!file) return;

        // Validar extensión (.csv)
        const name = file.name;
        const extension = name.split('.').pop().toLowerCase();

        if (extension !== 'csv') {
            alert('Por favor, seleccione únicamente un archivo en formato CSV.');
            $fileInput.val('');
            selectedCsvFile = null;
            $fileInfo.hide();
            $('#btnSiguientePrevisualizar').prop('disabled', true);
            return;
        }

        // Guardar archivo en memoria
        selectedCsvFile = file;

        // Mostrar detalles del archivo
        const sizeKB = (file.size / 1024).toFixed(2);
        $fileInfo.html(`📂 Archivo listo: <strong>${name}</strong> (${sizeKB} KB)`).fadeIn(200);
        $('#btnSiguientePrevisualizar').prop('disabled', false);
    }

    // Botón: Siguiente (Cargar y Previsualizar)
    $('#btnSiguientePrevisualizar').on('click', function() {
        if (!selectedCsvFile) return;

        const reader = new FileReader();
        reader.onload = function(e) {
            const text = e.target.result;
            const visitors = parseCsvToJSON(text);

            $('#importPreviewTableBody').empty();
            if (visitors.length > 0) {
                visitors.forEach(function(v) {
                    $('#importPreviewTableBody').append(renderPreviewRow(v));
                });
            } else {
                // Si está vacío, agregar al menos una fila en blanco
                $('#importPreviewTableBody').append(renderPreviewRow({cedula: '', nombres: '', apellidos: '', fecha_nacimiento: ''}));
            }

            // Cambiar visibilidad de paneles
            $('#importUploadPanel').hide();
            $('#importUploadFooter').attr('style', 'display: none !important;');
            $('#importPreviewPanel').show();
            $('#importPreviewFooter').attr('style', 'display: flex !important;');
        };
        reader.readAsText(selectedCsvFile);
    });

    // Botón: Atrás (Volver a carga de archivo)
    $('#btnAtrasUpload').on('click', function() {
        $('#importPreviewPanel').hide();
        $('#importPreviewFooter').attr('style', 'display: none !important;');
        $('#importUploadPanel').show();
        $('#importUploadFooter').attr('style', 'display: flex !important;');
    });

    // Botón: Añadir fila en blanco en la previsualización
    $('#btnAgregarVisitantePreview').on('click', function() {
        $('#importPreviewTableBody').append(renderPreviewRow({cedula: '', nombres: '', apellidos: '', fecha_nacimiento: ''}));
        const $tableContainer = $('#importPreviewTableBody').parent().parent();
        $tableContainer.scrollTop($tableContainer[0].scrollHeight);
    });

    // Eliminar fila de previsualización
    $(document).on('click', '.btn-remove-preview-visitor', function() {
        $(this).closest('tr').remove();
    });

    // Parsear texto del archivo CSV a JSON Array
    function parseCsvToJSON(text) {
        const lines = text.split(/\r?\n/);
        if (lines.length === 0) return [];

        let delimiter = ';';
        const firstLine = lines[0];
        if (firstLine.includes(',')) {
            const countSemicolon = (firstLine.match(/;/g) || []).length;
            const countComma = (firstLine.match(/,/g) || []).length;
            if (countComma > countSemicolon) {
                delimiter = ',';
            }
        }

        const visitors = [];

        for (let i = 0; i < lines.length; i++) {
            const line = lines[i].trim();
            if (line === '') continue;

            const fields = line.split(delimiter).map(f => f.trim().replace(/^["']|["']$/g, ''));
            if (fields.length < 3) continue;

            const ced = fields[0] || '';
            const nom = fields[1] || '';
            const ape = fields[2] || '';
            const fNac = fields[3] || '';

            // Omitir cabecera
            if (nom.toLowerCase() === 'nombres' || nom.toLowerCase() === 'nombre') continue;
            // Omitir fila de ejemplo
            if (ced === '12345678' && nom === 'Pedro' && ape === 'Pérez') continue;
            // Omitir vacíos
            if (nom === '' && ape === '') continue;

            visitors.push({
                cedula: ced,
                nombres: nom,
                apellidos: ape,
                fecha_nacimiento: fNac
            });
        }
        return visitors;
    }

    // Normalizar fechas de la plantilla para el control input[type=date]
    function sanitizeCsvDate(dateStr) {
        if (!dateStr) return '';
        dateStr = dateStr.trim();
        if (/^\d{4}-\d{2}-\d{2}$/.test(dateStr)) return dateStr;

        const parts = dateStr.split(/[\/-]/);
        if (parts.length === 3) {
            if (parts[0].length === 4) {
                return `${parts[0]}-${parts[1].padStart(2, '0')}-${parts[2].padStart(2, '0')}`;
            }
            if (parts[2].length === 4) {
                return `${parts[2]}-${parts[1].padStart(2, '0')}-${parts[0].padStart(2, '0')}`;
            }
        }
        return dateStr;
    }

    // Renderiza una fila en la tabla de previsualización
    function renderPreviewRow(v) {
        const dateVal = sanitizeCsvDate(v.fecha_nacimiento);
        return `
            <tr class="preview-visitor-row">
                <td style="padding-left: 10px;">
                    <input type="text" class="form-control form-premium-control p-1 text-center input-preview-cedula" 
                           value="${v.cedula || ''}" placeholder="Opcional" style="font-size:0.8rem; border-radius: 6px;">
                </td>
                <td>
                    <input type="text" class="form-control form-premium-control p-1 input-preview-nombres" 
                           value="${v.nombres || ''}" placeholder="Nombres" required style="font-size:0.8rem; border-radius: 6px;">
                </td>
                <td>
                    <input type="text" class="form-control form-premium-control p-1 input-preview-apellidos" 
                           value="${v.apellidos || ''}" placeholder="Apellidos" required style="font-size:0.8rem; border-radius: 6px;">
                </td>
                <td>
                    <input type="date" class="form-control form-premium-control p-1 input-preview-fecha" 
                           value="${dateVal}" required style="font-size:0.8rem; border-radius: 6px;">
                </td>
                <td>
                    <button type="button" class="btn-remove-preview-visitor btn-remove-contact" title="Quitar de la lista" style="width:24px; height:24px; font-size:10px; padding:0; display:flex; align-items:center; justify-content:center;">✕</button>
                </td>
            </tr>
        `;
    }

    // Submit del formulario de importación (envío final de datos previsualizados)
    $('#formImportarVisitantes').on('submit', function(e) {
        e.preventDefault();
        
        // Evitar submit si estamos en la pestaña 1
        if ($('#importPreviewPanel').is(':hidden')) {
            if (selectedCsvFile) {
                $('#btnSiguientePrevisualizar').click();
            }
            return;
        }

        const citaId = $('#import_cita_id').val();
        const visitorsList = [];
        let isValid = true;

        $('.preview-visitor-row').each(function() {
            const ced = $(this).find('.input-preview-cedula').val().trim();
            const nom = $(this).find('.input-preview-nombres').val().trim();
            const ape = $(this).find('.input-preview-apellidos').val().trim();
            const fNac = $(this).find('.input-preview-fecha').val().trim();

            if (nom === '' || ape === '' || fNac === '') {
                isValid = false;
                $(this).css('background-color', '#fff5f5');
            } else {
                $(this).css('background-color', '');
            }

            visitorsList.push({
                cedula: ced,
                nombres: nom,
                apellidos: ape,
                fecha_nacimiento: fNac
            });
        });

        if (!isValid) {
            alert('Por favor, complete todos los campos obligatorios (*) marcados en rojo.');
            return;
        }

        if (visitorsList.length === 0) {
            alert('Debe añadir al menos un visitante para importar.');
            return;
        }

        $btnImport.prop('disabled', true).text('Guardando listado...');

        $.ajax({
            url: 'controllers/CitaController.php?action=importar_visitantes',
            method: 'POST',
            data: {
                cita_id: citaId,
                visitantes_json: JSON.stringify(visitorsList)
            },
            dataType: 'json',
            success: function(response) {
                $btnImport.prop('disabled', false).text('Confirmar y Guardar');
                $('#modalImportarVisitantes').modal('hide');

                if (response.status === 'success') {
                    showGlobalAlert(response.message, 'success');
                    listarCitas();
                } else {
                    alert(response.message);
                }
            },
            error: function(xhr) {
                $btnImport.prop('disabled', false).text('Confirmar y Guardar');
                let errorMsg = 'Error en el servidor al guardar el listado de visitantes.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                alert(errorMsg);
            }
        });
    });

    // =========================================================================
    // 8. AUXILIARES
    // =========================================================================
    // BOTÓN: VER VISITANTES (Abre modal con listado de asistentes)
    $(document).on('click', '.btn-ver-visitantes', function() {
        const id = $(this).data('id');
        const name = $(this).data('name');
        
        $('#verVisitantesCitaNombreSpan').text(`#CIT-${id} - ${name}`);
        $('#tablaVerVisitantesBody').empty().append('<tr><td colspan="4" class="text-center py-4"><div class="spinner-border text-success" role="status"></div><br><small class="text-muted mt-2 d-block">Cargando listado de visitantes...</small></td></tr>');
        
        $('#modalVerVisitantes').modal('show');
        
        $.ajax({
            url: `controllers/CitaController.php?action=listar_visitantes&cita_id=${id}`,
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                $('#tablaVerVisitantesBody').empty();
                if (response.status === 'success' && response.data.length > 0) {
                    response.data.forEach(function(v, idx) {
                        const cedula = v.cedula ? v.cedula : '<span class="text-muted italic">Sin Cédula (Menor)</span>';
                        const fechaNac = v.fecha_nacimiento ? formatDate(v.fecha_nacimiento) : '<span class="text-muted italic">N/E</span>';
                        $('#tablaVerVisitantesBody').append(`
                            <tr>
                                <td class="text-center fw-600">${idx + 1}</td>
                                <td><strong>${cedula}</strong></td>
                                <td>${v.nombres} ${v.apellidos}</td>
                                <td class="text-center">${fechaNac}</td>
                            </tr>
                        `);
                    });
                } else {
                    $('#tablaVerVisitantesBody').append('<tr><td colspan="4" class="text-center text-muted py-4">No se encontraron visitantes registrados para esta cita.</td></tr>');
                }
            },
            error: function(xhr) {
                let errorMsg = 'Error al cargar los visitantes.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                $('#tablaVerVisitantesBody').html(`<tr><td colspan="4" class="text-center text-danger py-4"><strong>✕ Error:</strong> ${errorMsg}</td></tr>`);
            }
        });
    });

    function showGlobalAlert(message, type) {
        const $alert = $('#alertFeedbackGlobal');
        $alert.removeClass('alert-toast-success alert-toast-danger')
              .addClass(type === 'success' ? 'alert-toast-success' : 'alert-toast-danger')
              .html(`<strong>${type === 'success' ? 'Éxito: ' : 'Error: '}</strong> ${message}`)
              .slideDown(250);
        
        setTimeout(function() {
            $alert.slideUp(250);
        }, 5000);
    }

    function formatDate(dateString) {
        if (!dateString) return '';
        const parts = dateString.split('-');
        if (parts.length !== 3) return dateString;
        return `${parts[2]}/${parts[1]}/${parts[0]}`;
    }

    function formatTime(timeString) {
        if (!timeString) return '';
        const parts = timeString.split(':');
        if (parts.length < 2) return timeString;
        
        let hour = parseInt(parts[0]);
        const minutes = parts[1];
        const ampm = hour >= 12 ? 'PM' : 'AM';
        
        hour = hour % 12;
        hour = hour ? hour : 12; // 0 debe ser 12
        
        return `${hour}:${minutes} ${ampm}`;
    }
});
