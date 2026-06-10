<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Zoológico y Botánico Bararida</title>
    <!-- Bootstrap Local -->
    <link rel="stylesheet" href="view/assets/css/bootstrap.min.css">
    <!-- Google Fonts - Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Estilos Premium Personalizados -->
    <link rel="stylesheet" href="view/assets/css/login.css">
</head>
<body>

    <!-- Huellas de oso decorativas en el fondo de la pantalla -->
    <div class="decor-paw paw-1"></div>
    <div class="decor-paw paw-2"></div>
    <div class="decor-paw paw-3"></div>
    <div class="decor-paw paw-4"></div>

    <div class="login-container">
        <div class="login-card">
            
            <div class="brand-header">
                <!-- Logo real del Parque Bararida -->
                <img src="view/assets/img/logo.png" alt="Logo Parque Bararida" class="brand-logo">
                <p class="brand-subtitle">SISTEMA DE GESTIÓN DE CITAS</p>
            </div>

            <!-- Contenedor de alertas AJAX -->
            <div id="alertFeedback" class="alert" role="alert"></div>

            <form id="formLogin" novalidate>
                <div class="mb-3">
                    <label for="cedula" class="form-label">Documento de Identidad</label>
                    <div class="input-group">
                        <select class="form-select" id="tipo_documento" name="tipo_documento" style="max-width: 80px; border-top-right-radius: 0; border-bottom-right-radius: 0; background-color: #f9f9f9;" required>
                            <option value="V" selected>V</option>
                            <option value="E">E</option>
                            <option value="J">J</option>
                            <option value="G">G</option>
                            <option value="P">P</option>
                        </select>
                        <input type="text" class="form-control" id="cedula" name="cedula" placeholder="Ej. 12345678" style="border-top-left-radius: 0; border-bottom-left-radius: 0;" required autocomplete="username">
                    </div>
                </div>
                
                <div class="mb-4">
                    <label for="password" class="form-label">Contraseña</label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="password" name="password" placeholder="••••••••" style="border-top-right-radius: 0; border-bottom-right-radius: 0;" required autocomplete="current-password">
                        <button class="btn btn-outline-secondary" type="button" id="togglePassword" style="border-top-left-radius: 0; border-bottom-left-radius: 0; border-color: #ced4da; background-color: #fff; display: flex; align-items: center; justify-content: center;">
                            <svg id="eyeIcon" viewBox="0 0 24 24" width="20" height="20" fill="#6c757d">
                                <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-2.5" id="btnLogin">
                    <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                    <span>Ingresar</span>
                </button>
            </form>
        </div>
    </div>

    <!-- Carga de jQuery Local -->
    <script src="view/assets/js/jquery.min.js"></script>
    <!-- Bootstrap JS Bundle Local -->
    <script src="view/assets/js/bootstrap.bundle.min.js"></script>

    <!-- Lógica de Autenticación AJAX Externa -->
    <script src="view/assets/js/login.js"></script>
</body>
</html>
