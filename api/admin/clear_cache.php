<?php
// File: api/admin/clear_cache.php
// Clear System Cache API

require_once '../../config/config.php';
header('Content-Type: application/json');

if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    sendJsonResponse(false, 'Unauthorized. Admin access required.', [], 401);
}

try {
    // Clear session cache
    $cache_dirs = [
        '../cache/',
        '../tmp/',
        '../assets/cache/'
    ];
    
    $cleared_count = 0;
    
    foreach ($cache_dirs as $dir) {
        if (file_exists($dir)) {
            $files = glob($dir . '*');
            foreach ($files as $file) {
                if (is_file($file) && !str_contains($file, '.gitkeep')) {
                    unlink($file);
                    $cleared_count++;
                }
            }
        }
    }
    
    // Clear opcode cache if enabled
    if (function_exists('opcache_reset')) {
        opcache_reset();
    }
    
    // Clear APCu cache if enabled
    if (function_exists('apcu_clear_cache')) {
        apcu_clear_cache();
    }
    
    // Log audit
    logAudit($_SESSION['user_id'], 'cleared_cache', "Cleared {$cleared_count} cache files");
    
    sendJsonResponse(true, "Cache cleared successfully. {$cleared_count} files removed.");
    
} catch (Exception $e) {
    error_log("Error in clear_cache.php: " . $e->getMessage());
    sendJsonResponse(false, 'Failed to clear cache: ' . $e->getMessage(), [], 500);
}
?>