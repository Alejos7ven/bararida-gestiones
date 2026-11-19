<?php
/**
 * Pseudo-Librería de Validación para Controladores
 * Parque Zoológico y Botánico Bararida
 * 
 * Funciona de forma análoga y sincronizada con jQuery Validate y con el esquema de la base de datos MySQL.
 * Permite definir reglas complejas, mensajes personalizados y retornar respuestas JSON estructuradas
 * de forma limpia y estandarizada en los controladores (Arquitectura MVC).
 */

class Validator {

    /**
     * Datos recibidos para validar (ej. $_POST o $_GET)
     */
    private array $data = [];

    /**
     * Definición de reglas por campo
     */
    private array $rules = [];

    /**
     * Mensajes de error personalizados
     */
    private array $customMessages = [];

    /**
     * Etiquetas legibles para los nombres de campo
     */
    private array $labels = [];

    /**
     * Errores acumulados tras la validación: ['field_name' => 'Mensaje de error']
     */
    private array $errors = [];

    /**
     * Primer campo que falló
     */
    private ?string $firstFailedField = null;

    /**
     * Métodos de validación personalizados registrados en tiempo de ejecución
     */
    private static array $customMethods = [];

    /**
     * Etiquetas predeterminadas para los campos del sistema
     */
    private static array $defaultLabels = [
        'tipo_documento'       => 'Tipo de Documento',
        'documento'            => 'Documento / Cédula / RIF',
        'documento_inst'       => 'Documento / RIF de la Institución',
        'cedula'               => 'Cédula',
        'nombre_completo'      => 'Nombre Completo / Razón Social',
        'nombre'               => 'Nombre',
        'nombres'              => 'Nombres',
        'apellidos'            => 'Apellidos',
        'tipo_organizacion'    => 'Tipo de Organización',
        'tipo_gestion'         => 'Tipo de Gestión',
        'tipo'                 => 'Tipo',
        'rol'                  => 'Rol de Usuario',
        'password'             => 'Contraseña',
        'fecha_visita'         => 'Fecha de Visita',
        'fecha_nacimiento'     => 'Fecha de Nacimiento',
        'hora_inicio'          => 'Hora de Inicio',
        'metodo_pago'          => 'Método de Pago',
        'pago_estado'          => 'Estado del Pago',
        'estatus'              => 'Estatus',
        'recorrido_id'         => 'Recorrido',
        'institucion_id'       => 'Institución',
        'sede_id'              => 'Sede',
        'coordenadas'          => 'Coordenadas',
        'contacto'             => 'Contacto (Teléfono o Correo)',
        'detalles'             => 'Detalles de Dirección'
    ];

    /**
     * Constructor
     */
    public function __construct(array $data, array $rules = [], array $messages = [], array $labels = []) {
        $this->data = $data;
        $this->rules = $rules;
        $this->customMessages = $messages;
        $this->labels = array_merge(self::$defaultLabels, $labels);

        if (!empty($this->rules)) {
            $this->validate();
        }
    }

    /**
     * Método fábrica estático para instanciar y ejecutar la validación fluidamente
     * 
     * Ejemplo:
     * $val = Validator::make($_POST, [
     *     'nombres' => ['required' => true, 'minlength' => 2, 'maxlength' => 25, 'soloLetras' => true],
     *     'cedula'  => ['required' => false, 'digits' => true, 'maxlength' => 15]
     * ]);
     * if ($val->fails()) {
     *     $val->sendJsonResponse();
     * }
     */
    public static function make(array $data, array $rules, array $messages = [], array $labels = []): self {
        return new self($data, $rules, $messages, $labels);
    }

    /**
     * Registra un método de validación personalizado global (análogo a $.validator.addMethod)
     */
    public static function addMethod(string $name, callable $callback, string $defaultMessage = ''): void {
        self::$customMethods[$name] = [
            'callback' => $callback,
            'message'  => $defaultMessage
        ];
    }

    /**
     * Establece o actualiza etiquetas legibles de campos
     */
    public function setLabels(array $labels): self {
        $this->labels = array_merge($this->labels, $labels);
        return $this;
    }

    /**
     * Ejecuta todas las validaciones configuradas
     */
    public function validate(): bool {
        $this->errors = [];
        $this->firstFailedField = null;

        foreach ($this->rules as $field => $ruleSet) {
            $value = $this->getRawValue($field);
            $parsedRules = $this->parseRules($ruleSet);

            // Determinar si el campo es obligatorio
            $isRequired = false;
            if (isset($parsedRules['required'])) {
                $reqRule = $parsedRules['required'];
                if (is_callable($reqRule)) {
                    $isRequired = (bool)call_user_func($reqRule, $this->data);
                } else {
                    $isRequired = filter_var($reqRule, FILTER_VALIDATE_BOOLEAN);
                }
            }

            // Determinar si el valor es verdaderamente nulo/vacío o son solo espacios
            $isTrulyEmpty = ($value === null || (is_string($value) && $value === '') || (is_array($value) && empty($value)));
            $isOnlyWhitespace = is_string($value) && $value !== '' && trim($value) === '';

            // Si está verdaderamente vacío (null o "") y no es obligatorio, saltar el resto de las reglas
            if (!$isRequired && $isTrulyEmpty) {
                continue;
            }

            // Si es obligatorio y está vacío o compuesto únicamente por espacios, registrar error required
            if ($isRequired && ($isTrulyEmpty || $isOnlyWhitespace)) {
                $this->addError($field, 'required', null);
                continue;
            }

            // Evaluar cada una de las reglas
            foreach ($parsedRules as $ruleName => $ruleParam) {
                if ($ruleName === 'required') {
                    continue;
                }

                $isValid = $this->executeRule($ruleName, $value, $ruleParam, $field);
                if (!$isValid) {
                    $this->addError($field, $ruleName, $ruleParam);
                    break; // Solo registrar el primer error por campo
                }
            }
        }

        return empty($this->errors);
    }

    /**
     * Retorna true si hubo errores
     */
    public function fails(): bool {
        return !empty($this->errors);
    }

    /**
     * Retorna true si la validación fue exitosa
     */
    public function passes(): bool {
        return empty($this->errors);
    }

    /**
     * Retorna todos los errores encontrados
     */
    public function errors(): array {
        return $this->errors;
    }

    /**
     * Retorna el primer mensaje de error
     */
    public function firstError(): ?string {
        if (empty($this->errors)) {
            return null;
        }
        $first = reset($this->errors);
        return is_string($first) ? $first : null;
    }

    /**
     * Retorna el nombre del primer campo con error
     */
    public function firstField(): ?string {
        return $this->firstFailedField;
    }

    /**
     * Retorna los datos validados y limpiados (trim)
     */
    public function validated(): array {
        $result = [];
        foreach ($this->rules as $field => $ruleSet) {
            $val = $this->getRawValue($field);
            if (is_string($val)) {
                $result[$field] = trim($val);
            } else {
                $result[$field] = $val;
            }
        }
        return $result;
    }

    /**
     * Obtiene el valor procesado de un campo
     */
    public function getValue(string $field, $default = null) {
        $val = $this->getRawValue($field);
        if ($val === null) {
            return $default;
        }
        return is_string($val) ? trim($val) : $val;
    }

    /**
     * Emite directamente la respuesta JSON estandarizada con código HTTP y detiene la ejecución
     */
    public function sendJsonResponse(int $httpCode = 400): void {
        http_response_code($httpCode);
        echo json_encode([
            "status"  => "error",
            "message" => $this->firstError() ?? "Datos de formulario inválidos.",
            "field"   => $this->firstField(),
            "errors"  => $this->errors()
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // =========================================================================
    // LÓGICA INTERNA DE VALIDACIÓN DE REGLAS
    // =========================================================================

    /**
     * Obtiene el valor raw del campo buscando en la data suministrada
     */
    private function getRawValue(string $field) {
        return $this->data[$field] ?? null;
    }

    /**
     * Determina si un valor está vacío
     */
    private function isEmptyValue($val): bool {
        if ($val === null) return true;
        if (is_string($val)) return trim($val) === '';
        if (is_array($val)) return empty($val);
        return false;
    }

    /**
     * Parsea las reglas de un campo (soporta formato array asociativo estilo jQuery Validate o string con pipes)
     */
    private function parseRules($ruleSet): array {
        if (is_array($ruleSet)) {
            return $ruleSet;
        }

        if (!is_string($ruleSet)) {
            return [];
        }

        $rules = [];
        $parts = explode('|', $ruleSet);
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') continue;

            if (str_contains($part, ':')) {
                [$rName, $rParam] = explode(':', $part, 2);
                $rName = trim($rName);
                $rParam = trim($rParam);
                if (str_contains($rParam, ',')) {
                    $rParam = array_map('trim', explode(',', $rParam));
                }
                $rules[$rName] = $rParam;
            } else {
                $rules[$part] = true;
            }
        }

        return $rules;
    }

    /**
     * Ejecuta una regla individual
     */
    private function executeRule(string $rule, $value, $param, string $field): bool {
        $rawStr = is_scalar($value) ? (string)$value : '';
        $strVal = trim($rawStr);

        // Comprobar métodos personalizados registrados dinámicamente
        if (isset(self::$customMethods[$rule])) {
            return (bool)call_user_func(self::$customMethods[$rule]['callback'], $value, $param, $this->data);
        }

        switch ($rule) {
            case 'minlength':
                $min = (int)$param;
                return mb_strlen($strVal, 'UTF-8') >= $min;

            case 'maxlength':
                $max = (int)$param;
                return mb_strlen($strVal, 'UTF-8') <= $max;

            case 'exactlength':
            case 'length':
                $len = (int)$param;
                return mb_strlen($strVal, 'UTF-8') === $len;

            case 'digits':
                return ctype_digit($strVal);

            case 'numeric':
                return is_numeric($strVal);

            case 'integer':
            case 'int':
                return filter_var($strVal, FILTER_VALIDATE_INT) !== false;

            case 'min':
                return is_numeric($strVal) && ((float)$strVal >= (float)$param);

            case 'max':
                return is_numeric($strVal) && ((float)$strVal <= (float)$param);

            case 'in':
                $allowed = is_array($param) ? $param : array_map('trim', explode(',', (string)$param));
                return in_array($strVal, $allowed, true);

            case 'in_ci': // Case-insensitive in
                $allowed = array_map('strtolower', is_array($param) ? $param : array_map('trim', explode(',', (string)$param)));
                return in_array(strtolower($strVal), $allowed, true);

            case 'email':
            case 'formatoCorreo':
                return filter_var($strVal, FILTER_VALIDATE_EMAIL) !== false;

            case 'date':
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $strVal)) {
                    return false;
                }
                [$y, $m, $d] = explode('-', $strVal);
                return checkdate((int)$m, (int)$d, (int)$y);

            case 'time':
                return (bool)preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $strVal);

            case 'fechaNoFutura':
            case 'date_not_future':
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $strVal)) {
                    return false;
                }
                return $strVal <= date('Y-m-d');

            case 'fechaNoPasada':
            case 'date_not_past':
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $strVal)) {
                    return false;
                }
                return $strVal >= date('Y-m-d');

            case 'soloLetras':
                // No puede ser solo espacios
                if ($strVal === '') {
                    return false;
                }
                // No puede iniciar con espacios
                if (preg_match('/^\s/', $rawStr)) {
                    return false;
                }
                // No puede contener números
                if (preg_match('/\d/', $rawStr)) {
                    return false;
                }
                // Debe iniciar con letra y contener solo letras, acentos, espacios intermedios, apóstrofes, puntos y guiones
                return (bool)preg_match('/^\p{L}[\p{L}\s\.\'\-]*$/u', $rawStr);

            case 'noLeadingSpaces':
            case 'no_leading_spaces':
            case 'noInicioEspacio':
                if ($rawStr === '') {
                    return true;
                }
                return !preg_match('/^\s/', $rawStr) && $strVal !== '';

            case 'formatoTelefono':
                // 7 a 15 dígitos numéricos sin letras
                $clean = preg_replace('/[^0-9]/', '', $strVal);
                if (preg_match('/[a-zA-Z]/', $strVal)) {
                    return false;
                }
                $len = strlen($clean);
                return $len >= 7 && $len <= 15;

            case 'formatoContacto':
                // Acepta teléfono válido o correo válido (para directorio)
                $isEmail = filter_var($strVal, FILTER_VALIDATE_EMAIL) !== false;
                $clean = preg_replace('/[^0-9]/', '', $strVal);
                $isPhone = !preg_match('/[a-zA-Z]/', $strVal) && strlen($clean) >= 7 && strlen($clean) <= 15;
                return $isEmail || $isPhone;

            case 'coordenadas':
                return (bool)preg_match('/^[0-9]+(\.[0-9]+)?,[0-9]+(\.[0-9]+)?$/', $strVal);

            case 'regex':
            case 'pattern':
                return (bool)preg_match($param, $strVal);

            case 'boolean':
                return in_array($strVal, ['0', '1', 'true', 'false', 'TRUE', 'FALSE', true, false, 0, 1], true);

            case 'custom':
                if (is_callable($param)) {
                    return (bool)call_user_func($param, $value, $this->data);
                }
                return true;

            default:
                // Si la regla no está reconocida, no bloquear
                return true;
        }
    }

    /**
     * Agrega un error formateado
     */
    private function addError(string $field, string $rule, $param): void {
        if ($this->firstFailedField === null) {
            $this->firstFailedField = $field;
        }

        // 1. Mensaje personalizado explícito: $messages[$field][$rule]
        if (isset($this->customMessages[$field][$rule])) {
            $this->errors[$field] = $this->customMessages[$field][$rule];
            return;
        }

        // 2. Mensaje personalizado a nivel de campo: $messages[$field]
        if (isset($this->customMessages[$field]) && is_string($this->customMessages[$field])) {
            $this->errors[$field] = $this->customMessages[$field];
            return;
        }

        // 3. Mensaje predeterminado de método custom
        if (isset(self::$customMethods[$rule]) && !empty(self::$customMethods[$rule]['message'])) {
            $this->errors[$field] = self::$customMethods[$rule]['message'];
            return;
        }

        // 4. Mensaje predeterminado estándar
        $label = $this->labels[$field] ?? ucfirst(str_replace('_', ' ', $field));
        $this->errors[$field] = $this->getDefaultErrorMessage($rule, $label, $param);
    }

    /**
     * Genera el mensaje de error por defecto
     */
    private function getDefaultErrorMessage(string $rule, string $label, $param): string {
        switch ($rule) {
            case 'required':
                return "El campo {$label} es obligatorio.";

            case 'minlength':
                return "El campo {$label} debe tener al menos {$param} caracteres.";

            case 'maxlength':
                return "El campo {$label} no puede exceder los {$param} caracteres.";

            case 'exactlength':
            case 'length':
                return "El campo {$label} debe tener exactamente {$param} caracteres.";

            case 'digits':
                return "El campo {$label} debe contener exclusivamente dígitos numéricos.";

            case 'numeric':
                return "El campo {$label} debe ser un valor numérico.";

            case 'integer':
            case 'int':
                return "El campo {$label} debe ser un número entero.";

            case 'min':
                return "El campo {$label} no puede ser menor a {$param}.";

            case 'max':
                return "El campo {$label} no puede ser mayor a {$param}.";

            case 'in':
            case 'in_ci':
                $opts = is_array($param) ? implode(', ', $param) : (string)$param;
                return "El valor seleccionado para {$label} no es válido (Opciones: {$opts}).";

            case 'email':
            case 'formatoCorreo':
                return "El correo electrónico ingresado no tiene un formato válido (ej. contacto@ejemplo.com).";

            case 'date':
                return "El campo {$label} debe tener una fecha válida en formato AAAA-MM-DD.";

            case 'time':
                return "El campo {$label} debe ser una hora válida (HH:MM).";

            case 'fechaNoFutura':
            case 'date_not_future':
                return "La fecha de {$label} no puede ser futura (máximo hoy).";

            case 'fechaNoPasada':
            case 'date_not_past':
                return "La fecha de {$label} no puede ser anterior a hoy.";

            case 'soloLetras':
                return "El campo {$label} debe contener solo letras y no puede iniciar con espacios ni estar vacío.";

            case 'noLeadingSpaces':
            case 'no_leading_spaces':
            case 'noInicioEspacio':
                return "El campo {$label} no puede iniciar con espacios ni contener solo espacios.";

            case 'formatoTelefono':
                return "El teléfono debe contener solo números (entre 7 y 15 dígitos).";

            case 'formatoContacto':
                return "El contacto debe ser un teléfono válido (7-15 dígitos) o un correo electrónico válido.";

            case 'coordenadas':
                return "Las coordenadas de {$label} deben tener el formato numérico 'latitud,longitud'.";

            case 'regex':
            case 'pattern':
                return "El formato del campo {$label} es inválido.";

            default:
                return "El valor ingresado para {$label} es inválido.";
        }
    }
}

