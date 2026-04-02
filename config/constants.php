<?php
// File: config/constants.php
// System Constants Definition

// Database Constants
define('DB_HOST', 'localhost');
define('DB_NAME', 'mediqueue_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// Application Constants
define('APP_NAME', 'MediQueue');
define('APP_URL', 'http://localhost/MediQueue');
define('APP_VERSION', '1.0.0');

// Queue Settings
define('MAX_QUEUE_SIZE', 100);
define('DEFAULT_WAIT_TIME', 15); // minutes
define('AUTO_REFRESH_INTERVAL', 10); // seconds

// Notification Settings
define('SMS_ENABLED', false);
define('EMAIL_ENABLED', true);
define('PUSH_ENABLED', true);

// Timezone
date_default_timezone_set('Africa/Nairobi');

// Error Reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

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