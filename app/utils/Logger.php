<?php

namespace App\Utils;

/**
 * Logger Class - Handles application logging
 */
class Logger
{
    private static $logPath;
    
    static {
        self::$logPath = __DIR__ . '/../../logs/';
        if (!is_dir(self::$logPath)) {
            mkdir(self::$logPath, 0755, true);
        }
    }
    
    public static function info($message, $context = [])
    {
        self::log('INFO', $message, $context);
    }
    
    public static function warning($message, $context = [])
    {
        self::log('WARNING', $message, $context);
    }
    
    public static function error($message, $context = [])
    {
        self::log('ERROR', $message, $context);
    }
    
    public static function critical($message, $context = [])
    {
        self::log('CRITICAL', $message, $context);
    }
    
    private static function log($level, $message, $context = [])
    {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = [
            'timestamp' => $timestamp,
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'CLI',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'CLI',
            'user_id' => $_SESSION['user_id'] ?? null
        ];
        
        $logLine = json_encode($logEntry) . PHP_EOL;
        
        $filename = self::$logPath . date('Y-m-d') . '.log';
        file_put_contents($filename, $logLine, FILE_APPEND | LOCK_EX);
        
        // Also log to database if it's a significant error
        if (in_array($level, ['ERROR', 'CRITICAL'])) {
            self::logToDatabase($level, $message, $context);
        }
    }
    
    private static function logToDatabase($level, $message, $context)
    {
        try {
            $db = \App\Core\Database::getInstance();
            $db->insert('system_logs', [
                'user_id' => $_SESSION['user_id'] ?? null,
                'log_level' => strtolower($level),
                'action' => 'system_error',
                'message' => $message,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'request_data' => json_encode($context)
            ]);
        } catch (\Exception $e) {
            // Fallback to file logging if database fails
            error_log("Failed to log to database: " . $e->getMessage());
        }
    }
}
