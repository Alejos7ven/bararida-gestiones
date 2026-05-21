<?php
/**
 * Clase de Conexión a la Base de Datos
 * Lee las credenciales desde el archivo .env en la raíz del proyecto.
 * Utiliza PDO con consultas preparadas y modo estricto de errores.
 */
class Database {

    private $host;
    private $db_name;
    private $username;
    private $password;
    private $charset;
    private $conn = null;

    /**
     * Constructor: carga las variables del archivo .env
     * al instanciar la clase.
     */
    public function __construct() {
        $this->loadEnv();
    }

    /**
     * Lee y parsea el archivo .env ubicado en la raíz del proyecto.
     * Soporta comentarios (#), líneas vacías y valores entre comillas.
     * No sobreescribe variables ya definidas en el entorno del servidor.
     *
     * @throws RuntimeException Si el archivo .env no existe o no se puede leer.
     */
    private function loadEnv(): void {
        // Ruta absoluta al .env (un nivel arriba de /config/)
        $envPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';

        if (!file_exists($envPath) || !is_readable($envPath)) {
            throw new RuntimeException(
                "Archivo .env no encontrado o sin permisos de lectura en: {$envPath}"
            );
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            // Ignorar comentarios
            $line = trim($line);
            if (str_starts_with($line, '#') || $line === '') {
                continue;
            }

            // Separar clave=valor (solo en el primer '=')
            if (!str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key   = trim($key);
            $value = trim($value);

            // Remover comillas dobles o simples que envuelvan el valor
            if (preg_match('/^(["\'])(.+)\1$/', $value, $matches)) {
                $value = $matches[2];
            }

            // No sobreescribir variables ya definidas en el entorno del servidor
            if (!isset($_ENV[$key])) {
                $_ENV[$key]    = $value;
                $_SERVER[$key] = $value;
                putenv("{$key}={$value}");
            }
        }

        // Asignar propiedades con fallback a cadena vacía si alguna clave falta
        $this->host     = $_ENV['DB_HOST']    ?? 'localhost';
        $this->db_name  = $_ENV['DB_NAME']    ?? '';
        $this->username = $_ENV['DB_USER']    ?? '';
        $this->password = $_ENV['DB_PASS']    ?? '';
        $this->charset  = $_ENV['DB_CHARSET'] ?? 'utf8mb4';
    }

    /**
     * Obtiene la conexión PDO a la base de datos.
     * Reutiliza la conexión si ya fue establecida (patrón Singleton).
     *
     * @return PDO|null Retorna el objeto PDO o null si la conexión falla.
     */
    public function getConnection(): ?PDO {
        if ($this->conn !== null) {
            return $this->conn;
        }

        try {
            $dsn = "mysql:host={$this->host};dbname={$this->db_name};charset={$this->charset}";

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            $this->conn = new PDO($dsn, $this->username, $this->password, $options);

        } catch (PDOException $e) {
            // Registrar el error internamente sin exponer datos sensibles al cliente
            error_log("Error de conexión a la Base de Datos: " . $e->getMessage());
            $this->conn = null;
        }

        return $this->conn;
    }
}
