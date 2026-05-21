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
                    <label for="cedula" class="form-label">Cédula de Identidad</label>
                    <input type="text" class="form-control" id="cedula" name="cedula" placeholder="Ej. 12345678" required autocomplete="username">
                </div>
                
                <div class="mb-4">
                    <label for="password" class="form-label">Contraseña</label>
                    <input type="password" class="form-control" id="password" name="password" placeholder="••••••••" required autocomplete="current-password">
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
