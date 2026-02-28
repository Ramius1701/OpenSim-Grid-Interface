<?php
/**
 * Modern Security and Error Handling Library for OpenSim Webinterface
 * Version: 1.0.0
 */

class OSWebSecurity {
    
    /**
     * Generate CSRF Token
     */
    public static function generateCSRFToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Verify CSRF Token
     */
    public static function verifyCSRFToken($token) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Sanitize input data
     */
    public static function sanitizeInput($input, $type = 'string') {
        if (is_array($input)) {
            return array_map(function($item) use ($type) {
                return self::sanitizeInput($item, $type);
            }, $input);
        }
        
        $input = trim($input);
        
        switch ($type) {
            case 'email':
                return filter_var($input, FILTER_SANITIZE_EMAIL);
            
            case 'int':
                return filter_var($input, FILTER_SANITIZE_NUMBER_INT);
            
            case 'float':
                return filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            
            case 'url':
                return filter_var($input, FILTER_SANITIZE_URL);
            
            case 'string':
            default:
                return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
        }
    }
    
    /**
     * Validate input data
     */
    public static function validateInput($input, $type, $options = []) {
        switch ($type) {
            case 'email':
                return filter_var($input, FILTER_VALIDATE_EMAIL) !== false;
            
            case 'int':
                $min = $options['min'] ?? null;
                $max = $options['max'] ?? null;
                $flags = 0;
                $filter_options = [];
                
                if ($min !== null || $max !== null) {
                    $filter_options['options'] = [];
                    if ($min !== null) $filter_options['options']['min_range'] = $min;
                    if ($max !== null) $filter_options['options']['max_range'] = $max;
                }
                
                return filter_var($input, FILTER_VALIDATE_INT, $filter_options) !== false;
            
            case 'url':
                return filter_var($input, FILTER_VALIDATE_URL) !== false;
            
            case 'uuid':
                return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $input);
            
            case 'avatar_name':
                return preg_match('/^[a-zA-Z0-9\s]{2,31}$/', $input);
            
            case 'required':
                return !empty($input);
            
            case 'length':
                $min = $options['min'] ?? 0;
                $max = $options['max'] ?? PHP_INT_MAX;
                $len = strlen($input);
                return $len >= $min && $len <= $max;
            
            default:
                return true;
        }
    }
    
    /**
     * Check if password meets requirements
     */
    public static function validatePassword($password) {
        $errors = [];
        
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long';
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter';
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter';
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number';
        }
        
        return empty($errors) ? true : $errors;
    }
    
    /**
     * Rate limiting
     */
    public static function checkRateLimit($identifier, $max_attempts = 5, $time_window = 300) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $key = 'rate_limit_' . md5($identifier);
        $now = time();
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = [];
        }
        
        // Clean old attempts
        $_SESSION[$key] = array_filter($_SESSION[$key], function($timestamp) use ($now, $time_window) {
            return ($now - $timestamp) < $time_window;
        });
        
        if (count($_SESSION[$key]) >= $max_attempts) {
            return false;
        }
        
        $_SESSION[$key][] = $now;
        return true;
    }
    
    /**
     * Secure session management
     */
    public static function startSecureSession() {
        if (session_status() === PHP_SESSION_NONE) {
            // Secure session configuration
            ini_set('session.cookie_httponly', 1);
            ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
            ini_set('session.use_strict_mode', 1);
            
            session_start();
            
            // Regenerate session ID on login
            if (!isset($_SESSION['initiated'])) {
                session_regenerate_id(true);
                $_SESSION['initiated'] = true;
            }
        }
    }
}

class OSWebErrorHandler {
    
    /**
     * Display user-friendly error message
     */
    public static function displayError($message, $type = 'danger') {
        $icon_map = [
            'success' => 'check-circle',
            'info' => 'info-circle',
            'warning' => 'exclamation-triangle',
            'danger' => 'x-circle'
        ];
        
        $icon = $icon_map[$type] ?? 'info-circle';
        
        return '<div class="alert alert-' . $type . ' alert-dismissible fade show" role="alert">
                    <i class="bi bi-' . $icon . ' me-2"></i>
                    ' . htmlspecialchars($message) . '
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>';
    }
    
    /**
     * Log error securely
     */
    public static function logError($message, $context = []) {
        $log_entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'message' => $message,
            'context' => $context,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ];
        
        error_log('OSWeb Error: ' . json_encode($log_entry));
    }
    
    /**
     * Handle database errors gracefully
     */
    public static function handleDatabaseError($connection, $query = null) {
        if ($connection && mysqli_errno($connection)) {
            $error = mysqli_error($connection);
            self::logError('Database Error', [
                'error' => $error,
                'query' => $query,
                'errno' => mysqli_errno($connection)
            ]);
            
            return self::displayError('A database error occurred. Please try again later.');
        }
        
        return null;
    }
    
    /**
     * Validate form with multiple fields
     */
    public static function validateForm($data, $rules) {
        $errors = [];
        
        foreach ($rules as $field => $field_rules) {
            $value = $data[$field] ?? '';
            
            foreach ($field_rules as $rule => $options) {
                if (is_numeric($rule)) {
                    $rule = $options;
                    $options = [];
                }
                
                if (!OSWebSecurity::validateInput($value, $rule, $options)) {
                    $field_name = ucfirst(str_replace('_', ' ', $field));
                    
                    switch ($rule) {
                        case 'required':
                            $errors[$field] = $field_name . ' is required';
                            break;
                        case 'email':
                            $errors[$field] = $field_name . ' must be a valid email address';
                            break;
                        case 'int':
                            $errors[$field] = $field_name . ' must be a valid number';
                            break;
                        case 'length':
                            $min = $options['min'] ?? 0;
                            $max = $options['max'] ?? 'unlimited';
                            $errors[$field] = $field_name . " must be between {$min} and {$max} characters";
                            break;
                        case 'uuid':
                            $errors[$field] = $field_name . ' must be a valid UUID';
                            break;
                        case 'avatar_name':
                            $errors[$field] = $field_name . ' must be 2-31 characters, letters and numbers only';
                            break;
                        default:
                            $errors[$field] = $field_name . ' is invalid';
                    }
                    break; // Stop at first error for this field
                }
            }
        }
        
        return $errors;
    }
}

class OSWebDatabase {
    private $connection;
    
    public function __construct() {
        try {
            $this->connection = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
            
            if ($this->connection->connect_error) {
                throw new Exception("Connection failed: " . $this->connection->connect_error);
            }
            
            $this->connection->set_charset("utf8");
            
        } catch (Exception $e) {
            OSWebErrorHandler::logError('Database connection failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
    
    public function prepare($query) {
        $stmt = $this->connection->prepare($query);
        if (!$stmt) {
            OSWebErrorHandler::logError('Prepare failed', [
                'query' => $query,
                'error' => $this->connection->error
            ]);
            throw new Exception("Prepare failed: " . $this->connection->error);
        }
        return $stmt;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    public function close() {
        if ($this->connection) {
            $this->connection->close();
        }
    }
    
    public function __destruct() {
        $this->close();
    }
}

// Helper functions for backward compatibility
function sanitize_input($input, $type = 'string') {
    return OSWebSecurity::sanitizeInput($input, $type);
}

function display_error($message, $type = 'danger') {
    return OSWebErrorHandler::displayError($message, $type);
}

function validate_form($data, $rules) {
    return OSWebErrorHandler::validateForm($data, $rules);
}

function csrf_token_field() {
    $token = OSWebSecurity::generateCSRFToken();
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

function verify_csrf_token() {
    $token = $_POST['csrf_token'] ?? '';
    return OSWebSecurity::verifyCSRFToken($token);
}
?>