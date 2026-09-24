<?php
/**
 * Modelo Base Database
 * Gestiona de forma interna y encapsulada la conexión PDO a MySQL para todos los modelos del sistema.
 * Utiliza un patrón singleton interno para reutilizar la conexión y evitar conexiones redundantes.
 * Todos los modelos heredan de Database para disponer de $this->db sin necesidad de pasarlo por parámetro desde los controladores.
 */
class Database {
    /**
     * Instancia única de la conexión PDO (Singleton)
     */
    protected static ?PDO $sharedConn = null;

    /**
     * Propiedad protegida de conexión disponible para todos los modelos hijos
     */
    protected ?PDO $db = null;

    /**
     * Constructor del Modelo Database
     * Asigna la conexión singleton a la propiedad protegida de la instancia.
     */
    public function __construct() {
        $this->db = self::getConnection();
    }

    /**
     * Obtiene o reutiliza la instancia PDO singleton.
     * Lee credenciales desde el archivo .env ubicado en la raíz del proyecto.
     *
     * @return PDO|null Retorna el objeto PDO o null si la conexión falla.
     */
    public static function getConnection(): ?PDO {
        if (self::$sharedConn !== null) {
            return self::$sharedConn;
        }

        try {
            self::loadEnv();

            $host     = $_ENV['DB_HOST']    ?? 'localhost';
            $db_name  = $_ENV['DB_NAME']    ?? '';
            $username = $_ENV['DB_USER']    ?? '';
            $password = $_ENV['DB_PASS']    ?? '';
            $charset  = $_ENV['DB_CHARSET'] ?? 'utf8mb4';

            $dsn = "mysql:host={$host};dbname={$db_name};charset={$charset}";

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            self::$sharedConn = new PDO($dsn, $username, $password, $options);
            return self::$sharedConn;

        } catch (PDOException $e) {
            error_log("Error de conexión a la Base de Datos en Database Model: " . $e->getMessage());
            self::$sharedConn = null;
            return null;
        } catch (Exception $e) {
            error_log("Error al configurar entorno para la Base de Datos: " . $e->getMessage());
            self::$sharedConn = null;
            return null;
        }
    }

    /**
     * Getter para obtener la conexión PDO si fuera necesario
     */
    public function getDb(): ?PDO {
        return $this->db;
    }

    /**
     * Lee y parsea el archivo .env ubicado en la raíz del proyecto.
     */
    private static function loadEnv(): void {
        $envPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';

        if (!file_exists($envPath) || !is_readable($envPath)) {
            return;
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $line = trim($line);
            if (str_starts_with($line, '#') || $line === '') {
                continue;
            }

            if (!str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key   = trim($key);
            $value = trim($value);

            if (preg_match('/^(["\'])(.+)\1$/', $value, $matches)) {
                $value = $matches[2];
            }

            if (!isset($_ENV[$key])) {
                $_ENV[$key]    = $value;
                $_SERVER[$key] = $value;
                putenv("{$key}={$value}");
            }
        }
    }
}
