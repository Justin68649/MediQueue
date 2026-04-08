<?php
// File: config/constants.php
// System Constants Definition

// Load environment variables from .env file if it exists
if (file_exists(__DIR__ . '/../.env')) {
    $env_file = file_get_contents(__DIR__ . '/../.env');
    $env_lines = explode("\n", $env_file);
    foreach ($env_lines as $line) {
        $line = trim($line);
        if (!empty($line) && strpos($line, '#') !== 0) {
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                putenv(trim($key) . '=' . trim($value));
            }
        }
    }
}

// Database Constants
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'mediqueue_db');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');

// Application Constants
define('APP_NAME', getenv('APP_NAME') ?: 'MediQueue');
define('APP_URL', getenv('APP_URL') ?: 'http://localhost/MediQueue');
define('APP_VERSION', '1.0.0');
define('APP_ENV', getenv('APP_ENV') ?: 'development');

// Queue Settings
define('MAX_QUEUE_SIZE', 100);
define('DEFAULT_WAIT_TIME', 15); // minutes
define('AUTO_REFRESH_INTERVAL', 10); // seconds

// Notification Settings
define('SMS_ENABLED', false);
define('EMAIL_ENABLED', true);
define('PUSH_ENABLED', true);

// Timezone
date_default_timezone_set(getenv('TIMEZONE') ?: 'Africa/Nairobi');

// Error Reporting
$debug = getenv('DEBUG') === 'true' || getenv('APP_ENV') === 'development';
error_reporting($debug ? E_ALL : E_ALL & ~E_DEPRECATED & ~E_STRICT);
ini_set('display_errors', $debug ? 1 : 0);

// Upload Settings
define('MAX_FILE_SIZE', 5242880); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'png', 'pdf']);

// Pagination
define('ITEMS_PER_PAGE', 20);

// Security
define('SESSION_TIMEOUT', 3600); // 1 hour
define('PASSWORD_MIN_LENGTH', 8);
define('LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 15); // minutes